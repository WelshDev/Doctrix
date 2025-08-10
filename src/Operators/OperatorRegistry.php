<?php

namespace WelshDev\Doctrix\Operators;

use Doctrine\ORM\QueryBuilder;
use WelshDev\Doctrix\Interfaces\OperatorInterface;

/**
 * Registry for managing query operators
 *
 * Provides a central place to register and apply custom operators
 * like 'gte', 'lte', 'like', 'is_null', etc.
 */
class OperatorRegistry
{
    /**
     * Registered operators
     *
     * @var array<string, OperatorInterface>
     */
    private array $operators = [];

    /**
     * Constructor - registers default operators
     */
    public function __construct()
    {
        $this->registerDefaultOperators();
    }

    /**
     * Register a custom operator
     *
     * @param string $name The operator name
     * @param OperatorInterface $operator The operator implementation
     * @return void
     */
    public function register(string $name, OperatorInterface $operator): void
    {
        $this->operators[strtolower($name)] = $operator;
    }

    /**
     * Check if an operator is registered
     *
     * @param string $name The operator name
     * @return bool True if registered
     */
    public function hasOperator(string $name): bool
    {
        return isset($this->operators[strtolower($name)]);
    }

    /**
     * Apply an operator to a query
     *
     * @param string $operator The operator name
     * @param QueryBuilder $qb The query builder
     * @param string $field The field name
     * @param mixed $value The value
     * @param string $paramName The parameter name to use
     * @return string|null The expression or null if operator not found
     */
    public function apply(string $operator, QueryBuilder $qb, string $field, mixed $value, string $paramName): ?string
    {
        $operator = strtolower($operator);

        if (!isset($this->operators[$operator]))
        {
            return null;
        }

        return $this->operators[$operator]->apply($qb, $field, $value, $paramName);
    }

    /**
     * Get all registered operator names
     *
     * @return array<string> The operator names
     */
    public function getOperatorNames(): array
    {
        return array_keys($this->operators);
    }

    /**
     * Register default operators
     *
     * @return void
     */
    protected function registerDefaultOperators(): void
    {
        // Comparison operators
        $this->register('eq', new ComparisonOperator('='));
        $this->register('=', new ComparisonOperator('='));
        $this->register('neq', new ComparisonOperator('!='));
        $this->register('!=', new ComparisonOperator('!='));
        $this->register('lt', new ComparisonOperator('<'));
        $this->register('<', new ComparisonOperator('<'));
        $this->register('lte', new ComparisonOperator('<='));
        $this->register('<=', new ComparisonOperator('<='));
        $this->register('gt', new ComparisonOperator('>'));
        $this->register('>', new ComparisonOperator('>'));
        $this->register('gte', new ComparisonOperator('>='));
        $this->register('>=', new ComparisonOperator('>='));

        // Text operators
        $this->register('like', new TextOperator('like'));
        $this->register('not_like', new TextOperator('not_like'));
        $this->register('contains', new TextOperator('contains'));
        $this->register('starts_with', new TextOperator('starts_with'));
        $this->register('ends_with', new TextOperator('ends_with'));

        // Null operators
        $this->register('is_null', new NullOperator(true));
        $this->register('is_not_null', new NullOperator(false));

        // Collection operators
        $this->register('in', new CollectionOperator('in'));
        $this->register('not_in', new CollectionOperator('not_in'));
        $this->register('between', new CollectionOperator('between'));
        $this->register('not_between', new CollectionOperator('not_between'));
    }
}
