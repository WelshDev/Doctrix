<?php

namespace WelshDev\Doctrix\Request;

use Exception;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use WelshDev\Doctrix\QueryBuilder\FluentQueryBuilder;

/**
 * Builds Doctrix queries from HTTP request parameters
 *
 * Handles parsing of request parameters and converting them into
 * query criteria with proper validation and security
 */
class RequestQueryBuilder
{
    /**
     * The schema defining allowed operations
     *
     * @var RequestQuerySchema
     */
    protected RequestQuerySchema $schema;

    /**
     * Parsed filters from request
     *
     * @var array
     */
    protected array $filters = [];

    /**
     * Parsed sorting from request
     *
     * @var array
     */
    protected array $sorting = [];

    /**
     * Pagination parameters
     *
     * @var array
     */
    protected array $pagination = [
        'page' => 1,
        'limit' => null,
    ];

    /**
     * Search parameters
     *
     * @var array
     */
    protected array $search = [];

    /**
     * Validation errors
     *
     * @var array
     */
    protected array $errors = [];

    /**
     * Constructor
     *
     * @param RequestQuerySchema|null $schema
     */
    public function __construct(?RequestQuerySchema $schema = null)
    {
        $this->schema = $schema ?? new RequestQuerySchema();
    }

    /**
     * Parse request and build query
     *
     * @param Request|array $request Symfony Request object or array of parameters
     * @param FluentQueryBuilder $query
     * @return FluentQueryBuilder
     */
    public function build($request, FluentQueryBuilder $query): FluentQueryBuilder
    {
        $params = $this->extractParameters($request);

        // Validate parameters
        $this->errors = $this->schema->validate($params);
        if (!empty($this->errors))
        {
            throw new RequestQueryException('Invalid request parameters', $this->errors);
        }

        // Parse components
        $this->parseFilters($params);
        $this->parseSorting($params);
        $this->parsePagination($params);
        $this->parseSearch($params);

        // Apply to query
        $this->applyFilters($query);
        $this->applySorting($query);
        $this->applyPagination($query);
        $this->applySearch($query);

        return $query;
    }

    /**
     * Get parsed filters
     *
     * @return array
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * Get parsed sorting
     *
     * @return array
     */
    public function getSorting(): array
    {
        return $this->sorting;
    }

    /**
     * Get pagination parameters
     *
     * @return array
     */
    public function getPagination(): array
    {
        return $this->pagination;
    }

    /**
     * Get validation errors
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Check if the query is paginated
     *
     * @return bool
     */
    public function isPaginated(): bool
    {
        return $this->pagination['limit'] !== null;
    }

    /**
     * Extract parameters from request
     *
     * @param Request|array $request
     * @return array
     */
    protected function extractParameters($request): array
    {
        if (is_array($request))
        {
            return $request;
        }

        if ($request instanceof Request)
        {
            $params = [];

            // Merge GET and POST parameters based on method
            if ($request->getMethod() === 'GET')
            {
                $params = $request->query->all();
            }
            elseif ($request->getMethod() === 'POST')
            {
                // For POST, check if JSON content
                $contentType = $request->headers->get('Content-Type');
                if (strpos($contentType, 'application/json') !== false)
                {
                    $params = json_decode($request->getContent(), true) ?? [];
                }
                else
                {
                    $params = $request->request->all();
                }

                // Also include query parameters for hybrid approach
                $params = array_merge($request->query->all(), $params);
            }
            else
            {
                // For other methods, merge both
                $params = array_merge(
                    $request->query->all(),
                    $request->request->all(),
                );
            }

            return $params;
        }

        throw new InvalidArgumentException('Request must be a Symfony Request object or array');
    }

    /**
     * Parse filter parameters
     *
     * @param array $params
     * @return void
     */
    protected function parseFilters(array $params): void
    {
        $this->filters = [];

        // Apply default filters first
        $defaults = $this->schema->getDefaults();
        foreach ($defaults as $field => $value)
        {
            $this->filters[] = [$field, '=', $value];
        }

        $style = $this->schema->getConfig('parameterStyle', 'standard');

        switch ($style)
        {
            case 'jsonapi':
                $this->parseJsonApiFilters($params);
                break;
            case 'graphql':
                $this->parseGraphQLFilters($params);
                break;
            default:
                $this->parseStandardFilters($params);
                break;
        }
    }

    /**
     * Parse standard format filters
     *
     * @param array $params
     * @return void
     */
    protected function parseStandardFilters(array $params): void
    {
        // Handle filter array if present
        if (isset($params['filter']) && is_array($params['filter']))
        {
            foreach ($params['filter'] as $field => $value)
            {
                $this->addFilter($field, $value);
            }
        }

        // Handle direct parameters
        foreach ($params as $key => $value)
        {
            // Skip special parameters
            if (in_array($key, ['page', 'limit', 'sort', 'order', 'search', 'filter', 'q']))
            {
                continue;
            }

            // Check for operator suffixes (e.g., created_at_gte)
            if (preg_match('/^(.+)_(eq|neq|gt|gte|lt|lte|like|in|between|is_null|is_not_null)$/', $key, $matches))
            {
                $field = $matches[1];
                $operator = $matches[2];

                $fieldSchema = $this->schema->getField($field);
                if ($fieldSchema && $fieldSchema->isSearchable())
                {
                    if (in_array($operator, $fieldSchema->getOperators()))
                    {
                        $this->filters[] = [$field, $operator, $value];
                    }
                }
            }
            else
            {
                // Direct field = value (equals)
                $this->addFilter($key, $value);
            }
        }

        // Handle date range shortcuts
        if (isset($params['dateFrom']) || isset($params['dateTo']))
        {
            $this->handleDateRange($params);
        }
    }

    /**
     * Parse JSON:API format filters
     *
     * @param array $params
     * @return void
     */
    protected function parseJsonApiFilters(array $params): void
    {
        if (!isset($params['filter']) || !is_array($params['filter']))
        {
            return;
        }

        foreach ($params['filter'] as $field => $conditions)
        {
            if (is_array($conditions))
            {
                foreach ($conditions as $operator => $value)
                {
                    $fieldSchema = $this->schema->getField($field);
                    if ($fieldSchema && $fieldSchema->isSearchable())
                    {
                        if (in_array($operator, $fieldSchema->getOperators()))
                        {
                            $this->filters[] = [$field, $operator, $value];
                        }
                    }
                }
            }
            else
            {
                $this->addFilter($field, $conditions);
            }
        }
    }

    /**
     * Parse GraphQL format filters
     *
     * @param array $params
     * @return void
     */
    protected function parseGraphQLFilters(array $params): void
    {
        if (isset($params['where']) && is_array($params['where']))
        {
            $this->parseGraphQLWhere($params['where']);
        }
    }

    /**
     * Recursively parse GraphQL where clause
     *
     * @param array $where
     * @return void
     */
    protected function parseGraphQLWhere(array $where): void
    {
        foreach ($where as $key => $value)
        {
            if ($key === 'AND' && is_array($value))
            {
                foreach ($value as $condition)
                {
                    $this->parseGraphQLWhere($condition);
                }
            }
            elseif ($key === 'OR' && is_array($value))
            {
                $orConditions = [];
                foreach ($value as $condition)
                {
                    foreach ($condition as $field => $val)
                    {
                        $orConditions[$field] = $val;
                    }
                }
                $this->filters[] = ['or', $orConditions];
            }
            else
            {
                $this->addFilter($key, $value);
            }
        }
    }

    /**
     * Add a filter
     *
     * @param string $field
     * @param mixed $value
     * @return void
     */
    protected function addFilter(string $field, $value): void
    {
        // Resolve field alias
        $field = $this->schema->resolveFieldName($field);

        // Get field schema
        $fieldSchema = $this->schema->getField($field);
        if (!$fieldSchema || !$fieldSchema->isSearchable())
        {
            if ($this->schema->getConfig('strictMode'))
            {
                $this->errors[] = "Field '$field' is not searchable";
            }

            return;
        }

        // Transform value
        $value = $fieldSchema->transformValue($value);

        // Handle array values as IN operator
        if (is_array($value))
        {
            $maxIn = $this->schema->getConfig('maxInValues', 100);
            if (count($value) > $maxIn)
            {
                $this->errors[] = "Too many values for field '$field' (max: $maxIn)";

                return;
            }
            $this->filters[] = [$field, 'in', $value];
        }
        else
        {
            $this->filters[] = [$field, '=', $value];
        }
    }

    /**
     * Handle date range parameters
     *
     * @param array $params
     * @return void
     */
    protected function handleDateRange(array $params): void
    {
        $dateField = $params['dateField'] ?? 'createdAt';

        if (isset($params['dateFrom']))
        {
            $fieldSchema = $this->schema->getField($dateField);
            if ($fieldSchema && $fieldSchema->isSearchable())
            {
                $this->filters[] = [$dateField, 'gte', $params['dateFrom']];
            }
        }

        if (isset($params['dateTo']))
        {
            $fieldSchema = $this->schema->getField($dateField);
            if ($fieldSchema && $fieldSchema->isSearchable())
            {
                $this->filters[] = [$dateField, 'lte', $params['dateTo']];
            }
        }
    }

    /**
     * Parse sorting parameters
     *
     * @param array $params
     * @return void
     */
    protected function parseSorting(array $params): void
    {
        $this->sorting = [];

        // Handle 'sort' parameter
        if (isset($params['sort']))
        {
            $sorts = is_array($params['sort']) ? $params['sort'] : [$params['sort']];

            foreach ($sorts as $sort)
            {
                // Handle format: -createdAt (dash prefix for DESC)
                if (strpos($sort, '-') === 0)
                {
                    $field = substr($sort, 1);
                    $direction = 'DESC';
                }
                elseif (strpos($sort, '+') === 0)
                {
                    $field = substr($sort, 1);
                    $direction = 'ASC';
                }
                else
                {
                    $field = $sort;
                    $direction = $params['order'] ?? 'ASC';
                }

                // Resolve field alias
                $field = $this->schema->resolveFieldName($field);

                // Check if sortable
                $fieldSchema = $this->schema->getField($field);
                if ($fieldSchema && $fieldSchema->isSortable())
                {
                    $this->sorting[$field] = $direction;
                }
                elseif ($this->schema->getConfig('strictMode'))
                {
                    $this->errors[] = "Field '$field' is not sortable";
                }
            }
        }

        // Handle separate orderBy parameter
        if (isset($params['orderBy']))
        {
            $field = $this->schema->resolveFieldName($params['orderBy']);
            $direction = $params['orderDirection'] ?? $params['order'] ?? 'ASC';

            $fieldSchema = $this->schema->getField($field);
            if ($fieldSchema && $fieldSchema->isSortable())
            {
                $this->sorting[$field] = $direction;
            }
        }
    }

    /**
     * Parse pagination parameters
     *
     * @param array $params
     * @return void
     */
    protected function parsePagination(array $params): void
    {
        // Page number
        $this->pagination['page'] = isset($params['page'])
            ? max(1, (int) $params['page'])
            : 1;

        // Results per page
        $limit = $params['limit'] ?? $params['perPage'] ?? $params['pageSize'] ?? null;

        if ($limit !== null)
        {
            $maxLimit = $this->schema->getConfig('maxLimit', 100);
            $this->pagination['limit'] = min($maxLimit, max(1, (int) $limit));
        }
        else
        {
            $this->pagination['limit'] = $this->schema->getConfig('defaultLimit', 20);
        }

        // Calculate offset
        $this->pagination['offset'] = ($this->pagination['page'] - 1) * $this->pagination['limit'];
    }

    /**
     * Parse search parameters
     *
     * @param array $params
     * @return void
     */
    protected function parseSearch(array $params): void
    {
        $this->search = [];

        // Handle various search parameter names
        $searchTerm = $params['search'] ?? $params['q'] ?? $params['query'] ?? null;

        if ($searchTerm)
        {
            $this->search = [
                'term' => $searchTerm,
                'fields' => $params['searchFields'] ?? [],
            ];
        }
    }

    /**
     * Apply filters to query
     *
     * @param FluentQueryBuilder $query
     * @return void
     */
    protected function applyFilters(FluentQueryBuilder $query): void
    {
        foreach ($this->filters as $filter)
        {
            if (count($filter) === 3)
            {
                [$field, $operator, $value] = $filter;

                // Map operators to Doctrix format
                $operatorMap = [
                    '=' => 'eq',
                    '!=' => 'neq',
                    '>' => 'gt',
                    '>=' => 'gte',
                    '<' => 'lt',
                    '<=' => 'lte',
                    'eq' => 'eq',
                    'neq' => 'neq',
                    'gt' => 'gt',
                    'gte' => 'gte',
                    'lt' => 'lt',
                    'lte' => 'lte',
                ];

                $mappedOperator = $operatorMap[$operator] ?? $operator;

                // Apply based on operator
                switch ($mappedOperator)
                {
                    case 'eq':
                        $query->where($field, $value);
                        break;
                    case 'neq':
                        $query->where($field, '!=', $value);
                        break;
                    case 'in':
                        $query->whereIn($field, $value);
                        break;
                    case 'between':
                        if (is_array($value) && count($value) === 2)
                        {
                            $query->whereBetween($field, $value[0], $value[1]);
                        }
                        break;
                    case 'like':
                        $query->whereLike($field, $value);
                        break;
                    case 'is_null':
                        $query->whereNull($field);
                        break;
                    case 'is_not_null':
                        $query->whereNotNull($field);
                        break;
                    default:
                        $query->where($field, $operator, $value);
                        break;
                }
            }
            elseif ($filter[0] === 'or' && isset($filter[1]))
            {
                // Handle OR conditions
                $query->orWhere($filter[1]);
            }
        }
    }

    /**
     * Apply sorting to query
     *
     * @param FluentQueryBuilder $query
     * @return void
     */
    protected function applySorting(FluentQueryBuilder $query): void
    {
        foreach ($this->sorting as $field => $direction)
        {
            $query->orderBy($field, $direction);
        }
    }

    /**
     * Apply pagination to query
     *
     * @param FluentQueryBuilder $query
     * @return void
     */
    protected function applyPagination(FluentQueryBuilder $query): void
    {
        if ($this->pagination['limit'])
        {
            $query->limit($this->pagination['limit']);
            $query->offset($this->pagination['offset']);
        }
    }

    /**
     * Apply search to query
     *
     * @param FluentQueryBuilder $query
     * @return void
     */
    protected function applySearch(FluentQueryBuilder $query): void
    {
        if (empty($this->search))
        {
            return;
        }

        $term = $this->search['term'];
        $fields = $this->search['fields'];

        if (empty($fields))
        {
            // Get all searchable text fields
            $searchableFields = [];
            foreach ($this->schema->getSearchableFields() as $name => $field)
            {
                if ($field->getType() === null || $field->getType() === 'string')
                {
                    $searchableFields[] = $name;
                }
            }
            $fields = $searchableFields;
        }

        if (!empty($fields))
        {
            $searchConditions = [];
            foreach ($fields as $field)
            {
                $searchConditions[] = [$field, 'like', "%$term%"];
            }

            if (count($searchConditions) > 1)
            {
                $query->where(['or' => $searchConditions]);
            }
            elseif (count($searchConditions) === 1)
            {
                $query->whereLike($searchConditions[0][0], $searchConditions[0][2]);
            }
        }
    }
}

/**
 * Exception for request query errors
 */
class RequestQueryException extends Exception
{
    protected array $errors = [];

    public function __construct(string $message, array $errors = [])
    {
        parent::__construct($message);
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
