<?php

declare(strict_types=1);

namespace App\Security;

use App\Service\RefreshTokenService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use App\Service\Mercure\JwtMercureService;

class CustomAuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    private const JWT_TTL_SECONDS = 900; // 15 minutes

    public function __construct(
        private readonly JwtMercureService $jwtMercureService,
        private readonly JWTTokenManagerInterface $jwtTokenManager,
        private readonly RefreshTokenService $refreshTokenService
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): JsonResponse
    {
        $user = $token->getUser();

        // Generate JWT access token (15 minutes)
        $symfonyJwt = $this->jwtTokenManager->create($user);

        // Generate Mercure token for real-time messaging
        $mercureJwt = $this->jwtMercureService->createJwt();

        // 🔒 Security: Generate refresh token (7 days)
        $refreshToken = $this->refreshTokenService->createRefreshToken($user, $request);

        return new JsonResponse([
            'token' => $symfonyJwt,  // Access token (15 minutes)
            'refresh_token' => $refreshToken->getToken(),  // Refresh token (7 days)
            'mercure_token' => $mercureJwt,  // Mercure token
            'expires_in' => self::JWT_TTL_SECONDS,  // Token expiry in seconds
            'token_type' => 'Bearer',
        ]);
    }
}
