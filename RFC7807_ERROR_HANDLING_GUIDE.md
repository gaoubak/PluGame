# RFC 7807 Error Handling Guide

## Overview

This application implements **RFC 7807 Problem Details** for standardized API error responses. All exceptions thrown in `/api/*` endpoints are automatically converted to the RFC 7807 format.

**RFC 7807 Specification:** https://datatracker.ietf.org/doc/html/rfc7807

---

## Architecture

```
┌─────────────────┐
│   Controller    │
└────────┬────────┘
         │ throw exception
         ▼
┌─────────────────────────┐
│ ApiExceptionListener    │ ◄── Catches all exceptions
└────────┬────────────────┘
         │ converts to RFC 7807
         ▼
┌─────────────────────────┐
│   JSON Response         │
│ application/problem+json│
└─────────────────────────┘
```

---

## Standard Error Response Format

All API errors follow this structure:

```json
{
  "type": "validation-error",
  "title": "Validation Failed",
  "status": 422,
  "detail": "The request contains invalid data",
  "errors": {
    "email": ["Email is required"],
    "amount": ["Amount must be positive"]
  }
}
```

**Required fields:**
- `type` - URI reference identifying the problem type
- `title` - Short, human-readable summary
- `status` - HTTP status code (same as response status)

**Optional fields:**
- `detail` - Human-readable explanation
- `instance` - URI reference to specific occurrence
- `errors` - Validation errors (for 422 responses)
- `debug` - Debug info (only in dev environment)

---

## Usage Examples

### Example 1: Basic Validation Error

```php
use App\Exception\ApiProblemException;

#[Route('/api/bookings', methods: ['POST'])]
public function create(Request $request): Response
{
    $data = json_decode($request->getContent(), true);

    if (empty($data['amount'])) {
        throw ApiProblemException::validationFailed([
            'amount' => ['Amount is required']
        ]);
    }

    // ... rest of logic
}
```

**Response (422 Unprocessable Entity):**
```json
{
  "type": "validation-error",
  "title": "Validation Failed",
  "status": 422,
  "detail": "The request contains invalid data",
  "errors": {
    "amount": ["Amount is required"]
  }
}
```

---

### Example 2: Authorization Error

```php
use App\Exception\ApiProblemException;
use App\Security\Voter\BookingVoter;

#[Route('/api/bookings/{id}', methods: ['DELETE'])]
public function delete(Booking $booking): Response
{
    if (!$this->isGranted(BookingVoter::DELETE, $booking)) {
        throw ApiProblemException::forbidden(
            'You can only delete your own bookings'
        );
    }

    // ... delete logic
}
```

**Response (403 Forbidden):**
```json
{
  "type": "forbidden",
  "title": "Forbidden",
  "status": 403,
  "detail": "You can only delete your own bookings"
}
```

---

### Example 3: Resource Not Found

```php
use App\Exception\ApiProblemException;

#[Route('/api/users/{id}', methods: ['GET'])]
public function getUser(string $id, UserRepository $userRepo): Response
{
    $user = $userRepo->find($id);

    if (!$user) {
        throw ApiProblemException::notFound('user', $id);
    }

    return $this->json($user);
}
```

**Response (404 Not Found):**
```json
{
  "type": "not-found",
  "title": "Not Found",
  "status": 404,
  "detail": "The user with ID 'abc123' was not found"
}
```

---

### Example 4: Custom Error with Additional Data

```php
use App\Exception\ApiProblemException;

#[Route('/api/bookings/{id}/accept', methods: ['POST'])]
public function accept(Booking $booking): Response
{
    if ($booking->getStatus() !== 'pending') {
        throw new ApiProblemException(
            status: 409,
            title: 'Booking Cannot Be Accepted',
            detail: "Cannot accept booking in '{$booking->getStatus()}' status",
            type: 'booking-invalid-status',
            additionalData: [
                'booking_id' => $booking->getId(),
                'current_status' => $booking->getStatus(),
                'allowed_statuses' => ['pending']
            ]
        );
    }

    // ... accept logic
}
```

**Response (409 Conflict):**
```json
{
  "type": "booking-invalid-status",
  "title": "Booking Cannot Be Accepted",
  "status": 409,
  "detail": "Cannot accept booking in 'confirmed' status",
  "booking_id": "abc123",
  "current_status": "confirmed",
  "allowed_statuses": ["pending"]
}
```

---

### Example 5: Service Unavailable

```php
use App\Exception\ApiProblemException;

#[Route('/api/payments/charge', methods: ['POST'])]
public function charge(): Response
{
    try {
        $result = $this->stripeService->createCharge();
    } catch (StripeException $e) {
        throw ApiProblemException::serviceUnavailable(
            service: 'Stripe',
            reason: 'Payment provider is temporarily unavailable'
        );
    }

    return $this->json($result);
}
```

**Response (503 Service Unavailable):**
```json
{
  "type": "service-unavailable",
  "title": "Service Unavailable",
  "status": 503,
  "detail": "Service Stripe is unavailable: Payment provider is temporarily unavailable"
}
```

---

## Built-in Factory Methods

The `ApiProblemException` class provides convenient factory methods:

| Method | Status | Use Case |
|--------|--------|----------|
| `validationFailed($errors)` | 422 | Form/input validation failures |
| `forbidden($detail)` | 403 | Authorization failures |
| `notFound($resource, $id)` | 404 | Resource not found |
| `unauthorized($detail)` | 401 | Authentication required |
| `conflict($detail)` | 409 | Resource conflicts (e.g., duplicate email) |
| `badRequest($detail)` | 400 | Malformed requests |
| `internalError($detail)` | 500 | Unexpected server errors |
| `serviceUnavailable($service, $reason)` | 503 | External service failures |

---

## Automatic Exception Handling

The `ApiExceptionListener` automatically handles these Symfony exceptions:

### 1. Symfony Security Exceptions

```php
// No need to catch - automatically converted
$this->denyAccessUnlessGranted(BookingVoter::VIEW, $booking);
```

**Automatic Response (403):**
```json
{
  "type": "forbidden",
  "title": "Forbidden",
  "status": 403,
  "detail": "You do not have permission to perform this action"
}
```

### 2. HTTP Exceptions

```php
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

throw new NotFoundHttpException('User not found');
```

**Automatic Response (404):**
```json
{
  "type": "http-error",
  "title": "Not Found",
  "status": 404,
  "detail": "User not found"
}
```

### 3. Validation Exceptions

```php
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

$violations = $validator->validate($user);
if (count($violations) > 0) {
    throw new ValidationFailedException($user, $violations);
}
```

**Automatic Response (422):**
```json
{
  "type": "validation-error",
  "title": "Validation Failed",
  "status": 422,
  "detail": "The request contains invalid data",
  "errors": {
    "email": ["This value should not be blank"],
    "password": ["This value is too short"]
  }
}
```

---

## Development vs Production

### Development Environment (`APP_ENV=dev`)

```json
{
  "type": "internal-error",
  "title": "Internal Server Error",
  "status": 500,
  "detail": "An unexpected error occurred",
  "debug": {
    "message": "Division by zero",
    "exception": "DivisionByZeroError",
    "file": "/app/src/Controller/BookingController.php",
    "line": 142,
    "trace": "..."
  }
}
```

### Production Environment (`APP_ENV=prod`)

```json
{
  "type": "internal-error",
  "title": "Internal Server Error",
  "status": 500,
  "detail": "An unexpected error occurred"
}
```

**Security:** Debug information is only shown in `dev` environment to prevent leaking sensitive information.

---

## Logging

All exceptions are automatically logged with appropriate severity:

| Status Code | Log Level | Examples |
|-------------|-----------|----------|
| 500+ | `error` | Internal server errors, database failures |
| 400-499 | `warning` | Bad requests, validation errors, 404s |
| Other | `notice` | Other errors |

**Log Example:**
```
[2025-11-07 10:30:15] app.ERROR: API Server Error
{
  "exception": "App\\Exception\\ApiProblemException",
  "status": 500,
  "message": "Database connection failed",
  "file": "/app/src/Controller/UserController.php",
  "line": 45
}
```

---

## Best Practices

### ✅ DO: Use Specific Error Types

```php
// Good: Specific error type
throw ApiProblemException::conflict('Email already exists');

// Bad: Generic error
throw new \Exception('Email already exists');
```

### ✅ DO: Provide Helpful Detail Messages

```php
// Good: Clear, actionable message
throw ApiProblemException::validationFailed([
    'start_time' => ['Start time must be in the future']
]);

// Bad: Vague message
throw ApiProblemException::badRequest('Invalid input');
```

### ✅ DO: Use Factory Methods When Available

```php
// Good: Using factory method
throw ApiProblemException::notFound('booking', $id);

// Acceptable: Custom error with additional context
throw new ApiProblemException(
    status: 409,
    title: 'Booking Conflict',
    detail: 'Time slot is already booked',
    additionalData: ['slot_id' => $slotId]
);
```

### ❌ DON'T: Expose Sensitive Information

```php
// Bad: Exposes internal implementation
throw ApiProblemException::internalError('MySQL query failed: ' . $e->getMessage());

// Good: Generic message
throw ApiProblemException::internalError('Database operation failed');
```

### ❌ DON'T: Catch Exceptions Unnecessarily

```php
// Bad: Catching and re-throwing
try {
    $this->denyAccessUnlessGranted(BookingVoter::VIEW, $booking);
} catch (AccessDeniedException $e) {
    throw ApiProblemException::forbidden('Access denied');
}

// Good: Let the listener handle it automatically
$this->denyAccessUnlessGranted(BookingVoter::VIEW, $booking);
```

---

## Migration from Old Error Responses

### Before (Inconsistent Format)

```php
// Old style - inconsistent formats
return $this->json(['error' => 'Not found'], 404);
return $this->json(['message' => 'Invalid input'], 400);
return $this->json(['status' => 'error', 'errors' => [...]], 422);
```

### After (RFC 7807)

```php
// New style - consistent RFC 7807 format
throw ApiProblemException::notFound('booking', $id);
throw ApiProblemException::badRequest('Invalid input');
throw ApiProblemException::validationFailed(['email' => ['Required']]);
```

---

## Testing Error Responses

### Unit Test Example

```php
use App\Exception\ApiProblemException;
use PHPUnit\Framework\TestCase;

class ApiProblemExceptionTest extends TestCase
{
    public function testValidationFailedFormat(): void
    {
        $exception = ApiProblemException::validationFailed([
            'email' => ['Email is required']
        ]);

        $problemArray = $exception->toProblemArray();

        $this->assertEquals(422, $problemArray['status']);
        $this->assertEquals('validation-error', $problemArray['type']);
        $this->assertArrayHasKey('errors', $problemArray);
    }
}
```

### Integration Test Example

```php
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BookingApiTest extends WebTestCase
{
    public function testCannotDeleteOthersBooking(): void
    {
        $client = static::createClient();
        $client->request('DELETE', '/api/bookings/123', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->getJwtToken(),
        ]);

        $this->assertResponseStatusCodeSame(403);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals('forbidden', $response['type']);
        $this->assertEquals('Forbidden', $response['title']);
        $this->assertEquals(403, $response['status']);
    }
}
```

---

## Troubleshooting

### Problem: Errors not being formatted as RFC 7807

**Solution:** Check that the request is identified as an API request:
- URL starts with `/api`
- OR `Accept: application/json` header present
- OR `Content-Type: application/json` header present

### Problem: Stack traces visible in production

**Solution:** Ensure `APP_ENV=prod` in your `.env` file. Debug information is only shown when `APP_ENV=dev`.

### Problem: Custom exceptions not being caught

**Solution:** Ensure your exception extends `ApiProblemException` or one of Symfony's HTTP exceptions. The listener handles:
- `ApiProblemException`
- `HttpExceptionInterface`
- `AccessDeniedException`
- `AuthenticationException`
- `ValidationFailedException`
- Generic `\Throwable` (500 error)

---

## Complete Example: Refactored Controller

### Before (Mixed error formats)

```php
#[Route('/api/bookings/{id}/accept', methods: ['POST'])]
public function accept(string $id, BookingRepository $repo): Response
{
    $booking = $repo->find($id);

    if (!$booking) {
        return $this->json(['error' => 'Booking not found'], 404);
    }

    if ($booking->getCreator()->getId() !== $this->getUser()->getId()) {
        return $this->json(['message' => 'Access denied'], 403);
    }

    if ($booking->getStatus() !== 'pending') {
        return $this->json([
            'status' => 'error',
            'message' => 'Can only accept pending bookings'
        ], 400);
    }

    $booking->setStatus('confirmed');
    $repo->save($booking);

    return $this->json(['status' => 'success', 'booking' => $booking]);
}
```

### After (RFC 7807)

```php
use App\Exception\ApiProblemException;
use App\Security\Voter\BookingVoter;

#[Route('/api/bookings/{id}/accept', methods: ['POST'])]
public function accept(
    Booking $booking,  // ParamConverter handles 404 automatically
    BookingRepository $repo
): Response
{
    // Authorization check (403 if fails)
    $this->denyAccessUnlessGranted(BookingVoter::ACCEPT, $booking);

    // Business logic validation
    if ($booking->getStatus() !== 'pending') {
        throw ApiProblemException::conflict(
            "Cannot accept booking in '{$booking->getStatus()}' status"
        );
    }

    $booking->setStatus('confirmed');
    $repo->save($booking);

    return $this->json($booking);
}
```

**Benefits:**
- Consistent error format
- Less boilerplate code
- Clearer separation of concerns
- Automatic logging
- Better DX with type safety

---

## HTTP Status Code Guide

| Code | Exception | Use Case |
|------|-----------|----------|
| 400 | `badRequest()` | Malformed JSON, invalid query parameters |
| 401 | `unauthorized()` | Missing or invalid JWT token |
| 403 | `forbidden()` | Valid token but insufficient permissions |
| 404 | `notFound()` | Resource doesn't exist |
| 409 | `conflict()` | Duplicate resource, invalid state transition |
| 422 | `validationFailed()` | Valid JSON but invalid field values |
| 500 | `internalError()` | Database errors, unexpected exceptions |
| 503 | `serviceUnavailable()` | Stripe down, Redis unavailable |

---

**End of RFC 7807 Error Handling Guide**

All API endpoints should now use this standardized error format for consistent, developer-friendly error responses.
