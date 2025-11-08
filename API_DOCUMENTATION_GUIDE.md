# API Documentation Guide

## 📚 OpenAPI/Swagger Documentation Setup

The API is now documented using **NelmioApiDocBundle** with OpenAPI 3.0 specification.

### Access Documentation

- **Interactive UI:** http://localhost:8000/api/doc
- **JSON Spec:** http://localhost:8000/api/doc.json

---

## 🏷️ How to Document Endpoints

### Basic Endpoint Documentation

```php
<?php

namespace App\Controller;

use OpenApi\Attributes as OA;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/users')]
#[OA\Tag(name: 'Users')]
class UserController extends AbstractController
{
    #[Route('/{id}', name: 'api_user_get', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/users/{id}',
        summary: 'Get user by ID',
        description: 'Returns detailed information about a specific user including their profile and statistics.',
        tags: ['Users']
    )]
    #[OA\Parameter(
        name: 'id',
        description: 'User ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'User found',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'id', type: 'integer', example: 123),
                new OA\Property(property: 'username', type: 'string', example: 'johndoe'),
                new OA\Property(property: 'email', type: 'string', example: 'john@example.com'),
                new OA\Property(
                    property: 'creatorProfile',
                    type: 'object',
                    nullable: true,
                    properties: [
                        new OA\Property(property: 'displayName', type: 'string'),
                        new OA\Property(property: 'bio', type: 'string'),
                    ]
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'User not found',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'User not found'),
            ]
        )
    )]
    #[OA\Response(
        response: 403,
        description: 'Access denied',
        content: new OA\JsonContent(ref: '#/components/schemas/Error')
    )]
    public function getUser(int $id): JsonResponse
    {
        // Implementation
    }
}
```

### POST Endpoint with Request Body

```php
#[Route('/create', name: 'api_booking_create', methods: ['POST'])]
#[OA\Post(
    path: '/api/v1/bookings/create',
    summary: 'Create a new booking',
    description: 'Creates a booking for a service with specified time slots or duration.',
    tags: ['Bookings']
)]
#[OA\RequestBody(
    description: 'Booking creation data',
    required: true,
    content: new OA\JsonContent(
        required: ['serviceId'],
        properties: [
            new OA\Property(
                property: 'serviceId',
                description: 'UUID of the service to book',
                type: 'string',
                format: 'uuid',
                example: '550e8400-e29b-41d4-a716-446655440000'
            ),
            new OA\Property(
                property: 'slotIds',
                description: 'Array of availability slot UUIDs (Mode A)',
                type: 'array',
                items: new OA\Items(type: 'string', format: 'uuid'),
                example: ['550e8400-e29b-41d4-a716-446655440001']
            ),
            new OA\Property(
                property: 'startTime',
                description: 'Start time ISO 8601 (Mode B)',
                type: 'string',
                format: 'date-time',
                example: '2025-12-01T10:00:00Z'
            ),
            new OA\Property(
                property: 'durationMin',
                description: 'Duration in minutes (Mode B)',
                type: 'integer',
                example: 120
            ),
            new OA\Property(
                property: 'location',
                description: 'Short location name',
                type: 'string',
                example: 'Central Park'
            ),
            new OA\Property(
                property: 'locationText',
                description: 'Full address',
                type: 'string',
                example: 'Central Park, New York, NY 10022'
            ),
            new OA\Property(
                property: 'notes',
                description: 'Additional notes from athlete',
                type: 'string',
                nullable: true
            ),
        ],
        example: [
            'serviceId' => '550e8400-e29b-41d4-a716-446655440000',
            'startTime' => '2025-12-01T10:00:00Z',
            'durationMin' => 120,
            'location' => 'Central Park',
            'notes' => 'Please bring drone for aerial shots'
        ]
    )
)]
#[OA\Response(
    response: 201,
    description: 'Booking created successfully',
    content: new OA\JsonContent(
        properties: [
            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'startTime', type: 'string', format: 'date-time'),
            new OA\Property(property: 'endTime', type: 'string', format: 'date-time'),
            new OA\Property(property: 'subtotal', type: 'integer', description: 'Amount in cents'),
            new OA\Property(property: 'total', type: 'integer', description: 'Amount in cents'),
            new OA\Property(property: 'status', type: 'string', enum: ['PENDING']),
        ]
    )
)]
#[OA\Response(
    response: 400,
    description: 'Validation error',
    content: new OA\JsonContent(
        properties: [
            new OA\Property(property: 'error', type: 'string'),
            new OA\Property(
                property: 'errors',
                type: 'object',
                additionalProperties: new OA\AdditionalProperties(type: 'array', items: new OA\Items(type: 'string'))
            ),
        ]
    )
)]
public function create(Request $request): JsonResponse
{
    // Implementation
}
```

### Paginated List Endpoint

```php
#[Route('/', name: 'api_bookings_list', methods: ['GET'])]
#[OA\Get(
    path: '/api/v1/bookings',
    summary: 'List bookings',
    description: 'Get paginated list of bookings',
    tags: ['Bookings']
)]
#[OA\Parameter(
    name: 'page',
    description: 'Page number',
    in: 'query',
    required: false,
    schema: new OA\Schema(type: 'integer', default: 1, minimum: 1)
)]
#[OA\Parameter(
    name: 'limit',
    description: 'Items per page',
    in: 'query',
    required: false,
    schema: new OA\Schema(type: 'integer', default: 20, minimum: 1, maximum: 100)
)]
#[OA\Parameter(
    name: 'status',
    description: 'Filter by status',
    in: 'query',
    required: false,
    schema: new OA\Schema(
        type: 'string',
        enum: ['PENDING', 'ACCEPTED', 'DECLINED', 'CANCELLED', 'IN_PROGRESS', 'COMPLETED', 'REFUNDED']
    )
)]
#[OA\Response(
    response: 200,
    description: 'List of bookings',
    content: new OA\JsonContent(
        properties: [
            new OA\Property(
                property: 'data',
                type: 'array',
                items: new OA\Items(ref: '#/components/schemas/BookingDetails')
            ),
            new OA\Property(
                property: 'meta',
                type: 'object',
                properties: [
                    new OA\Property(property: 'page', type: 'integer'),
                    new OA\Property(property: 'limit', type: 'integer'),
                    new OA\Property(property: 'total', type: 'integer'),
                ]
            ),
            new OA\Property(
                property: 'links',
                type: 'object',
                properties: [
                    new OA\Property(property: 'self', type: 'string'),
                    new OA\Property(property: 'next', type: 'string', nullable: true),
                    new OA\Property(property: 'prev', type: 'string', nullable: true),
                ]
            ),
        ]
    )
)]
public function list(Request $request): JsonResponse
{
    // Implementation
}
```

### Authentication Endpoint

```php
#[Route('/api/login', name: 'api_login', methods: ['POST'])]
#[OA\Post(
    path: '/api/login',
    summary: 'User authentication',
    description: 'Authenticate user and receive JWT token',
    tags: ['Authentication']
)]
#[OA\RequestBody(
    required: true,
    content: new OA\JsonContent(
        required: ['email', 'password'],
        properties: [
            new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
            new OA\Property(property: 'password', type: 'string', format: 'password', example: 'SecurePass123!'),
        ]
    )
)]
#[OA\Response(
    response: 200,
    description: 'Login successful',
    content: new OA\JsonContent(
        properties: [
            new OA\Property(property: 'token', type: 'string', description: 'JWT access token'),
            new OA\Property(property: 'refreshToken', type: 'string', description: 'Refresh token'),
            new OA\Property(property: 'expiresIn', type: 'integer', description: 'Token expiration time in seconds'),
        ],
        example: [
            'token' => 'eyJ0eXAiOiJKV1QiLCJhbGc...',
            'refreshToken' => 'def50200...',
            'expiresIn' => 3600
        ]
    )
)]
#[OA\Response(
    response: 401,
    description: 'Invalid credentials',
    content: new OA\JsonContent(
        properties: [
            new OA\Property(property: 'error', type: 'string', example: 'Invalid credentials'),
        ]
    )
)]
public function login(Request $request): JsonResponse
{
    // Implementation
}
```

---

## 🎨 Reusable Schemas

Define common schemas once and reference them:

```php
#[OA\Schema(
    schema: 'Error',
    type: 'object',
    properties: [
        new OA\Property(property: 'type', type: 'string', example: 'https://api.example.com/problems/validation-error'),
        new OA\Property(property: 'title', type: 'string', example: 'Validation Failed'),
        new OA\Property(property: 'status', type: 'integer', example: 400),
        new OA\Property(property: 'detail', type: 'string', example: 'The request body contains invalid data'),
        new OA\Property(
            property: 'errors',
            type: 'object',
            nullable: true,
            additionalProperties: new OA\AdditionalProperties(type: 'array', items: new OA\Items(type: 'string'))
        ),
    ]
)]
class ErrorSchema {}
```

Then reference it:

```php
#[OA\Response(
    response: 400,
    description: 'Validation error',
    content: new OA\JsonContent(ref: '#/components/schemas/Error')
)]
```

---

## 📝 Documentation Checklist

For each endpoint, document:

- ✅ **Summary** - Brief one-line description
- ✅ **Description** - Detailed explanation
- ✅ **Tags** - Group related endpoints
- ✅ **Parameters** - Path/query/header parameters
- ✅ **Request Body** - For POST/PUT/PATCH
- ✅ **Responses** - All possible response codes (200, 201, 400, 401, 403, 404, 500)
- ✅ **Examples** - Real example requests/responses
- ✅ **Security** - Authentication requirements

---

## 🎯 Priority Documentation Order

### Phase 1: Critical Endpoints
1. **Authentication**
   - POST /api/login
   - POST /api/users/register
   - POST /api/logout

2. **Bookings**
   - POST /api/bookings/create
   - GET /api/bookings/{id}
   - GET /api/bookings/mine/as-athlete
   - POST /api/bookings/{id}/accept

3. **Payments**
   - POST /api/payments/intent
   - POST /api/payments/pay-remaining/{id}
   - GET /api/payments/status/{id}

### Phase 2: Important Endpoints
4. **Services**
   - GET /api/services
   - GET /api/services/{id}
   - POST /api/services/create

5. **Users**
   - GET /api/users/current
   - GET /api/users/{id}
   - PUT /api/users/update

### Phase 3: Additional Features
6. **Messages**
7. **Media/Deliverables**
8. **Reviews**
9. **Wallet**

---

## 🧪 Testing Documentation

### View in Browser
```bash
# Start server
symfony server:start

# Open browser
open http://localhost:8000/api/doc
```

### Export OpenAPI Spec
```bash
# Get JSON spec
curl http://localhost:8000/api/doc.json > openapi.json

# Import to Postman/Insomnia
```

### Generate Client SDK
```bash
# Using openapi-generator
openapi-generator-cli generate \
  -i http://localhost:8000/api/doc.json \
  -g typescript-axios \
  -o ./generated-client
```

---

## 💡 Best Practices

1. **Keep descriptions clear and concise**
2. **Provide realistic examples**
3. **Document all error cases**
4. **Use consistent naming conventions**
5. **Group related endpoints with tags**
6. **Version your API** (use /api/v1/, /api/v2/)
7. **Document breaking changes**
8. **Include authentication details**
9. **Update docs when code changes**
10. **Test examples are valid**

---

## 📚 Additional Resources

- [OpenAPI Specification](https://swagger.io/specification/)
- [NelmioApiDocBundle Docs](https://symfony.com/bundles/NelmioApiDocBundle/current/index.html)
- [Swagger Editor](https://editor.swagger.io/)
- [OpenAPI Generator](https://openapi-generator.tech/)

---

**End of API Documentation Guide**
