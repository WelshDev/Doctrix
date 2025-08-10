<?php

namespace WelshDev\Doctrix\Traits;

use DateTimeInterface;
use InvalidArgumentException;
use WelshDev\Doctrix\Exceptions\DuplicateEntityException;

/**
 * Trait for data validation and uniqueness checks
 *
 * Provides methods for checking uniqueness and finding duplicates
 */
trait ValidationTrait
{
    /**
     * Check if a field value is unique
     *
     * @param string $field Field name to check
     * @param mixed $value Value to check
     * @param mixed $excludeId Optional ID to exclude (for updates)
     * @return bool True if unique
     *
     * @example
     * // Check if email is unique
     * if ($repo->isUnique('email', 'user@example.com')) {
     *     // Email is available
     * }
     *
     * // Check uniqueness excluding current user (for updates)
     * if ($repo->isUnique('email', 'user@example.com', $currentUserId)) {
     *     // Email is available for this update
     * }
     */
    public function isUnique(string $field, $value, $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder($this->getAlias());

        $fieldPath = strpos($field, '.') !== false ? $field : $this->getAlias() . '.' . $field;
        $qb->where("{$fieldPath} = :value")
           ->setParameter('value', $value);

        if ($excludeId !== null)
        {
            $qb->andWhere($this->getAlias() . '.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        $qb->select('1')
           ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult() === null;
    }

    /**
     * Ensure a field value is unique or throw exception
     *
     * @param string $field Field name to check
     * @param mixed $value Value to check
     * @param mixed $excludeId Optional ID to exclude
     * @param string|null $message Custom error message
     * @throws DuplicateEntityException If not unique
     * @return void
     *
     * @example
     * try {
     *     $repo->ensureUnique('email', $email);
     *     // Proceed with registration
     * } catch (DuplicateEntityException $e) {
     *     // Handle duplicate email
     * }
     */
    public function ensureUnique(
        string $field,
        $value,
        $excludeId = null,
        ?string $message = null,
    ): void {
        if (!$this->isUnique($field, $value, $excludeId))
        {
            $message = $message ?? "Value '{$value}' for field '{$field}' already exists";

            $exception = new DuplicateEntityException($message);
            $exception->setField($field)
                      ->setValue($value)
                      ->setEntityClass($this->getClassName());

            throw $exception;
        }
    }

    /**
     * Check uniqueness of multiple fields combination
     *
     * @param array $fields Field => value pairs
     * @param mixed $excludeId Optional ID to exclude
     * @return bool True if combination is unique
     *
     * @example
     * // Check if user+role combination is unique
     * if ($repo->isUniqueCombination(['user_id' => 1, 'role_id' => 2])) {
     *     // Combination is unique
     * }
     */
    public function isUniqueCombination(array $fields, $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder($this->getAlias());

        foreach ($fields as $field => $value)
        {
            $fieldPath = strpos($field, '.') !== false ? $field : $this->getAlias() . '.' . $field;
            $paramName = str_replace('.', '_', $field) . '_unique';

            $qb->andWhere("{$fieldPath} = :{$paramName}")
               ->setParameter($paramName, $value);
        }

        if ($excludeId !== null)
        {
            $qb->andWhere($this->getAlias() . '.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        $qb->select('1')
           ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult() === null;
    }

    /**
     * Find duplicate entries based on specified fields
     *
     * @param array $fields Fields to check for duplicates
     * @param array $criteria Additional criteria
     * @return array Groups of duplicate entities
     *
     * @example
     * // Find all duplicate emails
     * $duplicates = $repo->fetchDuplicates(['email']);
     *
     * // Find duplicate name+date combinations
     * $duplicates = $repo->fetchDuplicates(['name', 'created_date']);
     *
     * // Returns: [
     * //   ['value' => 'duplicate@email.com', 'count' => 3, 'entities' => [...]],
     * //   ['value' => 'another@email.com', 'count' => 2, 'entities' => [...]]
     * // ]
     */
    public function fetchDuplicates(array $fields, array $criteria = []): array
    {
        $qb = $this->buildQuery($criteria);
        $alias = $this->getAlias();

        // Build group by fields
        $groupByFields = [];
        $selectFields = [];

        foreach ($fields as $field)
        {
            $fieldPath = strpos($field, '.') !== false ? $field : "{$alias}.{$field}";
            $groupByFields[] = $fieldPath;
            $selectFields[] = $fieldPath . ' as ' . str_replace('.', '_', $field);
        }

        // Find groups with duplicates
        $qb->select(implode(', ', $selectFields))
           ->addSelect("COUNT({$alias}.id) as duplicate_count")
           ->groupBy(implode(', ', $groupByFields))
           ->having("COUNT({$alias}.id) > 1")
           ->orderBy('duplicate_count', 'DESC');

        $duplicateGroups = $qb->getQuery()->getArrayResult();

        // Fetch actual entities for each duplicate group
        $results = [];

        foreach ($duplicateGroups as $group)
        {
            $groupCriteria = [];
            $groupValue = [];

            foreach ($fields as $field)
            {
                $fieldKey = str_replace('.', '_', $field);
                $groupCriteria[$field] = $group[$fieldKey];
                $groupValue[] = $group[$fieldKey];
            }

            // Fetch entities in this duplicate group
            $entities = $this->fetch(array_merge($criteria, $groupCriteria));

            $results[] = [
                'value' => count($groupValue) === 1 ? $groupValue[0] : $groupValue,
                'fields' => $fields,
                'count' => $group['duplicate_count'],
                'entities' => $entities,
            ];
        }

        return $results;
    }

    /**
     * Remove duplicate entries keeping only one
     *
     * @param array $fields Fields to check for duplicates
     * @param string $keepStrategy Strategy for which to keep: 'first', 'last', 'random'
     * @param array $criteria Additional criteria
     * @return int Number of duplicates removed
     *
     * @example
     * // Remove duplicate emails, keeping the first occurrence
     * $removed = $repo->removeDuplicates(['email'], 'first');
     */
    public function removeDuplicates(
        array $fields,
        string $keepStrategy = 'first',
        array $criteria = [],
    ): int {
        $duplicates = $this->fetchDuplicates($fields, $criteria);
        $removed = 0;
        $em = $this->getEntityManager();

        foreach ($duplicates as $group)
        {
            $entities = $group['entities'];

            if (count($entities) <= 1)
            {
                continue;
            }

            // Determine which entity to keep
            switch ($keepStrategy)
            {
                case 'first':
                    // Sort by ID ascending, keep first
                    usort($entities, fn($a, $b) => $a->getId() <=> $b->getId());
                    $toKeep = array_shift($entities);
                    break;
                case 'last':
                    // Sort by ID descending, keep last
                    usort($entities, fn($a, $b) => $b->getId() <=> $a->getId());
                    $toKeep = array_shift($entities);
                    break;
                case 'random':
                    // Keep random one
                    $keepIndex = array_rand($entities);
                    $toKeep = $entities[$keepIndex];
                    unset($entities[$keepIndex]);
                    $entities = array_values($entities);
                    break;
                default:
                    throw new InvalidArgumentException("Invalid keep strategy: {$keepStrategy}");
            }

            // Remove the duplicates
            foreach ($entities as $entity)
            {
                $em->remove($entity);
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * Validate entity against a set of rules
     *
     * @param object $entity Entity to validate
     * @param array $rules Validation rules
     * @return array Validation errors (empty if valid)
     *
     * @example
     * $errors = $repo->validate($user, [
     *     'email' => ['required', 'email', 'unique'],
     *     'age' => ['required', 'min:18', 'max:100'],
     *     'username' => ['required', 'regex:/^[a-zA-Z0-9_]+$/', 'unique']
     * ]);
     */
    public function validate(object $entity, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $fieldRules)
        {
            $getter = 'get' . ucfirst($field);

            if (!method_exists($entity, $getter))
            {
                $errors[$field][] = "Field '{$field}' does not exist";
                continue;
            }

            $value = $entity->$getter();

            foreach ($fieldRules as $rule)
            {
                $error = $this->validateRule($field, $value, $rule, $entity);
                if ($error !== null)
                {
                    $errors[$field][] = $error;
                }
            }
        }

        return $errors;
    }

    /**
     * Find entities with invalid data
     *
     * @param array $rules Validation rules
     * @param array $criteria Additional criteria
     * @return array Entities with validation errors
     *
     * @example
     * // Find users with invalid emails
     * $invalid = $repo->fetchInvalid(['email' => ['email']]);
     */
    public function fetchInvalid(array $rules, array $criteria = []): array
    {
        $entities = $this->fetch($criteria);
        $invalid = [];

        foreach ($entities as $entity)
        {
            $errors = $this->validate($entity, $rules);
            if (!empty($errors))
            {
                $invalid[] = [
                    'entity' => $entity,
                    'errors' => $errors,
                ];
            }
        }

        return $invalid;
    }

    /**
     * Validate a single rule
     *
     * @param string $field Field name
     * @param mixed $value Field value
     * @param string $rule Rule to validate
     * @param object $entity The entity being validated
     * @return string|null Error message or null if valid
     */
    protected function validateRule(string $field, $value, string $rule, object $entity): ?string
    {
        // Parse rule parameters
        $parts = explode(':', $rule, 2);
        $ruleName = $parts[0];
        $ruleParam = $parts[1] ?? null;

        switch ($ruleName)
        {
            case 'required':
                if ($value === null || $value === '')
                {
                    return "The {$field} field is required";
                }
                break;
            case 'unique':
                $entityId = method_exists($entity, 'getId') ? $entity->getId() : null;
                if (!$this->isUnique($field, $value, $entityId))
                {
                    return "The {$field} must be unique";
                }
                break;
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL))
                {
                    return "The {$field} must be a valid email address";
                }
                break;
            case 'min':
                if ($ruleParam !== null)
                {
                    if (is_numeric($value) && $value < $ruleParam)
                    {
                        return "The {$field} must be at least {$ruleParam}";
                    }
                    if (is_string($value) && strlen($value) < $ruleParam)
                    {
                        return "The {$field} must be at least {$ruleParam} characters";
                    }
                }
                break;
            case 'max':
                if ($ruleParam !== null)
                {
                    if (is_numeric($value) && $value > $ruleParam)
                    {
                        return "The {$field} must not exceed {$ruleParam}";
                    }
                    if (is_string($value) && strlen($value) > $ruleParam)
                    {
                        return "The {$field} must not exceed {$ruleParam} characters";
                    }
                }
                break;
            case 'regex':
                if ($ruleParam !== null && !preg_match($ruleParam, $value))
                {
                    return "The {$field} format is invalid";
                }
                break;
            case 'in':
                if ($ruleParam !== null)
                {
                    $allowed = explode(',', $ruleParam);
                    if (!in_array($value, $allowed))
                    {
                        return "The {$field} must be one of: " . implode(', ', $allowed);
                    }
                }
                break;
            case 'numeric':
                if (!is_numeric($value))
                {
                    return "The {$field} must be numeric";
                }
                break;
            case 'date':
                if (!$value instanceof DateTimeInterface && !strtotime($value))
                {
                    return "The {$field} must be a valid date";
                }
                break;
        }

        return null;
    }
}
