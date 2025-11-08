# Critical Security Fixes Implementation

**Priority:** HIGH
**Status:** Implementation Ready
**Estimated Time:** 4-6 hours total

---

## 🚨 Issue 1: JWT TTL Too Long (CRITICAL)

### Current Problem
- JWT tokens valid for 1 hour
- No refresh token mechanism
- Long-lived tokens increase attack surface

### Solution: Reduce TTL + Add Refresh Tokens

**Step 1: Update JWT Configuration**

Edit `config/packages/lexik_jwt_authentication.yaml`:

```yaml
lexik_jwt_authentication:
    secret_key: '%env(resolve:JWT_SECRET_KEY)%'
    public_key: '%env(resolve:JWT_PUBLIC_KEY)%'
    pass_phrase: '%env(JWT_PASSPHRASE)%'
    token_ttl: 900  # 15 minutes (changed from 3600)
```

**Step 2: Create Refresh Token Entity**

```php
// src/Entity/RefreshToken.php
<?php

namespace App\Entity;

use App\Entity\Traits\UuidId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'refresh_token')]
#[ORM\Index(columns: ['token'], name: 'idx_refresh_token')]
#[ORM\Index(columns: ['expires_at'], name: 'idx_refresh_expires')]
class RefreshToken
{
    use UuidId;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'string', length: 128, unique: true)]
    private string $token;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'boolean')]
    private bool $revoked = false;

    public function __construct()
    {
        $this->token = bin2hex(random_bytes(64));
        $this->createdAt = new \DateTimeImmutable();
        // Refresh tokens valid for 7 days
        $this->expiresAt = new \DateTimeImmutable('+7 days');
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }

    public function isValid(): bool
    {
        return !$this->revoked && !$this->isExpired();
    }

    // Getters and setters...
}
```

**Step 3: Create Refresh Token Service**

```php
// src/Service/RefreshTokenService.php
<?php

namespace App\Service;

use App\Entity\RefreshToken;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class RefreshTokenService
{
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {}

    public function createRefreshToken(User $user): RefreshToken
    {
        $refreshToken = new RefreshToken();
        $refreshToken->setUser($user);

        $this->em->persist($refreshToken);
        $this->em->flush();

        return $refreshToken;
    }

    public function findByToken(string $token): ?RefreshToken
    {
        return $this->em->getRepository(RefreshToken::class)
            ->findOneBy(['token' => $token]);
    }

    public function revokeToken(RefreshToken $token): void
    {
        $token->setRevoked(true);
        $this->em->flush();
    }

    public function revokeAllUserTokens(User $user): void
    {
        $this->em->createQueryBuilder()
            ->update(RefreshToken::class, 'rt')
            ->set('rt.revoked', ':revoked')
            ->where('rt.user = :user')
            ->setParameter('revoked', true)
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    public function cleanupExpiredTokens(): int
    {
        return $this->em->createQueryBuilder()
            ->delete(RefreshToken::class, 'rt')
            ->where('rt.expiresAt < :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }
}
```

**Step 4: Update Login Controller**

```php
// src/Controller/SecurityController.php - update login method

#[Route('/api/login', name: 'api_login', methods: ['POST'])]
public function login(
    Request $request,
    JWTTokenManagerInterface $jwtManager,
    RefreshTokenService $refreshTokenService
): JsonResponse {
    // ... existing authentication logic ...

    // Generate access token (15 minutes)
    $accessToken = $jwtManager->create($user);

    // Generate refresh token (7 days)
    $refreshToken = $refreshTokenService->createRefreshToken($user);

    return $this->json([
        'token' => $accessToken,
        'refresh_token' => $refreshToken->getToken(),
        'expires_in' => 900, // 15 minutes in seconds
    ]);
}
```

**Step 5: Create Refresh Token Endpoint**

```php
// src/Controller/SecurityController.php

#[Route('/api/token/refresh', name: 'api_token_refresh', methods: ['POST'])]
public function refreshToken(
    Request $request,
    RefreshTokenService $refreshTokenService,
    JWTTokenManagerInterface $jwtManager
): JsonResponse {
    $data = json_decode($request->getContent(), true);
    $refreshTokenString = $data['refresh_token'] ?? null;

    if (!$refreshTokenString) {
        throw ApiProblemException::badRequest('Refresh token is required');
    }

    $refreshToken = $refreshTokenService->findByToken($refreshTokenString);

    if (!$refreshToken || !$refreshToken->isValid()) {
        throw ApiProblemException::unauthorized('Invalid or expired refresh token');
    }

    $user = $refreshToken->getUser();

    // Generate new access token
    $accessToken = $jwtManager->create($user);

    // Optionally: Rotate refresh token for added security
    $refreshTokenService->revokeToken($refreshToken);
    $newRefreshToken = $refreshTokenService->createRefreshToken($user);

    return $this->json([
        'token' => $accessToken,
        'refresh_token' => $newRefreshToken->getToken(),
        'expires_in' => 900,
    ]);
}
```

**Step 6: Create Migration**

```bash
docker compose exec alpine bin/console make:migration
docker compose exec alpine bin/console doctrine:migrations:migrate
```

**Testing:**
```bash
# 1. Login
curl -X POST http://localhost/api/login \
  -d '{"username":"test@example.com","password":"password"}' \
  -H "Content-Type: application/json"

# Response:
# {
#   "token": "eyJ0eXAiOiJKV1...",
#   "refresh_token": "a1b2c3d4...",
#   "expires_in": 900
# }

# 2. After 15 minutes, refresh token
curl -X POST http://localhost/api/token/refresh \
  -d '{"refresh_token":"a1b2c3d4..."}' \
  -H "Content-Type: application/json"
```

---

## 🚨 Issue 2: No Rate Limiting (HIGH)

### Current Problem
- API vulnerable to brute force attacks
- No protection against abuse
- Can be DDoS'd easily

### Solution: Implement Symfony Rate Limiter

**Step 1: Install Rate Limiter Bundle**

```bash
composer require symfony/rate-limiter
```

**Step 2: Configure Rate Limiter**

Create `config/packages/rate_limiter.yaml`:

```yaml
framework:
    rate_limiter:
        # Login rate limiting (5 attempts per 15 minutes)
        login:
            policy: 'sliding_window'
            limit: 5
            interval: '15 minutes'

        # API rate limiting (100 requests per minute per IP)
        api:
            policy: 'token_bucket'
            limit: 100
            rate: { interval: '1 minute', amount: 100 }

        # Strict API rate limiting (10 requests per minute)
        api_strict:
            policy: 'sliding_window'
            limit: 10
            interval: '1 minute'

        # Password reset (3 attempts per hour)
        password_reset:
            policy: 'sliding_window'
            limit: 3
            interval: '1 hour'
```

**Step 3: Apply to Login Endpoint**

```php
// src/Controller/SecurityController.php

use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

#[Route('/api/login', name: 'api_login', methods: ['POST'])]
public function login(
    Request $request,
    #[Autowire(service: 'limiter.login')]
    RateLimiterFactory $loginLimiter
): JsonResponse {
    // Rate limit by IP + username
    $username = json_decode($request->getContent(), true)['username'] ?? 'unknown';
    $limiter = $loginLimiter->create($request->getClientIp() . '_' . $username);

    if (false === $limiter->consume(1)->isAccepted()) {
        throw new TooManyRequestsHttpException(
            retry_after: null,
            message: 'Too many login attempts. Please try again in 15 minutes.'
        );
    }

    // ... rest of login logic
}
```

**Step 4: Global API Rate Limiting (Event Listener)**

```php
// src/EventListener/ApiRateLimitListener.php
<?php

namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class ApiRateLimitListener
{
    public function __construct(
        private readonly RateLimiterFactory $apiLimiter
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Only apply to API routes
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        // Skip rate limiting for documentation
        if (str_starts_with($request->getPathInfo(), '/api/doc')) {
            return;
        }

        // Rate limit by IP
        $limiter = $this->apiLimiter->create($request->getClientIp());
        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException(
                retry_after: $limit->getRetryAfter()->getTimestamp(),
                message: 'Too many requests. Please slow down.'
            );
        }

        // Add rate limit headers
        $response = $event->getResponse();
        if ($response) {
            $response->headers->set('X-RateLimit-Limit', $limit->getLimit());
            $response->headers->set('X-RateLimit-Remaining', $limit->getRemainingTokens());
        }
    }
}
```

**Register listener in `config/services.yaml`:**

```yaml
App\EventListener\ApiRateLimitListener:
    arguments:
        $apiLimiter: '@limiter.api'
    tags:
        - { name: kernel.event_listener, event: kernel.request, priority: 10 }
```

**Testing:**
```bash
# Test rate limiting
for i in {1..110}; do
  curl http://localhost/api/bookings -H "Authorization: Bearer token"
done

# After 100 requests, should get 429 Too Many Requests
```

---

## 🚨 Issue 3: Password Reset Tokens Not Hashed (CRITICAL)

### Current Problem
- Password reset tokens stored in plain text
- If database compromised, attacker can reset any password

### Solution: Hash Reset Tokens

**Step 1: Update User Entity**

```php
// src/Entity/User.php

#[ORM\Column(type: 'string', length: 255, nullable: true)]
private ?string $resetToken = null;

#[ORM\Column(type: 'datetime_immutable', nullable: true)]
private ?\DateTimeImmutable $resetTokenExpiresAt = null;

public function createPasswordResetToken(): string
{
    // Generate random token
    $token = bin2hex(random_bytes(32)); // 64 character hex string

    // Hash it before storing (like passwords)
    $this->resetToken = password_hash($token, PASSWORD_BCRYPT);
    $this->resetTokenExpiresAt = new \DateTimeImmutable('+1 hour');

    // Return plain token (send via email)
    return $token;
}

public function verifyPasswordResetToken(string $token): bool
{
    if (!$this->resetToken || !$this->resetTokenExpiresAt) {
        return false;
    }

    // Check expiration
    if ($this->resetTokenExpiresAt < new \DateTimeImmutable()) {
        return false;
    }

    // Verify hash
    return password_verify($token, $this->resetToken);
}

public function clearPasswordResetToken(): void
{
    $this->resetToken = null;
    $this->resetTokenExpiresAt = null;
}
```

**Step 2: Request Password Reset Endpoint**

```php
// src/Controller/SecurityController.php

#[Route('/api/password/reset-request', methods: ['POST'])]
public function requestPasswordReset(
    Request $request,
    UserRepository $userRepo,
    MailerInterface $mailer,
    #[Autowire(service: 'limiter.password_reset')]
    RateLimiterFactory $passwordResetLimiter
): JsonResponse {
    // Rate limiting
    $limiter = $passwordResetLimiter->create($request->getClientIp());
    if (!$limiter->consume(1)->isAccepted()) {
        throw new TooManyRequestsHttpException(
            message: 'Too many password reset attempts. Try again in 1 hour.'
        );
    }

    $data = json_decode($request->getContent(), true);
    $email = $data['email'] ?? null;

    if (!$email) {
        throw ApiProblemException::badRequest('Email is required');
    }

    $user = $userRepo->findOneBy(['email' => $email]);

    // Always return success (don't reveal if user exists)
    if (!$user) {
        return $this->json([
            'message' => 'If that email exists, a reset link has been sent.'
        ]);
    }

    // Generate token (plain text, will be hashed)
    $plainToken = $user->createPasswordResetToken();
    $this->em->flush();

    // Send email with plain token
    $resetUrl = "https://yourapp.com/reset-password?token={$plainToken}";

    // TODO: Send actual email
    // $mailer->send(...)

    return $this->json([
        'message' => 'If that email exists, a reset link has been sent.'
    ]);
}
```

**Step 3: Reset Password Endpoint**

```php
#[Route('/api/password/reset', methods: ['POST'])]
public function resetPassword(
    Request $request,
    UserRepository $userRepo,
    UserPasswordHasherInterface $passwordHasher
): JsonResponse {
    $data = json_decode($request->getContent(), true);
    $token = $data['token'] ?? null;
    $newPassword = $data['password'] ?? null;

    if (!$token || !$newPassword) {
        throw ApiProblemException::badRequest('Token and new password are required');
    }

    // Find user by verifying token hash
    $users = $userRepo->findBy(['resetToken' => ['IS NOT NULL']]);

    $user = null;
    foreach ($users as $u) {
        if ($u->verifyPasswordResetToken($token)) {
            $user = $u;
            break;
        }
    }

    if (!$user) {
        throw ApiProblemException::badRequest('Invalid or expired reset token');
    }

    // Update password
    $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
    $user->setPassword($hashedPassword);
    $user->clearPasswordResetToken();

    $this->em->flush();

    return $this->json(['message' => 'Password reset successfully']);
}
```

---

## 🚨 Issue 4: Minimal Test Coverage (MEDIUM)

### Current Status: <5%
### Target: 70%+

**Create comprehensive test suite (already started with 3 test files)**

**Priority Test Files to Create:**

1. **Controller Tests** (Most Important)
   - BookingControllerTest.php ✅ (created)
   - UserControllerTest.php
   - PaymentControllerTest.php
   - MessageControllerTest.php
   - SecurityControllerTest.php

2. **Service Tests**
   - CacheServiceTest.php
   - PaymentIntentFactoryTest.php
   - StripeWebhookHandlerTest.php
   - RefreshTokenServiceTest.php

3. **Entity Tests**
   - SoftDeletableTest.php ✅ (created)
   - UserTest.php
   - BookingTest.php

4. **DTO Tests**
   - AbstractRequestDTOTest.php ✅ (created)
   - CreateBookingDTOTest.php
   - PaginatedRequestDTOTest.php

**Run tests regularly:**
```bash
vendor/bin/phpunit
vendor/bin/phpunit --coverage-html var/coverage
```

---

## 📋 Implementation Checklist

### JWT & Refresh Tokens
- [ ] Update `lexik_jwt_authentication.yaml` (TTL: 900)
- [ ] Create RefreshToken entity
- [ ] Create RefreshTokenService
- [ ] Update login endpoint to return refresh token
- [ ] Create `/api/token/refresh` endpoint
- [ ] Create migration
- [ ] Test token refresh flow

### Rate Limiting
- [ ] Install symfony/rate-limiter
- [ ] Create `rate_limiter.yaml` config
- [ ] Apply to login endpoint
- [ ] Create global API rate limit listener
- [ ] Register listener in services.yaml
- [ ] Test rate limits

### Password Reset Security
- [ ] Update User entity with hashed token methods
- [ ] Create `/api/password/reset-request` endpoint
- [ ] Create `/api/password/reset` endpoint
- [ ] Add rate limiting to reset request
- [ ] Test reset flow

### Test Coverage
- [ ] Create remaining controller tests
- [ ] Create service tests
- [ ] Create entity tests
- [ ] Run coverage report
- [ ] Aim for 70%+ coverage

---

## 🚀 Deployment Steps

```bash
# 1. Update JWT configuration
# Edit config/packages/lexik_jwt_authentication.yaml

# 2. Install rate limiter
composer require symfony/rate-limiter

# 3. Create entities and services
# (Use the code examples above)

# 4. Create migration
docker compose exec alpine bin/console make:migration

# 5. Run migration
docker compose exec alpine bin/console doctrine:migrations:migrate

# 6. Clear cache
docker compose exec alpine bin/console cache:clear

# 7. Test
./scripts/test.sh
```

---

**Estimated Total Time:** 4-6 hours
**Priority:** HIGH - These are critical security issues
**Next Steps:** Implement in order: JWT → Rate Limiting → Password Reset → Tests
