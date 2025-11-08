<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\ApiProblemException;
use App\Service\Mercure\JwtMercureService;
use App\Service\RefreshTokenService;
use App\Traits\ApiResponseTrait;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Attributes as OA;

#[Route('/api/token')]
#[OA\Tag(name: 'Authentication')]
class TokenController extends AbstractController
{
    use ApiResponseTrait;

    private const JWT_TTL_SECONDS = 900; // 15 minutes

    public function __construct(
        private readonly RefreshTokenService $refreshTokenService,
        private readonly JWTTokenManagerInterface $jwtTokenManager,
        private readonly JwtMercureService $jwtMercureService
    ) {
    }

    /**
     * Refresh access token using a refresh token
     */
    #[Route('/refresh', name: 'api_token_refresh', methods: ['POST'])]
    #[OA\Post(
        path: '/api/token/refresh',
        summary: 'Refresh access token',
        description: 'Exchange a refresh token for a new access token and refresh token pair. The old refresh token will be revoked (token rotation for security).',
        tags: ['Authentication']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['refresh_token'],
            properties: [
                new OA\Property(
                    property: 'refresh_token',
                    type: 'string',
                    description: 'The refresh token obtained from login',
                    example: '3f2504e04f8911e3a0c200505001c1c4...'
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'New tokens generated successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'token', type: 'string', description: 'New JWT access token (15 minutes)'),
                new OA\Property(property: 'refresh_token', type: 'string', description: 'New refresh token (7 days)'),
                new OA\Property(property: 'mercure_token', type: 'string', description: 'Mercure JWT for real-time features'),
                new OA\Property(property: 'expires_in', type: 'integer', description: 'Token expiry in seconds', example: 900),
                new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Invalid, expired, or revoked refresh token',
        content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails')
    )]
    #[OA\Response(
        response: 400,
        description: 'Missing refresh_token in request body',
        content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails')
    )]
    public function refresh(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['refresh_token']) || empty($data['refresh_token'])) {
            throw ApiProblemException::badRequest('Missing or empty refresh_token');
        }

        // 🔒 Security: Validate and rotate refresh token
        $user = $this->refreshTokenService->validateAndRotate($data['refresh_token'], $request);

        // Generate new access token
        $accessToken = $this->jwtTokenManager->create($user);

        // Generate new Mercure token
        $mercureToken = $this->jwtMercureService->createJwt();

        // Create new refresh token (the old one was revoked in validateAndRotate)
        $newRefreshToken = $this->refreshTokenService->createRefreshToken($user, $request);

        return $this->createApiResponse([
            'token' => $accessToken,
            'refresh_token' => $newRefreshToken->getToken(),
            'mercure_token' => $mercureToken,
            'expires_in' => self::JWT_TTL_SECONDS,
            'token_type' => 'Bearer',
        ], Response::HTTP_OK);
    }

    /**
     * Revoke all refresh tokens for the current user
     * Useful for "logout from all devices"
     */
    #[Route('/revoke-all', name: 'api_token_revoke_all', methods: ['POST'])]
    #[OA\Post(
        path: '/api/token/revoke-all',
        summary: 'Revoke all refresh tokens (logout from all devices)',
        security: [['bearerAuth' => []]],
        tags: ['Authentication']
    )]
    #[OA\Response(
        response: 200,
        description: 'All refresh tokens revoked successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'All refresh tokens have been revoked'),
                new OA\Property(property: 'revoked_count', type: 'integer', example: 5),
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Unauthorized')]
    public function revokeAll(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            throw ApiProblemException::unauthorized('Authentication required');
        }

        $revokedCount = $this->refreshTokenService->revokeAllForUser($user);

        return $this->createApiResponse([
            'message' => 'All refresh tokens have been revoked',
            'revoked_count' => $revokedCount,
        ], Response::HTTP_OK);
    }
}
