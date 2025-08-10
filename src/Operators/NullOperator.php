<?php

namespace WelshDev\Doctrix\Operators;

use Doctrine\ORM\QueryBuilder;
use WelshDev\Doctrix\Interfaces\OperatorInterface;

/**
 * Handles NULL checking operators (is_null, is_not_null)
 */
class NullOperator implements OperatorInterface
{
    /**
     * Whether to check for NULL or NOT NULL
     *
     * @var bool
     */
    private bool $isNull;

    /**
     * Constructor
     *
     * @param bool $isNull True for IS NULL, false for IS NOT NULL
     */
    public function __construct(bool $isNull)
    {
        $this->isNull = $isNull;
    }

    /**
     * Apply the null operator
     *
     * @param QueryBuilder $qb The query builder
     * @param string $field The field name
     * @param mixed $value The value (ignored for null checks)
     * @param string $paramName The parameter name (unused)
     * @return string The expression
     */
    public function apply(QueryBuilder $qb, string $field, mixed $value, string $paramName): string
    {
        // Value parameter is ignored for null checks
        // Some users might pass true/false to indicate the check type
        if (is_bool($value))
        {
            return $value ? $field . ' IS NULL' : $field . ' IS NOT NULL';
        }

        // Use the configured behavior
        return $this->isNull ? $field . ' IS NULL' : $field . ' IS NOT NULL';
    }
}
