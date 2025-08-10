<?php

namespace WelshDev\Doctrix\Interfaces;

use Doctrine\ORM\QueryBuilder;

/**
 * Interface for query operators
 *
 * All custom operators must implement this interface to be usable
 * in the criteria parser
 */
interface OperatorInterface
{
    /**
     * Apply the operator to a query
     *
     * @param QueryBuilder $qb The query builder
     * @param string $field The field name (already normalized with alias)
     * @param mixed $value The value to compare/use
     * @param string $paramName The parameter name to use for binding
     * @return string The expression to add to the query
     */
    public function apply(QueryBuilder $qb, string $field, mixed $value, string $paramName): string;
}
