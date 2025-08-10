<?php

namespace WelshDev\Doctrix\Traits;

use Doctrine\ORM\Query;

/**
 * Trait for adding caching capabilities to repositories
 *
 * Provides methods for caching query results with configurable
 * cache keys and lifetimes
 */
trait CacheableTrait
{
    /**
     * Default cache lifetime in seconds
     *
     * @var int
     */
    protected int $defaultCacheLifetime = 3600;

    /**
     * Cache key prefix for this repository
     *
     * @var string|null
     */
    protected ?string $cacheKeyPrefix = null;

    /**
     * Fetch entities with caching
     *
     * @param array $criteria The search criteria
     * @param array|null $orderBy Optional ordering
     * @param int|null $limit Optional limit
     * @param int|null $offset Optional offset
     * @param int|null $cacheLifetime Cache lifetime in seconds
     * @return array The cached results
     */
    public function fetchCached(
        array $criteria = [],
        ?array $orderBy = null,
        ?int $limit = null,
        ?int $offset = null,
        ?int $cacheLifetime = null,
    ): array {
        $qb = $this->buildQuery($criteria, $orderBy);

        if ($limit !== null)
        {
            $qb->setMaxResults($limit);
        }

        if ($offset !== null)
        {
            $qb->setFirstResult($offset);
        }

        $query = $qb->getQuery();
        $this->configureCaching($query, $criteria, $cacheLifetime);

        return $query->getResult();
    }

    /**
     * Fetch a single entity with caching
     *
     * @param array $criteria The search criteria
     * @param array|null $orderBy Optional ordering
     * @param int|null $cacheLifetime Cache lifetime in seconds
     * @return object|null The cached result or null
     */
    public function fetchOneCached(
        array $criteria = [],
        ?array $orderBy = null,
        ?int $cacheLifetime = null,
    ): ?object {
        $qb = $this->buildQuery($criteria, $orderBy);
        $qb->setMaxResults(1);

        $query = $qb->getQuery();
        $this->configureCaching($query, $criteria, $cacheLifetime);

        return $query->getOneOrNullResult();
    }

    /**
     * Set the default cache lifetime
     *
     * @param int $seconds The lifetime in seconds
     * @return void
     */
    public function setDefaultCacheLifetime(int $seconds): void
    {
        $this->defaultCacheLifetime = $seconds;
    }

    /**
     * Clear cache for specific criteria
     *
     * @param array $criteria The criteria
     * @return void
     */
    public function clearCache(array $criteria = []): void
    {
        // This would require access to the cache implementation
        // For now, this is a placeholder that could be implemented
        // based on the specific cache configuration
    }

    /**
     * Configure caching for a query
     *
     * @param Query $query The query to configure
     * @param array $criteria The criteria used (for cache key generation)
     * @param int|null $lifetime Cache lifetime in seconds
     * @return void
     */
    protected function configureCaching(Query $query, array $criteria, ?int $lifetime = null): void
    {
        $cacheKey = $this->generateCacheKey($criteria);
        $lifetime = $lifetime ?? $this->defaultCacheLifetime;

        $query->enableResultCache($lifetime, $cacheKey);
    }

    /**
     * Generate a cache key based on criteria
     *
     * @param array $criteria The criteria
     * @return string The cache key
     */
    protected function generateCacheKey(array $criteria): string
    {
        $prefix = $this->getCacheKeyPrefix();
        $hash = md5(serialize($criteria));

        return $prefix . '_' . $hash;
    }

    /**
     * Get the cache key prefix for this repository
     *
     * @return string The prefix
     */
    protected function getCacheKeyPrefix(): string
    {
        if ($this->cacheKeyPrefix === null)
        {
            // Generate from class name
            $className = static::class;
            $parts = explode('\\', $className);
            $this->cacheKeyPrefix = strtolower(end($parts));
        }

        return $this->cacheKeyPrefix;
    }
}
