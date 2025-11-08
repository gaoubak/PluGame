# Symfony Voter Implementation Guide

## ✅ Voters Created

All authorization voters have been implemented to fix IDOR (Insecure Direct Object Reference) vulnerabilities:

1. **BookingVoter** - Controls access to bookings
2. **MessageVoter** - Controls access to messages
3. **ServiceOfferingVoter** - Controls access to services
4. **PaymentVoter** - Controls access to payment data
5. **UserVoter** - Controls access to user profiles

---

## 🔒 How to Use Voters in Controllers

### Method 1: Using `denyAccessUnlessGranted()`

```php
class BookingController extends AbstractController
{
    #[Route('/{id}', name: 'booking_get', methods: ['GET'])]
    public function getOne(Booking $booking): Response
    {
        // This will throw AccessDeniedException if user doesn't have permission
        $this->denyAccessUnlessGranted(BookingVoter::VIEW, $booking);

        $data = $this->serializer->normalize($booking, null, ['groups' => ['booking:read']]);
        return $this->createApiResponse($data, Response::HTTP_OK);
    }

    #[Route('/{id}/accept', name: 'booking_accept', methods: ['POST'])]
    public function accept(Booking $booking): Response
    {
        // Only creator can accept
        $this->denyAccessUnlessGranted(BookingVoter::ACCEPT, $booking);

        $booking->setStatus(Booking::STATUS_ACCEPTED);
        $this->em->flush();

        return $this->createApiResponse(['status' => $booking->getStatus()]);
    }
}
```

### Method 2: Using Authorization Checker (for conditional logic)

```php
use Symfony\Bundle\SecurityBundle\Security;

class BookingController extends AbstractController
{
    public function __construct(
        private readonly Security $security,
    ) {}

    #[Route('/{id}', methods: ['GET'])]
    public function getOne(Booking $booking): Response
    {
        if (!$this->security->isGranted(BookingVoter::VIEW, $booking)) {
            return $this->createApiResponse(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        // ... rest of logic
    }
}
```

---

## 📋 Implementation Checklist

### Update BookingController

```php
// BEFORE (VULNERABLE):
#[Route('/{id}', name: 'booking_get', methods: ['GET'])]
public function getOne(Booking $booking): Response
{
    // ❌ No authorization check - anyone can view any booking
    $data = $this->serializer->normalize($booking, null, ['groups' => ['booking:read']]);
    return $this->createApiResponse($data);
}

// AFTER (SECURE):
#[Route('/{id}', name: 'booking_get', methods: ['GET'])]
public function getOne(Booking $booking): Response
{
    // ✅ Authorization check added
    $this->denyAccessUnlessGranted(BookingVoter::VIEW, $booking);

    $data = $this->serializer->normalize($booking, null, ['groups' => ['booking:read']]);
    return $this->createApiResponse($data);
}
```

### Update MessageController

```php
// Add to MessageController.php:

#[Route('/conversation/{id}', name: 'message_by_conversation', methods: ['GET'])]
public function byConversation(Conversation $conversation, #[CurrentUser] User $user): JsonResponse
{
    // Instead of manual checks:
    // if (!$this->isUserInConversation($user, $conversation)) { ... }

    // Use voter:
    $this->denyAccessUnlessGranted(ConversationVoter::VIEW, $conversation);

    $messages = $this->messageRepository->findBy(['conversation' => $conversation]);
    // ... rest
}
```

### Update ServiceOfferingController

```php
#[Route('/update/{id}', name: 'service_update', methods: ['PUT','PATCH'])]
public function update(Request $request, ServiceOffering $service): Response
{
    // Instead of:
    // if ($service->getCreator()->getId() !== $currentUser->getId()) { ... }

    // Use voter:
    $this->denyAccessUnlessGranted(ServiceOfferingVoter::EDIT, $service);

    // ... update logic
}
```

### Update PaymentController

```php
#[Route('/status/{id}', name: 'payment_status', methods: ['GET'])]
public function getStatus(string $id, #[CurrentUser] User $user): JsonResponse
{
    $booking = $this->bookingRepository->find($id);

    if (!$booking) {
        return $this->createApiResponse(['error' => 'Booking not found'], Response::HTTP_NOT_FOUND);
    }

    // Instead of manual checks:
    // if ($booking->getCreator()->getId() !== $user->getId() && ...)

    // Use voter:
    $this->denyAccessUnlessGranted(BookingVoter::VIEW, $booking);

    return $this->createApiResponse([/* payment status */]);
}
```

### Update UserController

```php
#[Route('/{id}', name: 'get_user', methods: ['GET'])]
public function getUserById(User $user): JsonResponse
{
    // Public profile check
    $this->denyAccessUnlessGranted(UserVoter::VIEW, $user);

    $data = $this->serializer->normalize($user, null, [
        'groups' => ['user:read']
    ]);

    return $this->createApiResponse($data);
}

#[Route('/update', name: 'update_current_user', methods: ['PUT', 'PATCH'])]
public function updateCurrentUser(Request $request, #[CurrentUser] User $user): JsonResponse
{
    // Ensure user can only update their own profile
    $this->denyAccessUnlessGranted(UserVoter::EDIT, $user);

    // ... update logic
}
```

---

## 🎯 Priority Implementation Order

### Phase 1: Critical Endpoints (Fix Immediately)

1. **BookingController**:
   - ✅ `getOne()` - Add `BookingVoter::VIEW`
   - ✅ `accept()` - Add `BookingVoter::ACCEPT`
   - ✅ `decline()` - Add `BookingVoter::DECLINE`
   - ✅ `cancel()` - Add `BookingVoter::CANCEL`
   - ✅ `complete()` - Add `BookingVoter::COMPLETE`
   - ✅ `delete()` - Add `BookingVoter::DELETE`

2. **PaymentController**:
   - ✅ `getStatus()` - Add `BookingVoter::VIEW`
   - ✅ `createPaymentIntent()` - Add authorization
   - ✅ `payRemaining()` - Add `BookingVoter::VIEW`

3. **MessageController**:
   - ✅ `byConversation()` - Add `ConversationVoter::VIEW` (need to create)
   - ✅ `getOne()` - Add `MessageVoter::VIEW`
   - ✅ `send()` - Add `MessageVoter::CREATE`
   - ✅ `delete()` - Add `MessageVoter::DELETE`

### Phase 2: Important Endpoints

4. **ServiceOfferingController**:
   - ✅ `update()` - Add `ServiceOfferingVoter::EDIT`
   - ✅ `delete()` - Add `ServiceOfferingVoter::DELETE`
   - ✅ `create()` - Add `ServiceOfferingVoter::CREATE`

5. **UserController**:
   - ✅ `updateCurrentUser()` - Add `UserVoter::EDIT`
   - ✅ `deleteUser()` - Add `UserVoter::DELETE`

### Phase 3: Additional Voters Needed

Create these additional voters:

```php
// src/Security/Voter/ConversationVoter.php
class ConversationVoter extends Voter {
    const VIEW = 'CONVERSATION_VIEW';
    const CREATE = 'CONVERSATION_CREATE';
    // ...
}

// src/Security/Voter/DeliverableVoter.php
class DeliverableVoter extends Voter {
    const VIEW = 'DELIVERABLE_VIEW';
    const DOWNLOAD = 'DELIVERABLE_DOWNLOAD';
    const UPLOAD = 'DELIVERABLE_UPLOAD';
    // ...
}

// src/Security/Voter/ReviewVoter.php
class ReviewVoter extends Voter {
    const CREATE = 'REVIEW_CREATE';
    const EDIT = 'REVIEW_EDIT';
    const DELETE = 'REVIEW_DELETE';
    // ...
}
```

---

## 🧪 Testing Voters

### Unit Test Example

```php
// tests/Security/Voter/BookingVoterTest.php

namespace App\Tests\Security\Voter;

use App\Entity\Booking;
use App\Entity\User;
use App\Security\Voter\BookingVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class BookingVoterTest extends TestCase
{
    private BookingVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new BookingVoter();
    }

    public function testAthleteCanViewOwnBooking(): void
    {
        $athlete = new User();
        $athlete->setEmail('athlete@test.com');

        $creator = new User();
        $creator->setEmail('creator@test.com');

        $booking = new Booking();
        $booking->setAthlete($athlete);
        $booking->setCreator($creator);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($athlete);

        $result = $this->voter->vote($token, $booking, [BookingVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testStrangerCannotViewBooking(): void
    {
        $athlete = new User();
        $stranger = new User();

        $booking = new Booking();
        $booking->setAthlete($athlete);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($stranger);

        $result = $this->voter->vote($token, $booking, [BookingVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testOnlyCreatorCanAcceptBooking(): void
    {
        $athlete = new User();
        $creator = new User();

        $booking = new Booking();
        $booking->setAthlete($athlete);
        $booking->setCreator($creator);
        $booking->setStatus(Booking::STATUS_PENDING);

        // Athlete cannot accept
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($athlete);
        $result = $this->voter->vote($token, $booking, [BookingVoter::ACCEPT]);
        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);

        // Creator can accept
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($creator);
        $result = $this->voter->vote($token, $booking, [BookingVoter::ACCEPT]);
        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);
    }
}
```

### Integration Test Example

```php
// tests/Controller/BookingControllerTest.php

public function testCannotViewOtherUsersBooking(): void
{
    $client = static::createClient();

    // Create two users
    $athlete = $this->createUser('athlete@test.com');
    $stranger = $this->createUser('stranger@test.com');

    // Create booking for athlete
    $booking = $this->createBooking($athlete);

    // Try to access as stranger (should be denied)
    $client->loginUser($stranger);
    $client->request('GET', "/api/bookings/{$booking->getId()}");

    $this->assertResponseStatusCodeSame(403);
    $this->assertJsonContains(['error' => 'Access Denied']);
}
```

---

## 🚨 Common Mistakes to Avoid

### ❌ Don't Do This:
```php
// Manual authorization check in controller
if ($booking->getAthlete()->getId() !== $user->getId()) {
    throw new AccessDeniedException();
}
```

### ✅ Do This Instead:
```php
// Use voter
$this->denyAccessUnlessGranted(BookingVoter::VIEW, $booking);
```

### ❌ Don't Do This:
```php
// Checking in services
if ($this->security->getUser()->getId() !== $booking->getCreator()->getId()) {
    throw new Exception('Unauthorized');
}
```

### ✅ Do This Instead:
```php
// Pass object to voter
$this->security->denyAccessUnlessGranted(BookingVoter::EDIT, $booking);
```

---

## 📊 Security Improvements

### Before (Vulnerable):
- ❌ Anyone can view any booking
- ❌ Anyone can read any message
- ❌ Anyone can see payment details
- ❌ Users can modify others' services
- ❌ No centralized authorization logic

### After (Secure):
- ✅ Users can only view their own bookings
- ✅ Messages restricted to conversation participants
- ✅ Payment data protected
- ✅ Service modifications restricted to owners
- ✅ Centralized, testable authorization
- ✅ Admin override capability
- ✅ Easy to audit and extend

---

## 🔄 Next Steps

1. **Implement in controllers** (use this guide)
2. **Write tests** (examples provided above)
3. **Create additional voters** (Conversation, Deliverable, Review)
4. **Remove manual checks** (grep for `->getId() === $user->getId()`)
5. **Add logging** (track authorization failures)
6. **Document permissions** (update API docs)

---

**End of Voter Implementation Guide**
