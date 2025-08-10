<?php

namespace WelshDev\Doctrix\Traits;

use Doctrine\ORM\QueryBuilder;

/**
 * Trait providing persistent filter support for repositories
 *
 * Filters registered through this trait persist across multiple query operations
 * including paginate() which internally calls count() and fetch()
 */
trait PersistentFiltersTrait
{
    /**
     * Persistent filters that survive across query operations
     * Initialize to empty array to avoid undefined property errors
     *
     * @var array<string, mixed>
     */
    protected array $persistentFilters = [];

    /**
     * Clone handler
     */
    public function __clone()
    {
        // Arrays are already copied by value during cloning
        // Just call parent clone if it exists
        if (method_exists(parent::class, '__clone'))
        {
            parent::__clone();
        }
    }

    /**
     * Register a persistent filter
     *
     * @param string $name Filter name
     * @param mixed $value Filter value
     * @return static For method chaining
     */
    public function withFilter(string $name, mixed $value): static
    {
        $clone = clone $this;
        $clone->persistentFilters[$name] = $value;

        return $clone;
    }

    /**
     * Register multiple persistent filters
     *
     * @param array<string, mixed> $filters
     * @return static For method chaining
     */
    public function withFilters(array $filters): static
    {
        $clone = clone $this;
        $clone->persistentFilters = array_merge($clone->persistentFilters, $filters);

        return $clone;
    }

    /**
     * Remove a persistent filter
     *
     * @param string $name Filter name
     * @return static For method chaining
     */
    public function withoutFilter(string $name): static
    {
        $clone = clone $this;
        unset($clone->persistentFilters[$name]);

        return $clone;
    }

    /**
     * Clear all persistent filters
     *
     * @return static For method chaining
     */
    public function withoutFilters(): static
    {
        $clone = clone $this;
        $clone->persistentFilters = [];

        return $clone;
    }

    /**
     * Get current persistent filters
     *
     * @return array<string, mixed>
     */
    public function getFilters(): array
    {
        return $this->persistentFilters;
    }

    /**
     * Check if a filter is set
     *
     * @param string $name Filter name
     * @return bool
     */
    public function hasFilter(string $name): bool
    {
        return isset($this->persistentFilters[$name]);
    }

    /**
     * Get a filter value
     *
     * @param string $name Filter name
     * @param mixed $default Default value if not set
     * @return mixed
     */
    public function getFilter(string $name, mixed $default = null): mixed
    {
        return $this->persistentFilters[$name] ?? $default;
    }

    /**
     * Apply persistent filters to query builder
     * Should be called in buildQuery() after parent::buildQuery()
     *
     * @param QueryBuilder $qb
     * @return void
     */
    protected function applyPersistentFilters(QueryBuilder $qb): void
    {
        // Let child classes define how to apply each filter
        foreach ($this->persistentFilters as $name => $value)
        {
            $methodName = 'apply' . ucfirst($name) . 'Filter';

            if (method_exists($this, $methodName))
            {
                $this->$methodName($qb, $value);
            }
            elseif (method_exists($this, 'applyFilter'))
            {
                // Fallback to generic handler
                $this->applyFilter($qb, $name, $value);
            }
        }
    }
}
