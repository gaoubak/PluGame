<?php

namespace App\Entity\Traits;

use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Soft Delete Trait
 *
 * Adds soft delete functionality to entities. Instead of physically deleting
 * records from the database, this marks them as deleted with a timestamp.
 *
 * Benefits:
 * - Prevents accidental data loss
 * - Enables "undo delete" functionality
 * - Maintains referential integrity
 * - Audit trail of deletions
 *
 * Usage:
 * ```
 * use App\Entity\Traits\SoftDeletable;
 *
 * #[ORM\Entity]
 * class Booking {
 *     use SoftDeletable;
 *     // ... other fields
 * }
 * ```
 */
trait SoftDeletable
{
    /**
     * When the record was soft-deleted (null = not deleted)
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['admin:read'])] // Only admins see deleted records
    private ?\DateTimeImmutable $deletedAt = null;

    /**
     * Who soft-deleted this record (for audit trail)
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'deleted_by_id', referencedColumnName: 'id', nullable: true)]
    #[Groups(['admin:read'])]
    private ?User $deletedBy = null;

    /**
     * Check if this record is soft-deleted
     */
    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    /**
     * Check if this record is NOT soft-deleted
     */
    public function isActive(): bool
    {
        return $this->deletedAt === null;
    }

    /**
     * Soft delete this record
     *
     * @param User|null $deletedBy Who is deleting this record
     */
    public function softDelete(?User $deletedBy = null): self
    {
        $this->deletedAt = new \DateTimeImmutable();
        $this->deletedBy = $deletedBy;

        return $this;
    }

    /**
     * Restore a soft-deleted record (undo delete)
     */
    public function restore(): self
    {
        $this->deletedAt = null;
        $this->deletedBy = null;

        return $this;
    }

    /**
     * Get when this record was deleted
     */
    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    /**
     * Get who deleted this record
     */
    public function getDeletedBy(): ?User
    {
        return $this->deletedBy;
    }

    /**
     * Manually set deletedAt (for migrations or special cases)
     *
     * @internal Use softDelete() in normal code
     */
    public function setDeletedAt(?\DateTimeImmutable $deletedAt): self
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    /**
     * Manually set deletedBy (for migrations or special cases)
     *
     * @internal Use softDelete() in normal code
     */
    public function setDeletedBy(?User $deletedBy): self
    {
        $this->deletedBy = $deletedBy;
        return $this;
    }
}
