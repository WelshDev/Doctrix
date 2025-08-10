<?php

namespace WelshDev\Doctrix\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

/**
 * Service for creating enhanced query builders
 *
 * This service can be injected into controllers or other services
 * to provide enhanced query capabilities without requiring repository inheritance
 */
class QueryBuilderService
{
    /**
     * The entity manager
     *
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * Cache of enhanced builders for reuse
     *
     * @var array<string, EnhancedQueryBuilder>
     */
    private array $builders = [];

    /**
     * Constructor
     *
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Create an enhanced query builder for a repository
     *
     * @param EntityRepository|class-string $repositoryOrEntity Repository instance or entity class name
     * @param string|null $alias Optional custom alias
     * @param array $joins Optional join configuration
     * @return EnhancedQueryBuilder
     */
    public function enhance(
        EntityRepository|string $repositoryOrEntity,
        ?string $alias = null,
        array $joins = [],
    ): EnhancedQueryBuilder {
        // If entity class name provided, get its repository
        if (is_string($repositoryOrEntity))
        {
            $repository = $this->entityManager->getRepository($repositoryOrEntity);
        }
        else
        {
            $repository = $repositoryOrEntity;
        }

        return new EnhancedQueryBuilder($repository, $alias, $joins);
    }

    /**
     * Create an enhanced query builder for an entity class
     * Convenience method that accepts entity class directly
     *
     * @param class-string $entityClass The entity class name
     * @param string|null $alias Optional custom alias
     * @param array $joins Optional join configuration
     * @return EnhancedQueryBuilder
     */
    public function for(string $entityClass, ?string $alias = null, array $joins = []): EnhancedQueryBuilder
    {
        return $this->enhance($entityClass, $alias, $joins);
    }

    /**
     * Get or create a cached enhanced builder for an entity
     * Useful when the same enhanced builder is used multiple times
     *
     * @param class-string $entityClass The entity class name
     * @param string|null $alias Optional custom alias
     * @return EnhancedQueryBuilder
     */
    public function cached(string $entityClass, ?string $alias = null): EnhancedQueryBuilder
    {
        $key = $entityClass . '_' . ($alias ?? 'default');

        if (!isset($this->builders[$key]))
        {
            $this->builders[$key] = $this->for($entityClass, $alias);
        }

        // Return a clone to avoid state pollution between uses
        return clone $this->builders[$key];
    }

    /**
     * Clear the builder cache
     *
     * @return void
     */
    public function clearCache(): void
    {
        $this->builders = [];
    }

    /**
     * Create a query directly without enhanced features
     * Useful for simple queries that don't need enhancement
     *
     * @param class-string $entityClass The entity class name
     * @param string|null $alias Optional alias
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function simple(string $entityClass, ?string $alias = null): \Doctrine\ORM\QueryBuilder
    {
        $repository = $this->entityManager->getRepository($entityClass);

        if ($alias === null)
        {
            $parts = explode('\\', $entityClass);
            $className = end($parts);
            $alias = strtolower(substr($className, 0, 2));
        }

        return $repository->createQueryBuilder($alias);
    }
}
