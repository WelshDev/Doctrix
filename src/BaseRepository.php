<?php

namespace WelshDev\Doctrix;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use WelshDev\Doctrix\Traits\BulkOperationsTrait;
use WelshDev\Doctrix\Traits\ChunkProcessingTrait;
use WelshDev\Doctrix\Traits\EnhancedQueryTrait;
use WelshDev\Doctrix\Traits\ExistenceCheckTrait;
use WelshDev\Doctrix\Traits\FetchOrFailTrait;
use WelshDev\Doctrix\Traits\MacroableTrait;
use WelshDev\Doctrix\Traits\PaginationTrait;
use WelshDev\Doctrix\Traits\PersistentFiltersTrait;
use WelshDev\Doctrix\Traits\RandomSelectionTrait;
use WelshDev\Doctrix\Traits\RelationshipCheckTrait;
use WelshDev\Doctrix\Traits\RequestQueryTrait;
use WelshDev\Doctrix\Traits\ValidationTrait;

/**
 * Enhanced base repository using traits for all functionality
 *
 * This repository provides:
 * - Enhanced query methods (fetch, fetchOne, fetchAll, count)
 * - Fluent query interface
 * - Pagination support
 * - Extensible operator system
 * - Automatic join management
 * - Filter functions and global scopes
 * - Request-based query building with validation
 * - Bulk operations (bulkUpdate, bulkDelete)
 * - Fetch or fail/create patterns
 * - Memory-efficient chunk processing
 * - Existence checks without fetching entities
 * - Random entity selection
 * - Relationship checking and eager loading
 * - Data validation and uniqueness checks
 * - Duplicate detection and removal
 * - Custom macro support
 *
 * Child repositories can also use additional traits like:
 * - CacheableTrait for query caching
 * - SoftDeleteTrait for soft delete handling
 * - GlobalScopesTrait for global query scopes
 *
 * @template T of object
 * @extends ServiceEntityRepository<T>
 */
abstract class BaseRepository extends ServiceEntityRepository
{
    use EnhancedQueryTrait {
        EnhancedQueryTrait::buildQuery as enhancedBuildQuery;
    }
    use PersistentFiltersTrait;
    use PaginationTrait;
    use BulkOperationsTrait;
    use MacroableTrait {
        MacroableTrait::__call as macroCall;
    }
    use RequestQueryTrait;
    use FetchOrFailTrait;
    use ChunkProcessingTrait;
    use ExistenceCheckTrait;
    use RandomSelectionTrait;
    use RelationshipCheckTrait;
    use ValidationTrait;

    /**
     * Constructor
     *
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        // Determine entity class from repository class name
        $entityClass = $this->getEntityClassName();
        parent::__construct($registry, $entityClass);

        // Initialize alias if not set
        if (empty($this->alias))
        {
            $this->alias = strtolower(substr($this->getEntityShortName(), 0, 2));
        }
    }

    /**
     * Handle dynamic method calls for macros
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters): mixed
    {
        // Check if it's a macro
        if (static::hasMacro($method))
        {
            return $this->macroCall($method, $parameters);
        }

        // Otherwise, delegate to parent (ServiceEntityRepository)
        return parent::__call($method, $parameters);
    }

    /**
     * Build a query with both standard and persistent filters
     *
     * @param array $criteria
     * @param array|null $orderBy
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function buildQuery(array $criteria = [], ?array $orderBy = null): \Doctrine\ORM\QueryBuilder
    {
        // Use the enhanced build query
        $qb = $this->enhancedBuildQuery($criteria, $orderBy);

        // Apply persistent filters if any exist
        if (!empty($this->persistentFilters))
        {
            $this->applyPersistentFilters($qb);
        }

        return $qb;
    }

    /**
     * Get the entity class name (alias for Doctrine compatibility)
     *
     * @return string The entity class name
     */
    public function getClassName(): string
    {
        return $this->getEntityName();
    }

    /**
     * Define relationship mappings for dotted criteria keys
     * Override in child repositories to provide mappings
     *
     * @return array<string, array{entityClass: class-string, description: string}>
     */
    public function getRelationshipMappings(): array
    {
        return [];
    }

    /**
     * Get the entity class name from repository name
     * Converts App\Repository\Users\UserRepository to App\Entity\Users\User
     *
     * @return string The entity class name
     */
    protected function getEntityClassName(): string
    {
        $className = static::class;

        // Replace Repository namespace with Entity
        $className = str_replace('\Repository\\', '\Entity\\', $className);

        // Remove Repository suffix
        if (str_ends_with($className, 'Repository'))
        {
            $className = substr($className, 0, -10);
        }

        return $className;
    }

    /**
     * Get the short class name for the entity
     *
     * @return string The short class name
     */
    protected function getEntityShortName(): string
    {
        $parts = explode('\\', $this->getEntityClassName());

        return end($parts);
    }

    /**
     * Define named filters for this repository
     * Override in child repositories to provide reusable filters
     *
     * @return array<string, callable>
     */
    protected function defineFilters(): array
    {
        return [];
    }

    /**
     * Define global scopes that are automatically applied to all queries
     * Override in child repositories to provide global filters
     *
     * @return array<string, callable>
     */
    protected function globalScopes(): array
    {
        return [];
    }
}
