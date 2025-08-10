<?php

namespace WelshDev\Doctrix\Operators;

use Doctrine\ORM\QueryBuilder;
use InvalidArgumentException;
use WelshDev\Doctrix\Interfaces\OperatorInterface;

/**
 * Handles collection-based operators (in, not_in, between, not_between)
 */
class CollectionOperator implements OperatorInterface
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
     * @param string $type The operator type (in, not_in, between, not_between)
     */
    public function __construct(string $type)
    {
        $this->type = $type;
    }

    /**
     * Apply the collection operator
     *
     * @param QueryBuilder $qb The query builder
     * @param string $field The field name
     * @param mixed $value The value (array for IN, array with 2 elements for BETWEEN)
     * @param string $paramName The parameter name
     * @return string The expression
     */
    public function apply(QueryBuilder $qb, string $field, mixed $value, string $paramName): string
    {
        switch ($this->type)
        {
            case 'in':
                if (!is_array($value))
                {
                    $value = [$value];
                }
                if (empty($value))
                {
                    // Empty IN clause - always false
                    return '1 = 0';
                }
                $qb->setParameter($paramName, $value);

                return $field . ' IN (:' . $paramName . ')';
            case 'not_in':
                if (!is_array($value))
                {
                    $value = [$value];
                }
                if (empty($value))
                {
                    // Empty NOT IN clause - always true
                    return '1 = 1';
                }
                $qb->setParameter($paramName, $value);

                return $field . ' NOT IN (:' . $paramName . ')';
            case 'between':
                if (!is_array($value) || count($value) !== 2)
                {
                    throw new InvalidArgumentException('BETWEEN operator requires an array with exactly 2 elements');
                }
                $paramName1 = $paramName . '_1';
                $paramName2 = $paramName . '_2';
                $qb->setParameter($paramName1, $value[0]);
                $qb->setParameter($paramName2, $value[1]);

                return $field . ' BETWEEN :' . $paramName1 . ' AND :' . $paramName2;
            case 'not_between':
                if (!is_array($value) || count($value) !== 2)
                {
                    throw new InvalidArgumentException('NOT BETWEEN operator requires an array with exactly 2 elements');
                }
                $paramName1 = $paramName . '_1';
                $paramName2 = $paramName . '_2';
                $qb->setParameter($paramName1, $value[0]);
                $qb->setParameter($paramName2, $value[1]);

                return $field . ' NOT BETWEEN :' . $paramName1 . ' AND :' . $paramName2;
            default:
                throw new InvalidArgumentException('Unknown collection operator: ' . $this->type);
        }
    }
}
