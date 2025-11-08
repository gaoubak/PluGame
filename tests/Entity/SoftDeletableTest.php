<?php

namespace App\Tests\Entity;

use App\Entity\Booking;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * Test soft delete functionality
 */
class SoftDeletableTest extends TestCase
{
    public function testEntityIsActiveByDefault(): void
    {
        $booking = new Booking();

        $this->assertTrue($booking->isActive());
        $this->assertFalse($booking->isDeleted());
        $this->assertNull($booking->getDeletedAt());
        $this->assertNull($booking->getDeletedBy());
    }

    public function testSoftDeleteMarksEntityAsDeleted(): void
    {
        $booking = new Booking();
        $user = new User();

        $booking->softDelete($user);

        $this->assertFalse($booking->isActive());
        $this->assertTrue($booking->isDeleted());
        $this->assertInstanceOf(\DateTimeImmutable::class, $booking->getDeletedAt());
        $this->assertSame($user, $booking->getDeletedBy());
    }

    public function testSoftDeleteWithoutUser(): void
    {
        $booking = new Booking();

        $booking->softDelete(null);

        $this->assertTrue($booking->isDeleted());
        $this->assertInstanceOf(\DateTimeImmutable::class, $booking->getDeletedAt());
        $this->assertNull($booking->getDeletedBy());
    }

    public function testRestoreUndoesSoftDelete(): void
    {
        $booking = new Booking();
        $user = new User();

        // Soft delete
        $booking->softDelete($user);
        $this->assertTrue($booking->isDeleted());

        // Restore
        $booking->restore();

        $this->assertTrue($booking->isActive());
        $this->assertFalse($booking->isDeleted());
        $this->assertNull($booking->getDeletedAt());
        $this->assertNull($booking->getDeletedBy());
    }

    public function testDeletedAtTimestampIsAccurate(): void
    {
        $booking = new Booking();
        $before = new \DateTimeImmutable();

        sleep(1); // Ensure time passes
        $booking->softDelete(null);
        sleep(1);

        $after = new \DateTimeImmutable();

        $this->assertGreaterThan($before, $booking->getDeletedAt());
        $this->assertLessThan($after, $booking->getDeletedAt());
    }

    public function testMultipleSoftDeletesUpdateTimestamp(): void
    {
        $booking = new Booking();

        $booking->softDelete(null);
        $firstDeletedAt = $booking->getDeletedAt();

        sleep(1);

        $booking->restore();
        $booking->softDelete(null);
        $secondDeletedAt = $booking->getDeletedAt();

        $this->assertGreaterThan($firstDeletedAt, $secondDeletedAt);
    }
}
