<?php

declare(strict_types=1);

namespace App\Traits;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

trait ApiResponseTrait
{
    /**
     * Create a JSON API response.
     *
     * @param mixed $data
     * @param int $statusCode
     * @param array $headers
     */
    protected function createApiResponse(
        mixed $data,
        int $statusCode = Response::HTTP_OK,
        array $headers = []
    ): JsonResponse {
        return new JsonResponse($data, $statusCode, $headers);
    }

    /**
     * Success response with data (200 OK)
     */
    protected function successResponse(
        mixed $data,
        string $message = 'Success'
    ): JsonResponse {
        return $this->createApiResponse([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], Response::HTTP_OK);
    }

    /**
     * Created response (201 CREATED)
     */
    protected function createdResponse(
        mixed $data,
        string $message = 'Resource created successfully'
    ): JsonResponse {
        return $this->createApiResponse([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], Response::HTTP_CREATED);
    }

    /**
     * Render created response (backward compatibility with your existing code)
     */
    public function renderCreatedResponse(
        string $message = 'Resource created successfully',
        int $statusCode = Response::HTTP_CREATED
    ): JsonResponse {
        return $this->createApiResponse(['message' => $message], $statusCode);
    }

    /**
     * Render updated response (backward compatibility)
     */
    public function renderUpdatedResponse(
        string $message = 'Resource updated successfully',
        int $statusCode = Response::HTTP_OK
    ): JsonResponse {
        return $this->createApiResponse(['message' => $message], $statusCode);
    }

    /**
     * Render deleted response (backward compatibility)
     */
    public function renderDeletedResponse(
        string $message = 'Resource deleted successfully',
        int $statusCode = Response::HTTP_OK
    ): JsonResponse {
        return $this->createApiResponse(['message' => $message], $statusCode);
    }

    /**
     * Render forbidden response (backward compatibility)
     */
    public function renderForbiddenResponse(
        string $message = 'Forbidden',
        int $statusCode = Response::HTTP_FORBIDDEN
    ): JsonResponse {
        return $this->createApiResponse(['message' => $message], $statusCode);
    }

    /**
     * Error response (generic)
     */
    protected function errorResponse(
        string $message,
        int $statusCode = Response::HTTP_BAD_REQUEST,
        ?array $errors = null
    ): JsonResponse {
        $data = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $data['errors'] = $errors;
        }

        return $this->createApiResponse($data, $statusCode);
    }

    /**
     * Validation error response (400 BAD REQUEST)
     */
    protected function validationErrorResponse(
        array $errors,
        string $message = 'Validation failed'
    ): JsonResponse {
        return $this->errorResponse($message, Response::HTTP_BAD_REQUEST, $errors);
    }

    /**
     * Not found response (404 NOT FOUND)
     */
    protected function notFoundResponse(
        string $message = 'Resource not found'
    ): JsonResponse {
        return $this->errorResponse($message, Response::HTTP_NOT_FOUND);
    }

    /**
     * Unauthorized response (401 UNAUTHORIZED)
     */
    protected function unauthorizedResponse(
        string $message = 'Unauthorized'
    ): JsonResponse {
        return $this->errorResponse($message, Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Forbidden response (403 FORBIDDEN)
     */
    protected function forbiddenResponse(
        string $message = 'Forbidden'
    ): JsonResponse {
        return $this->errorResponse($message, Response::HTTP_FORBIDDEN);
    }

    /**
     * Conflict response (409 CONFLICT)
     */
    protected function conflictResponse(
        string $message = 'Conflict',
        ?array $errors = null
    ): JsonResponse {
        return $this->errorResponse($message, Response::HTTP_CONFLICT, $errors);
    }

    /**
     * No content response (204 NO CONTENT)
     */
    protected function noContentResponse(): Response
    {
        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * Paginated response helper
     */
    protected function paginatedResponse(
        array $items,
        int $page,
        int $limit,
        int $total
    ): JsonResponse {
        return $this->createApiResponse([
            'success' => true,
            'data' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int) ceil($total / $limit),
            ],
        ]);
    }
}
