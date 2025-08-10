<?php

namespace WelshDev\Doctrix\Service;

use BadMethodCallException;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder as DoctrineQueryBuilder;
use WelshDev\Doctrix\Traits\CacheableTrait;
use WelshDev\Doctrix\Traits\EnhancedQueryTrait;
use WelshDev\Doctrix\Traits\GlobalScopesTrait;
use WelshDev\Doctrix\Traits\PaginationTrait;
use WelshDev\Doctrix\Traits\SoftDeleteTrait;

/**
 * Enhanced query builder wrapper for any Doctrine repository
 *
 * Provides all enhanced query features without requiring inheritance
 * This allows using enhanced queries with any existing repository
 */
class EnhancedQueryBuilder
{
    use EnhancedQueryTrait;
    use PaginationTrait;
    use CacheableTrait;
    use SoftDeleteTrait;
    use GlobalScopesTrait;

    /**
     * The wrapped repository
     *
     * @var EntityRepository
     */
    private EntityRepository $repository;

    /**
     * The entity class name
     *
     * @var string
     */
    private string $entityClass;

    /**
     * Constructor
     *
     * @param EntityRepository $repository The repository to enhance
     * @param string|null $alias Optional custom alias
     * @param array $joins Optional join configuration
     */
    public function __construct(
        EntityRepository $repository,
        ?string $alias = null,
        array $joins = [],
    ) {
        $this->repository = $repository;
        $this->entityClass = $repository->getClassName();
        $this->joins = $joins;

        // Auto-generate alias if not provided
        if ($alias === null)
        {
            $parts = explode('\\', $this->entityClass);
            $className = end($parts);
            $alias = strtolower(substr($className, 0, 2));
        }

        $this->alias = $alias;
    }

    /**
     * Proxy undefined methods to the repository
     * This allows using native repository methods
     *
     * @param string $method
     * @param array $arguments
     * @return mixed
     */
    public function __call(string $method, array $arguments): mixed
    {
        if (method_exists($this->repository, $method))
        {
            return $this->repository->$method(...$arguments);
        }

        throw new BadMethodCallException(
            sprintf('Method "%s" does not exist on %s or the wrapped repository', $method, static::class),
        );
    }

    /**
     * Clone handler to ensure proper object copying
     */
    public function __clone()
    {
        // Reset parser instances to avoid sharing state
        $this->criteriaParser = null;
        $this->filterChain = null;
        $this->joinManager = null;
        $this->filterFunctions = [];
    }

    /**
     * Create a query builder
     * Required by EnhancedQueryTrait
     *
     * @param string $alias The entity alias
     * @return DoctrineQueryBuilder
     */
    public function createQueryBuilder(string $alias): DoctrineQueryBuilder
    {
        return $this->repository->createQueryBuilder($alias);
    }

    /**
     * Get the wrapped repository
     *
     * @return EntityRepository
     */
    public function getRepository(): EntityRepository
    {
        return $this->repository;
    }

    /**
     * Get the entity class
     *
     * @return string
     */
    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    /**
     * Configure joins for this instance
     *
     * @param array $joins Join configuration
     * @return self
     */
    public function withJoins(array $joins): self
    {
        $clone = clone $this;
        $clone->joins = $joins;

        return $clone;
    }

    /**
     * Add a single join
     *
     * @param string $type Join type (leftJoin, innerJoin, etc.)
     * @param string $relation The relation to join
     * @param string $alias The alias for the joined entity
     * @return self
     */
    public function addJoin(string $type, string $relation, string $alias): self
    {
        $clone = clone $this;
        $clone->joins[] = [$type, $relation, $alias];

        return $clone;
    }
}
