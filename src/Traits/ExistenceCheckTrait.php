<?php

namespace WelshDev\Doctrix\Traits;

/**
 * Trait for checking entity existence
 *
 * Provides methods for efficiently checking if entities exist
 * without fetching full entity data
 */
trait ExistenceCheckTrait
{
    /**
     * Check if any entities exist matching criteria
     *
     * @param array $criteria Search criteria
     * @return bool
     *
     * @example
     * if ($repo->exists(['status' => 'active'])) {
     *     // Has active records
     * }
     */
    public function exists(array $criteria = []): bool
    {
        $qb = $this->buildQuery($criteria);
        $qb->select('1');
        $qb->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult() !== null;
    }

    /**
     * Check if no entities exist matching criteria
     *
     * @param array $criteria Search criteria
     * @return bool
     *
     * @example
     * if ($repo->doesntExist(['email' => $email])) {
     *     // Email is available
     * }
     */
    public function doesntExist(array $criteria = []): bool
    {
        return !$this->exists($criteria);
    }

    /**
     * Check if exactly the specified number of entities exist
     *
     * @param int $count Expected count
     * @param array $criteria Search criteria
     * @return bool
     *
     * @example
     * if ($repo->hasExactly(5, ['status' => 'pending'])) {
     *     // Exactly 5 pending items
     * }
     */
    public function hasExactly(int $count, array $criteria = []): bool
    {
        return $this->count($criteria) === $count;
    }

    /**
     * Check if at least the specified number of entities exist
     *
     * @param int $minimum Minimum count
     * @param array $criteria Search criteria
     * @return bool
     *
     * @example
     * if ($repo->hasAtLeast(2, ['role' => 'moderator'])) {
     *     // Has at least 2 moderators
     * }
     */
    public function hasAtLeast(int $minimum, array $criteria = []): bool
    {
        // Optimize by limiting count query
        $qb = $this->buildQuery($criteria);
        $qb->select('COUNT(DISTINCT ' . $this->getAlias() . '.id)');
        $qb->setMaxResults($minimum + 1);

        $count = (int) $qb->getQuery()->getSingleScalarResult();

        return $count >= $minimum;
    }

    /**
     * Check if at most the specified number of entities exist
     *
     * @param int $maximum Maximum count
     * @param array $criteria Search criteria
     * @return bool
     *
     * @example
     * if ($repo->hasAtMost(10, ['failed_attempts' => ['gte', 3]])) {
     *     // No more than 10 users with 3+ failed attempts
     * }
     */
    public function hasAtMost(int $maximum, array $criteria = []): bool
    {
        // Optimize by limiting count query
        $qb = $this->buildQuery($criteria);
        $qb->select('COUNT(DISTINCT ' . $this->getAlias() . '.id)');
        $qb->setMaxResults($maximum + 1);

        $count = (int) $qb->getQuery()->getSingleScalarResult();

        return $count <= $maximum;
    }

    /**
     * Check if count is between min and max (inclusive)
     *
     * @param int $min Minimum count
     * @param int $max Maximum count
     * @param array $criteria Search criteria
     * @return bool
     *
     * @example
     * if ($repo->hasBetween(5, 10, ['priority' => 'high'])) {
     *     // Has between 5 and 10 high priority items
     * }
     */
    public function hasBetween(int $min, int $max, array $criteria = []): bool
    {
        $count = $this->count($criteria);

        return $count >= $min && $count <= $max;
    }

    /**
     * Check if any entities exist (alias for exists with no criteria)
     *
     * @return bool
     *
     * @example
     * if ($repo->hasAny()) {
     *     // Table is not empty
     * }
     */
    public function hasAny(): bool
    {
        return $this->exists();
    }

    /**
     * Check if no entities exist (alias for doesntExist with no criteria)
     *
     * @return bool
     *
     * @example
     * if ($repo->isEmpty()) {
     *     // Table is empty
     * }
     */
    public function isEmpty(): bool
    {
        return $this->doesntExist();
    }

    /**
     * Check if only one entity exists matching criteria
     *
     * @param array $criteria Search criteria
     * @return bool
     *
     * @example
     * if ($repo->hasOne(['role' => 'super_admin'])) {
     *     // Exactly one super admin exists
     * }
     */
    public function hasOne(array $criteria = []): bool
    {
        return $this->hasExactly(1, $criteria);
    }

    /**
     * Check if multiple entities exist matching criteria
     *
     * @param array $criteria Search criteria
     * @return bool
     *
     * @example
     * if ($repo->hasMany(['status' => 'active'])) {
     *     // More than one active entity exists
     * }
     */
    public function hasMany(array $criteria = []): bool
    {
        return $this->count($criteria) > 1;
    }

    /**
     * Get count with optional limit for performance
     *
     * @param array $criteria Search criteria
     * @param int|null $limit Stop counting at this number
     * @return int
     *
     * @example
     * // Stop counting after 1000 for performance
     * $hasMany = $repo->countUpTo(['status' => 'active'], 1000) >= 1000;
     */
    public function countUpTo(array $criteria = [], ?int $limit = null): int
    {
        $qb = $this->buildQuery($criteria);
        $qb->select('COUNT(DISTINCT ' . $this->getAlias() . '.id)');

        if ($limit !== null)
        {
            $qb->setMaxResults($limit);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Check existence with callback for complex conditions
     *
     * @param callable $callback Callback that receives query builder
     * @return bool
     *
     * @example
     * $exists = $repo->existsWhere(function($qb) {
     *     $qb->where('status = :status')
     *        ->andWhere('created > :date')
     *        ->setParameter('status', 'active')
     *        ->setParameter('date', new DateTime('-30 days'));
     * });
     */
    public function existsWhere(callable $callback): bool
    {
        $qb = $this->createQueryBuilder($this->getAlias());
        $callback($qb);
        $qb->select('1');
        $qb->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult() !== null;
    }
}
