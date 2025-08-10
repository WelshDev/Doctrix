<?php

namespace WelshDev\Doctrix\Operators;

use Doctrine\ORM\QueryBuilder;
use WelshDev\Doctrix\Interfaces\OperatorInterface;

/**
 * Handles text-based operators (like, contains, starts_with, ends_with)
 */
class TextOperator implements OperatorInterface
{
    /**
     * The operator type
     *
     * @var string
     */
    private string $type;

    /**
     * Constructor
     *
     * @param string $type The operator type (like, contains, etc.)
     */
    public function __construct(string $type)
    {
        $this->type = $type;
    }

    /**
     * Apply the text operator
     *
     * @param QueryBuilder $qb The query builder
     * @param string $field The field name
     * @param mixed $value The value to search for
     * @param string $paramName The parameter name
     * @return string The expression
     */
    public function apply(QueryBuilder $qb, string $field, mixed $value, string $paramName): string
    {
        $value = (string) $value;

        switch ($this->type)
        {
            case 'like':
                $qb->setParameter($paramName, $value);

                return $field . ' LIKE :' . $paramName;
            case 'not_like':
                $qb->setParameter($paramName, $value);

                return $field . ' NOT LIKE :' . $paramName;
            case 'contains':
                $qb->setParameter($paramName, '%' . $value . '%');

                return $field . ' LIKE :' . $paramName;
            case 'starts_with':
                $qb->setParameter($paramName, $value . '%');

                return $field . ' LIKE :' . $paramName;
            case 'ends_with':
                $qb->setParameter($paramName, '%' . $value);

                return $field . ' LIKE :' . $paramName;
            default:
                // Fallback to LIKE
                $qb->setParameter($paramName, $value);

                return $field . ' LIKE :' . $paramName;
        }
    }
}
