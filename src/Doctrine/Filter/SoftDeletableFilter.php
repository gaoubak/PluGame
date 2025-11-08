<?php

namespace App\Doctrine\Filter;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Doctrine SQL Filter for Soft Deletes
 *
 * Automatically excludes soft-deleted records from all queries.
 * This filter is applied globally and can be disabled when needed
 * (e.g., in admin interfaces to show deleted records).
 *
 * When enabled, all queries automatically include: WHERE deleted_at IS NULL
 */
class SoftDeletableFilter extends SQLFilter
{
    /**
     * Add WHERE deleted_at IS NULL to all queries on entities with soft delete
     */
    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias): string
    {
        // Check if entity has the deletedAt field
        if (!$targetEntity->hasField('deletedAt')) {
            return '';
        }

        // Get the column name for deletedAt
        $column = $targetEntity->getColumnName('deletedAt');

        // Return SQL condition: deleted_at IS NULL
        return sprintf('%s.%s IS NULL', $targetTableAlias, $column);
    }
}
