<?php

namespace WelshDev\Doctrix\QueryBuilder;

use Doctrine\ORM\QueryBuilder;

/**
 * Manages filter functions for query building
 *
 * Handles:
 * - Custom filter functions
 * - Named filters
 * - Global scopes
 * - Filter composition
 */
class FilterChain
{
    /**
     * Apply a chain of filter functions to a query builder
     *
     * @param QueryBuilder $qb The query builder
     * @param array<callable> $filters The filter functions to apply
     * @return void
     */
    public function applyFilters(QueryBuilder $qb, array $filters): void
    {
        foreach ($filters as $filter)
        {
            if (!is_callable($filter))
            {
                continue;
            }

            // Call the filter function with the query builder
            $result = $filter($qb);

            // If the filter returns a QueryBuilder, use it
            // This allows filters to return a modified query builder
            if ($result instanceof QueryBuilder)
            {
                $qb = $result;
            }
        }
    }

    /**
     * Apply named filters from a definition array
     *
     * @param QueryBuilder $qb The query builder
     * @param array<string, callable> $filterDefinitions The filter definitions
     * @param array<string> $filterNames The names of filters to apply
     * @return void
     */
    public function applyNamedFilters(QueryBuilder $qb, array $filterDefinitions, array $filterNames): void
    {
        $filters = [];

        foreach ($filterNames as $name)
        {
            if (isset($filterDefinitions[$name]) && is_callable($filterDefinitions[$name]))
            {
                $filters[] = $filterDefinitions[$name];
            }
        }

        $this->applyFilters($qb, $filters);
    }

    /**
     * Apply global scopes to a query builder
     *
     * @param QueryBuilder $qb The query builder
     * @param array<string, callable> $globalScopes The global scope definitions
     * @param array<string> $excludedScopes Names of scopes to exclude
     * @return void
     */
    public function applyGlobalScopes(QueryBuilder $qb, array $globalScopes, array $excludedScopes = []): void
    {
        $filters = [];

        foreach ($globalScopes as $name => $scope)
        {
            if (!in_array($name, $excludedScopes) && is_callable($scope))
            {
                $filters[] = $scope;
            }
        }

        $this->applyFilters($qb, $filters);
    }

    /**
     * Compose multiple filters into a single filter function
     *
     * @param array<callable> $filters The filters to compose
     * @return callable The composed filter function
     */
    public function compose(array $filters): callable
    {
        return function (QueryBuilder $qb) use ($filters)
        {
            $this->applyFilters($qb, $filters);

            return $qb;
        };
    }

    /**
     * Create a filter that adds a WHERE condition
     *
     * @param string $condition The WHERE condition
     * @param array $parameters The parameters to bind
     * @return callable The filter function
     */
    public function createWhereFilter(string $condition, array $parameters = []): callable
    {
        return function (QueryBuilder $qb) use ($condition, $parameters)
        {
            $qb->andWhere($condition);

            foreach ($parameters as $key => $value)
            {
                $qb->setParameter($key, $value);
            }

            return $qb;
        };
    }

    /**
     * Create a filter that adds an OR WHERE condition
     *
     * @param string $condition The WHERE condition
     * @param array $parameters The parameters to bind
     * @return callable The filter function
     */
    public function createOrWhereFilter(string $condition, array $parameters = []): callable
    {
        return function (QueryBuilder $qb) use ($condition, $parameters)
        {
            $qb->orWhere($condition);

            foreach ($parameters as $key => $value)
            {
                $qb->setParameter($key, $value);
            }

            return $qb;
        };
    }

    /**
     * Create a filter that adds a HAVING condition
     *
     * @param string $condition The HAVING condition
     * @param array $parameters The parameters to bind
     * @return callable The filter function
     */
    public function createHavingFilter(string $condition, array $parameters = []): callable
    {
        return function (QueryBuilder $qb) use ($condition, $parameters)
        {
            $qb->andHaving($condition);

            foreach ($parameters as $key => $value)
            {
                $qb->setParameter($key, $value);
            }

            return $qb;
        };
    }

    /**
     * Validate that a filter is callable
     *
     * @param mixed $filter The filter to validate
     * @return bool True if valid
     */
    public function isValidFilter(mixed $filter): bool
    {
        return is_callable($filter);
    }
}
