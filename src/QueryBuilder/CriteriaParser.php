<?php

namespace WelshDev\Doctrix\QueryBuilder;

use Doctrine\ORM\QueryBuilder;
use WelshDev\Doctrix\Operators\OperatorRegistry;

/**
 * Parses criteria arrays and applies them to Doctrine QueryBuilder
 *
 * Supports various criteria formats:
 * - Simple key-value pairs: ["name" => "John"]
 * - Operator arrays: [["age", "gte", 18]]
 * - Logical operators: [["or", [...]]]
 * - Null values: ["deleted" => null]
 * - Boolean values: ["active" => true]
 */
class CriteriaParser
{
    /**
     * The operator registry instance
     *
     * @var OperatorRegistry
     */
    private OperatorRegistry $operatorRegistry;

    /**
     * Parameter counter for unique parameter names
     *
     * @var int
     */
    private int $parameterCounter = 0;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->operatorRegistry = new OperatorRegistry();
    }

    /**
     * Apply criteria to a query builder
     *
     * @param QueryBuilder $qb The query builder
     * @param array $criteria The criteria to apply
     * @param string $alias The main entity alias
     * @return void
     */
    public function applyCriteria(QueryBuilder $qb, array $criteria, string $alias): void
    {
        if (empty($criteria))
        {
            return;
        }

        $conditions = $this->parseCriteria($criteria, $qb, $alias);

        if (!empty($conditions))
        {
            $qb->andWhere($conditions);
        }
    }

    /**
     * Reset the parameter counter
     * Useful for testing or when reusing the parser
     *
     * @return void
     */
    public function resetParameterCounter(): void
    {
        $this->parameterCounter = 0;
    }

    /**
     * Parse criteria array into Doctrine expressions
     *
     * @param array $criteria The criteria to parse
     * @param QueryBuilder $qb The query builder
     * @param string $alias The main entity alias
     * @return string|null The parsed expression or null
     */
    protected function parseCriteria(array $criteria, QueryBuilder $qb, string $alias): ?string
    {
        $expressions = [];

        foreach ($criteria as $key => $value)
        {
            // Handle array-based criteria
            if (is_int($key) && is_array($value))
            {
                $expression = $this->parseArrayCriteria($value, $qb, $alias);
                if ($expression !== null)
                {
                    $expressions[] = $expression;
                }
                continue;
            }

            // Handle simple key-value criteria
            if (is_string($key))
            {
                $expression = $this->parseSimpleCriteria($key, $value, $qb, $alias);
                if ($expression !== null)
                {
                    $expressions[] = $expression;
                }
            }
        }

        if (empty($expressions))
        {
            return null;
        }

        // Combine with AND by default
        return count($expressions) === 1 ? $expressions[0] : '(' . implode(' AND ', $expressions) . ')';
    }

    /**
     * Parse array-based criteria (operators and logical groups)
     *
     * @param array $criteria The array criteria
     * @param QueryBuilder $qb The query builder
     * @param string $alias The main entity alias
     * @return string|null The parsed expression or null
     */
    protected function parseArrayCriteria(array $criteria, QueryBuilder $qb, string $alias): ?string
    {
        if (empty($criteria))
        {
            return null;
        }

        $first = $criteria[0] ?? null;

        // Check for logical operators (or, and, not)
        if ($first === 'or' || $first === 'and' || $first === 'not')
        {
            return $this->parseLogicalOperator($first, $criteria[1] ?? [], $qb, $alias);
        }

        // Check for field operator criteria: ["field", "operator", "value"]
        if (count($criteria) >= 2 && is_string($first))
        {
            $field = $this->normalizeField($first, $alias);
            $operator = $criteria[1] ?? '=';
            $value = $criteria[2] ?? null;

            // Handle special operators
            if ($this->operatorRegistry->hasOperator($operator))
            {
                return $this->operatorRegistry->apply($operator, $qb, $field, $value, $this->generateParameterName());
            }

            // Default to equals
            return $this->createComparison($qb, $field, '=', $value);
        }

        return null;
    }

    /**
     * Parse simple key-value criteria
     *
     * @param string $key The field name
     * @param mixed $value The value to compare
     * @param QueryBuilder $qb The query builder
     * @param string $alias The main entity alias
     * @return string|null The parsed expression or null
     */
    protected function parseSimpleCriteria(string $key, mixed $value, QueryBuilder $qb, string $alias): ?string
    {
        $field = $this->normalizeField($key, $alias);

        // Handle null values
        if ($value === null)
        {
            return $field . ' IS NULL';
        }

        // Handle boolean values
        if (is_bool($value))
        {
            $paramName = $this->generateParameterName();
            $qb->setParameter($paramName, $value);

            return $field . ' = :' . $paramName;
        }

        // Handle array values (IN clause)
        if (is_array($value))
        {
            if (empty($value))
            {
                // Empty array means no matches
                return '1 = 0';
            }
            $paramName = $this->generateParameterName();
            $qb->setParameter($paramName, $value);

            return $field . ' IN (:' . $paramName . ')';
        }

        // Handle regular values
        return $this->createComparison($qb, $field, '=', $value);
    }

    /**
     * Parse logical operators (or, and, not)
     *
     * @param string $operator The logical operator
     * @param array $criteria The nested criteria
     * @param QueryBuilder $qb The query builder
     * @param string $alias The main entity alias
     * @return string|null The parsed expression or null
     */
    protected function parseLogicalOperator(string $operator, array $criteria, QueryBuilder $qb, string $alias): ?string
    {
        if (!is_array($criteria))
        {
            return null;
        }

        $expressions = [];

        // Parse each sub-criteria
        foreach ($criteria as $key => $value)
        {
            if (is_int($key) && is_array($value))
            {
                $expression = $this->parseArrayCriteria($value, $qb, $alias);
            }
            elseif (is_string($key))
            {
                $expression = $this->parseSimpleCriteria($key, $value, $qb, $alias);
            }
            else
            {
                continue;
            }

            if ($expression !== null)
            {
                $expressions[] = $expression;
            }
        }

        if (empty($expressions))
        {
            return null;
        }

        // Combine expressions based on operator
        switch (strtolower($operator))
        {
            case 'or':
                return '(' . implode(' OR ', $expressions) . ')';
            case 'and':
                return '(' . implode(' AND ', $expressions) . ')';
            case 'not':
                return 'NOT (' . implode(' AND ', $expressions) . ')';
            default:
                return null;
        }
    }

    /**
     * Create a comparison expression
     *
     * @param QueryBuilder $qb The query builder
     * @param string $field The field name
     * @param string $operator The comparison operator
     * @param mixed $value The value to compare
     * @return string The comparison expression
     */
    protected function createComparison(QueryBuilder $qb, string $field, string $operator, mixed $value): string
    {
        $paramName = $this->generateParameterName();
        $qb->setParameter($paramName, $value);

        return $field . ' ' . $operator . ' :' . $paramName;
    }

    /**
     * Normalize a field name with proper alias
     *
     * @param string $field The field name
     * @param string $alias The default alias
     * @return string The normalized field name
     */
    protected function normalizeField(string $field, string $alias): string
    {
        // If field already contains a dot, assume it's properly aliased
        if (strpos($field, '.') !== false)
        {
            return $field;
        }

        // Add the default alias
        return $alias . '.' . $field;
    }

    /**
     * Generate a unique parameter name
     *
     * @return string The parameter name
     */
    protected function generateParameterName(): string
    {
        return 'param_' . ++$this->parameterCounter;
    }
}
