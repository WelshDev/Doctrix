<?php

namespace WelshDev\Doctrix\Traits;

use InvalidArgumentException;

/**
 * Trait for checking entity relationships
 *
 * Provides methods for checking if entities have related data
 */
trait RelationshipCheckTrait
{
    /**
     * Check if entities have a relationship
     *
     * @param string $relation Relationship name
     * @param string|callable|null $operatorOrCallback Optional comparison operator or callback
     * @param mixed $value Optional value for comparison
     * @param array $criteria Optional additional criteria
     * @return \Doctrine\ORM\QueryBuilder Query builder for chaining
     *
     * @example
     * // Users with orders
     * $usersWithOrders = $repo->has('orders')->fetch();
     *
     * // Users with more than 5 orders
     * $activeUsers = $repo->has('orders', '>', 5)->fetch();
     *
     * // Users with pending orders
     * $users = $repo->has('orders', function($qb) {
     *     $qb->where('o.status = :status')
     *        ->setParameter('status', 'pending');
     * })->fetch();
     */
    public function has(
        string $relation,
        $operatorOrCallback = null,
        $value = null,
        array $criteria = [],
    ) {
        $qb = $this->buildQuery($criteria);
        $alias = $this->getAlias();
        $relationAlias = substr($relation, 0, 1);

        // Handle callback for complex conditions
        if (is_callable($operatorOrCallback))
        {
            $qb->leftJoin("{$alias}.{$relation}", $relationAlias);
            $operatorOrCallback($qb);
            $qb->andWhere($qb->expr()->isNotNull($relationAlias));

            return $qb;
        }

        // Handle count comparisons
        if ($operatorOrCallback !== null && $value !== null)
        {
            $countAlias = 'rel_count';
            $subQb = $this->getEntityManager()->createQueryBuilder();
            $subQb->select("COUNT({$relationAlias}.id)")
                  ->from($this->getEntityName(), 'sub')
                  ->leftJoin("sub.{$relation}", $relationAlias)
                  ->where("sub.id = {$alias}.id");

            $countExpr = '(' . $subQb->getDQL() . ')';

            switch ($operatorOrCallback)
            {
                case '>':
                    $qb->andWhere("{$countExpr} > :count");
                    break;
                case '>=':
                    $qb->andWhere("{$countExpr} >= :count");
                    break;
                case '<':
                    $qb->andWhere("{$countExpr} < :count");
                    break;
                case '<=':
                    $qb->andWhere("{$countExpr} <= :count");
                    break;
                case '=':
                case '==':
                    $qb->andWhere("{$countExpr} = :count");
                    break;
                case '!=':
                case '<>':
                    $qb->andWhere("{$countExpr} <> :count");
                    break;
                default:
                    throw new InvalidArgumentException("Invalid operator: {$operatorOrCallback}");
            }

            $qb->setParameter('count', $value);
        }
        else
        {
            // Simple existence check
            $qb->innerJoin("{$alias}.{$relation}", $relationAlias);
        }

        return $qb;
    }

    /**
     * Check if entities don't have a relationship
     *
     * @param string $relation Relationship name
     * @param array $criteria Optional additional criteria
     * @return \Doctrine\ORM\QueryBuilder Query builder for chaining
     *
     * @example
     * // Users without any orders
     * $newUsers = $repo->doesntHave('orders')->fetch();
     */
    public function doesntHave(string $relation, array $criteria = [])
    {
        $qb = $this->buildQuery($criteria);
        $alias = $this->getAlias();
        $relationAlias = substr($relation, 0, 1) . '_rel';

        $qb->leftJoin("{$alias}.{$relation}", $relationAlias)
           ->andWhere($qb->expr()->isNull("{$relationAlias}.id"));

        return $qb;
    }

    /**
     * Filter entities by relationship count
     *
     * @param string $relation Relationship name
     * @param int $count Exact count
     * @param array $criteria Optional additional criteria
     * @return \Doctrine\ORM\QueryBuilder Query builder for chaining
     *
     * @example
     * // Users with exactly 3 orders
     * $users = $repo->hasCount('orders', 3)->fetch();
     */
    public function hasCount(string $relation, int $count, array $criteria = [])
    {
        return $this->has($relation, '=', $count, $criteria);
    }

    /**
     * Check if entity has any of the specified relationships
     *
     * @param array $relations Array of relationship names
     * @param array $criteria Optional additional criteria
     * @return \Doctrine\ORM\QueryBuilder Query builder for chaining
     *
     * @example
     * // Users with orders OR reviews
     * $activeUsers = $repo->hasAnyRelation(['orders', 'reviews'])->fetch();
     */
    public function hasAnyRelation(array $relations, array $criteria = [])
    {
        $qb = $this->buildQuery($criteria);
        $alias = $this->getAlias();
        $orX = $qb->expr()->orX();

        foreach ($relations as $relation)
        {
            $relationAlias = substr($relation, 0, 1) . '_' . uniqid();
            $qb->leftJoin("{$alias}.{$relation}", $relationAlias);
            $orX->add($qb->expr()->isNotNull("{$relationAlias}.id"));
        }

        $qb->andWhere($orX);

        return $qb;
    }

    /**
     * Check if entity has all of the specified relationships
     *
     * @param array $relations Array of relationship names
     * @param array $criteria Optional additional criteria
     * @return \Doctrine\ORM\QueryBuilder Query builder for chaining
     *
     * @example
     * // Users with both orders AND reviews
     * $valuableUsers = $repo->hasAllRelations(['orders', 'reviews'])->fetch();
     */
    public function hasAllRelations(array $relations, array $criteria = [])
    {
        $qb = $this->buildQuery($criteria);
        $alias = $this->getAlias();

        foreach ($relations as $relation)
        {
            $relationAlias = substr($relation, 0, 1) . '_' . uniqid();
            $qb->innerJoin("{$alias}.{$relation}", $relationAlias);
        }

        return $qb;
    }

    /**
     * Filter by relationship field value
     *
     * @param string $relation Relationship name
     * @param string $field Field in the related entity
     * @param mixed $value Value to match
     * @param array $criteria Optional additional criteria
     * @return \Doctrine\ORM\QueryBuilder Query builder for chaining
     *
     * @example
     * // Users with pending orders
     * $users = $repo->whereHas('orders', 'status', 'pending')->fetch();
     */
    public function whereHas(
        string $relation,
        string $field,
        $value,
        array $criteria = [],
    ) {
        $qb = $this->buildQuery($criteria);
        $alias = $this->getAlias();
        $relationAlias = substr($relation, 0, 1) . '_wh';

        $qb->innerJoin("{$alias}.{$relation}", $relationAlias)
           ->andWhere("{$relationAlias}.{$field} = :rel_value")
           ->setParameter('rel_value', $value);

        return $qb;
    }

    /**
     * Filter by multiple relationship conditions
     *
     * @param string $relation Relationship name
     * @param array $conditions Field => value pairs
     * @param array $criteria Optional additional criteria
     * @return \Doctrine\ORM\QueryBuilder Query builder for chaining
     *
     * @example
     * // Users with high-value pending orders
     * $users = $repo->whereRelation('orders', [
     *     'status' => 'pending',
     *     'total' => ['>', 1000]
     * ])->fetch();
     */
    public function whereRelation(
        string $relation,
        array $conditions,
        array $criteria = [],
    ) {
        $qb = $this->buildQuery($criteria);
        $alias = $this->getAlias();
        $relationAlias = substr($relation, 0, 1) . '_wr';

        $qb->innerJoin("{$alias}.{$relation}", $relationAlias);

        foreach ($conditions as $field => $value)
        {
            if (is_array($value) && count($value) === 2)
            {
                // Handle operator conditions
                [$operator, $val] = $value;
                $paramName = 'rel_' . str_replace('.', '_', $field) . '_' . uniqid();

                switch ($operator)
                {
                    case '>':
                        $qb->andWhere("{$relationAlias}.{$field} > :{$paramName}");
                        break;
                    case '>=':
                        $qb->andWhere("{$relationAlias}.{$field} >= :{$paramName}");
                        break;
                    case '<':
                        $qb->andWhere("{$relationAlias}.{$field} < :{$paramName}");
                        break;
                    case '<=':
                        $qb->andWhere("{$relationAlias}.{$field} <= :{$paramName}");
                        break;
                    case '!=':
                    case '<>':
                        $qb->andWhere("{$relationAlias}.{$field} <> :{$paramName}");
                        break;
                    default:
                        $qb->andWhere("{$relationAlias}.{$field} = :{$paramName}");
                }

                $qb->setParameter($paramName, $val);
            }
            else
            {
                // Simple equality
                $paramName = 'rel_' . str_replace('.', '_', $field) . '_' . uniqid();
                $qb->andWhere("{$relationAlias}.{$field} = :{$paramName}")
                   ->setParameter($paramName, $value);
            }
        }

        return $qb;
    }

    /**
     * Load relationships for performance optimization
     *
     * @param array|string $relations Relations to eager load
     * @param array $criteria Optional criteria
     * @return array Entities with loaded relationships
     *
     * @example
     * // Eager load orders and reviews
     * $users = $repo->withRelations(['orders', 'reviews']);
     */
    public function withRelations($relations, array $criteria = []): array
    {
        $qb = $this->buildQuery($criteria);
        $alias = $this->getAlias();

        if (!is_array($relations))
        {
            $relations = [$relations];
        }

        foreach ($relations as $relation)
        {
            $relationAlias = substr($relation, 0, 1) . '_with';
            $qb->leftJoin("{$alias}.{$relation}", $relationAlias)
               ->addSelect($relationAlias);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Count related entities
     *
     * @param string $relation Relationship name
     * @param array $criteria Optional criteria
     * @return array Array with entity and count
     *
     * @example
     * // Get users with their order counts
     * $userCounts = $repo->withCount('orders');
     * // Returns: [['entity' => $user, 'orders_count' => 5], ...]
     */
    public function withCount(string $relation, array $criteria = []): array
    {
        $qb = $this->buildQuery($criteria);
        $alias = $this->getAlias();
        $relationAlias = substr($relation, 0, 1) . '_cnt';
        $countAlias = $relation . '_count';

        $qb->leftJoin("{$alias}.{$relation}", $relationAlias)
           ->addSelect("COUNT({$relationAlias}.id) as {$countAlias}")
           ->groupBy("{$alias}.id");

        $results = $qb->getQuery()->getResult();

        // Format results
        $formatted = [];
        foreach ($results as $row)
        {
            if (is_array($row))
            {
                $formatted[] = [
                    'entity' => $row[0],
                    $countAlias => $row[$countAlias],
                ];
            }
            else
            {
                $formatted[] = [
                    'entity' => $row,
                    $countAlias => 0,
                ];
            }
        }

        return $formatted;
    }
}
