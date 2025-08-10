<?php

namespace WelshDev\Doctrix\Operators;

use Doctrine\ORM\QueryBuilder;
use WelshDev\Doctrix\Interfaces\OperatorInterface;

/**
 * Handles comparison operators (=, !=, <, <=, >, >=)
 */
class ComparisonOperator implements OperatorInterface
{
    /**
     * The SQL operator to use
     *
     * @var string
     */
    private string $sqlOperator;

    /**
     * Constructor
     *
     * @param string $sqlOperator The SQL operator (=, !=, <, etc.)
     */
    public function __construct(string $sqlOperator)
    {
        $this->sqlOperator = $sqlOperator;
    }

    /**
     * Apply the comparison operator
     *
     * @param QueryBuilder $qb The query builder
     * @param string $field The field name
     * @param mixed $value The value to compare
     * @param string $paramName The parameter name
     * @return string The expression
     */
    public function apply(QueryBuilder $qb, string $field, mixed $value, string $paramName): string
    {
        $qb->setParameter($paramName, $value);

        return $field . ' ' . $this->sqlOperator . ' :' . $paramName;
    }
}
