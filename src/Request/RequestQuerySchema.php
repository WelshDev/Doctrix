<?php

namespace WelshDev\Doctrix\Request;

use DateTime;

/**
 * Schema for defining how request parameters map to query criteria
 *
 * Provides a flexible way to configure which fields can be searched,
 * sorted, and filtered with specific validation rules
 */
class RequestQuerySchema
{
    /**
     * Field definitions
     *
     * @var array<string, RequestFieldSchema>
     */
    protected array $fields = [];

    /**
     * Global configuration
     *
     * @var array
     */
    protected array $config = [
        'maxLimit' => 100,
        'defaultLimit' => 20,
        'maxFilters' => 20,
        'maxInValues' => 100,
        'allowDeepRelations' => false,
        'requirePagination' => false,
        'parameterStyle' => 'standard', // 'standard', 'jsonapi', 'graphql'
        'strictMode' => true, // Reject unknown parameters
    ];

    /**
     * Default values for filters
     *
     * @var array
     */
    protected array $defaults = [];

    /**
     * Required fields
     *
     * @var array
     */
    protected array $required = [];

    /**
     * Field aliases (map request param to entity field)
     *
     * @var array<string, string>
     */
    protected array $aliases = [];

    /**
     * Define a field in the schema
     *
     * @param string $name The field name
     * @return RequestFieldSchema
     */
    public function field(string $name): RequestFieldSchema
    {
        if (!isset($this->fields[$name]))
        {
            $this->fields[$name] = new RequestFieldSchema($name, $this);
        }

        return $this->fields[$name];
    }

    /**
     * Define multiple fields at once
     *
     * @param array $fields
     * @return self
     */
    public function fields(array $fields): self
    {
        foreach ($fields as $field)
        {
            $this->field($field);
        }

        return $this;
    }

    /**
     * Mark fields as searchable
     *
     * @param array $fields
     * @param array $operators Allowed operators for these fields
     * @return self
     */
    public function searchable(array $fields, array $operators = ['eq']): self
    {
        foreach ($fields as $field)
        {
            $this->field($field)->searchable($operators);
        }

        return $this;
    }

    /**
     * Mark fields as sortable
     *
     * @param array $fields
     * @return self
     */
    public function sortable(array $fields): self
    {
        foreach ($fields as $field)
        {
            $this->field($field)->sortable();
        }

        return $this;
    }

    /**
     * Set default filter values
     *
     * @param array $defaults
     * @return self
     */
    public function defaults(array $defaults): self
    {
        $this->defaults = array_merge($this->defaults, $defaults);

        return $this;
    }

    /**
     * Mark fields as required
     *
     * @param array|string $fields
     * @return self
     */
    public function require($fields): self
    {
        $fields = (array) $fields;
        foreach ($fields as $field)
        {
            $this->required[] = $field;
            $this->field($field)->required();
        }

        return $this;
    }

    /**
     * Set field alias
     *
     * @param string $requestParam The parameter name in the request
     * @param string $entityField The actual entity field name
     * @return self
     */
    public function alias(string $requestParam, string $entityField): self
    {
        $this->aliases[$requestParam] = $entityField;

        return $this;
    }

    /**
     * Set multiple aliases
     *
     * @param array $aliases
     * @return self
     */
    public function aliases(array $aliases): self
    {
        $this->aliases = array_merge($this->aliases, $aliases);

        return $this;
    }

    /**
     * Configure schema options
     *
     * @param array $config
     * @return self
     */
    public function configure(array $config): self
    {
        $this->config = array_merge($this->config, $config);

        return $this;
    }

    /**
     * Set maximum result limit
     *
     * @param int $limit
     * @return self
     */
    public function maxLimit(int $limit): self
    {
        $this->config['maxLimit'] = $limit;

        return $this;
    }

    /**
     * Set default result limit
     *
     * @param int $limit
     * @return self
     */
    public function defaultLimit(int $limit): self
    {
        $this->config['defaultLimit'] = $limit;

        return $this;
    }

    /**
     * Enable/disable strict mode
     *
     * @param bool $strict
     * @return self
     */
    public function strictMode(bool $strict = true): self
    {
        $this->config['strictMode'] = $strict;

        return $this;
    }

    /**
     * Allow filtering on relations
     *
     * @param array $relations Map of relation names to allowed fields
     * @return self
     */
    public function allowRelations(array $relations): self
    {
        foreach ($relations as $relation => $fields)
        {
            foreach ($fields as $field)
            {
                $this->field("$relation.$field")
                    ->searchable()
                    ->relation($relation);
            }
        }

        $this->config['allowDeepRelations'] = true;

        return $this;
    }

    /**
     * Get field schema
     *
     * @param string $name
     * @return RequestFieldSchema|null
     */
    public function getField(string $name): ?RequestFieldSchema
    {
        // Check direct field
        if (isset($this->fields[$name]))
        {
            return $this->fields[$name];
        }

        // Check aliases
        if (isset($this->aliases[$name]))
        {
            $actualField = $this->aliases[$name];
            if (isset($this->fields[$actualField]))
            {
                return $this->fields[$actualField];
            }
        }

        return null;
    }

    /**
     * Get all searchable fields
     *
     * @return array
     */
    public function getSearchableFields(): array
    {
        $searchable = [];
        foreach ($this->fields as $name => $field)
        {
            if ($field->isSearchable())
            {
                $searchable[$name] = $field;
            }
        }

        return $searchable;
    }

    /**
     * Get all sortable fields
     *
     * @return array
     */
    public function getSortableFields(): array
    {
        $sortable = [];
        foreach ($this->fields as $name => $field)
        {
            if ($field->isSortable())
            {
                $sortable[$name] = $field;
            }
        }

        return $sortable;
    }

    /**
     * Validate request parameters against schema
     *
     * @param array $params
     * @return array Validation errors
     */
    public function validate(array $params): array
    {
        $errors = [];

        // Check required fields
        foreach ($this->required as $field)
        {
            if (!isset($params[$field]) && !isset($params['filter'][$field]))
            {
                $errors[] = "Required field '$field' is missing";
            }
        }

        // Check unknown fields in strict mode
        if ($this->config['strictMode'])
        {
            foreach ($params as $key => $value)
            {
                if (in_array($key, ['page', 'limit', 'sort', 'order', 'filter', 'search']))
                {
                    continue;
                }

                if (!$this->getField($key))
                {
                    $errors[] = "Unknown field '$key'";
                }
            }
        }

        // Validate field values
        foreach ($params as $key => $value)
        {
            $field = $this->getField($key);
            if ($field)
            {
                $fieldErrors = $field->validate($value);
                $errors = array_merge($errors, $fieldErrors);
            }
        }

        return $errors;
    }

    /**
     * Get configuration value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getConfig(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Get default values
     *
     * @return array
     */
    public function getDefaults(): array
    {
        return $this->defaults;
    }

    /**
     * Resolve field name considering aliases
     *
     * @param string $name
     * @return string
     */
    public function resolveFieldName(string $name): string
    {
        return $this->aliases[$name] ?? $name;
    }

    /**
     * Create a preset schema for common use cases
     *
     * @param string $preset
     * @return self
     */
    public static function preset(string $preset): self
    {
        $schema = new self();

        switch ($preset)
        {
            case 'basic':
                $schema->configure([
                    'maxLimit' => 100,
                    'defaultLimit' => 20,
                    'strictMode' => false,
                ]);
                break;
            case 'strict':
                $schema->configure([
                    'maxLimit' => 50,
                    'defaultLimit' => 10,
                    'strictMode' => true,
                    'requirePagination' => true,
                ]);
                break;
            case 'api':
                $schema->configure([
                    'maxLimit' => 100,
                    'defaultLimit' => 20,
                    'parameterStyle' => 'jsonapi',
                    'strictMode' => true,
                ]);
                break;
            case 'admin':
                $schema->configure([
                    'maxLimit' => 1000,
                    'defaultLimit' => 50,
                    'strictMode' => false,
                    'allowDeepRelations' => true,
                ]);
                break;
        }

        return $schema;
    }
}

/**
 * Schema for individual field configuration
 */
class RequestFieldSchema
{
    private string $name;
    private RequestQuerySchema $schema;
    private bool $searchable = false;
    private bool $sortable = false;
    private bool $required = false;
    private array $operators = ['eq'];
    private array $validators = [];
    private $transformer;
    private ?string $type = null;
    private ?string $relation = null;
    private array $enumValues = [];
    private array $validation = [];

    public function __construct(string $name, RequestQuerySchema $schema)
    {
        $this->name = $name;
        $this->schema = $schema;
    }

    /**
     * Mark field as searchable
     *
     * @param array $operators Allowed operators
     * @return self
     */
    public function searchable(array $operators = ['eq']): self
    {
        $this->searchable = true;
        $this->operators = $operators;

        return $this;
    }

    /**
     * Mark field as sortable
     *
     * @return self
     */
    public function sortable(): self
    {
        $this->sortable = true;

        return $this;
    }

    /**
     * Mark field as required
     *
     * @return self
     */
    public function required(): self
    {
        $this->required = true;

        return $this;
    }

    /**
     * Set field type for validation
     *
     * @param string $type
     * @return self
     */
    public function type(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Set allowed enum values
     *
     * @param array $values
     * @return self
     */
    public function enum(array $values): self
    {
        $this->enumValues = $values;

        return $this;
    }

    /**
     * Set allowed operators
     *
     * @param array $operators
     * @return self
     */
    public function operators(array $operators): self
    {
        $this->operators = $operators;

        return $this;
    }

    /**
     * Add a validator function
     *
     * @param callable $validator
     * @return self
     */
    public function validator(callable $validator): self
    {
        $this->validators[] = $validator;

        return $this;
    }

    /**
     * Set value transformer
     *
     * @param callable $transformer
     * @return self
     */
    public function transform(callable $transformer): self
    {
        $this->transformer = $transformer;

        return $this;
    }

    /**
     * Configure date/datetime field
     *
     * @param string $format
     * @return self
     */
    public function date(string $format = 'Y-m-d'): self
    {
        $this->type = 'date';
        $this->validation['format'] = $format;
        $this->transform(function ($value) use ($format)
        {
            if ($value instanceof DateTime)
            {
                return $value;
            }

            return DateTime::createFromFormat($format, $value);
        });

        return $this;
    }

    /**
     * Configure numeric field
     *
     * @param float|null $min
     * @param float|null $max
     * @return self
     */
    public function numeric(?float $min = null, ?float $max = null): self
    {
        $this->type = 'numeric';
        if ($min !== null)
        {
            $this->validation['min'] = $min;
        }
        if ($max !== null)
        {
            $this->validation['max'] = $max;
        }
        $this->transform(fn($value) => is_numeric($value) ? (float) $value : null);

        return $this;
    }

    /**
     * Configure boolean field
     *
     * @return self
     */
    public function boolean(): self
    {
        $this->type = 'boolean';
        $this->transform(function ($value)
        {
            if (is_bool($value))
            {
                return $value;
            }
            if (in_array($value, ['true', '1', 1, 'yes'], true))
            {
                return true;
            }
            if (in_array($value, ['false', '0', 0, 'no'], true))
            {
                return false;
            }
        });

        return $this;
    }

    /**
     * Mark as relation field
     *
     * @param string $relation
     * @return self
     */
    public function relation(string $relation): self
    {
        $this->relation = $relation;

        return $this;
    }

    /**
     * Validate field value
     *
     * @param mixed $value
     * @return array Errors
     */
    public function validate($value): array
    {
        $errors = [];

        // Check enum values
        if (!empty($this->enumValues) && !in_array($value, $this->enumValues))
        {
            $errors[] = "Field '{$this->name}' must be one of: " . implode(', ', $this->enumValues);
        }

        // Type validation
        if ($this->type)
        {
            switch ($this->type)
            {
                case 'numeric':
                    if (!is_numeric($value))
                    {
                        $errors[] = "Field '{$this->name}' must be numeric";
                    }
                    else
                    {
                        if (isset($this->validation['min']) && $value < $this->validation['min'])
                        {
                            $errors[] = "Field '{$this->name}' must be >= {$this->validation['min']}";
                        }
                        if (isset($this->validation['max']) && $value > $this->validation['max'])
                        {
                            $errors[] = "Field '{$this->name}' must be <= {$this->validation['max']}";
                        }
                    }
                    break;
                case 'date':
                    $format = $this->validation['format'] ?? 'Y-m-d';
                    $date = DateTime::createFromFormat($format, $value);
                    if (!$date || $date->format($format) !== $value)
                    {
                        $errors[] = "Field '{$this->name}' must be a valid date in format: $format";
                    }
                    break;
            }
        }

        // Custom validators
        foreach ($this->validators as $validator)
        {
            $result = $validator($value);
            if ($result !== true)
            {
                $errors[] = is_string($result) ? $result : "Field '{$this->name}' validation failed";
            }
        }

        return $errors;
    }

    /**
     * Transform field value
     *
     * @param mixed $value
     * @return mixed
     */
    public function transformValue($value)
    {
        if ($this->transformer)
        {
            return ($this->transformer)($value);
        }

        return $value;
    }

    // Getters
    public function getName(): string
    {
        return $this->name;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    public function isSortable(): bool
    {
        return $this->sortable;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function getOperators(): array
    {
        return $this->operators;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getRelation(): ?string
    {
        return $this->relation;
    }
}
