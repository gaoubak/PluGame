# DTO Implementation Guide

## 🎯 Overview

**DTOs (Data Transfer Objects)** validate and structure request/response data. This project uses a standardized DTO pattern to reduce boilerplate and ensure consistency.

### Benefits

✅ **Type Safety** - Strong typing with PHP 8.2+ features
✅ **Automatic Validation** - Built-in Symfony validator integration
✅ **Less Boilerplate** - Factory methods reduce repetitive code
✅ **RFC 7807 Integration** - Validation errors automatically formatted
✅ **Reusable Patterns** - Base classes for pagination, date ranges, etc.
✅ **Clear Contracts** - API inputs/outputs explicitly defined

---

## 📦 Core Components

### 1. AbstractRequestDTO

**Base class for all request DTOs**

Location: `src/DTO/AbstractRequestDTO.php`

**Features:**
- Automatic JSON deserialization from Request
- Type conversion (int, float, bool, array)
- Validation with groups support
- Factory methods: `fromRequest()`, `fromArray()`

**Example:**
```php
// Create DTO from HTTP request
$dto = CreateBookingDTO::fromRequest($request, $validator);

// DTO is automatically validated and populated
echo $dto->serviceId; // Type-safe access
```

---

### 2. PaginatedRequestDTO

**Standardized pagination for list endpoints**

Location: `src/DTO/PaginatedRequestDTO.php`

**Fields:**
- `page` - Page number (default: 1)
- `limit` - Items per page (default: 20, max: 100)
- `sortBy` - Field to sort by
- `sortOrder` - 'asc' or 'desc' (default: 'desc')
- `search` - Search query string

**Methods:**
- `getOffset()` - Calculate database offset
- `fromQueryParams()` - Create from URL query params

---

### 3. PaginatedResponseDTO

**Standardized pagination wrapper for responses**

Location: `src/DTO/PaginatedResponseDTO.php`

**Output format:**
```json
{
  "data": [...],
  "pagination": {
    "page": 2,
    "limit": 20,
    "total": 157,
    "totalPages": 8,
    "hasNext": true,
    "hasPrev": true,
    "nextPage": 3,
    "prevPage": 1
  }
}
```

---

### 4. DateRangeRequestDTO

**Date range filtering**

Location: `src/DTO/DateRangeRequestDTO.php`

**Features:**
- Start/end date validation
- Ensures end date is after start date
- Maximum range validation (365 days)
- Timezone support

---

## 💻 Usage Examples

### Example 1: Simple Request DTO

```php
// src/DTO/User/CreateUserDTO.php

namespace App\DTO\User;

use App\DTO\AbstractRequestDTO;
use Symfony\Component\Validator\Constraints as Assert;

class CreateUserDTO extends AbstractRequestDTO
{
    #[Assert\NotBlank(message: 'Username is required')]
    #[Assert\Length(min: 3, max: 50)]
    public string $username;

    #[Assert\NotBlank(message: 'Email is required')]
    #[Assert\Email(message: 'Invalid email format')]
    public string $email;

    #[Assert\NotBlank(message: 'Password is required')]
    #[Assert\Length(min: 8, message: 'Password must be at least 8 characters')]
    public string $password;

    #[Assert\Choice(choices: ['athlete', 'creator'], message: 'Invalid user type')]
    public string $userType = 'athlete';
}
```

**Controller usage:**
```php
use App\DTO\User\CreateUserDTO;
use App\Exception\ApiProblemException;

#[Route('/api/users', methods: ['POST'])]
public function create(
    Request $request,
    ValidatorInterface $validator
): Response {
    // ✨ Automatic validation - throws ValidationFailedException on error
    $dto = CreateUserDTO::fromRequest($request, $validator);

    // Create user with validated data
    $user = new User();
    $user->setUsername($dto->username);
    $user->setEmail($dto->email);
    // ... etc

    $this->em->persist($user);
    $this->em->flush();

    return $this->json($user, 201);
}
```

**Request:**
```bash
curl -X POST http://localhost/api/users \
  -H "Content-Type: application/json" \
  -d '{
    "username": "johndoe",
    "email": "john@example.com",
    "password": "securepass123",
    "userType": "athlete"
  }'
```

**Validation Error (RFC 7807):**
```json
{
  "type": "validation-error",
  "title": "Validation Failed",
  "status": 422,
  "detail": "The request contains invalid data",
  "errors": {
    "email": ["Invalid email format"],
    "password": ["Password must be at least 8 characters"]
  }
}
```

---

### Example 2: Paginated List Endpoint

```php
use App\DTO\PaginatedRequestDTO;
use App\DTO\PaginatedResponseDTO;

#[Route('/api/bookings', methods: ['GET'])]
public function list(
    Request $request,
    ValidatorInterface $validator,
    BookingRepository $repo
): Response {
    // Get pagination params from query string
    $pagination = PaginatedRequestDTO::fromQueryParams($request, $validator);

    // Query database with pagination
    $bookings = $repo->createQueryBuilder('b')
        ->orderBy("b.{$pagination->sortBy}", $pagination->sortOrder)
        ->setFirstResult($pagination->getOffset())
        ->setMaxResults($pagination->limit)
        ->getQuery()
        ->getResult();

    // Get total count
    $total = $repo->count([]);

    // Wrap in paginated response
    $response = new PaginatedResponseDTO(
        data: $bookings,
        page: $pagination->page,
        limit: $pagination->limit,
        total: $total
    );

    return $this->json($response->toArray());
}
```

**Request:**
```bash
curl "http://localhost/api/bookings?page=2&limit=10&sortBy=createdAt&sortOrder=desc"
```

**Response:**
```json
{
  "data": [
    {"id": "abc123", "status": "pending", ...},
    {"id": "def456", "status": "confirmed", ...}
  ],
  "pagination": {
    "page": 2,
    "limit": 10,
    "total": 157,
    "totalPages": 16,
    "hasNext": true,
    "hasPrev": true,
    "nextPage": 3,
    "prevPage": 1
  }
}
```

---

### Example 3: Date Range Filtering

```php
use App\DTO\DateRangeRequestDTO;

#[Route('/api/bookings/filter', methods: ['GET'])]
public function filterByDate(
    Request $request,
    ValidatorInterface $validator,
    BookingRepository $repo
): Response {
    // Get and validate date range
    $dateFilter = DateRangeRequestDTO::fromQueryParams($request, $validator);

    // Query bookings in date range
    $bookings = $repo->createQueryBuilder('b')
        ->where('b.startTime >= :start')
        ->andWhere('b.startTime <= :end')
        ->setParameter('start', $dateFilter->startDate)
        ->setParameter('end', $dateFilter->endDate)
        ->orderBy('b.startTime', 'ASC')
        ->getQuery()
        ->getResult();

    return $this->json([
        'bookings' => $bookings,
        'dateRange' => [
            'start' => $dateFilter->startDate->format('Y-m-d'),
            'end' => $dateFilter->endDate->format('Y-m-d'),
            'days' => $dateFilter->getDayCount()
        ]
    ]);
}
```

**Request:**
```bash
curl "http://localhost/api/bookings/filter?startDate=2025-11-01T00:00:00Z&endDate=2025-11-30T23:59:59Z"
```

---

### Example 4: Complex DTO with Business Logic

```php
// src/DTO/Booking/CreateBookingDTO.php (already created)

#[Route('/api/bookings', methods: ['POST'])]
public function create(
    Request $request,
    ValidatorInterface $validator,
    BookingRepository $repo
): Response {
    // Validate and populate DTO
    $dto = CreateBookingDTO::fromRequest($request, $validator, ['create']);

    // Use DTO methods to determine booking mode
    if ($dto->isSlotMode()) {
        return $this->createFromSlots($dto);
    } elseif ($dto->isAdHocMode()) {
        return $this->createAdHoc($dto);
    }

    throw ApiProblemException::badRequest('Invalid booking mode');
}

private function createFromSlots(CreateBookingDTO $dto): Response
{
    // Use validated slot IDs
    foreach ($dto->slotIds as $slotId) {
        $slot = $this->slotRepo->find($slotId);
        if (!$slot || $slot->isBooked()) {
            throw ApiProblemException::conflict("Slot {$slotId} not available");
        }
    }

    // ... create booking
}
```

---

### Example 5: Partial Updates (PATCH)

```php
use App\DTO\Booking\UpdateBookingDTO;

#[Route('/api/bookings/{id}', methods: ['PATCH'])]
public function update(
    Booking $booking,
    Request $request,
    ValidatorInterface $validator
): Response {
    // Authorization check
    $this->denyAccessUnlessGranted(BookingVoter::EDIT, $booking);

    // Validate partial update
    $dto = UpdateBookingDTO::fromRequest($request, $validator, ['update']);

    // Check if there are any updates
    if (!$dto->hasUpdates()) {
        throw ApiProblemException::badRequest('No fields to update');
    }

    // Apply updates
    $dto->applyTo($booking);

    $this->em->flush();

    return $this->json($booking);
}
```

**Request:**
```bash
curl -X PATCH http://localhost/api/bookings/abc123 \
  -H "Content-Type: application/json" \
  -d '{
    "location": "Zoom",
    "notes": "Updated notes"
  }'
```

---

### Example 6: Validation Groups (Create vs Update)

```php
class ServiceOfferingDTO extends AbstractRequestDTO
{
    #[Assert\NotBlank(groups: ['create'])]
    #[Assert\Length(min: 3, max: 100)]
    public string $title;

    #[Assert\NotBlank(groups: ['create'])]
    #[Assert\Positive]
    public int $pricePerHour;

    #[Assert\Length(max: 1000)]
    public ?string $description = null;
}

// In controller:

// Create - requires title and pricePerHour
$dto = ServiceOfferingDTO::fromRequest($request, $validator, ['create']);

// Update - title and pricePerHour optional
$dto = ServiceOfferingDTO::fromRequest($request, $validator, ['update']);
```

---

## 🔧 Advanced Patterns

### Custom Validation Methods

```php
class TransferFundsDTO extends AbstractRequestDTO
{
    #[Assert\Positive]
    public int $amountCents;

    #[Assert\Uuid]
    public string $fromUserId;

    #[Assert\Uuid]
    public string $toUserId;

    /**
     * Custom validation: can't transfer to self
     */
    #[Assert\IsTrue(message: 'Cannot transfer funds to yourself')]
    public function isNotSelfTransfer(): bool
    {
        return $this->fromUserId !== $this->toUserId;
    }

    /**
     * Custom validation: amount must be divisible by 100 (whole dollars only)
     */
    #[Assert\IsTrue(message: 'Amount must be in whole dollars')]
    public function isWholeDollarAmount(): bool
    {
        return $this->amountCents % 100 === 0;
    }
}
```

---

### Custom Type Conversion

```php
class EventDTO extends AbstractRequestDTO
{
    public array $tags = [];
    public ?\DateTimeImmutable $eventDate = null;

    protected function populate(array $data): void
    {
        // Convert comma-separated string to array
        if (isset($data['tags'])) {
            $this->tags = is_array($data['tags'])
                ? $data['tags']
                : explode(',', $data['tags']);
        }

        // Convert date string to DateTimeImmutable
        if (isset($data['eventDate']) && is_string($data['eventDate'])) {
            try {
                $this->eventDate = new \DateTimeImmutable($data['eventDate']);
            } catch (\Exception $e) {
                $this->eventDate = null; // Let validator handle error
            }
        }
    }
}
```

---

### Nested DTOs

```php
class AddressDTO extends AbstractRequestDTO
{
    #[Assert\NotBlank]
    public string $street;

    #[Assert\NotBlank]
    public string $city;

    #[Assert\NotBlank]
    #[Assert\Length(exactly: 2)]
    public string $state;

    #[Assert\NotBlank]
    #[Assert\Regex('/^\d{5}$/')]
    public string $zipCode;
}

class CreateUserDTO extends AbstractRequestDTO
{
    #[Assert\NotBlank]
    public string $username;

    #[Assert\Email]
    public string $email;

    // Nested DTO
    #[Assert\Valid]
    public ?AddressDTO $address = null;

    protected function populate(array $data): void
    {
        parent::populate($data);

        // Handle nested DTO
        if (isset($data['address']) && is_array($data['address'])) {
            $this->address = new AddressDTO();
            $this->address->populate($data['address']);
        }
    }
}
```

**Request:**
```json
{
  "username": "johndoe",
  "email": "john@example.com",
  "address": {
    "street": "123 Main St",
    "city": "New York",
    "state": "NY",
    "zipCode": "10001"
  }
}
```

---

## 📝 Best Practices

### ✅ DO

1. **Always extend AbstractRequestDTO** for request DTOs
2. **Use validation groups** for create vs update operations
3. **Add custom validation methods** for business logic
4. **Use meaningful validation messages** for better error responses
5. **Keep DTOs immutable** after validation (use readonly properties when possible)
6. **Document DTO usage** with PHPDoc comments

### ❌ DON'T

1. **Don't put business logic** in DTOs (use services)
2. **Don't access database** in DTOs (validation only)
3. **Don't reuse DTOs** across different operations (create separate DTOs)
4. **Don't skip validation** (always call `fromRequest()` or `fromArray()`)
5. **Don't expose DTOs** in API responses (use entities or response DTOs)

---

## 🧪 Testing DTOs

### Unit Test Example

```php
// tests/DTO/CreateBookingDTOTest.php

use App\DTO\Booking\CreateBookingDTO;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CreateBookingDTOTest extends KernelTestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->validator = self::getContainer()->get(ValidatorInterface::class);
    }

    public function testValidSlotMode(): void
    {
        $data = [
            'serviceId' => '123e4567-e89b-12d3-a456-426614174000',
            'slotIds' => [
                '123e4567-e89b-12d3-a456-426614174001',
                '123e4567-e89b-12d3-a456-426614174002',
            ],
        ];

        $dto = CreateBookingDTO::fromArray($data, $this->validator, ['create']);

        $this->assertTrue($dto->isSlotMode());
        $this->assertFalse($dto->isAdHocMode());
        $this->assertCount(2, $dto->slotIds);
    }

    public function testValidationFailsWhenServiceIdMissing(): void
    {
        $this->expectException(ValidationFailedException::class);

        CreateBookingDTO::fromArray([], $this->validator, ['create']);
    }

    public function testValidationFailsWhenNoModeProvided(): void
    {
        $this->expectException(ValidationFailedException::class);

        $data = [
            'serviceId' => '123e4567-e89b-12d3-a456-426614174000',
            // Missing both slotIds and (creatorId + startTime + durationMin)
        ];

        CreateBookingDTO::fromArray($data, $this->validator, ['create']);
    }
}
```

---

## 🔄 Migration Guide

### Before (No DTOs)

```php
#[Route('/api/bookings', methods: ['POST'])]
public function create(Request $request): Response
{
    $data = json_decode($request->getContent(), true);

    // Manual validation
    if (empty($data['serviceId'])) {
        return $this->json(['error' => 'Service ID required'], 400);
    }

    if (!isset($data['durationMin']) || $data['durationMin'] <= 0) {
        return $this->json(['error' => 'Invalid duration'], 400);
    }

    // Manual type conversion
    $durationMin = (int) $data['durationMin'];
    $serviceId = $data['serviceId'];

    // ... create booking
}
```

### After (With DTOs)

```php
#[Route('/api/bookings', methods: ['POST'])]
public function create(
    Request $request,
    ValidatorInterface $validator
): Response {
    // ✨ Automatic validation, type conversion, and RFC 7807 error format
    $dto = CreateBookingDTO::fromRequest($request, $validator, ['create']);

    // Type-safe access
    $duration = $dto->durationMin; // guaranteed to be positive int
    $serviceId = $dto->serviceId;  // guaranteed to be valid UUID

    // ... create booking
}
```

**Benefits:**
- 10 lines → 3 lines
- Type-safe property access
- Consistent RFC 7807 error responses
- Reusable validation rules
- Self-documenting API contracts

---

## 📊 DTO Catalog

### Existing DTOs

| DTO | Purpose | Location |
|-----|---------|----------|
| `AbstractRequestDTO` | Base class | `src/DTO/AbstractRequestDTO.php` |
| `PaginatedRequestDTO` | Pagination params | `src/DTO/PaginatedRequestDTO.php` |
| `PaginatedResponseDTO` | Pagination wrapper | `src/DTO/PaginatedResponseDTO.php` |
| `DateRangeRequestDTO` | Date filtering | `src/DTO/DateRangeRequestDTO.php` |
| `CreatePaymentIntentDTO` | Payment creation | `src/DTO/Payment/CreatePaymentIntentDTO.php` |
| `PayRemainingDTO` | Pay remaining balance | `src/DTO/Payment/PayRemainingDTO.php` |
| `CreateBookingDTO` | Create booking | `src/DTO/Booking/CreateBookingDTO.php` |
| `UpdateBookingDTO` | Update booking | `src/DTO/Booking/UpdateBookingDTO.php` |

---

## 🎯 Next Steps

- [ ] Create DTOs for remaining controllers (User, ServiceOffering, Message, etc.)
- [ ] Add DTO validation to all POST/PUT/PATCH endpoints
- [ ] Create response DTOs for consistent output formats
- [ ] Add integration tests for DTO validation
- [ ] Document all DTOs in API documentation (OpenAPI)

---

**End of DTO Implementation Guide**

Your API now has type-safe, validated, standardized request handling! 🎉
