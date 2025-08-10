<?php

namespace WelshDev\Doctrix\Traits;

use Exception;
use WelshDev\Doctrix\Exceptions\EntityNotFoundException;
use WelshDev\Doctrix\Exceptions\MultipleEntitiesFoundException;

/**
 * Trait providing fetch or fail/create functionality
 *
 * Provides methods that fetch entities with automatic exception handling
 * or entity creation when not found
 */
trait FetchOrFailTrait
{
    /**
     * Default exception class for not found errors
     * Override in repository to use framework-specific exceptions
     *
     * @var string
     */
    protected string $notFoundException = EntityNotFoundException::class;

    /**
     * Default exception class for multiple results errors
     *
     * @var string
     */
    protected string $multipleFoundException = MultipleEntitiesFoundException::class;

    /**
     * Fetch one entity or throw exception
     *
     * @param array $criteria Search criteria
     * @param string|callable|null $exception Exception class, callable, or null for default
     * @param string|null $message Custom exception message
     * @throws Exception
     * @return object The found entity
     *
     * @example
     * // Use default exception
     * $user = $repo->fetchOneOrFail(['id' => $id]);
     *
     * // Use custom exception class
     * use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
     * $user = $repo->fetchOneOrFail(['id' => $id], NotFoundHttpException::class);
     *
     * // Use callback for complex exception
     * $user = $repo->fetchOneOrFail(
     *     ['id' => $id],
     *     fn($criteria) => new CustomException('User not found: ' . $id)
     * );
     */
    public function fetchOneOrFail(
        array $criteria,
        $exception = null,
        ?string $message = null,
    ): object {
        $result = $this->fetchOne($criteria);

        if (!$result)
        {
            throw $this->createNotFoundException($criteria, $exception, $message);
        }

        return $result;
    }

    /**
     * Fetch first result or throw exception (for fluent interface)
     *
     * @param string|callable|null $exception
     * @param string|null $message
     * @throws Exception
     * @return object
     */
    public function firstOrFail($exception = null, ?string $message = null): object
    {
        $result = $this->fetchOne([]);

        if (!$result)
        {
            throw $this->createNotFoundException([], $exception, $message);
        }

        return $result;
    }

    /**
     * Fetch one entity or create new one
     *
     * @param array $criteria Search criteria (only used for searching, not for setting values)
     * @param array|callable|null $valuesOrCallback Array of values to set on new entity, or callback to create entity
     * @return object The found or created entity
     *
     * @example
     * // Using array of values
     * $user = $repo->fetchOneOrCreate(
     *     ['email' => 'john@example.com'],
     *     ['name' => 'John Doe', 'status' => 'active']
     * );
     *
     * // Using callback for custom creation logic
     * $user = $repo->fetchOneOrCreate(
     *     ['email' => 'john@example.com'],
     *     function($criteria) {
     *         $user = new User();
     *         $user->setName('John Doe');
     *         $user->setToken(bin2hex(random_bytes(16)));
     *         return $user;
     *     }
     * );
     */
    public function fetchOneOrCreate(array $criteria, $valuesOrCallback = null): object
    {
        $entity = $this->fetchOne($criteria);

        if (!$entity)
        {
            if (is_callable($valuesOrCallback))
            {
                // Use callback to create entity
                $entity = $valuesOrCallback($criteria);
            }
            else
            {
                // Create entity and set values from array
                $entityClass = $this->getClassName();
                $entity = new $entityClass();

                if (is_array($valuesOrCallback))
                {
                    // Set only the provided values (not the criteria)
                    foreach ($valuesOrCallback as $field => $value)
                    {
                        $setter = 'set' . ucfirst($field);
                        if (method_exists($entity, $setter))
                        {
                            $entity->$setter($value);
                        }
                        elseif (property_exists($entity, $field))
                        {
                            $entity->$field = $value;
                        }
                    }
                }
            }
        }

        return $entity;
    }


    /**
     * Update existing or create new entity
     *
     * @param array $criteria Search criteria (only used for searching, not for setting values)
     * @param array $values Values to set/update on the entity
     * @return object The updated or created entity
     *
     * @example
     * // If product found by SKU, update with values. If not found, create with values only.
     * $product = $repo->updateOrCreate(
     *     ['sku' => 'PROD-123'],
     *     ['price' => 29.99, 'stock' => 100, 'name' => 'Product Name']
     * );
     */
    public function updateOrCreate(array $criteria, array $values): object
    {
        $entity = $this->fetchOne($criteria);

        if ($entity)
        {
            // Update existing entity
            foreach ($values as $field => $value)
            {
                $setter = 'set' . ucfirst($field);
                if (method_exists($entity, $setter))
                {
                    $entity->$setter($value);
                }
                elseif (property_exists($entity, $field))
                {
                    $entity->$field = $value;
                }
            }
        }
        else
        {
            // Create new entity
            $entityClass = $this->getClassName();
            $entity = new $entityClass();

            // Set only the provided values (not the criteria)
            foreach ($values as $field => $value)
            {
                $setter = 'set' . ucfirst($field);
                if (method_exists($entity, $setter))
                {
                    $entity->$setter($value);
                }
                elseif (property_exists($entity, $field))
                {
                    $entity->$field = $value;
                }
            }
        }

        return $entity;
    }

    /**
     * Fetch exactly one result or throw exception
     * Throws if zero or more than one result
     *
     * @param array $criteria
     * @param string|callable|null $exception
     * @throws Exception
     * @return object
     *
     * @example
     * // Ensures exactly one super admin exists
     * $superAdmin = $repo->sole(['role' => 'super_admin']);
     */
    public function sole(array $criteria, $exception = null): object
    {
        $results = $this->fetch($criteria);

        if (count($results) === 0)
        {
            throw $this->createNotFoundException($criteria, $exception, 'No results found');
        }

        if (count($results) > 1)
        {
            throw $this->createMultipleFoundException($criteria, count($results));
        }

        return $results[0];
    }

    /**
     * Create not found exception
     *
     * @param array $criteria
     * @param string|callable|null $exception
     * @param string|null $message
     * @return Exception
     */
    protected function createNotFoundException(
        array $criteria,
        $exception = null,
        ?string $message = null,
    ): Exception {
        // Handle callable exception
        if (is_callable($exception))
        {
            return $exception($criteria, $message);
        }

        // Determine exception class
        $exceptionClass = $exception
            ?? $this->notFoundException
            ?? $this->detectFrameworkNotFoundException()
            ?? EntityNotFoundException::class;

        // Build message if not provided
        if (!$message)
        {
            $entity = $this->getEntityShortName();
            if (!empty($criteria))
            {
                $message = "$entity not found";
            }
            else
            {
                $message = "$entity not found";
            }
        }

        // Handle framework-specific exceptions
        if (strpos($exceptionClass, 'NotFoundHttpException') !== false)
        {
            // Symfony's exception
            return new $exceptionClass($message);
        }

        // Generic exception
        return new $exceptionClass($message);
    }

    /**
     * Create multiple found exception
     *
     * @param array $criteria
     * @param int $count
     * @return Exception
     */
    protected function createMultipleFoundException(array $criteria, int $count): Exception
    {
        $entity = $this->getEntityShortName();
        $message = "Expected exactly one $entity but found $count with criteria: " . json_encode($criteria);

        return new $this->multipleFoundException($message);
    }

    /**
     * Try to detect framework's not found exception
     *
     * @return string|null
     */
    protected function detectFrameworkNotFoundException(): ?string
    {
        $frameworkExceptions = [
            'Symfony\Component\HttpKernel\Exception\NotFoundHttpException',
            'Illuminate\Database\Eloquent\ModelNotFoundException',
            'Slim\Exception\HttpNotFoundException',
            'Cake\Datasource\Exception\RecordNotFoundException',
            'Laminas\ApiTools\ApiProblem\Exception\DomainException',
        ];

        foreach ($frameworkExceptions as $exceptionClass)
        {
            if (class_exists($exceptionClass))
            {
                return $exceptionClass;
            }
        }

        return null;
    }

    /**
     * Get entity short name for messages
     * Override if needed
     *
     * @return string
     */
    protected function getEntityShortName(): string
    {
        if (method_exists($this, 'getClassName'))
        {
            $parts = explode('\\', $this->getClassName());

            return end($parts);
        }

        return 'Entity';
    }
}
