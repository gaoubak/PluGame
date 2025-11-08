# Code Quality & Testing Guide

## 🎯 Overview

This project uses industry-standard PHP linting and testing tools to ensure code quality, maintainability, and reliability.

---

## 🔍 Code Quality Tools

### 1. PHP_CodeSniffer (phpcs)

**Purpose:** Enforces PSR-12 coding standards and detects code style violations.

**Configuration:** `phpcs.xml`

**Run linting:**
```bash
# Check all files
vendor/bin/phpcs

# Check specific directory
vendor/bin/phpcs src/Controller

# Check specific file
vendor/bin/phpcs src/Controller/BookingController.php

# Show full report with warnings
vendor/bin/phpcs --report=full
```

**Auto-fix issues:**
```bash
# Fix all auto-fixable issues
vendor/bin/phpcbf

# Fix specific directory
vendor/bin/phpcbf src/Controller

# Fix specific file
vendor/bin/phpcbf src/Controller/BookingController.php
```

**Example output:**
```
FILE: /app/src/Controller/BookingController.php
--------------------------------------------------------------------------------
FOUND 3 ERRORS AFFECTING 2 LINES
--------------------------------------------------------------------------------
 12 | ERROR | Missing return type declaration
 45 | ERROR | Line exceeds 120 characters; contains 135 characters
 67 | ERROR | Expected 1 blank line after method; 2 found
--------------------------------------------------------------------------------
Time: 250ms; Memory: 10MB
```

---

### 2. PHPStan (Static Analysis)

**Purpose:** Finds bugs without running code, detects type errors, undefined variables, etc.

**Configuration:** `phpstan.neon`

**Run analysis:**
```bash
# Analyze entire project (level 6)
vendor/bin/phpstan analyse

# Analyze specific directory
vendor/bin/phpstan analyse src/Service

# Analyze with different levels (0-9, 9 is strictest)
vendor/bin/phpstan analyse --level 8

# Generate baseline (ignore existing issues)
vendor/bin/phpstan analyse --generate-baseline

# Show verbose errors
vendor/bin/phpstan analyse -vvv
```

**Example output:**
```
 ------ -------------------------------------------------------------------
  Line   Controller/BookingController.php
 ------ -------------------------------------------------------------------
  45     Parameter #1 $booking of method setStatus() expects string|null,
         int given.
  89     Method BookingController::delete() has no return type specified.
  120    Variable $user might not be defined.
 ------ -------------------------------------------------------------------

 [ERROR] Found 3 errors
```

---

## 🧪 Testing with PHPUnit

### Test Structure

```
tests/
├── bootstrap.php
├── Controller/           # API endpoint tests
│   └── BookingControllerTest.php
├── DTO/                  # DTO validation tests
│   └── AbstractRequestDTOTest.php
├── Entity/               # Entity tests
│   └── SoftDeletableTest.php
├── Service/              # Business logic tests
│   └── CacheServiceTest.php
└── Repository/           # Repository tests
    └── BookingRepositoryTest.php
```

### Running Tests

```bash
# Run all tests
vendor/bin/phpunit

# Run specific test file
vendor/bin/phpunit tests/Entity/SoftDeletableTest.php

# Run specific test method
vendor/bin/phpunit --filter testSoftDeleteMarksEntityAsDeleted

# Run tests with coverage report (requires Xdebug)
vendor/bin/phpunit --coverage-html var/coverage

# Run tests in specific group
vendor/bin/phpunit --group integration

# Show more detail
vendor/bin/phpunit --verbose
```

### Test Types

#### 1. Unit Tests

**Test a single class in isolation:**

```php
namespace App\Tests\Entity;

use App\Entity\Booking;
use PHPUnit\Framework\TestCase;

class BookingTest extends TestCase
{
    public function testBookingCanBeCreated(): void
    {
        $booking = new Booking();
        $booking->setStatus('PENDING');

        $this->assertEquals('PENDING', $booking->getStatus());
    }
}
```

#### 2. Integration Tests

**Test multiple classes working together:**

```php
namespace App\Tests\Service;

use App\Service\Cache\CacheService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CacheServiceTest extends KernelTestCase
{
    public function testCacheServiceStoresAndRetrieves(): void
    {
        self::bootKernel();
        $cache = self::getContainer()->get(CacheService::class);

        $cache->getServices(fn() => ['test data'], ['test']);

        // Assert data is cached...
    }
}
```

#### 3. API Tests (Functional Tests)

**Test HTTP endpoints:**

```php
namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BookingControllerTest extends WebTestCase
{
    public function testListBookingsReturnsJson(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/bookings', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->getToken(),
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseFormatSame('json');
    }
}
```

---

## 📊 Test Coverage

### Generate Coverage Report

```bash
# HTML report (requires Xdebug or PCOV)
vendor/bin/phpunit --coverage-html var/coverage

# Open in browser
open var/coverage/index.html

# Text report in terminal
vendor/bin/phpunit --coverage-text

# Clover XML (for CI/CD)
vendor/bin/phpunit --coverage-clover coverage.xml
```

### Coverage Goals

| Component | Current | Target |
|-----------|---------|--------|
| Controllers | 0% | 70% |
| Services | 0% | 80% |
| Entities | 10% | 60% |
| DTOs | 0% | 90% |
| **Overall** | **<5%** | **70%** |

---

## 🚀 Continuous Integration Scripts

### Create Helper Scripts

**`scripts/lint.sh`** (Code linting)
```bash
#!/bin/bash
set -e

echo "🔍 Running PHP_CodeSniffer..."
vendor/bin/phpcs

echo ""
echo "🔬 Running PHPStan..."
vendor/bin/phpstan analyse

echo ""
echo "✅ All linting checks passed!"
```

**`scripts/test.sh`** (Run tests)
```bash
#!/bin/bash
set -e

echo "🧪 Running PHPUnit tests..."
vendor/bin/phpunit

echo ""
echo "✅ All tests passed!"
```

**`scripts/check.sh`** (Full quality check)
```bash
#!/bin/bash
set -e

echo "================================"
echo "  Code Quality & Test Suite"
echo "================================"

./scripts/lint.sh
./scripts/test.sh

echo ""
echo "🎉 All checks passed! Ready to commit."
```

**Make scripts executable:**
```bash
chmod +x scripts/*.sh
```

**Run full check:**
```bash
./scripts/check.sh
```

---

## 🔧 IDE Integration

### PHPStorm / IntelliJ IDEA

1. **Enable PHP_CodeSniffer:**
   - Settings → PHP → Quality Tools → PHP_CodeSniffer
   - Configuration: `/path/to/project/vendor/bin/phpcs`
   - Coding Standard: Custom
   - Path to ruleset: `/path/to/project/phpcs.xml`

2. **Enable PHPStan:**
   - Settings → PHP → Quality Tools → PHPStan
   - Configuration: `/path/to/project/vendor/bin/phpstan`
   - Configuration file: `/path/to/project/phpstan.neon`

3. **Enable PHPUnit:**
   - Settings → PHP → Test Frameworks → PHPUnit
   - Path to phpunit.phar: `/path/to/project/vendor/bin/phpunit`
   - Default configuration file: `/path/to/project/phpunit.xml.dist`

### VS Code

**Install extensions:**
- PHP Intelephense
- PHPUnit Test Explorer
- PHP_CodeSniffer

**`.vscode/settings.json`:**
```json
{
    "phpcs.enable": true,
    "phpcs.executablePath": "./vendor/bin/phpcs",
    "phpcs.standard": "./phpcs.xml",
    "phpstan.enabled": true,
    "phpstan.path": "./vendor/bin/phpstan",
    "phpunit.php": "./vendor/bin/phpunit"
}
```

---

## 📝 Pre-Commit Hook

**Create `.git/hooks/pre-commit`:**

```bash
#!/bin/bash

echo "Running pre-commit checks..."

# Run PHP_CodeSniffer on staged files
STAGED_FILES=$(git diff --cached --name-only --diff-filter=ACM | grep "\.php$")

if [ "$STAGED_FILES" != "" ]; then
    echo "Checking PHP files with phpcs..."
    vendor/bin/phpcs $STAGED_FILES

    if [ $? != 0 ]; then
        echo "❌ PHP_CodeSniffer found issues. Fix them before committing."
        echo "Run: vendor/bin/phpcbf $STAGED_FILES"
        exit 1
    fi

    echo "Running PHPStan..."
    vendor/bin/phpstan analyse $STAGED_FILES

    if [ $? != 0 ]; then
        echo "❌ PHPStan found issues. Fix them before committing."
        exit 1
    fi
fi

echo "✅ Pre-commit checks passed!"
exit 0
```

**Make executable:**
```bash
chmod +x .git/hooks/pre-commit
```

---

## 🎯 Common Issues & Fixes

### Issue 1: Line Too Long

```php
// ❌ BAD: 135 characters
throw new ApiProblemException(status: 409, title: 'Booking Cannot Be Accepted', detail: "Cannot accept booking in '{$booking->getStatus()}' status");

// ✅ GOOD: Split into multiple lines
throw new ApiProblemException(
    status: 409,
    title: 'Booking Cannot Be Accepted',
    detail: "Cannot accept booking in '{$booking->getStatus()}' status"
);
```

### Issue 2: Missing Return Type

```php
// ❌ BAD: No return type
public function getBooking(string $id)
{
    return $this->repo->find($id);
}

// ✅ GOOD: Explicit return type
public function getBooking(string $id): ?Booking
{
    return $this->repo->find($id);
}
```

### Issue 3: Unused Variable

```php
// ❌ BAD: Variable declared but never used
public function process(): void
{
    $data = $this->getData();
    $this->doSomething();
}

// ✅ GOOD: Remove unused variable
public function process(): void
{
    $this->doSomething();
}
```

### Issue 4: Complex Conditional

```php
// ❌ BAD: Too complex (cyclomatic complexity > 10)
public function validate($data): bool
{
    if ($data['type'] === 'A') {
        if ($data['status'] === 'active') {
            if ($data['amount'] > 100) {
                if ($data['verified']) {
                    // ... 10 more nested ifs
                }
            }
        }
    }
}

// ✅ GOOD: Early returns, extract methods
public function validate($data): bool
{
    if (!$this->isTypeA($data)) {
        return false;
    }

    if (!$this->isActive($data)) {
        return false;
    }

    return $this->meetsAmountRequirement($data)
        && $this->isVerified($data);
}
```

---

## 📊 CI/CD Integration

### GitHub Actions Example

**`.github/workflows/php.yml`:**

```yaml
name: PHP CI

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest

    steps:
    - uses: actions/checkout@v2

    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.2'
        extensions: mbstring, xml, ctype, iconv, intl, pdo_mysql
        coverage: xdebug

    - name: Install dependencies
      run: composer install --prefer-dist --no-progress

    - name: Run PHP_CodeSniffer
      run: vendor/bin/phpcs

    - name: Run PHPStan
      run: vendor/bin/phpstan analyse

    - name: Run PHPUnit
      run: vendor/bin/phpunit --coverage-clover coverage.xml

    - name: Upload coverage
      uses: codecov/codecov-action@v2
      with:
        file: ./coverage.xml
```

---

## 🎉 Quick Reference

### Daily Commands

```bash
# Before committing
./scripts/check.sh

# Quick lint
vendor/bin/phpcs src/

# Quick test
vendor/bin/phpunit

# Fix style issues
vendor/bin/phpcbf src/

# Check specific file
vendor/bin/phpcs src/Controller/BookingController.php
vendor/bin/phpstan analyse src/Controller/BookingController.php
vendor/bin/phpunit tests/Controller/BookingControllerTest.php
```

### When to Run

| When | Command | Why |
|------|---------|-----|
| Before commit | `./scripts/check.sh` | Catch issues early |
| After writing code | `vendor/bin/phpcs src/` | Check style |
| After refactoring | `vendor/bin/phpunit` | Ensure nothing broke |
| Before PR | `./scripts/check.sh` | Full validation |
| After merge | CI/CD pipeline | Automated checks |

---

## 📚 Resources

- **PHP_CodeSniffer:** https://github.com/squizlabs/PHP_CodeSniffer
- **PHPStan:** https://phpstan.org/
- **PHPUnit:** https://phpunit.de/
- **PSR-12:** https://www.php-fig.org/psr/psr-12/
- **Symfony Testing:** https://symfony.com/doc/current/testing.html

---

**Installed Tools:**
- ✅ PHP_CodeSniffer (PSR-12 standard)
- ✅ PHPStan (Level 6 analysis)
- ✅ PHPUnit (Testing framework)
- ✅ Configuration files ready
- ✅ Test examples included

**Next Steps:**
1. Run `vendor/bin/phpcs` to see current code style issues
2. Run `vendor/bin/phpstan analyse` to find type errors
3. Run `vendor/bin/phpunit` to execute tests
4. Fix issues and gradually increase coverage to 70%+

🎯 **Goal:** Production-ready code with 70%+ test coverage!
