<?php

namespace WelshDev\Doctrix\QueryBuilder;

use Doctrine\ORM\QueryBuilder;
use InvalidArgumentException;

/**
 * Manages joins for query building
 *
 * Handles:
 * - Automatic join detection from dot notation
 * - Manual join configuration
 * - Join deduplication
 * - Nested joins
 */
class JoinManager
{
    /**
     * Track applied joins to prevent duplicates
     *
     * @var array<string, bool>
     */
    private array $appliedJoins = [];

    /**
     * Apply configured joins to a query builder
     *
     * @param QueryBuilder $qb The query builder
     * @param array $joins The join configuration
     * @return void
     */
    public function applyJoins(QueryBuilder $qb, array $joins): void
    {
        foreach ($joins as $join)
        {
            if (!is_array($join) || count($join) < 3)
            {
                continue;
            }

            [$type, $relation, $alias] = $join;
            $condition = $join[3] ?? null;
            $conditionType = $join[4] ?? null;

            $this->addJoin($qb, $type, $relation, $alias, $condition, $conditionType);
        }
    }

    /**
     * Add a single join to the query builder
     *
     * @param QueryBuilder $qb The query builder
     * @param string $type The join type (leftJoin, innerJoin, etc.)
     * @param string $relation The relation to join
     * @param string $alias The alias for the joined entity
     * @param string|null $condition Optional join condition
     * @param string|null $conditionType Optional condition type (WITH or ON)
     * @return void
     */
    public function addJoin(
        QueryBuilder $qb,
        string $type,
        string $relation,
        string $alias,
        ?string $condition = null,
        ?string $conditionType = null,
    ): void {
        // Check if this join was already applied
        $joinKey = $type . '_' . $relation . '_' . $alias;
        if (isset($this->appliedJoins[$joinKey]))
        {
            return;
        }

        // Apply the join based on type
        switch (strtolower($type))
        {
            case 'innerjoin':
            case 'inner':
                if ($condition !== null)
                {
                    $qb->innerJoin($relation, $alias, $conditionType ?? 'WITH', $condition);
                }
                else
                {
                    $qb->innerJoin($relation, $alias);
                }
                break;
            case 'leftjoin':
            case 'left':
                if ($condition !== null)
                {
                    $qb->leftJoin($relation, $alias, $conditionType ?? 'WITH', $condition);
                }
                else
                {
                    $qb->leftJoin($relation, $alias);
                }
                break;
            case 'rightjoin':
            case 'right':
                if ($condition !== null)
                {
                    $qb->rightJoin($relation, $alias, $conditionType ?? 'WITH', $condition);
                }
                else
                {
                    $qb->rightJoin($relation, $alias);
                }
                break;
            default:
                throw new InvalidArgumentException('Unknown join type: ' . $type);
        }

        // Mark as applied
        $this->appliedJoins[$joinKey] = true;
    }

    /**
     * Detect and apply joins from dot notation in field names
     *
     * @param QueryBuilder $qb The query builder
     * @param string $field The field name with potential dot notation
     * @param string $rootAlias The root entity alias
     * @return string The final field reference with proper alias
     */
    public function detectAndApplyJoins(QueryBuilder $qb, string $field, string $rootAlias): string
    {
        // Split field by dots
        $parts = explode('.', $field);

        if (count($parts) === 1)
        {
            // No dots, just return with root alias
            return $rootAlias . '.' . $field;
        }

        // If first part is an alias, use as-is
        $aliases = $this->getExistingAliases($qb);
        if (in_array($parts[0], $aliases))
        {
            return $field;
        }

        // Build joins for nested relations
        $currentAlias = $rootAlias;
        $fieldPath = [];

        for ($i = 0; $i < count($parts) - 1; $i++)
        {
            $relation = $parts[$i];
            $nextAlias = $this->generateAlias($relation, $i);

            // Build the relation path
            $relationPath = $currentAlias . '.' . $relation;

            // Add join if not already present
            if (!$this->hasJoin($qb, $relationPath, $nextAlias))
            {
                $this->addJoin($qb, 'leftJoin', $relationPath, $nextAlias);
            }

            $currentAlias = $nextAlias;
        }

        // Return the final field reference
        return $currentAlias . '.' . $parts[count($parts) - 1];
    }

    /**
     * Reset the applied joins tracking
     * Useful for testing or when reusing the manager
     *
     * @return void
     */
    public function reset(): void
    {
        $this->appliedJoins = [];
    }

    /**
     * Check if a join already exists in the query builder
     *
     * @param QueryBuilder $qb The query builder
     * @param string $relation The relation path
     * @param string $alias The alias
     * @return bool True if join exists
     */
    protected function hasJoin(QueryBuilder $qb, string $relation, string $alias): bool
    {
        $dqlParts = $qb->getDQLParts();
        $joins = $dqlParts['join'] ?? [];

        foreach ($joins as $rootJoins)
        {
            foreach ($rootJoins as $join)
            {
                if ($join->getJoin() === $relation && $join->getAlias() === $alias)
                {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get existing aliases from the query builder
     *
     * @param QueryBuilder $qb The query builder
     * @return array<string> The aliases
     */
    protected function getExistingAliases(QueryBuilder $qb): array
    {
        $aliases = [$qb->getRootAliases()[0] ?? ''];

        $dqlParts = $qb->getDQLParts();
        $joins = $dqlParts['join'] ?? [];

        foreach ($joins as $rootJoins)
        {
            foreach ($rootJoins as $join)
            {
                $aliases[] = $join->getAlias();
            }
        }

        return array_filter($aliases);
    }

    /**
     * Generate an alias for a relation
     *
     * @param string $relation The relation name
     * @param int $depth The nesting depth
     * @return string The generated alias
     */
    protected function generateAlias(string $relation, int $depth): string
    {
        // Take first 2 letters of relation name and add depth
        $prefix = strtolower(substr($relation, 0, 2));

        return $prefix . ($depth > 0 ? $depth : '');
    }
}
