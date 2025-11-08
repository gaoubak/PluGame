<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Exception\ApiProblemException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\HttpFoundation\JsonResponse;

class RateLimitListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly RateLimiterFactory $loginLimiter,
        private readonly RateLimiterFactory $apiLimiter,
        private readonly RateLimiterFactory $passwordResetLimiter,
        private readonly RateLimiterFactory $tokenRefreshLimiter,
        private readonly RateLimiterFactory $registrationLimiter,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Skip rate limiting for non-API requests
        if (!str_starts_with($path, '/api/')) {
            return;
        }

        // Skip rate limiting for API documentation
        if (str_starts_with($path, '/api/doc')) {
            return;
        }

        // Apply specific rate limiters based on endpoint
        $this->applyRateLimiting($request, $path, $event);
    }

    private function applyRateLimiting(Request $request, string $path, RequestEvent $event): void
    {
        $clientIp = $request->getClientIp() ?? 'unknown';

        // 🔒 Login Rate Limiting: By IP + username
        if ($path === '/api/login_check' || $path === '/api/login') {
            $data = json_decode($request->getContent(), true);
            $username = $data['username'] ?? '';
            $key = $clientIp . '_' . $username;
            $limiter = $this->loginLimiter->create($key);

            $limit = $limiter->consume(1);
            if (!$limit->isAccepted()) {
                $this->handleRateLimitExceeded($event, $limit->getRetryAfter(), 'Too many login attempts. Please try again later.');
                return;
            }
        }

        // 🔒 Password Reset Rate Limiting: By IP
        if (str_starts_with($path, '/api/password/reset')) {
            $limiter = $this->passwordResetLimiter->create($clientIp);
            $limit = $limiter->consume(1);

            if (!$limit->isAccepted()) {
                $this->handleRateLimitExceeded($event, $limit->getRetryAfter(), 'Too many password reset attempts. Please try again later.');
                return;
            }
        }

        // 🔒 Token Refresh Rate Limiting: By IP
        if ($path === '/api/token/refresh') {
            $limiter = $this->tokenRefreshLimiter->create($clientIp);
            $limit = $limiter->consume(1);

            if (!$limit->isAccepted()) {
                $this->handleRateLimitExceeded($event, $limit->getRetryAfter(), 'Too many token refresh attempts. Please try again later.');
                return;
            }
        }

        // 🔒 Registration Rate Limiting: By IP
        if ($path === '/api/users/register') {
            $limiter = $this->registrationLimiter->create($clientIp);
            $limit = $limiter->consume(1);

            if (!$limit->isAccepted()) {
                $this->handleRateLimitExceeded($event, $limit->getRetryAfter(), 'Too many registration attempts. Please try again later.');
                return;
            }
        }

        // 🔒 General API Rate Limiting: By IP (for all other API requests)
        $limiter = $this->apiLimiter->create($clientIp);
        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            $this->handleRateLimitExceeded($event, $limit->getRetryAfter(), 'Rate limit exceeded. Please slow down your requests.');
            return;
        }

        // Add rate limit headers to response
        $response = $event->getResponse() ?? new JsonResponse();
        $response->headers->set('X-RateLimit-Remaining', (string) $limit->getRemainingTokens());
        $response->headers->set('X-RateLimit-Limit', (string) $limit->getLimit());
        if ($limit->getRetryAfter()) {
            $response->headers->set('X-RateLimit-Reset', (string) $limit->getRetryAfter()->getTimestamp());
        }
    }

    private function handleRateLimitExceeded(RequestEvent $event, ?\DateTimeImmutable $retryAfter, string $message): void
    {
        $retryAfterSeconds = $retryAfter ? $retryAfter->getTimestamp() - time() : null;

        // Create RFC 7807 Problem Details response
        $problemDetails = [
            'type' => 'https://api.23hec001.com/errors/rate-limit-exceeded',
            'title' => 'Rate Limit Exceeded',
            'status' => 429,
            'detail' => $message,
            'instance' => $event->getRequest()->getRequestUri(),
        ];

        if ($retryAfterSeconds) {
            $problemDetails['retry_after'] = $retryAfterSeconds;
        }

        $response = new JsonResponse($problemDetails, 429);

        if ($retryAfter) {
            $response->headers->set('Retry-After', (string) $retryAfterSeconds);
            $response->headers->set('X-RateLimit-Reset', (string) $retryAfter->getTimestamp());
        }

        $event->setResponse($response);
    }
}
