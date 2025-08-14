<?php

namespace WelshDev\Doctrix\Traits;

use Doctrine\ORM\QueryBuilder;

/**
 * Trait for managing global scopes in repositories
 *
 * Global scopes are automatically applied to all queries unless
 * explicitly excluded
 */
trait GlobalScopesTrait
{
    /**
     * Global scopes to exclude for the current query
     *
     * @var array<string>
     */
    protected array $excludedGlobalScopes = [];

    /**
     * Whether global scopes are enabled
     *
     * @var bool
     */
    protected bool $globalScopesEnabled = true;

    /**
     * Fetch entities without specific global scopes
     *
     * @param string|array $scopes Scope name(s) to exclude
     * @param array $criteria The search criteria
     * @param array|null $orderBy Optional ordering
     * @param int|null $limit Optional limit
     * @param int|null $offset Optional offset
     * @return array The results
     */
    public function fetchWithoutScopes(
        string|array $scopes,
        array $criteria = [],
        ?array $orderBy = null,
        ?int $limit = null,
        ?int $offset = null,
    ): array {
        $this->withoutGlobalScopes($scopes);
        $results = $this->fetch($criteria, $orderBy, $limit, $offset);
        $this->resetGlobalScopes();

        return $results;
    }

    /**
     * Fetch entities without any global scopes
     *
     * @param array $criteria The search criteria
     * @param array|null $orderBy Optional ordering
     * @param int|null $limit Optional limit
     * @param int|null $offset Optional offset
     * @return array The results
     */
    public function fetchWithoutAnyScopes(
        array $criteria = [],
        ?array $orderBy = null,
        ?int $limit = null,
        ?int $offset = null,
    ): array {
        $this->disableGlobalScopes();
        $results = $this->fetch($criteria, $orderBy, $limit, $offset);
        $this->enableGlobalScopes();

        return $results;
    }

    /**
     * Temporarily exclude specific global scopes
     *
     * @param string|array $scopes Scope name(s) to exclude
     * @return self
     */
    public function withoutGlobalScopes(string|array $scopes): self
    {
        if (is_string($scopes))
        {
            $scopes = [$scopes];
        }

        $this->excludedGlobalScopes = array_merge($this->excludedGlobalScopes, $scopes);

        return $this;
    }

    /**
     * Reset excluded global scopes
     *
     * @return self
     */
    public function resetGlobalScopes(): self
    {
        $this->excludedGlobalScopes = [];

        return $this;
    }

    /**
     * Disable all global scopes temporarily
     *
     * @return self
     */
    public function disableGlobalScopes(): self
    {
        $this->globalScopesEnabled = false;

        return $this;
    }

    /**
     * Enable global scopes
     *
     * @return self
     */
    public function enableGlobalScopes(): self
    {
        $this->globalScopesEnabled = true;

        return $this;
    }

    /**
     * Check if a global scope is currently excluded
     *
     * @param string $scopeName The scope name
     * @return bool
     */
    public function isScopeExcluded(string $scopeName): bool
    {
        return in_array($scopeName, $this->excludedGlobalScopes);
    }

    /**
     * Get the list of currently excluded scopes
     *
     * @return array<string>
     */
    public function getExcludedScopes(): array
    {
        return $this->excludedGlobalScopes;
    }

    /**
     * Add a dynamic global scope
     *
     * @param string $name The scope name
     * @param callable $scope The scope function
     * @return self
     */
    public function addGlobalScope(string $name, callable $scope): self
    {
        // This would require storing dynamic scopes
        // Implementation depends on how you want to handle dynamic scopes
        // For now, this is a placeholder

        return $this;
    }

    /**
     * Apply global scopes to a query builder
     *
     * @param QueryBuilder $qb The query builder
     * @return void
     */
    protected function applyGlobalScopes(QueryBuilder $qb): void
    {
        if (!$this->globalScopesEnabled)
        {
            return;
        }

        $scopes = $this->globalScopes();

        foreach ($scopes as $name => $scope)
        {
            if (!in_array($name, $this->excludedGlobalScopes) && is_callable($scope))
            {
                $result = $scope($qb);

                // Allow scopes to return modified query builder
                if ($result instanceof QueryBuilder)
                {
                    $qb = $result;
                }
            }
        }
    }

    /**
     * Create a query builder with global scopes applied
     *
     * @param string $alias The query alias
     * @return QueryBuilder
     */
    public function createScopedQueryBuilder(string $alias): QueryBuilder
    {
        $qb = $this->createQueryBuilder($alias);
        $this->applyGlobalScopes($qb);

        return $qb;
    }

    /**
     * Override in child repositories to modify global scope behavior
     *
     * @return array<string, callable>
     */
    protected function globalScopes(): array
    {
        // Default implementation returns empty array
        // Child repositories should override this
        return [];
    }
}
