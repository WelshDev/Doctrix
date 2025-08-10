<?php

namespace WelshDev\Doctrix\Traits;

use Symfony\Component\HttpFoundation\Request;
use WelshDev\Doctrix\Pagination\PaginationResult;
use WelshDev\Doctrix\QueryBuilder\FluentQueryBuilder;
use WelshDev\Doctrix\Request\RequestQueryBuilder;
use WelshDev\Doctrix\Request\RequestQuerySchema;

/**
 * Trait for building queries from HTTP requests
 *
 * Provides methods to easily build queries from request parameters
 * with proper validation and security
 */
trait RequestQueryTrait
{
    /**
     * The request query schema
     *
     * @var RequestQuerySchema|null
     */
    protected ?RequestQuerySchema $requestSchema = null;

    /**
     * Get or create the request schema
     *
     * @return RequestQuerySchema
     */
    public function getRequestSchema(): RequestQuerySchema
    {
        if ($this->requestSchema === null)
        {
            $this->requestSchema = $this->defineRequestSchema();
        }

        return $this->requestSchema;
    }

    /**
     * Build query from request
     *
     * @param Request|array $request
     * @param RequestQuerySchema|array|null $schema Schema or configuration array
     * @return FluentQueryBuilder
     *
     * @example
     * // Simple usage with repository-defined schema
     * $users = $repo->fromRequest($request)->get();
     *
     * // With custom schema
     * $schema = RequestQuerySchema::preset('strict')
     *     ->searchable(['name', 'email'])
     *     ->sortable(['createdAt']);
     * $users = $repo->fromRequest($request, $schema)->get();
     *
     * // With configuration array
     * $users = $repo->fromRequest($request, [
     *     'searchable' => ['name', 'status'],
     *     'maxLimit' => 50
     * ])->get();
     */
    public function fromRequest($request, $schema = null): FluentQueryBuilder
    {
        // Determine schema to use
        if ($schema === null)
        {
            $schema = $this->getRequestSchema();
        }
        elseif (is_array($schema))
        {
            // Convert array to schema
            $schemaConfig = $schema;
            $schema = new RequestQuerySchema();

            if (isset($schemaConfig['searchable']))
            {
                $schema->searchable($schemaConfig['searchable']);
            }
            if (isset($schemaConfig['sortable']))
            {
                $schema->sortable($schemaConfig['sortable']);
            }
            if (isset($schemaConfig['defaults']))
            {
                $schema->defaults($schemaConfig['defaults']);
            }
            if (isset($schemaConfig['required']))
            {
                $schema->require($schemaConfig['required']);
            }
            if (isset($schemaConfig['maxLimit']))
            {
                $schema->maxLimit($schemaConfig['maxLimit']);
            }
            if (isset($schemaConfig['defaultLimit']))
            {
                $schema->defaultLimit($schemaConfig['defaultLimit']);
            }
            if (isset($schemaConfig['strictMode']))
            {
                $schema->strictMode($schemaConfig['strictMode']);
            }
        }

        // Create query builder
        $builder = new RequestQueryBuilder($schema);

        // Start with fluent query
        $query = $this->query();

        // Build and return
        return $builder->build($request, $query);
    }

    /**
     * Build and paginate query from request
     *
     * @param Request|array $request
     * @param RequestQuerySchema|array|null $schema
     * @return PaginationResult
     */
    public function paginateFromRequest($request, $schema = null): PaginationResult
    {
        $query = $this->fromRequest($request, $schema);

        // Extract pagination params from request
        $params = is_array($request) ? $request : $request->query->all();
        $page = (int) ($params['page'] ?? 1);
        $limit = (int) ($params['limit'] ?? $params['perPage'] ?? 20);

        // Apply max limit from schema
        $schema = $schema instanceof RequestQuerySchema ? $schema : $this->getRequestSchema();
        $maxLimit = $schema->getConfig('maxLimit', 100);
        $limit = min($limit, $maxLimit);

        return $query->paginate($page, $limit);
    }

    /**
     * Create a request query builder
     * Provides more control over the building process
     *
     * @param RequestQuerySchema|null $schema
     * @return RequestQueryHelper
     */
    public function requestQuery(?RequestQuerySchema $schema = null): RequestQueryHelper
    {
        return new RequestQueryHelper($this, $schema ?? $this->getRequestSchema());
    }

    /**
     * Configure request schema fluently
     *
     * @return RequestQuerySchema
     *
     * @example
     * $repo->configureRequestSchema()
     *     ->searchable(['name', 'email', 'status'])
     *     ->sortable(['createdAt', 'name'])
     *     ->defaults(['status' => 'active'])
     *     ->maxLimit(50);
     */
    public function configureRequestSchema(): RequestQuerySchema
    {
        $this->requestSchema = new RequestQuerySchema();

        return $this->requestSchema;
    }

    /**
     * Define the request query schema
     * Override in repository to configure searchable/sortable fields
     *
     * @return RequestQuerySchema
     */
    protected function defineRequestSchema(): RequestQuerySchema
    {
        // Default implementation - override in child classes
        $schema = new RequestQuerySchema();

        // Try to auto-configure from existing properties if defined
        if (property_exists($this, 'searchableFields'))
        {
            $schema->searchable($this->searchableFields);
        }

        if (property_exists($this, 'sortableFields'))
        {
            $schema->sortable($this->sortableFields);
        }

        if (property_exists($this, 'requestDefaults'))
        {
            $schema->defaults($this->requestDefaults);
        }

        return $schema;
    }
}

/**
 * Helper class for building request queries with more control
 */
class RequestQueryHelper
{
    protected $repository;
    protected RequestQuerySchema $schema;
    protected array $overrides = [];
    protected bool $validateStrict = true;
    protected ?string $cacheKey = null;
    protected ?int $cacheTtl = null;

    public function __construct($repository, RequestQuerySchema $schema)
    {
        $this->repository = $repository;
        $this->schema = $schema;
    }

    /**
     * Allow additional fields for this query
     *
     * @param array $fields
     * @return self
     */
    public function allowFields(array $fields): self
    {
        foreach ($fields as $field)
        {
            $this->schema->field($field)->searchable();
        }

        return $this;
    }

    /**
     * Allow sorting on additional fields
     *
     * @param array $fields
     * @return self
     */
    public function allowSorting(array $fields): self
    {
        foreach ($fields as $field)
        {
            $this->schema->field($field)->sortable();
        }

        return $this;
    }

    /**
     * Set default filters
     *
     * @param array $defaults
     * @return self
     */
    public function withDefaults(array $defaults): self
    {
        $this->schema->defaults($defaults);

        return $this;
    }

    /**
     * Override specific request parameters
     *
     * @param array $overrides
     * @return self
     */
    public function override(array $overrides): self
    {
        $this->overrides = array_merge($this->overrides, $overrides);

        return $this;
    }

    /**
     * Set validation mode
     *
     * @param bool $strict
     * @return self
     */
    public function validate(bool $strict = true): self
    {
        $this->validateStrict = $strict;
        $this->schema->strictMode($strict);

        return $this;
    }

    /**
     * Enable caching for this query
     *
     * @param string $key
     * @param int $ttl
     * @return self
     */
    public function cache(string $key, int $ttl = 3600): self
    {
        $this->cacheKey = $key;
        $this->cacheTtl = $ttl;

        return $this;
    }

    /**
     * Build query from request
     *
     * @param Request|array $request
     * @return FluentQueryBuilder
     */
    public function fromRequest($request): FluentQueryBuilder
    {
        // Merge overrides with request
        if (!empty($this->overrides))
        {
            if (is_array($request))
            {
                $request = array_merge($request, $this->overrides);
            }
            elseif ($request instanceof Request)
            {
                // Create new request with merged params
                $params = array_merge(
                    $request->query->all(),
                    $request->request->all(),
                    $this->overrides,
                );
                $request = $params;
            }
        }

        // Build query
        $query = $this->repository->fromRequest($request, $this->schema);

        // Apply caching if configured
        if ($this->cacheKey && method_exists($query, 'cache'))
        {
            $query->cache($this->cacheTtl);
        }

        return $query;
    }

    /**
     * Build and execute query
     *
     * @param Request|array $request
     * @return array
     */
    public function get($request): array
    {
        return $this->fromRequest($request)->get();
    }

    /**
     * Build and paginate query
     *
     * @param Request|array $request
     * @return PaginationResult
     */
    public function paginate($request): PaginationResult
    {
        return $this->repository->paginateFromRequest($request, $this->schema);
    }
}
