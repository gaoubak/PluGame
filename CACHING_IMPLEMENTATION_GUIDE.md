# Caching Implementation Guide

## 🚀 Redis Caching Configured

### Architecture

```
┌─────────────────┐
│   Controller    │
└────────┬────────┘
         │
    ┌────▼─────┐
    │ Service  │
    └────┬─────┘
         │
    ┌────▼──────────┐
    │ CacheService  │◄─── Redis
    └────┬──────────┘
         │
    ┌────▼────────┐
    │ Repository  │◄─── Database
    └─────────────┘
```

**Cache Strategy:**
- **Write-Through:** Update cache when data changes
- **Cache-Aside:** Load from DB if cache miss
- **Tag-Based Invalidation:** Clear related caches easily

---

## 📦 Cache Pools Configured

| Pool | TTL | Use Case | Tags |
|------|-----|----------|------|
| `cache.services` | 1 hour | Service listings | `services`, `service.{id}` |
| `cache.users` | 30 min | User profiles | `users`, `user.{id}` |
| `cache.availability` | 15 min | Creator availability | `availability`, `creator.{id}` |
| `cache.static` | 24 hours | Rarely changing data | `static`, custom |
| `cache.sessions` | Session | User sessions | - |

---

## 🔧 Setup Instructions

### Step 1: Add Redis to Docker

```yaml
# docker-compose.yml
services:
  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"
    volumes:
      - redis_data:/data
    command: redis-server --appendonly yes
    networks:
      - app-network

  php:
    # ... existing config
    depends_on:
      - redis
    environment:
      REDIS_URL: redis://redis:6379

volumes:
  redis_data:
```

### Step 2: Install Redis Extension (if not already)

```dockerfile
# Dockerfile - add this to PHP extensions
RUN docker-php-ext-install ... redis
```

Or install via PECL:
```dockerfile
RUN pecl install redis && docker-php-ext-enable redis
```

### Step 3: Update .env

```.env
# .env
REDIS_URL=redis://redis:6379

# For production with password:
# REDIS_URL=redis://password@redis:6379
```

### Step 4: Restart Docker

```bash
docker-compose down
docker-compose up -d --build
```

### Step 5: Verify Redis Connection

```bash
# Check if Redis is running
docker-compose exec redis redis-cli ping
# Expected output: PONG

# Check connection from PHP
docker-compose exec php php -r "echo (new Redis())->connect('redis', 6379) ? 'Connected' : 'Failed';"
```

---

## 💡 Usage Examples

### Example 1: Cache Service Listings

```php
// Before (No caching):
class ServiceController extends AbstractController
{
    public function list(): JsonResponse
    {
        $services = $this->serviceRepo->findBy(['isActive' => true]);
        return $this->json($services);
    }
}

// After (With caching):
class ServiceController extends AbstractController
{
    public function __construct(
        private readonly ServiceOfferingRepositoryCached $serviceRepo,
    ) {}

    public function list(): JsonResponse
    {
        // Cached for 1 hour, no DB query on cache hit
        $services = $this->serviceRepo->findAllActiveCached();
        return $this->json($services);
    }
}
```

### Example 2: Cache User Profile

```php
use App\Service\Cache\CacheService;

class UserController extends AbstractController
{
    public function __construct(
        private readonly CacheService $cache,
        private readonly UserRepository $userRepo,
    ) {}

    public function getProfile(string $userId): JsonResponse
    {
        $user = $this->cache->getUserProfile($userId, function () use ($userId) {
            return $this->userRepo->findByIdWithProfiles($userId);
        });

        return $this->json($user);
    }

    public function updateProfile(Request $request, string $userId): JsonResponse
    {
        $user = $this->userRepo->find($userId);
        // ... update user

        $this->em->flush();

        // Invalidate cache after update
        $this->cache->invalidateUser($userId);

        return $this->json(['success' => true]);
    }
}
```

### Example 3: Cache Creator Availability

```php
class AvailabilityController extends AbstractController
{
    public function getCreatorAvailability(string $creatorId): JsonResponse
    {
        $availability = $this->cache->getCreatorAvailability($creatorId, function () use ($creatorId) {
            return $this->slotRepo->createQueryBuilder('s')
                ->where('s.creator = :creatorId')
                ->andWhere('s.isBooked = false')
                ->andWhere('s.startTime > :now')
                ->setParameter('creatorId', $creatorId)
                ->setParameter('now', new \DateTimeImmutable())
                ->orderBy('s.startTime', 'ASC')
                ->getQuery()
                ->getResult();
        });

        return $this->json($availability);
    }

    public function bookSlot(string $slotId): JsonResponse
    {
        $slot = $this->slotRepo->find($slotId);
        $slot->setIsBooked(true);
        $this->em->flush();

        // Invalidate creator's availability cache
        $this->cache->invalidateAvailability($slot->getCreator()->getId());

        return $this->json(['success' => true]);
    }
}
```

### Example 4: Manual Cache Control

```php
use App\Service\Cache\CacheService;

// Get with custom TTL
$data = $this->cache->getStatic('custom.key', function (ItemInterface $item) {
    $item->expiresAfter(7200); // 2 hours
    return $this->expensiveOperation();
}, ['custom', 'tag']);

// Invalidate by custom tags
$this->cache->invalidateTags(['custom']);

// Clear all caches (emergency only!)
$this->cache->invalidateAll();
```

---

## 🎯 What to Cache

### ✅ Good Candidates (High Read, Low Write)
- Service listings
- User profiles (read-only data)
- Creator availability
- Popular search results
- Static configuration
- API rate limit counters

### ⚠️ Moderate Candidates (Moderate Read/Write)
- Booking lists (invalidate on status change)
- Message counts (invalidate on new message)
- Creator stats (invalidate on booking complete)

### ❌ Bad Candidates (High Write, Low Read)
- Payment transactions (always fetch fresh)
- Real-time message content
- Live booking status
- Stripe webhook events

---

## 🔄 Cache Invalidation Strategies

### Strategy 1: Event-Driven Invalidation

```php
// src/EventListener/CacheInvalidationListener.php
class CacheInvalidationListener
{
    public function __construct(
        private readonly CacheService $cache,
    ) {}

    public function onServiceUpdated(ServiceUpdatedEvent $event): void
    {
        $this->cache->invalidateService($event->getService()->getId());
        $this->cache->invalidateServices(); // Also clear list
    }

    public function onUserUpdated(UserUpdatedEvent $event): void
    {
        $this->cache->invalidateUser($event->getUser()->getId());
    }

    public function onBookingCreated(BookingCreatedEvent $event): void
    {
        $booking = $event->getBooking();

        // Invalidate creator's availability
        $this->cache->invalidateAvailability($booking->getCreator()->getId());

        // Invalidate booking lists for both parties
        $this->cache->invalidateUser($booking->getAthlete()->getId());
        $this->cache->invalidateUser($booking->getCreator()->getId());
    }
}
```

### Strategy 2: Time-Based Invalidation (Already Configured)

- Services: 1 hour TTL (updates are rare)
- Users: 30 min TTL (profiles change occasionally)
- Availability: 15 min TTL (bookings happen frequently)
- Static: 24 hours TTL (almost never changes)

### Strategy 3: Write-Through Cache

```php
// Update cache immediately on write
public function updateService(ServiceOffering $service): void
{
    $this->em->persist($service);
    $this->em->flush();

    // Write to cache immediately (skip next DB query)
    $this->cache->set("service.{$service->getId()}", $service, 3600);
}
```

---

## 📊 Cache Performance Monitoring

### View Cache Stats

```php
// src/Controller/Admin/CacheController.php
class CacheController extends AbstractController
{
    #[Route('/admin/cache/stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        $redis = new \Redis();
        $redis->connect('redis', 6379);

        return $this->json([
            'info' => $redis->info(),
            'dbsize' => $redis->dbSize(),
            'memory' => $redis->info('memory'),
        ]);
    }

    #[Route('/admin/cache/clear', methods: ['POST'])]
    public function clear(CacheService $cache): JsonResponse
    {
        $cache->invalidateAll();
        return $this->json(['message' => 'Cache cleared']);
    }
}
```

### Monitor Cache Hit Rate

```bash
# Connect to Redis CLI
docker-compose exec redis redis-cli

# Get cache statistics
redis-cli> INFO stats

# Monitor live commands
redis-cli> MONITOR

# View all keys
redis-cli> KEYS *

# View specific cache pool
redis-cli> KEYS *services*
```

---

## 🧪 Testing with Cache

### Disable Cache in Tests

```php
// tests/bootstrap.php
$_ENV['APP_ENV'] = 'test';

// Cache configuration automatically uses array adapter in test env
// No Redis needed for tests
```

### Test Cache Invalidation

```php
// tests/Service/Cache/CacheServiceTest.php
class CacheServiceTest extends KernelTestCase
{
    public function testServiceCacheInvalidation(): void
    {
        $cache = self::getContainer()->get(CacheService::class);

        // Cache something
        $service = new ServiceOffering();
        $cache->getService('test-id', fn() => $service);

        // Invalidate
        $cache->invalidateService('test-id');

        // Should fetch fresh data
        $callCount = 0;
        $cache->getService('test-id', function () use (&$callCount) {
            $callCount++;
            return new ServiceOffering();
        });

        $this->assertEquals(1, $callCount, 'Cache was not invalidated');
    }
}
```

---

## 🔒 Cache Security Considerations

### 1. Never Cache Sensitive Data

```php
// ❌ BAD: Caching password reset tokens
$token = $cache->get('reset.' . $email, fn() => $this->generateToken());

// ✅ GOOD: Always generate fresh tokens
$token = $this->generateToken();
```

### 2. Cache Key Isolation

```php
// ❌ BAD: Could cause key collisions
$cache->get("user.{$id}", ...);
$cache->get("service.{$id}", ...);

// ✅ GOOD: Namespace keys
$cache->get("user.profile.{$id}", ...);
$cache->get("service.details.{$id}", ...);
```

### 3. Validate Cached Data

```php
$user = $cache->getUserProfile($id, fn() => $this->userRepo->find($id));

// Validate user still exists and is active
if (!$user || !$user->isActive()) {
    $cache->invalidateUser($id);
    throw new NotFoundHttpException();
}
```

---

## 🎯 Expected Performance Impact

### Before Caching
- Service list: 50ms (DB query)
- User profile: 80ms (DB query with joins)
- Creator availability: 120ms (DB query with filtering)
- **Total for 3 endpoints:** 250ms

### After Caching (Cache Hit)
- Service list: 2ms (Redis)
- User profile: 2ms (Redis)
- Creator availability: 2ms (Redis)
- **Total for 3 endpoints:** 6ms

**Improvement:** **42x faster** (250ms → 6ms)

### Cache Hit Rate Goals
- Services: 95%+ (rarely change)
- Users: 85%+ (change occasionally)
- Availability: 70%+ (change frequently)
- **Overall:** 85%+ hit rate

### Database Load Reduction
- Read queries: **85% reduction**
- CPU usage: **60% reduction**
- Can handle **10x more traffic** with same hardware

---

## 🚀 Deployment Checklist

- [ ] Redis container running in Docker
- [ ] `REDIS_URL` configured in .env
- [ ] PHP Redis extension installed
- [ ] Cache configuration deployed
- [ ] CacheService autowired in services
- [ ] Critical endpoints using cache
- [ ] Cache invalidation implemented
- [ ] Monitoring enabled
- [ ] Test cache hit rate
- [ ] Document cache strategy for team

---

**End of Caching Implementation Guide**
