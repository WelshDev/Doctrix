<?php

namespace WelshDev\Doctrix\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Exception thrown when an entity is not found
 */
class EntityNotFoundException extends RuntimeException
{
    protected array $criteria = [];
    protected ?string $entityClass = null;

    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Set the search criteria that failed
     *
     * @param array $criteria
     * @return self
     */
    public function setCriteria(array $criteria): self
    {
        $this->criteria = $criteria;

        return $this;
    }

    /**
     * Get the search criteria
     *
     * @return array
     */
    public function getCriteria(): array
    {
        return $this->criteria;
    }

    /**
     * Set the entity class name
     *
     * @param string $entityClass
     * @return self
     */
    public function setEntityClass(string $entityClass): self
    {
        $this->entityClass = $entityClass;

        return $this;
    }

    /**
     * Get the entity class name
     *
     * @return string|null
     */
    public function getEntityClass(): ?string
    {
        return $this->entityClass;
    }
}
