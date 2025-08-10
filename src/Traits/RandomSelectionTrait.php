<?php

namespace WelshDev\Doctrix\Traits;

/**
 * Trait for random entity selection
 *
 * Provides methods for selecting random entities from the database
 */
trait RandomSelectionTrait
{
    /**
     * Get random entity(s)
     *
     * @param int $count Number of random entities to fetch
     * @return object|array Single entity if count=1, array otherwise
     *
     * @example
     * // Get single random user
     * $randomUser = $repo->random();
     *
     * // Get 5 random users
     * $randomUsers = $repo->random(5);
     */
    public function random(int $count = 1)
    {
        $results = $this->randomWhere([], $count);

        return $count === 1 && !empty($results) ? $results[0] : $results;
    }

    /**
     * Get random entity(s) matching criteria
     *
     * @param array $criteria Search criteria
     * @param int $count Number of random entities to fetch
     * @return array Array of random entities
     *
     * @example
     * // Get 3 random active users
     * $activeUsers = $repo->randomWhere(['status' => 'active'], 3);
     */
    public function randomWhere(array $criteria = [], int $count = 1): array
    {
        $qb = $this->buildQuery($criteria);

        // Get database platform
        $connection = $this->getEntityManager()->getConnection();
        $platform = $connection->getDatabasePlatform()->getName();

        // Apply random ordering based on database platform
        switch ($platform)
        {
            case 'mysql':
            case 'mysql2':
                $qb->orderBy('RAND()');
                break;
            case 'postgresql':
            case 'pdo_pgsql':
                $qb->orderBy('RANDOM()');
                break;
            case 'sqlite':
            case 'pdo_sqlite':
                $qb->orderBy('RANDOM()');
                break;
            case 'mssql':
            case 'pdo_sqlsrv':
                $qb->orderBy('NEWID()');
                break;
            default:
                // Fallback method: fetch IDs and randomly select
                return $this->randomFallback($criteria, $count);
        }

        $qb->setMaxResults($count);

        return $qb->getQuery()->getResult();
    }

    /**
     * Get a random sample with specific distribution
     *
     * @param array $weights Field => weight mapping for weighted random
     * @param int $count Number of entities to fetch
     * @return array
     *
     * @example
     * // Users with higher priority have higher chance of selection
     * $users = $repo->weightedRandom(['priority' => 'ASC'], 5);
     */
    public function weightedRandom(array $weights, int $count = 1): array
    {
        $qb = $this->createQueryBuilder($this->getAlias());

        // Build weighted random query
        $weightExpression = '';
        foreach ($weights as $field => $direction)
        {
            $fieldPath = strpos($field, '.') !== false ? $field : $this->getAlias() . '.' . $field;

            if ($direction === 'ASC' || $direction === 'asc')
            {
                $weightExpression .= " * $fieldPath";
            }
            else
            {
                $weightExpression .= " / NULLIF($fieldPath, 0)";
            }
        }

        $connection = $this->getEntityManager()->getConnection();
        $platform = $connection->getDatabasePlatform()->getName();

        // Apply weighted random based on platform
        switch ($platform)
        {
            case 'mysql':
            case 'mysql2':
                $qb->orderBy("RAND() $weightExpression");
                break;
            case 'postgresql':
            case 'pdo_pgsql':
                $qb->orderBy("RANDOM() $weightExpression");
                break;
            default:
                // For unsupported platforms, fall back to regular random
                return $this->random($count);
        }

        $qb->setMaxResults($count);

        return $qb->getQuery()->getResult();
    }

    /**
     * Get random entity or null if none exist
     *
     * @param array $criteria Optional criteria
     * @return object|null
     *
     * @example
     * $user = $repo->randomOrNull(['status' => 'active']);
     */
    public function randomOrNull(array $criteria = []): ?object
    {
        $result = $this->randomWhere($criteria, 1);

        return !empty($result) ? $result[0] : null;
    }

    /**
     * Get random distinct values for a field
     *
     * @param string $field Field name
     * @param int $count Number of distinct values
     * @param array $criteria Optional criteria
     * @return array
     *
     * @example
     * // Get 5 random unique categories
     * $categories = $repo->randomDistinct('category', 5);
     */
    public function randomDistinct(string $field, int $count = 1, array $criteria = []): array
    {
        $qb = $this->buildQuery($criteria);

        // Add field to select and make distinct
        $fieldPath = strpos($field, '.') !== false ? $field : $this->getAlias() . '.' . $field;
        $qb->select("DISTINCT $fieldPath");

        // Apply random ordering
        $connection = $this->getEntityManager()->getConnection();
        $platform = $connection->getDatabasePlatform()->getName();

        switch ($platform)
        {
            case 'mysql':
            case 'mysql2':
                $qb->orderBy('RAND()');
                break;
            case 'postgresql':
            case 'pdo_pgsql':
            case 'sqlite':
            case 'pdo_sqlite':
                $qb->orderBy('RANDOM()');
                break;
            default:
                // Fetch all and randomly select
                $all = $qb->getQuery()->getArrayResult();
                shuffle($all);

                return array_slice(array_column($all, $field), 0, $count);
        }

        $qb->setMaxResults($count);

        return array_column($qb->getQuery()->getArrayResult(), $field);
    }

    /**
     * Fallback method for random selection when database doesn't support RAND()
     *
     * @param array $criteria
     * @param int $count
     * @return array
     */
    protected function randomFallback(array $criteria, int $count): array
    {
        // Get all IDs matching criteria
        $qb = $this->buildQuery($criteria);
        $qb->select($this->getAlias() . '.id');

        $ids = array_column($qb->getQuery()->getArrayResult(), 'id');

        if (empty($ids))
        {
            return [];
        }

        // Randomly select IDs
        $selectedIds = [];
        $maxIndex = count($ids) - 1;
        $count = min($count, count($ids));

        if ($count === count($ids))
        {
            // If selecting all, just shuffle
            $selectedIds = $ids;
            shuffle($selectedIds);
        }
        else
        {
            // Select random unique IDs
            $selectedIndexes = array_rand($ids, $count);
            if (!is_array($selectedIndexes))
            {
                $selectedIndexes = [$selectedIndexes];
            }

            foreach ($selectedIndexes as $index)
            {
                $selectedIds[] = $ids[$index];
            }
        }

        // Fetch entities by selected IDs
        return $this->fetch([
            'id' => ['in', $selectedIds],
        ]);
    }
}
