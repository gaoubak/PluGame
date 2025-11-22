<?php

namespace App\Service;

use App\Entity\OAuthProvider;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OAuthService
{
    private const GOOGLE_TOKEN_INFO_URL = 'https://oauth2.googleapis.com/tokeninfo';
    private const APPLE_KEYS_URL = 'https://appleid.apple.com/auth/keys';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * Verify Google ID token and extract user data
     *
     * @param string $idToken The ID token from Google Sign-In
     * @return array|null Returns user data or null if invalid
     */
    public function verifyGoogleToken(string $idToken): ?array
    {
        try {
            $response = $this->httpClient->request('GET', self::GOOGLE_TOKEN_INFO_URL, [
                'query' => ['id_token' => $idToken],
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->error('Google token verification failed', [
                    'status' => $response->getStatusCode(),
                ]);
                return null;
            }

            $data = $response->toArray();

            // Validate required fields
            if (!isset($data['sub'], $data['email'])) {
                $this->logger->error('Google token missing required fields', ['data' => $data]);
                return null;
            }

            // Extract user data
            return [
                'provider' => 'google',
                'provider_user_id' => $data['sub'],
                'email' => $data['email'],
                'email_verified' => $data['email_verified'] ?? false,
                'name' => $data['name'] ?? null,
                'given_name' => $data['given_name'] ?? null,
                'family_name' => $data['family_name'] ?? null,
                'picture' => $data['picture'] ?? null,
                'locale' => $data['locale'] ?? null,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Google token verification exception', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Verify Apple ID token and extract user data
     *
     * @param string $idToken The ID token from Apple Sign-In
     * @param array|null $userData Optional user data from first sign-in
     * @return array|null Returns user data or null if invalid
     */
    public function verifyAppleToken(string $idToken, ?array $userData = null): ?array
    {
        try {
            // Decode JWT without verification first to get header
            $parts = explode('.', $idToken);
            if (count($parts) !== 3) {
                $this->logger->error('Invalid Apple token format');
                return null;
            }

            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

            if (!isset($payload['sub'], $payload['email'])) {
                $this->logger->error('Apple token missing required fields', ['payload' => $payload]);
                return null;
            }

            // TODO: In production, verify the JWT signature using Apple's public keys
            // For now, we trust the token for development purposes
            // See: https://developer.apple.com/documentation/sign_in_with_apple/sign_in_with_apple_rest_api/verifying_a_user

            return [
                'provider' => 'apple',
                'provider_user_id' => $payload['sub'],
                'email' => $payload['email'],
                'email_verified' => $payload['email_verified'] ?? true, // Apple emails are always verified
                'name' => $userData['name']['firstName'] ?? null . ' ' . $userData['name']['lastName'] ?? null,
                'given_name' => $userData['name']['firstName'] ?? null,
                'family_name' => $userData['name']['lastName'] ?? null,
                'picture' => null, // Apple doesn't provide profile pictures
                'locale' => null,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Apple token verification exception', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Find or create user from OAuth data
     *
     * @param array $oauthData OAuth data from verifyGoogleToken or verifyAppleToken
     * @param array|null $additionalInfo Additional user info (phone, location, sport, role)
     * @return User
     */
    public function findOrCreateUser(array $oauthData, ?array $additionalInfo = null): User
    {
        $provider = $oauthData['provider'];
        $providerUserId = $oauthData['provider_user_id'];
        $email = $oauthData['email'];

        // 1. Try to find existing OAuth link
        $oauthProvider = $this->em->getRepository(OAuthProvider::class)->findOneBy([
            'provider' => $provider,
            'providerUserId' => $providerUserId,
        ]);

        if ($oauthProvider) {
            // Update OAuth provider data
            $this->updateOAuthProvider($oauthProvider, $oauthData);
            return $oauthProvider->getUser();
        }

        // 2. Try to find existing user by email (auto-link)
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);

        if ($user) {
            // Link OAuth provider to existing user
            $oauthProvider = $this->createOAuthProvider($user, $oauthData);
            $this->em->persist($oauthProvider);
            $this->em->flush();

            $this->logger->info('Linked OAuth provider to existing user', [
                'user_id' => $user->getId(),
                'provider' => $provider,
            ]);

            return $user;
        }

        // 3. Create new user
        $user = $this->createUserFromOAuth($oauthData, $additionalInfo);
        $oauthProvider = $this->createOAuthProvider($user, $oauthData);

        $user->addOauthProvider($oauthProvider);

        $this->em->persist($user);
        $this->em->persist($oauthProvider);
        $this->em->flush();

        $this->logger->info('Created new user from OAuth', [
            'user_id' => $user->getId(),
            'provider' => $provider,
            'email' => $email,
        ]);

        return $user;
    }

    /**
     * Create a new User from OAuth data
     */
    private function createUserFromOAuth(array $oauthData, ?array $additionalInfo): User
    {
        $user = new User();

        // Email and username
        $email = $oauthData['email'];
        $user->setEmail($email);

        // Generate unique username from email
        $baseUsername = explode('@', $email)[0];
        $username = $this->generateUniqueUsername($baseUsername);
        $user->setUsername($username);

        // Name
        if (isset($oauthData['name'])) {
            $user->setFullName($oauthData['name']);
        } elseif (isset($oauthData['given_name'], $oauthData['family_name'])) {
            $user->setFullName($oauthData['given_name'] . ' ' . $oauthData['family_name']);
        }

        // Photo
        if (isset($oauthData['picture'])) {
            $user->setUserPhoto($oauthData['picture']);
        }

        // Locale
        if (isset($oauthData['locale'])) {
            $user->setLocale(substr($oauthData['locale'], 0, 2)); // Extract language code
        }

        // Email verification
        if ($oauthData['email_verified'] ?? false) {
            $user->setIsVerified(true);
        }

        // No password for OAuth users (they can set one later if they want)
        // Set a random password that they'll never know
        $user->setPassword(bin2hex(random_bytes(32)));

        // Role from additional info
        if (isset($additionalInfo['role'])) {
            $role = $additionalInfo['role'];
            if (in_array($role, [User::ROLE_ATHLETE, User::ROLE_CREATOR])) {
                $user->setRoles([$role]);
            }
        } else {
            // Default role
            $user->setRoles([User::ROLE_USER]);
        }

        // Additional info
        if (isset($additionalInfo['phone'])) {
            $user->setPhoneNumber($additionalInfo['phone']);
        }
        if (isset($additionalInfo['location'])) {
            $user->setLocation($additionalInfo['location']);
        }
        if (isset($additionalInfo['sport'])) {
            $user->setSport($additionalInfo['sport']);
        }

        $user->setIsActive(true);

        return $user;
    }

    /**
     * Create OAuthProvider entity
     */
    private function createOAuthProvider(User $user, array $oauthData): OAuthProvider
    {
        $provider = new OAuthProvider(
            $user,
            $oauthData['provider'],
            $oauthData['provider_user_id']
        );

        $provider->setProviderEmail($oauthData['email']);
        $provider->setProviderName($oauthData['name'] ?? null);
        $provider->setProviderPhotoUrl($oauthData['picture'] ?? null);
        $provider->setProviderData($oauthData);

        return $provider;
    }

    /**
     * Update existing OAuthProvider with new data
     */
    private function updateOAuthProvider(OAuthProvider $provider, array $oauthData): void
    {
        $provider->setProviderEmail($oauthData['email']);
        $provider->setProviderName($oauthData['name'] ?? null);
        $provider->setProviderPhotoUrl($oauthData['picture'] ?? null);
        $provider->setProviderData($oauthData);

        $this->em->flush();
    }

    /**
     * Generate a unique username from base
     */
    private function generateUniqueUsername(string $base): string
    {
        // Clean the base
        $base = preg_replace('/[^a-zA-Z0-9_]/', '', $base);
        $base = substr($base, 0, 20); // Limit length

        $username = $base;
        $counter = 1;

        while ($this->em->getRepository(User::class)->findOneBy(['username' => $username])) {
            $username = $base . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Check if user needs to complete their profile
     * (role, phone, location, sport)
     */
    public function needsProfileCompletion(User $user): bool
    {
        // Check if user has a specific role (not just ROLE_USER)
        $roles = $user->getRoles();
        $hasSpecificRole = in_array(User::ROLE_ATHLETE, $roles) || in_array(User::ROLE_CREATOR, $roles);

        return !$hasSpecificRole;
    }

    /**
     * Get profile completion fields needed
     */
    public function getProfileCompletionFields(User $user): array
    {
        $needed = [];

        $roles = $user->getRoles();
        if (!in_array(User::ROLE_ATHLETE, $roles) && !in_array(User::ROLE_CREATOR, $roles)) {
            $needed[] = 'role';
        }

        if (!$user->getPhoneNumber()) {
            $needed[] = 'phone';
        }

        if (!$user->getLocation()) {
            $needed[] = 'location';
        }

        if (!$user->getSport()) {
            $needed[] = 'sport';
        }

        return $needed;
    }
}
