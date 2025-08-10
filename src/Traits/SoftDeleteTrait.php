<?php

namespace WelshDev\Doctrix\Traits;

use Doctrine\ORM\QueryBuilder;

/**
 * Trait for adding soft delete functionality to repositories
 *
 * Automatically filters out soft-deleted records unless explicitly included
 */
trait SoftDeleteTrait
{
    /**
     * The soft delete field name
     *
     * @var string
     */
    protected string $softDeleteField = 'deletedAt';

    /**
     * Whether to include soft-deleted records by default
     *
     * @var bool
     */
    protected bool $includeSoftDeleted = false;

    /**
     * Fetch entities excluding soft-deleted ones
     *
     * @param array $criteria The search criteria
     * @param array|null $orderBy Optional ordering
     * @param int|null $limit Optional limit
     * @param int|null $offset Optional offset
     * @return array The results
     */
    public function fetchWithoutDeleted(
        array $criteria = [],
        ?array $orderBy = null,
        ?int $limit = null,
        ?int $offset = null,
    ): array {
        $this->excludeSoftDeleted();
        $results = $this->fetch($criteria, $orderBy, $limit, $offset);
        $this->resetSoftDeleteFilter();

        return $results;
    }

    /**
     * Fetch entities including soft-deleted ones
     *
     * @param array $criteria The search criteria
     * @param array|null $orderBy Optional ordering
     * @param int|null $limit Optional limit
     * @param int|null $offset Optional offset
     * @return array The results
     */
    public function fetchWithDeleted(
        array $criteria = [],
        ?array $orderBy = null,
        ?int $limit = null,
        ?int $offset = null,
    ): array {
        $this->includeSoftDeletedRecords();
        $results = $this->fetch($criteria, $orderBy, $limit, $offset);
        $this->resetSoftDeleteFilter();

        return $results;
    }

    /**
     * Fetch only soft-deleted entities
     *
     * @param array $criteria The search criteria
     * @param array|null $orderBy Optional ordering
     * @param int|null $limit Optional limit
     * @param int|null $offset Optional offset
     * @return array The results
     */
    public function fetchOnlyDeleted(
        array $criteria = [],
        ?array $orderBy = null,
        ?int $limit = null,
        ?int $offset = null,
    ): array {
        // Add condition for only deleted records
        $criteria[] = [$this->softDeleteField, 'is_not_null', true];

        $this->includeSoftDeletedRecords();
        $results = $this->fetch($criteria, $orderBy, $limit, $offset);
        $this->resetSoftDeleteFilter();

        return $results;
    }

    /**
     * Temporarily exclude soft-deleted records
     *
     * @return self
     */
    public function excludeSoftDeleted(): self
    {
        $this->includeSoftDeleted = false;

        // Add filter function
        $this->addFilterFunction(function (QueryBuilder $qb)
        {
            $this->applySoftDeleteFilter($qb);

            return $qb;
        });

        return $this;
    }

    /**
     * Temporarily include soft-deleted records
     *
     * @return self
     */
    public function includeSoftDeletedRecords(): self
    {
        $this->includeSoftDeleted = true;

        return $this;
    }

    /**
     * Reset soft delete filter to default
     *
     * @return self
     */
    public function resetSoftDeleteFilter(): self
    {
        $this->includeSoftDeleted = false;

        return $this;
    }

    /**
     * Set the soft delete field name
     *
     * @param string $fieldName The field name
     * @return void
     */
    public function setSoftDeleteField(string $fieldName): void
    {
        $this->softDeleteField = $fieldName;
    }

    /**
     * Get the soft delete field name
     *
     * @return string
     */
    public function getSoftDeleteField(): string
    {
        return $this->softDeleteField;
    }

    /**
     * Count non-deleted entities
     *
     * @param array $criteria The search criteria
     * @return int The count
     */
    public function countWithoutDeleted(array $criteria = []): int
    {
        $this->excludeSoftDeleted();
        $count = $this->count($criteria);
        $this->resetSoftDeleteFilter();

        return $count;
    }

    /**
     * Count only deleted entities
     *
     * @param array $criteria The search criteria
     * @return int The count
     */
    public function countOnlyDeleted(array $criteria = []): int
    {
        $criteria[] = [$this->softDeleteField, 'is_not_null', true];

        $this->includeSoftDeletedRecords();
        $count = $this->count($criteria);
        $this->resetSoftDeleteFilter();

        return $count;
    }

    /**
     * Apply soft delete filter to query builder
     *
     * @param QueryBuilder $qb The query builder
     * @return void
     */
    protected function applySoftDeleteFilter(QueryBuilder $qb): void
    {
        if (!$this->includeSoftDeleted && $this->hasSoftDeleteField())
        {
            $alias = $this->getAlias();
            $field = $alias . '.' . $this->softDeleteField;

            $qb->andWhere($field . ' IS NULL');
        }
    }

    /**
     * Check if the entity has a soft delete field
     *
     * @return bool
     */
    protected function hasSoftDeleteField(): bool
    {
        // This is a simple check - could be enhanced to actually
        // check the entity metadata
        return !empty($this->softDeleteField);
    }
}
