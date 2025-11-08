<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Exception\ApiProblemException;
use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class RefreshTokenService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RefreshTokenRepository $refreshTokenRepo
    ) {
    }

    /**
     * Create a new refresh token for a user
     */
    public function createRefreshToken(User $user, ?Request $request = null): RefreshToken
    {
        $refreshToken = new RefreshToken();
        $refreshToken->setUser($user);

        // Track IP and user agent for security
        if ($request) {
            $refreshToken->setIpAddress($request->getClientIp());
            $refreshToken->setUserAgent($request->headers->get('User-Agent'));
        }

        $this->em->persist($refreshToken);
        $this->em->flush();

        return $refreshToken;
    }

    /**
     * Validate and rotate refresh token
     * Returns the user if token is valid, throws exception otherwise
     */
    public function validateAndRotate(string $tokenString, ?Request $request = null): User
    {
        $refreshToken = $this->refreshTokenRepo->findOneByToken($tokenString);

        if (!$refreshToken) {
            throw ApiProblemException::unauthorized('Invalid refresh token');
        }

        if ($refreshToken->isRevoked()) {
            throw ApiProblemException::unauthorized('Refresh token has been revoked');
        }

        if ($refreshToken->isExpired()) {
            throw ApiProblemException::unauthorized('Refresh token has expired');
        }

        $user = $refreshToken->getUser();

        // 🔒 Security: Revoke the old token (token rotation)
        $refreshToken->revoke();
        $this->em->flush();

        // Create a new refresh token
        $this->createRefreshToken($user, $request);

        return $user;
    }

    /**
     * Revoke all refresh tokens for a user
     * Useful for logout from all devices
     */
    public function revokeAllForUser(User $user): int
    {
        return $this->refreshTokenRepo->revokeAllForUser($user);
    }

    /**
     * Clean up expired and revoked tokens
     * Should be run periodically (e.g., daily cron job)
     */
    public function cleanup(): array
    {
        $expiredCount = $this->refreshTokenRepo->deleteExpired();
        $revokedCount = $this->refreshTokenRepo->deleteRevoked();

        return [
            'expired' => $expiredCount,
            'revoked' => $revokedCount,
            'total' => $expiredCount + $revokedCount,
        ];
    }
}
