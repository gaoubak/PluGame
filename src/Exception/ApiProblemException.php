<?php

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * RFC 7807 Problem Details Exception
 *
 * Standard API error response format following RFC 7807:
 * https://datatracker.ietf.org/doc/html/rfc7807
 *
 * Example usage:
 * throw new ApiProblemException(
 *     status: 403,
 *     title: 'Insufficient permissions',
 *     detail: 'You do not have permission to access this booking',
 *     type: 'https://api.example.com/errors/forbidden'
 * );
 */
class ApiProblemException extends HttpException
{
    private string $problemType;
    private string $problemTitle;
    private ?string $problemDetail;
    private ?string $problemInstance;
    private array $additionalData;

    /**
     * @param int $status HTTP status code (400-599)
     * @param string $title Short, human-readable summary (e.g., "Validation Failed")
     * @param string|null $detail Human-readable explanation (e.g., "Email field is required")
     * @param string|null $type URI reference identifying the problem type (default: about:blank)
     * @param string|null $instance URI reference identifying specific occurrence
     * @param array $additionalData Extra fields (e.g., ['errors' => [...]])
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        int $status,
        string $title,
        ?string $detail = null,
        ?string $type = null,
        ?string $instance = null,
        array $additionalData = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($status, $title, $previous);

        $this->problemType = $type ?? 'about:blank';
        $this->problemTitle = $title;
        $this->problemDetail = $detail;
        $this->problemInstance = $instance;
        $this->additionalData = $additionalData;
    }

    /**
     * Convert exception to RFC 7807 array format
     */
    public function toProblemArray(): array
    {
        $problem = [
            'type' => $this->problemType,
            'title' => $this->problemTitle,
            'status' => $this->getStatusCode(),
        ];

        if ($this->problemDetail !== null) {
            $problem['detail'] = $this->problemDetail;
        }

        if ($this->problemInstance !== null) {
            $problem['instance'] = $this->problemInstance;
        }

        // Add any additional fields
        foreach ($this->additionalData as $key => $value) {
            $problem[$key] = $value;
        }

        return $problem;
    }

    /**
     * Factory: Create validation error
     */
    public static function validationFailed(array $errors, ?string $detail = null): self
    {
        return new self(
            status: 422,
            title: 'Validation Failed',
            detail: $detail ?? 'The request contains invalid data',
            type: 'validation-error',
            additionalData: ['errors' => $errors]
        );
    }

    /**
     * Factory: Create forbidden error
     */
    public static function forbidden(string $detail = 'Access denied'): self
    {
        return new self(
            status: 403,
            title: 'Forbidden',
            detail: $detail,
            type: 'forbidden'
        );
    }

    /**
     * Factory: Create not found error
     */
    public static function notFound(string $resource, ?string $id = null): self
    {
        $detail = $id
            ? "The {$resource} with ID '{$id}' was not found"
            : "The requested {$resource} was not found";

        return new self(
            status: 404,
            title: 'Not Found',
            detail: $detail,
            type: 'not-found'
        );
    }

    /**
     * Factory: Create unauthorized error
     */
    public static function unauthorized(string $detail = 'Authentication required'): self
    {
        return new self(
            status: 401,
            title: 'Unauthorized',
            detail: $detail,
            type: 'unauthorized'
        );
    }

    /**
     * Factory: Create conflict error
     */
    public static function conflict(string $detail): self
    {
        return new self(
            status: 409,
            title: 'Conflict',
            detail: $detail,
            type: 'conflict'
        );
    }

    /**
     * Factory: Create bad request error
     */
    public static function badRequest(string $detail): self
    {
        return new self(
            status: 400,
            title: 'Bad Request',
            detail: $detail,
            type: 'bad-request'
        );
    }

    /**
     * Factory: Create internal server error
     */
    public static function internalError(string $detail = 'An unexpected error occurred'): self
    {
        return new self(
            status: 500,
            title: 'Internal Server Error',
            detail: $detail,
            type: 'internal-error'
        );
    }

    /**
     * Factory: Create service unavailable error
     */
    public static function serviceUnavailable(string $service, ?string $reason = null): self
    {
        $detail = $reason
            ? "Service {$service} is unavailable: {$reason}"
            : "Service {$service} is temporarily unavailable";

        return new self(
            status: 503,
            title: 'Service Unavailable',
            detail: $detail,
            type: 'service-unavailable'
        );
    }
}
