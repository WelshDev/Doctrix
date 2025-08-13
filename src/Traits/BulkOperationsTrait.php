<?php

namespace WelshDev\Doctrix\Traits;

use InvalidArgumentException;

/**
 * Trait for bulk database operations
 *
 * Provides methods for efficient bulk updates and deletes without fetching entities
 */
trait BulkOperationsTrait
{
    /**
     * Perform a bulk update on entities matching criteria
     *
     * @param array $updates The fields to update and their values
     * @param array $criteria The criteria to match entities
     * @param array $orderBy Optional ordering
     * @param int|null $limit Optional limit on number of records to update
     * @return int The number of affected rows
     *
     * @example
     * // Set all inactive users to archived
     * $repo->bulkUpdate(
     *     ['status' => 'archived', 'archivedAt' => new \DateTime()],
     *     ['status' => 'inactive', ['lastLogin', 'lt', '-6 months']]
     * );
     */
    public function bulkUpdate(array $updates, array $criteria = [], array $orderBy = [], ?int $limit = null): int
    {
        $qb = $this->createQueryBuilder($this->getAlias());
        $qb->update();

        // Apply criteria
        if (!empty($criteria))
        {
            $this->applyCriteria($qb, $criteria);
        }

        // Set update values
        $paramIndex = 0;
        foreach ($updates as $field => $value)
        {
            $paramName = 'update_param_' . $paramIndex++;

            // Handle field with alias
            $fieldWithAlias = strpos($field, '.') !== false ? $field : $this->getAlias() . '.' . $field;

            if ($value === null)
            {
                $qb->set($fieldWithAlias, 'NULL');
            }
            else
            {
                $qb->set($fieldWithAlias, ':' . $paramName);
                $qb->setParameter($paramName, $value);
            }
        }

        // Note: DQL UPDATE doesn't support ORDER BY or LIMIT directly
        // If limit is needed, we need to use a subquery approach
        if ($limit !== null)
        {
            return $this->bulkUpdateWithLimit($updates, $criteria, $orderBy, $limit);
        }

        return $qb->getQuery()->execute();
    }

    /**
     * Perform a bulk delete on entities matching criteria
     *
     * @param array $criteria The criteria to match entities for deletion
     * @param array $orderBy Optional ordering (for limit-based deletes)
     * @param int|null $limit Optional limit on number of records to delete
     * @return int The number of deleted rows
     *
     * @example
     * // Delete all expired sessions
     * $repo->bulkDelete([['expiresAt', 'lt', new \DateTime()]]);
     *
     * // Delete oldest 100 logs
     * $repo->bulkDelete(['type' => 'debug'], ['createdAt' => 'ASC'], 100);
     */
    public function bulkDelete(array $criteria = [], array $orderBy = [], ?int $limit = null): int
    {
        $qb = $this->createQueryBuilder($this->getAlias());
        $qb->delete();

        // Apply criteria
        if (!empty($criteria))
        {
            $this->applyCriteria($qb, $criteria);
        }

        // Note: DQL DELETE doesn't support ORDER BY or LIMIT directly
        // If limit is needed, we need to use a different approach
        if ($limit !== null)
        {
            return $this->bulkDeleteWithLimit($criteria, $orderBy, $limit);
        }

        return $qb->getQuery()->execute();
    }

    /**
     * Count entities matching criteria (useful before bulk operations)
     *
     * @param array $criteria The criteria to match entities
     * @return int The count of matching entities
     *
     * @example
     * $count = $repo->countMatching(['status' => 'pending']);
     * if ($count > 1000) {
     *     // Process in batches
     * }
     */
    public function countMatching(array $criteria = []): int
    {
        $qb = $this->createQueryBuilder($this->getAlias());
        $qb->select('COUNT(DISTINCT ' . $this->getAlias() . '.id)');

        if (!empty($criteria))
        {
            $this->applyCriteria($qb, $criteria);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Perform a bulk operation in batches to avoid memory issues
     *
     * @param string $operation 'update' or 'delete'
     * @param array $criteria The criteria
     * @param array $updates Updates for update operation
     * @param int $batchSize Size of each batch
     * @return int Total affected rows
     *
     * @example
     * // Update large dataset in batches
     * $affected = $repo->bulkBatch('update', ['status' => 'pending'], ['processed' => true], 500);
     */
    public function bulkBatch(string $operation, array $criteria = [], array $updates = [], int $batchSize = 1000): int
    {
        $totalAffected = 0;
        $offset = 0;

        do
        {
            if ($operation === 'update')
            {
                $affected = $this->bulkUpdateWithLimit($updates, $criteria, [], $batchSize);
            }
            elseif ($operation === 'delete')
            {
                $affected = $this->bulkDeleteWithLimit($criteria, [], $batchSize);
            }
            else
            {
                throw new InvalidArgumentException("Invalid operation: $operation. Use 'update' or 'delete'.");
            }

            $totalAffected += $affected;
            $offset += $batchSize;

            // If we affected fewer rows than batch size, we're done
        }
        while ($affected === $batchSize);

        return $totalAffected;
    }

    /**
     * Conditional bulk update - only update if condition is met
     *
     * @param array $updates Updates to apply
     * @param array $criteria Selection criteria
     * @param callable $condition Function to check before updating
     * @return int Number of affected rows
     *
     * @example
     * $repo->conditionalBulkUpdate(
     *     ['status' => 'archived'],
     *     ['status' => 'inactive'],
     *     fn($count) => $count < 100  // Only archive if less than 100 records
     * );
     */
    public function conditionalBulkUpdate(array $updates, array $criteria, callable $condition): int
    {
        $count = $this->countMatching($criteria);

        if ($condition($count))
        {
            return $this->bulkUpdate($updates, $criteria);
        }

        return 0;
    }

    /**
     * Perform a safe bulk delete with confirmation
     *
     * @param array $criteria The criteria
     * @param bool $dryRun If true, only return count without deleting
     * @return array ['count' => int, 'deleted' => int]
     *
     * @example
     * $result = $repo->safeBulkDelete(['status' => 'expired'], true); // Dry run
     * echo "Would delete {$result['count']} records\n";
     *
     * $result = $repo->safeBulkDelete(['status' => 'expired'], false); // Actually delete
     * echo "Deleted {$result['deleted']} records\n";
     */
    public function safeBulkDelete(array $criteria, bool $dryRun = true): array
    {
        $count = $this->countMatching($criteria);

        $result = [
            'count' => $count,
            'deleted' => 0,
        ];

        if (!$dryRun && $count > 0)
        {
            $result['deleted'] = $this->bulkDelete($criteria);
        }

        return $result;
    }

    /**
     * Perform a bulk update with limit (using subquery)
     *
     * @param array $updates The fields to update
     * @param array $criteria The criteria
     * @param array $orderBy The ordering
     * @param int $limit The limit
     * @return int Number of affected rows
     */
    protected function bulkUpdateWithLimit(array $updates, array $criteria, array $orderBy, int $limit): int
    {
        // First, get the IDs of entities to update
        $qb = $this->createQueryBuilder($this->getAlias());
        $qb->select($this->getAlias() . '.id');

        if (!empty($criteria))
        {
            $this->applyCriteria($qb, $criteria);
        }

        if (!empty($orderBy))
        {
            $this->applyOrderBy($qb, $orderBy);
        }

        $qb->setMaxResults($limit);

        $ids = array_column($qb->getQuery()->getArrayResult(), 'id');

        if (empty($ids))
        {
            return 0;
        }

        // Now perform the update on these specific IDs
        $updateQb = $this->createQueryBuilder($this->getAlias());
        $updateQb->update();

        $paramIndex = 0;
        foreach ($updates as $field => $value)
        {
            $paramName = 'update_param_' . $paramIndex++;
            $fieldWithAlias = strpos($field, '.') !== false ? $field : $this->getAlias() . '.' . $field;

            if ($value === null)
            {
                $updateQb->set($fieldWithAlias, 'NULL');
            }
            else
            {
                $updateQb->set($fieldWithAlias, ':' . $paramName);
                $updateQb->setParameter($paramName, $value);
            }
        }

        $updateQb->where($updateQb->expr()->in($this->getAlias() . '.id', ':ids'));
        $updateQb->setParameter('ids', $ids);

        return $updateQb->getQuery()->execute();
    }

    /**
     * Perform a bulk delete with limit (using subquery)
     *
     * @param array $criteria The criteria
     * @param array $orderBy The ordering
     * @param int $limit The limit
     * @return int Number of deleted rows
     */
    protected function bulkDeleteWithLimit(array $criteria, array $orderBy, int $limit): int
    {
        // First, get the IDs of entities to delete
        $qb = $this->createQueryBuilder($this->getAlias());
        $qb->select($this->getAlias() . '.id');

        if (!empty($criteria))
        {
            $this->applyCriteria($qb, $criteria);
        }

        if (!empty($orderBy))
        {
            $this->applyOrderBy($qb, $orderBy);
        }

        $qb->setMaxResults($limit);

        $ids = array_column($qb->getQuery()->getArrayResult(), 'id');

        if (empty($ids))
        {
            return 0;
        }

        // Now perform the delete on these specific IDs
        $deleteQb = $this->createQueryBuilder($this->getAlias());
        $deleteQb->delete();
        $deleteQb->where($deleteQb->expr()->in($this->getAlias() . '.id', ':ids'));
        $deleteQb->setParameter('ids', $ids);

        return $deleteQb->getQuery()->execute();
    }
}
