<?php

namespace WelshDev\Doctrix\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Exception thrown when a duplicate entity is found
 */
class DuplicateEntityException extends RuntimeException
{
    protected string $field = '';
    protected $value;
    protected ?string $entityClass = null;

    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Set the field that has duplicate value
     *
     * @param string $field
     * @return self
     */
    public function setField(string $field): self
    {
        $this->field = $field;

        return $this;
    }

    /**
     * Get the field name
     *
     * @return string
     */
    public function getField(): string
    {
        return $this->field;
    }

    /**
     * Set the duplicate value
     *
     * @param mixed $value
     * @return self
     */
    public function setValue($value): self
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Get the duplicate value
     *
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
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
