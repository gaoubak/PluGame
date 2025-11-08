# Soft Delete Implementation Guide

## 🗑️ Overview

**Soft delete** marks records as deleted without physically removing them from the database. This prevents accidental data loss and enables "undo delete" functionality.

### Benefits

✅ **Prevents Data Loss** - Deleted records can be restored
✅ **Audit Trail** - Track who deleted what and when
✅ **Referential Integrity** - Foreign key relationships remain intact
✅ **Compliance** - Retain data for legal/regulatory requirements
✅ **Better UX** - Users can undo accidental deletions

---

## 📦 Components

### 1. SoftDeletable Trait

**Location:** `src/Entity/Traits/SoftDeletable.php`

Adds two fields to entities:
- `deletedAt` - When the record was deleted (null = active)
- `deletedBy` - Who deleted the record

**Methods:**
```php
$entity->isDeleted()     // Check if deleted
$entity->isActive()      // Check if NOT deleted
$entity->softDelete($user)  // Mark as deleted
$entity->restore()       // Undo deletion
```

### 2. Doctrine Filter

**Location:** `src/Doctrine/Filter/SoftDeletableFilter.php`

Automatically excludes soft-deleted records from ALL queries by adding:
```sql
WHERE deleted_at IS NULL
```

**Enabled by default** in `config/packages/doctrine.yaml`

### 3. Entities with Soft Delete

Currently enabled on:
- ✅ **Booking** - Critical transaction data
- ✅ **ServiceOffering** - Creator services
- ✅ **Message** - User messages (can be undeleted)
- ✅ **Comment** - User comments
- ✅ **Review** - Creator reviews

---

## 💻 Usage Examples

### Example 1: Soft Delete a Booking

```php
use App\Controller\BookingController;

#[Route('/delete/{id}', name: 'booking_delete', methods: ['DELETE'])]
public function delete(Booking $booking): Response
{
    // Authorization check
    $this->denyAccessUnlessGranted(BookingVoter::DELETE, $booking);

    // Soft delete with audit trail
    $user = $this->security->getUser();
    $booking->softDelete($user);

    $this->em->flush();

    return $this->json(['message' => 'Booking deleted successfully']);
}
```

**What happens:**
- `deletedAt` set to current timestamp
- `deletedBy` set to current user
- Record stays in database
- Automatically hidden from normal queries

---

### Example 2: Restore a Deleted Booking

```php
#[Route('/{id}/restore', name: 'booking_restore', methods: ['POST'])]
public function restore(Booking $booking): Response
{
    // Check if actually deleted
    if (!$booking->isDeleted()) {
        throw ApiProblemException::badRequest('Booking is not deleted');
    }

    // Restore the booking
    $booking->restore();
    $this->em->flush();

    return $this->json(['message' => 'Booking restored successfully']);
}
```

**What happens:**
- `deletedAt` set to null
- `deletedBy` set to null
- Record becomes visible again
- All relationships still intact

---

### Example 3: Include Deleted Records (Admin View)

```php
use Doctrine\ORM\EntityManagerInterface;

#[Route('/admin/bookings/all', methods: ['GET'])]
public function allBookings(EntityManagerInterface $em): Response
{
    // Temporarily disable soft delete filter
    $em->getFilters()->disable('soft_deletable');

    // Now deleted records are included
    $allBookings = $this->bookingRepo->findAll();

    // Re-enable filter for subsequent queries
    $em->getFilters()->enable('soft_deletable');

    return $this->json($allBookings);
}
```

---

### Example 4: Query Only Deleted Records

```php
#[Route('/admin/bookings/deleted', methods: ['GET'])]
public function deletedBookings(BookingRepository $repo, EntityManagerInterface $em): Response
{
    // Disable filter to see all records
    $em->getFilters()->disable('soft_deletable');

    // Query for deleted records only
    $deletedBookings = $repo->createQueryBuilder('b')
        ->where('b.deletedAt IS NOT NULL')
        ->orderBy('b.deletedAt', 'DESC')
        ->getQuery()
        ->getResult();

    $em->getFilters()->enable('soft_deletable');

    return $this->json($deletedBookings, context: ['groups' => ['booking:read', 'admin:read']]);
}
```

---

### Example 5: Check Before Deleting

```php
#[Route('/messages/{id}', methods: ['DELETE'])]
public function deleteMessage(Message $message): Response
{
    $this->denyAccessUnlessGranted(MessageVoter::DELETE, $message);

    // Check if already deleted
    if ($message->isDeleted()) {
        throw ApiProblemException::conflict('Message is already deleted');
    }

    // Soft delete
    $message->softDelete($this->getUser());
    $this->em->flush();

    return $this->json(['message' => 'Message deleted successfully']);
}
```

---

### Example 6: Permanent Hard Delete (Admin Only)

```php
#[Route('/admin/bookings/{id}/permanent-delete', methods: ['DELETE'])]
#[IsGranted('ROLE_ADMIN')]
public function permanentDelete(Booking $booking, EntityManagerInterface $em): Response
{
    // Disable filter to access deleted records
    $em->getFilters()->disable('soft_deletable');

    // Confirm booking exists
    if (!$booking) {
        throw ApiProblemException::notFound('booking', $id);
    }

    // Hard delete - PERMANENTLY removes from database
    $em->remove($booking);
    $em->flush();

    $em->getFilters()->enable('soft_deletable');

    return $this->json(['message' => 'Booking permanently deleted']);
}
```

**⚠️ Warning:** Use hard delete sparingly - data cannot be recovered!

---

## 🔧 Advanced Usage

### Custom Repository Method with Soft Delete Aware

```php
// src/Repository/BookingRepository.php

/**
 * Find bookings by user, including soft-deleted if admin
 */
public function findByUserWithDeleted(User $user, bool $includeDeleted = false): array
{
    $qb = $this->createQueryBuilder('b')
        ->where('b.athlete = :user OR b.creator = :user')
        ->setParameter('user', $user);

    // Include deleted records if requested
    if ($includeDeleted) {
        $this->getEntityManager()
            ->getFilters()
            ->disable('soft_deletable');
    }

    $results = $qb->getQuery()->getResult();

    // Re-enable filter
    if ($includeDeleted) {
        $this->getEntityManager()
            ->getFilters()
            ->enable('soft_deletable');
    }

    return $results;
}
```

---

### Cascade Soft Delete (Delete Related Records)

```php
#[Route('/services/{id}', methods: ['DELETE'])]
public function deleteService(ServiceOffering $service): Response
{
    $this->denyAccessUnlessGranted(ServiceOfferingVoter::DELETE, $service);

    $user = $this->getUser();

    // Soft delete the service
    $service->softDelete($user);

    // Also soft delete all related bookings
    foreach ($service->getBookings() as $booking) {
        if (!$booking->isDeleted()) {
            $booking->softDelete($user);
        }
    }

    $this->em->flush();

    return $this->json(['message' => 'Service and related bookings deleted']);
}
```

---

### Auto-Delete After Retention Period

```php
// src/Command/CleanupDeletedRecordsCommand.php

#[AsCommand(name: 'app:cleanup-deleted')]
class CleanupDeletedRecordsCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $retentionDays = 90; // Keep deleted records for 90 days
        $cutoffDate = new \DateTimeImmutable("-{$retentionDays} days");

        $em = $this->entityManager;
        $em->getFilters()->disable('soft_deletable');

        // Find bookings deleted more than 90 days ago
        $oldDeleted = $this->bookingRepo->createQueryBuilder('b')
            ->where('b.deletedAt IS NOT NULL')
            ->andWhere('b.deletedAt < :cutoff')
            ->setParameter('cutoff', $cutoffDate)
            ->getQuery()
            ->getResult();

        $count = 0;
        foreach ($oldDeleted as $booking) {
            $em->remove($booking); // Hard delete
            $count++;
        }

        $em->flush();
        $em->getFilters()->enable('soft_deletable');

        $output->writeln("Permanently deleted {$count} old records");
        return Command::SUCCESS;
    }
}
```

Run with cron:
```bash
# Every day at 2am
0 2 * * * cd /app && bin/console app:cleanup-deleted
```

---

## 📊 Database Schema

### Booking Table After Migration

```sql
CREATE TABLE booking (
    id VARCHAR(36) PRIMARY KEY,
    athlete_user_id INT NOT NULL,
    creator_user_id INT NOT NULL,
    status VARCHAR(20),
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    -- 🗑️ Soft Delete Fields
    deleted_at DATETIME DEFAULT NULL,
    deleted_by_id INT DEFAULT NULL,

    FOREIGN KEY (deleted_by_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_booking_deleted_at (deleted_at)
);
```

**Query Performance:**
- Index on `deleted_at` ensures fast filtering
- `deleted_at IS NULL` queries use the index efficiently

---

## 🔒 Security Considerations

### 1. Hide Deleted Records from Normal Users

```php
// ❌ BAD: Exposes deleted records
$bookings = $repo->findAll();

// ✅ GOOD: Doctrine filter automatically excludes deleted
$bookings = $repo->findAll(); // Only returns active records
```

### 2. Only Admins Can View Deleted Records

```php
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/deleted-bookings', methods: ['GET'])]
public function viewDeleted(EntityManagerInterface $em): Response
{
    $em->getFilters()->disable('soft_deletable');
    $deleted = $this->bookingRepo->findAll();
    $em->getFilters()->enable('soft_deletable');

    return $this->json($deleted);
}
```

### 3. Serialization Groups

```php
// src/Entity/Traits/SoftDeletable.php

#[Groups(['admin:read'])] // Only visible to admins
private ?\DateTimeImmutable $deletedAt = null;

#[Groups(['admin:read'])]
private ?User $deletedBy = null;
```

---

## 🧪 Testing

### Unit Test Example

```php
// tests/Entity/BookingTest.php

use App\Entity\Booking;
use PHPUnit\Framework\TestCase;

class BookingTest extends TestCase
{
    public function testSoftDelete(): void
    {
        $booking = new Booking();
        $user = new User();

        // Initially active
        $this->assertTrue($booking->isActive());
        $this->assertFalse($booking->isDeleted());
        $this->assertNull($booking->getDeletedAt());

        // After soft delete
        $booking->softDelete($user);
        $this->assertFalse($booking->isActive());
        $this->assertTrue($booking->isDeleted());
        $this->assertInstanceOf(\DateTimeImmutable::class, $booking->getDeletedAt());
        $this->assertSame($user, $booking->getDeletedBy());
    }

    public function testRestore(): void
    {
        $booking = new Booking();
        $booking->softDelete(new User());

        // After restore
        $booking->restore();
        $this->assertTrue($booking->isActive());
        $this->assertFalse($booking->isDeleted());
        $this->assertNull($booking->getDeletedAt());
        $this->assertNull($booking->getDeletedBy());
    }
}
```

### Integration Test with Filter

```php
// tests/Repository/BookingRepositoryTest.php

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class BookingRepositoryTest extends KernelTestCase
{
    public function testSoftDeleteFilter(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $repo = $em->getRepository(Booking::class);

        // Create and delete a booking
        $booking = new Booking();
        $em->persist($booking);
        $em->flush();

        $bookingId = $booking->getId();
        $booking->softDelete(null);
        $em->flush();
        $em->clear();

        // Filter should exclude it
        $found = $repo->find($bookingId);
        $this->assertNull($found, 'Soft-deleted booking should be filtered');

        // Disable filter to find it
        $em->getFilters()->disable('soft_deletable');
        $found = $repo->find($bookingId);
        $this->assertNotNull($found, 'Should find booking with filter disabled');
        $this->assertTrue($found->isDeleted());
    }
}
```

---

## 🚀 Migration Guide

### Step 1: Run Migration

```bash
docker compose exec alpine bin/console doctrine:migrations:migrate
```

This adds `deleted_at` and `deleted_by_id` columns to:
- booking
- service_offering
- message
- comments
- review

### Step 2: Verify Filter is Enabled

Check `config/packages/doctrine.yaml`:
```yaml
doctrine:
    orm:
        filters:
            soft_deletable:
                class: App\Doctrine\Filter\SoftDeletableFilter
                enabled: true
```

### Step 3: Update Controllers

Replace:
```php
// Old: Hard delete
$this->em->remove($entity);
```

With:
```php
// New: Soft delete
$entity->softDelete($this->getUser());
```

### Step 4: Clear Cache

```bash
docker compose exec alpine bin/console cache:clear
```

---

## 📝 Best Practices

### ✅ DO

1. **Use soft delete for user data** (bookings, messages, reviews)
2. **Track who deleted** by passing user to `softDelete()`
3. **Add restore endpoints** for user-facing entities
4. **Use serialization groups** to hide deleted fields from non-admins
5. **Test with filter disabled** in admin interfaces

### ❌ DON'T

1. **Don't soft delete highly transactional data** (payment intents, audit logs)
2. **Don't expose deleted records** to normal users
3. **Don't forget to flush** after soft delete
4. **Don't hard delete** without admin approval
5. **Don't query deleted records** without disabling filter

---

## 🐛 Troubleshooting

### Problem: Can't find entity after soft delete

```php
// ❌ This returns null after soft delete
$booking = $repo->find($id);

// ✅ Disable filter to find deleted records
$em->getFilters()->disable('soft_deletable');
$booking = $repo->find($id);
$em->getFilters()->enable('soft_deletable');
```

### Problem: Foreign key constraint errors

```php
// If related entities are NOT soft deleted, you may get FK errors
// Solution: Either soft delete related records OR use ON DELETE SET NULL

#[ORM\JoinColumn(onDelete: 'SET NULL')]
private ?ServiceOffering $service = null;
```

### Problem: Filter not working in tests

```php
// In tests, explicitly enable the filter
protected function setUp(): void
{
    parent::setUp();
    $em = self::getContainer()->get(EntityManagerInterface::class);
    $em->getFilters()->enable('soft_deletable');
}
```

---

## 🎯 Next Steps

- [ ] Add soft delete to remaining entities (Conversation, MediaAsset, etc.)
- [ ] Create admin UI for viewing/restoring deleted records
- [ ] Add retention policy automation (auto-hard-delete after N days)
- [ ] Add soft delete metrics to monitoring dashboard
- [ ] Document soft delete behavior for API consumers

---

**End of Soft Delete Guide**

Your entities now have enterprise-grade soft delete with audit trails! 🎉
