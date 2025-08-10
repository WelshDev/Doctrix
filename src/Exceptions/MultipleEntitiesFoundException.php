<?php

namespace WelshDev\Doctrix\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Exception thrown when multiple entities are found when exactly one was expected
 */
class MultipleEntitiesFoundException extends RuntimeException
{
    protected int $count = 0;
    protected array $criteria = [];

    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Set the number of entities found
     *
     * @param int $count
     * @return self
     */
    public function setCount(int $count): self
    {
        $this->count = $count;

        return $this;
    }

    /**
     * Get the number of entities found
     *
     * @return int
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * Set the search criteria
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
}
