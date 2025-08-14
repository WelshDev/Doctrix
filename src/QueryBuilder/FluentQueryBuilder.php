<?php

namespace WelshDev\Doctrix\QueryBuilder;

use BadMethodCallException;
use Closure;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use WelshDev\Doctrix\BaseRepository;
use WelshDev\Doctrix\Debug\QueryDebugger;
use WelshDev\Doctrix\Pagination\PaginationResult;
use WelshDev\Doctrix\Traits\MacroableTrait;

/**
 * Fluent interface for building queries
 *
 * Provides a modern, chainable API for query construction
 * while maintaining full compatibility with Doctrine QueryBuilder
 */
class FluentQueryBuilder
{
    use MacroableTrait;
    /**
     * The parent repository
     *
     * @var BaseRepository
     */
    private BaseRepository $repository;

    /**
     * The underlying Doctrine QueryBuilder
     *
     * @var QueryBuilder
     */
    private QueryBuilder $queryBuilder;

    /**
     * Criteria to apply
     *
     * @var array
     */
    private array $criteria = [];

    /**
     * Order by clauses
     *
     * @var array
     */
    private array $orderBy = [];

    /**
     * Limit value
     *
     * @var int|null
     */
    private ?int $limit = null;

    /**
     * Offset value
     *
     * @var int|null
     */
    private ?int $offset = null;

    /**
     * Global scopes to exclude
     *
     * @var array<string>
     */
    private array $withoutScopes = [];

    /**
     * Cache configuration
     *
     * @var array{enabled: bool, lifetime: int, key: string|null}
     */
    private array $cache = ['enabled' => false, 'lifetime' => 3600, 'key' => null];

    /**
     * Constructor
     *
     * @param BaseRepository $repository The parent repository
     */
    public function __construct(BaseRepository $repository)
    {
        $this->repository = $repository;
        $this->reset();
    }

    /**
     * Dynamically handle calls to macros and undefined methods
     *
     * @param string $method
     * @param array $parameters
     *
     * @throws BadMethodCallException
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        // Check if it's a registered macro
        if (static::hasMacro($method))
        {
            $macro = static::$macros[static::class][$method];

            if ($macro instanceof Closure)
            {
                // Bind the macro to this instance and pass $this as first parameter
                $macro = $macro->bindTo($this, static::class);

                return $macro($this, ...$parameters);
            }

            return $macro($this, ...$parameters);
        }

        // If not a macro, throw an exception
        throw new BadMethodCallException(sprintf(
            'Method %s::%s does not exist.',
            static::class,
            $method,
        ));
    }

    /**
     * Reset the query builder to initial state
     *
     * @return self
     */
    public function reset(): self
    {
        $this->queryBuilder = $this->repository->createQueryBuilder($this->repository->getAlias());
        $this->criteria = [];
        $this->orderBy = [];
        $this->limit = null;
        $this->offset = null;
        $this->withoutScopes = [];
        $this->cache = ['enabled' => false, 'lifetime' => 3600, 'key' => null];

        return $this;
    }

    /**
     * Add a WHERE condition
     *
     * @param string|array|callable $field Field name, criteria array, or callback
     * @param mixed $operator Operator or value
     * @param mixed $value Value (when operator is provided)
     * @return self
     */
    public function where(string|array|callable $field, mixed $operator = null, mixed $value = null): self
    {
        // Handle callback for nested conditions
        if (is_callable($field))
        {
            $subQuery = new self($this->repository);
            $field($subQuery);
            $this->criteria[] = ['and', $subQuery->getCriteria()];

            return $this;
        }

        // Handle array of criteria
        if (is_array($field))
        {
            $this->criteria = array_merge($this->criteria, $field);

            return $this;
        }

        // Handle simple field conditions
        if ($value === null && $operator !== null)
        {
            // Two argument form: field, value
            $this->criteria[$field] = $operator;
        }
        elseif ($value !== null)
        {
            // Three argument form: field, operator, value
            $this->criteria[] = [$field, $operator, $value];
        }
        else
        {
            // Single argument: assume checking for not null
            $this->criteria[] = [$field, 'is_not_null', true];
        }

        return $this;
    }

    /**
     * Add an OR WHERE condition
     *
     * @param string|array|callable $field Field name, criteria array, or callback
     * @param mixed $operator Operator or value
     * @param mixed $value Value (when operator is provided)
     * @return self
     */
    public function orWhere(string|array|callable $field, mixed $operator = null, mixed $value = null): self
    {
        // Handle callback for nested conditions
        if (is_callable($field))
        {
            $subQuery = new self($this->repository);
            $field($subQuery);
            $this->criteria[] = ['or', $subQuery->getCriteria()];

            return $this;
        }

        // Build the OR condition
        $orCondition = [];

        if (is_array($field))
        {
            $orCondition = $field;
        }
        elseif ($value === null && $operator !== null)
        {
            $orCondition = [$field => $operator];
        }
        elseif ($value !== null)
        {
            $orCondition = [[$field, $operator, $value]];
        }
        else
        {
            $orCondition = [[$field, 'is_not_null', true]];
        }

        $this->criteria[] = ['or', $orCondition];

        return $this;
    }

    /**
     * Add a WHERE IN condition
     *
     * @param string $field Field name
     * @param array $values Array of values
     * @return self
     */
    public function whereIn(string $field, array $values): self
    {
        $this->criteria[] = [$field, 'in', $values];

        return $this;
    }

    /**
     * Add a WHERE NOT IN condition
     *
     * @param string $field Field name
     * @param array $values Array of values
     * @return self
     */
    public function whereNotIn(string $field, array $values): self
    {
        $this->criteria[] = [$field, 'not_in', $values];

        return $this;
    }

    /**
     * Add a WHERE BETWEEN condition
     *
     * @param string $field Field name
     * @param mixed $start Start value
     * @param mixed $end End value
     * @return self
     */
    public function whereBetween(string $field, mixed $start, mixed $end): self
    {
        $this->criteria[] = [$field, 'between', [$start, $end]];

        return $this;
    }

    /**
     * Add a WHERE NULL condition
     *
     * @param string $field Field name
     * @return self
     */
    public function whereNull(string $field): self
    {
        $this->criteria[] = [$field, 'is_null', true];

        return $this;
    }

    /**
     * Add a WHERE NOT NULL condition
     *
     * @param string $field Field name
     * @return self
     */
    public function whereNotNull(string $field): self
    {
        $this->criteria[] = [$field, 'is_not_null', true];

        return $this;
    }

    /**
     * Add a WHERE LIKE condition
     *
     * @param string $field Field name
     * @param string $pattern LIKE pattern
     * @return self
     */
    public function whereLike(string $field, string $pattern): self
    {
        $this->criteria[] = [$field, 'like', $pattern];

        return $this;
    }

    /**
     * Add a WHERE contains condition (wraps value with %)
     *
     * @param string $field Field name
     * @param string $value Value to search for
     * @return self
     */
    public function whereContains(string $field, string $value): self
    {
        $this->criteria[] = [$field, 'contains', $value];

        return $this;
    }

    /**
     * Add an ORDER BY clause
     *
     * @param string $field Field name
     * @param string $direction Direction (ASC or DESC)
     * @return self
     */
    public function orderBy(string $field, string $direction = 'ASC'): self
    {
        $this->orderBy[$field] = strtoupper($direction);

        return $this;
    }

    /**
     * Set the query limit
     *
     * @param int $limit The limit
     * @return self
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Set the query offset
     *
     * @param int $offset The offset
     * @return self
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset;

        return $this;
    }

    /**
     * Alias for limit
     *
     * @param int $limit The limit
     * @return self
     */
    public function take(int $limit): self
    {
        return $this->limit($limit);
    }

    /**
     * Alias for offset
     *
     * @param int $offset The offset
     * @return self
     */
    public function skip(int $offset): self
    {
        return $this->offset($offset);
    }

    /**
     * Apply a named filter from the repository
     *
     * @param string $filterName The filter name
     * @return self
     */
    public function applyFilter(string $filterName): self
    {
        $filters = $this->repository->defineFilters();

        if (isset($filters[$filterName]) && is_callable($filters[$filterName]))
        {
            $this->repository->addFilterFunction($filters[$filterName]);
        }

        return $this;
    }

    /**
     * Exclude a global scope
     *
     * @param string|array $scopes Scope name(s) to exclude
     * @return self
     */
    public function withoutGlobalScope(string|array $scopes): self
    {
        if (is_string($scopes))
        {
            $scopes = [$scopes];
        }

        $this->withoutScopes = array_merge($this->withoutScopes, $scopes);

        return $this;
    }

    /**
     * Enable query result caching
     *
     * @param int $lifetime Cache lifetime in seconds
     * @param string|null $key Optional cache key
     * @return self
     */
    public function cache(int $lifetime = 3600, ?string $key = null): self
    {
        $this->cache = [
            'enabled' => true,
            'lifetime' => $lifetime,
            'key' => $key,
        ];

        return $this;
    }

    /**
     * Get the results
     *
     * @return array The query results
     */
    public function get(): array
    {
        $qb = $this->buildQueryBuilder();
        $query = $qb->getQuery();

        // Apply caching if enabled
        if ($this->cache['enabled'])
        {
            $query->enableResultCache($this->cache['lifetime'], $this->cache['key']);
        }

        return $query->getResult();
    }

    /**
     * Get the first result
     *
     * @return object|null The first result or null
     */
    public function first(): ?object
    {
        $this->limit(1);
        $results = $this->get();

        return $results[0] ?? null;
    }

    /**
     * Get a single result (throws exception if multiple)
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @return object|null The single result or null
     */
    public function one(): ?object
    {
        $qb = $this->buildQueryBuilder();
        $query = $qb->getQuery();

        // Apply caching if enabled
        if ($this->cache['enabled'])
        {
            $query->enableResultCache($this->cache['lifetime'], $this->cache['key']);
        }

        return $query->getOneOrNullResult();
    }

    /**
     * Count the results
     *
     * @return int The count
     */
    public function count(): int
    {
        $qb = $this->buildQueryBuilder();
        $qb->select('COUNT(DISTINCT ' . $this->repository->getAlias() . '.id)');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Sum a field
     *
     * @param string $field The field to sum
     * @return float The sum
     */
    public function sum(string $field): float
    {
        $qb = $this->buildQueryBuilder();
        $field = $this->normalizeField($field);
        $qb->select('SUM(' . $field . ')');

        return (float) ($qb->getQuery()->getSingleScalarResult() ?? 0);
    }

    /**
     * Get the average of a field
     *
     * @param string $field The field to average
     * @return float The average
     */
    public function avg(string $field): float
    {
        $qb = $this->buildQueryBuilder();
        $field = $this->normalizeField($field);
        $qb->select('AVG(' . $field . ')');

        return (float) ($qb->getQuery()->getSingleScalarResult() ?? 0);
    }

    /**
     * Get the maximum value of a field
     *
     * @param string $field The field
     * @return mixed The maximum value
     */
    public function max(string $field): mixed
    {
        $qb = $this->buildQueryBuilder();
        $field = $this->normalizeField($field);
        $qb->select('MAX(' . $field . ')');

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Get the minimum value of a field
     *
     * @param string $field The field
     * @return mixed The minimum value
     */
    public function min(string $field): mixed
    {
        $qb = $this->buildQueryBuilder();
        $field = $this->normalizeField($field);
        $qb->select('MIN(' . $field . ')');

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Check if any results exist
     *
     * @return bool True if results exist
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Get paginated results
     *
     * @param int $page The page number (1-indexed)
     * @param int $perPage Number of items per page
     * @return PaginationResult The pagination result
     */
    public function paginate(int $page = 1, int $perPage = 20): PaginationResult
    {
        // Ensure valid page and perPage values
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        // Get total count
        $total = $this->count();

        // Set limit and offset for current page
        $this->limit($perPage);
        $this->offset(($page - 1) * $perPage);

        // Fetch items
        $items = $this->get();

        return new PaginationResult($items, $total, $page, $perPage);
    }

    /**
     * Get simple paginated results (just items and hasMore flag)
     * Useful for infinite scroll or load more implementations
     *
     * @param int $page The page number (1-indexed)
     * @param int $perPage Number of items per page
     * @return array{items: array, hasMore: bool}
     */
    public function simplePaginate(int $page = 1, int $perPage = 20): array
    {
        // Ensure valid page and perPage values
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        // Set limit to fetch one extra item
        $this->limit($perPage + 1);
        $this->offset(($page - 1) * $perPage);

        // Fetch items
        $items = $this->get();

        // Check if there are more items
        $hasMore = count($items) > $perPage;

        // Return only the requested number of items
        if ($hasMore)
        {
            $items = array_slice($items, 0, $perPage);
        }

        return [
            'items' => $items,
            'hasMore' => $hasMore,
        ];
    }

    /**
     * Set the page for pagination
     * Convenience method for pagination
     *
     * @param int $page The page number (1-indexed)
     * @param int $perPage Items per page
     * @return self
     */
    public function page(int $page, int $perPage = 20): self
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $this->limit($perPage);
        $this->offset(($page - 1) * $perPage);

        return $this;
    }

    /**
     * Get the SQL query string
     *
     * @return string The SQL query
     */
    public function toSql(): string
    {
        $qb = $this->buildQueryBuilder();

        return $qb->getQuery()->getSQL();
    }

    /**
     * Get the query parameters
     *
     * @return array The parameters
     */
    public function getParameters(): array
    {
        $qb = $this->buildQueryBuilder();
        $params = [];

        foreach ($qb->getParameters() as $parameter)
        {
            $params[$parameter->getName()] = $parameter->getValue();
        }

        return $params;
    }

    /**
     * Get the underlying QueryBuilder
     *
     * @return QueryBuilder
     */
    public function getQueryBuilder(): QueryBuilder
    {
        return $this->buildQueryBuilder();
    }

    /**
     * Debug the query - show SQL, parameters, execution plan, and timing
     *
     * @param string $format Output format: 'text', 'html', 'json', 'array'
     * @param bool $execute Whether to actually execute the query
     * @return array Debug information
     *
     * @example
     * // Debug without executing
     * $repo->query()->where('status', 'active')->debug();
     *
     * // Debug with execution to see timing
     * $repo->query()->where('status', 'active')->debug('text', true);
     *
     * // Get debug info as array
     * $debugInfo = $repo->query()->where('status', 'active')->debug('array');
     */
    public function debug(string $format = 'text', bool $execute = false): array
    {
        $qb = $this->buildQueryBuilder();

        return QueryDebugger::debug($qb, $format, $execute);
    }

    /**
     * Execute query with debug output
     * Similar to get() but with debug information
     *
     * @param string $format Debug output format
     * @return array The query results
     */
    public function getWithDebug(string $format = 'text'): array
    {
        // Show debug info with execution
        $this->debug($format, true);

        // Return actual results
        return $this->get();
    }

    /**
     * Execute paginated query with debug output
     *
     * @param int $page Current page
     * @param int $perPage Items per page
     * @param string $format Debug output format
     * @return PaginationResult
     */
    public function paginateWithDebug(int $page = 1, int $perPage = 20, string $format = 'text'): PaginationResult
    {
        // Show debug info
        $this->debug($format, true);

        // Return paginated results
        return $this->paginate($page, $perPage);
    }

    /**
     * Build the final QueryBuilder with all criteria applied
     *
     * @return QueryBuilder
     */
    protected function buildQueryBuilder(): QueryBuilder
    {
        // Use repository's buildQuery method for compatibility
        $qb = $this->repository->buildQuery($this->criteria, $this->orderBy);

        // Apply limit and offset
        if ($this->limit !== null)
        {
            $qb->setMaxResults($this->limit);
        }

        if ($this->offset !== null)
        {
            $qb->setFirstResult($this->offset);
        }

        return $qb;
    }

    /**
     * Get the current criteria array
     *
     * @return array
     */
    protected function getCriteria(): array
    {
        return $this->criteria;
    }

    /**
     * Normalize a field name with alias
     *
     * @param string $field
     * @return string
     */
    protected function normalizeField(string $field): string
    {
        if (strpos($field, '.') === false)
        {
            return $this->repository->getAlias() . '.' . $field;
        }

        return $field;
    }
}
