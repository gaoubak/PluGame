<?php

namespace App\Controller;

use App\Service\OAuthService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/auth')]
class OAuthController extends AbstractController
{
    public function __construct(
        private readonly OAuthService $oauthService,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Google OAuth login/register
     *
     * Expected request body:
     * {
     *   "idToken": "google-id-token-from-mobile-sdk",
     *   "additionalInfo": {
     *     "role": "ROLE_ATHLETE|ROLE_CREATOR",  // Optional: if new user
     *     "phone": "+1234567890",                // Optional
     *     "location": "Paris, France",           // Optional
     *     "sport": "football"                    // Optional
     *   }
     * }
     */
    #[Route('/google', name: 'oauth_google', methods: ['POST'])]
    public function googleAuth(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['idToken'])) {
            return $this->json([
                'error' => 'Missing idToken',
            ], 400);
        }

        $idToken = $data['idToken'];
        $additionalInfo = $data['additionalInfo'] ?? null;

        // Verify Google token
        $oauthData = $this->oauthService->verifyGoogleToken($idToken);

        if (!$oauthData) {
            return $this->json([
                'error' => 'Invalid Google token',
            ], 401);
        }

        try {
            // Find or create user
            $user = $this->oauthService->findOrCreateUser($oauthData, $additionalInfo);

            // Check if profile needs completion
            $needsCompletion = $this->oauthService->needsProfileCompletion($user);
            $completionFields = $needsCompletion ? $this->oauthService->getProfileCompletionFields($user) : [];

            // Generate JWT token
            $token = $this->jwtManager->create($user);

            return $this->json([
                'token' => $token,
                'user' => [
                    'id' => $user->getId(),
                    'username' => $user->getUsername(),
                    'email' => $user->getEmail(),
                    'fullName' => $user->getFullName(),
                    'userPhoto' => $user->getUserPhoto(),
                    'roles' => $user->getRoles(),
                    'isVerified' => $user->isVerified(),
                ],
                'needsProfileCompletion' => $needsCompletion,
                'completionFields' => $completionFields,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Google OAuth error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->json([
                'error' => 'Authentication failed',
            ], 500);
        }
    }

    /**
     * Apple OAuth login/register
     *
     * Expected request body:
     * {
     *   "idToken": "apple-id-token-from-mobile-sdk",
     *   "userData": {               // Only provided on first sign-in
     *     "name": {
     *       "firstName": "John",
     *       "lastName": "Doe"
     *     },
     *     "email": "john@example.com"
     *   },
     *   "additionalInfo": {
     *     "role": "ROLE_ATHLETE|ROLE_CREATOR",  // Optional: if new user
     *     "phone": "+1234567890",                // Optional
     *     "location": "Paris, France",           // Optional
     *     "sport": "football"                    // Optional
     *   }
     * }
     */
    #[Route('/apple', name: 'oauth_apple', methods: ['POST'])]
    public function appleAuth(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['idToken'])) {
            return $this->json([
                'error' => 'Missing idToken',
            ], 400);
        }

        $idToken = $data['idToken'];
        $userData = $data['userData'] ?? null;
        $additionalInfo = $data['additionalInfo'] ?? null;

        // Verify Apple token
        $oauthData = $this->oauthService->verifyAppleToken($idToken, $userData);

        if (!$oauthData) {
            return $this->json([
                'error' => 'Invalid Apple token',
            ], 401);
        }

        try {
            // Find or create user
            $user = $this->oauthService->findOrCreateUser($oauthData, $additionalInfo);

            // Check if profile needs completion
            $needsCompletion = $this->oauthService->needsProfileCompletion($user);
            $completionFields = $needsCompletion ? $this->oauthService->getProfileCompletionFields($user) : [];

            // Generate JWT token
            $token = $this->jwtManager->create($user);

            return $this->json([
                'token' => $token,
                'user' => [
                    'id' => $user->getId(),
                    'username' => $user->getUsername(),
                    'email' => $user->getEmail(),
                    'fullName' => $user->getFullName(),
                    'userPhoto' => $user->getUserPhoto(),
                    'roles' => $user->getRoles(),
                    'isVerified' => $user->isVerified(),
                ],
                'needsProfileCompletion' => $needsCompletion,
                'completionFields' => $completionFields,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Apple OAuth error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->json([
                'error' => 'Authentication failed',
            ], 500);
        }
    }

    /**
     * Complete user profile after OAuth registration
     *
     * Expected request body:
     * {
     *   "role": "ROLE_ATHLETE|ROLE_CREATOR",  // Required if not set
     *   "phone": "+1234567890",                // Optional
     *   "location": "Paris, France",           // Optional
     *   "sport": "football"                    // Optional
     * }
     */
    #[Route('/complete-profile', name: 'oauth_complete_profile', methods: ['POST'])]
    public function completeProfile(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $data = json_decode($request->getContent(), true);

        // Update role if provided and valid
        if (isset($data['role'])) {
            $role = $data['role'];
            if (!in_array($role, ['ROLE_ATHLETE', 'ROLE_CREATOR'])) {
                return $this->json(['error' => 'Invalid role. Must be ROLE_ATHLETE or ROLE_CREATOR'], 400);
            }

            $user->setRoles([$role]);
        }

        // Update optional fields
        if (isset($data['phone'])) {
            $user->setPhoneNumber($data['phone']);
        }

        if (isset($data['location'])) {
            $user->setLocation($data['location']);
        }

        if (isset($data['sport'])) {
            $user->setSport($data['sport']);
        }

        $this->em->flush();

        $this->logger->info('User profile completed', [
            'user_id' => $user->getId(),
        ]);

        return $this->json([
            'success' => true,
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'fullName' => $user->getFullName(),
                'userPhoto' => $user->getUserPhoto(),
                'roles' => $user->getRoles(),
                'phoneNumber' => $user->getPhoneNumber(),
                'location' => $user->getLocation(),
                'sport' => $user->getSport(),
            ],
        ]);
    }

    /**
     * Get current user's OAuth providers
     */
    #[Route('/oauth-providers', name: 'get_oauth_providers', methods: ['GET'])]
    public function getOAuthProviders(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $providers = [];
        foreach ($user->getOauthProviders() as $oauthProvider) {
            $providers[] = [
                'provider' => $oauthProvider->getProvider(),
                'email' => $oauthProvider->getProviderEmail(),
                'name' => $oauthProvider->getProviderName(),
                'linkedAt' => $oauthProvider->getCreatedAt()->format(\DATE_ATOM),
            ];
        }

        return $this->json([
            'providers' => $providers,
        ]);
    }

    /**
     * Unlink an OAuth provider from the user's account
     *
     * Expected request: DELETE /api/auth/oauth-providers/{provider}
     * where {provider} is 'google' or 'apple'
     */
    #[Route('/oauth-providers/{provider}', name: 'unlink_oauth_provider', methods: ['DELETE'])]
    public function unlinkOAuthProvider(string $provider): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        // Check if user has a password (can't remove OAuth if it's the only login method)
        if (!$user->getPassword() && count($user->getOauthProviders()) === 1) {
            return $this->json([
                'error' => 'Cannot remove your only login method. Please set a password first.',
            ], 400);
        }

        $oauthProvider = $user->getOAuthProvider($provider);

        if (!$oauthProvider) {
            return $this->json(['error' => 'OAuth provider not linked'], 404);
        }

        $user->removeOauthProvider($oauthProvider);
        $this->em->remove($oauthProvider);
        $this->em->flush();

        $this->logger->info('OAuth provider unlinked', [
            'user_id' => $user->getId(),
            'provider' => $provider,
        ]);

        return $this->json([
            'success' => true,
            'message' => 'OAuth provider unlinked successfully',
        ]);
    }
}
