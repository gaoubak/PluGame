<?php

namespace App\EventListener;

use App\Exception\ApiProblemException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Global API Exception Handler
 *
 * Converts all exceptions to RFC 7807 Problem Details format
 * for consistent error responses across the API.
 */
class ApiExceptionListener
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $environment,
    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        // Only handle JSON API requests (skip HTML requests)
        if (!$this->isApiRequest($request)) {
            return;
        }

        // Convert exception to RFC 7807 format
        $problemData = $this->convertToProblemDetails($exception);

        // Log the error
        $this->logException($exception, $problemData);

        // Create JSON response
        $response = new JsonResponse(
            $problemData,
            $problemData['status'],
            [
                'Content-Type' => 'application/problem+json',
            ]
        );

        $event->setResponse($response);
    }

    /**
     * Check if request expects JSON response
     */
    private function isApiRequest($request): bool
    {
        // Check if request path starts with /api
        if (str_starts_with($request->getPathInfo(), '/api')) {
            return true;
        }

        // Check Accept header
        $accept = $request->headers->get('Accept', '');
        if (str_contains($accept, 'application/json')) {
            return true;
        }

        // Check Content-Type header for POST/PUT requests
        $contentType = $request->headers->get('Content-Type', '');
        if (str_contains($contentType, 'application/json')) {
            return true;
        }

        return false;
    }

    /**
     * Convert exception to RFC 7807 Problem Details
     */
    private function convertToProblemDetails(\Throwable $exception): array
    {
        // 1. Handle our custom ApiProblemException
        if ($exception instanceof ApiProblemException) {
            return $exception->toProblemArray();
        }

        // 2. Handle Symfony Validation exceptions
        if ($exception instanceof ValidationFailedException) {
            return $this->handleValidationException($exception);
        }

        // 3. Handle Symfony Security exceptions
        if ($exception instanceof AccessDeniedException) {
            return [
                'type' => 'forbidden',
                'title' => 'Forbidden',
                'status' => 403,
                'detail' => 'You do not have permission to perform this action',
            ];
        }

        if ($exception instanceof AuthenticationException) {
            return [
                'type' => 'unauthorized',
                'title' => 'Unauthorized',
                'status' => 401,
                'detail' => $exception->getMessage() ?: 'Authentication required',
            ];
        }

        // 4. Handle Symfony HTTP exceptions
        if ($exception instanceof HttpExceptionInterface) {
            return [
                'type' => 'http-error',
                'title' => Response::$statusTexts[$exception->getStatusCode()] ?? 'Error',
                'status' => $exception->getStatusCode(),
                'detail' => $exception->getMessage() ?: null,
            ];
        }

        // 5. Handle generic exceptions (500 Internal Server Error)
        return $this->handleGenericException($exception);
    }

    /**
     * Handle validation exceptions
     */
    private function handleValidationException(ValidationFailedException $exception): array
    {
        $violations = $exception->getViolations();
        $errors = [];

        foreach ($violations as $violation) {
            $propertyPath = $violation->getPropertyPath();
            if (!isset($errors[$propertyPath])) {
                $errors[$propertyPath] = [];
            }
            $errors[$propertyPath][] = $violation->getMessage();
        }

        return [
            'type' => 'validation-error',
            'title' => 'Validation Failed',
            'status' => 422,
            'detail' => 'The request contains invalid data',
            'errors' => $errors,
        ];
    }

    /**
     * Handle generic exceptions
     */
    private function handleGenericException(\Throwable $exception): array
    {
        $problem = [
            'type' => 'internal-error',
            'title' => 'Internal Server Error',
            'status' => 500,
            'detail' => 'An unexpected error occurred',
        ];

        // In development, include exception details for debugging
        if ($this->environment === 'dev') {
            $problem['debug'] = [
                'message' => $exception->getMessage(),
                'exception' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ];
        }

        return $problem;
    }

    /**
     * Log the exception
     */
    private function logException(\Throwable $exception, array $problemData): void
    {
        $context = [
            'exception' => get_class($exception),
            'status' => $problemData['status'],
            'message' => $exception->getMessage(),
        ];

        // Log level based on status code
        $statusCode = $problemData['status'];

        if ($statusCode >= 500) {
            // Server errors (500+)
            $this->logger->error('API Server Error', $context + [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);
        } elseif ($statusCode >= 400) {
            // Client errors (400-499) - less severe
            $this->logger->warning('API Client Error', $context);
        } else {
            // Other errors
            $this->logger->notice('API Error', $context);
        }
    }
}
