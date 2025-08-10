<?php

namespace WelshDev\Doctrix\Pagination;

use ArrayAccess;
use ArrayIterator;
use BadMethodCallException;
use Countable;
use IteratorAggregate;

/**
 * Container for paginated query results
 *
 * Provides convenient access to paginated data along with
 * metadata about the pagination state
 *
 * Implements IteratorAggregate and Countable to allow direct iteration
 * and counting in templates without needing to access ->items
 *
 * @template T of object
 * @implements IteratorAggregate<int, T>
 * @implements ArrayAccess<int, T>
 */
class PaginationResult implements IteratorAggregate, Countable, ArrayAccess
{
    /**
     * The items for the current page
     *
     * @var array<T>
     */
    public readonly array $items;

    /**
     * Total number of items across all pages
     *
     * @var int
     */
    public readonly int $total;

    /**
     * Current page number (1-indexed)
     *
     * @var int
     */
    public readonly int $page;

    /**
     * Number of items per page
     *
     * @var int
     */
    public readonly int $perPage;

    /**
     * Total number of pages
     *
     * @var int
     */
    public readonly int $lastPage;

    /**
     * Whether there are more pages after the current one
     *
     * @var bool
     */
    public readonly bool $hasMore;

    /**
     * Whether there are pages before the current one
     *
     * @var bool
     */
    public readonly bool $hasPrevious;

    /**
     * The next page number, or null if on last page
     *
     * @var int|null
     */
    public readonly ?int $nextPage;

    /**
     * The previous page number, or null if on first page
     *
     * @var int|null
     */
    public readonly ?int $previousPage;

    /**
     * The starting item number for the current page (1-indexed)
     *
     * @var int
     */
    public readonly int $from;

    /**
     * The ending item number for the current page (1-indexed)
     *
     * @var int
     */
    public readonly int $to;

    /**
     * Constructor
     *
     * @param array<T> $items The items for the current page
     * @param int $total Total count of all items
     * @param int $page Current page number (1-indexed)
     * @param int $perPage Items per page
     */
    public function __construct(
        array $items,
        int $total,
        int $page,
        int $perPage,
    ) {
        $this->items = $items;
        $this->total = $total;
        $this->page = max(1, $page);
        $this->perPage = max(1, $perPage);

        // Calculate derived values
        $this->lastPage = max(1, (int) ceil($this->total / $this->perPage));
        $this->hasMore = $this->page < $this->lastPage;
        $this->hasPrevious = $this->page > 1;
        $this->nextPage = $this->hasMore ? $this->page + 1 : null;
        $this->previousPage = $this->hasPrevious ? $this->page - 1 : null;

        // Calculate item range
        if ($this->total > 0)
        {
            $this->from = (($this->page - 1) * $this->perPage) + 1;
            $this->to = min($this->from + count($this->items) - 1, $this->total);
        }
        else
        {
            $this->from = 0;
            $this->to = 0;
        }
    }

    /**
     * Magic method to check if empty using Twig's 'is empty' test
     *
     * @return bool
     */
    public function __toString(): string
    {
        return count($this->items) > 0 ? 'non-empty' : '';
    }

    /**
     * Get the items for the current page
     *
     * @return array<T>
     */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * Get the total number of items
     *
     * @return int
     */
    public function total(): int
    {
        return $this->total;
    }

    /**
     * Get the current page number
     *
     * @return int
     */
    public function currentPage(): int
    {
        return $this->page;
    }

    /**
     * Get the last page number
     *
     * @return int
     */
    public function lastPage(): int
    {
        return $this->lastPage;
    }

    /**
     * Get the number of items per page
     *
     * @return int
     */
    public function perPage(): int
    {
        return $this->perPage;
    }

    /**
     * Check if there are more pages
     *
     * @return bool
     */
    public function hasMorePages(): bool
    {
        return $this->hasMore;
    }

    /**
     * Check if there are previous pages
     *
     * @return bool
     */
    public function hasPreviousPages(): bool
    {
        return $this->hasPrevious;
    }

    /**
     * Check if on the first page
     *
     * @return bool
     */
    public function onFirstPage(): bool
    {
        return $this->page === 1;
    }

    /**
     * Check if on the last page
     *
     * @return bool
     */
    public function onLastPage(): bool
    {
        return $this->page >= $this->lastPage;
    }

    /**
     * Check if the result set is empty
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return count($this->items) === 0;
    }

    /**
     * Check if the result set is not empty
     *
     * @return bool
     */
    public function isNotEmpty(): bool
    {
        return count($this->items) > 0;
    }

    /**
     * Get pagination links for a given URL
     *
     * @param string $url Base URL for pagination links
     * @param array $queryParams Additional query parameters to preserve
     * @return array{first: string, previous: string|null, next: string|null, last: string}
     */
    public function links(string $url, array $queryParams = []): array
    {
        $buildUrl = function (int $page) use ($url, $queryParams): string
        {
            $params = array_merge($queryParams, ['page' => $page]);
            $query = http_build_query($params);

            return $url . (str_contains($url, '?') ? '&' : '?') . $query;
        };

        return [
            'first' => $buildUrl(1),
            'previous' => $this->previousPage ? $buildUrl($this->previousPage) : null,
            'next' => $this->nextPage ? $buildUrl($this->nextPage) : null,
            'last' => $buildUrl($this->lastPage),
        ];
    }

    /**
     * Convert to array representation
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'items' => $this->items,
            'total' => $this->total,
            'page' => $this->page,
            'per_page' => $this->perPage,
            'last_page' => $this->lastPage,
            'from' => $this->from,
            'to' => $this->to,
            'has_more' => $this->hasMore,
            'has_previous' => $this->hasPrevious,
            'next_page' => $this->nextPage,
            'previous_page' => $this->previousPage,
        ];
    }

    /**
     * Get metadata without items
     *
     * @return array
     */
    public function meta(): array
    {
        $data = $this->toArray();
        unset($data['items']);

        return $data;
    }

    /**
     * Map over the items
     *
     * @param callable $callback
     * @return self
     */
    public function map(callable $callback): self
    {
        return new self(
            array_map($callback, $this->items),
            $this->total,
            $this->page,
            $this->perPage,
        );
    }

    /**
     * Filter the items
     *
     * @param callable $callback
     * @return array<T>
     */
    public function filter(callable $callback): array
    {
        return array_filter($this->items, $callback);
    }

    // ========================================================================
    // Interface implementations for seamless template usage
    // ========================================================================

    /**
     * Implementation of IteratorAggregate
     * Allows direct iteration over the pagination result in templates
     * Example: {% for customer in customers %}
     *
     * @return ArrayIterator<int, T>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    /**
     * Implementation of Countable
     * Allows using count() and |length filter directly on pagination result
     * Example: {% if customers|length > 0 %}
     *
     * Note: This returns the count of items on the current page,
     * not the total count across all pages
     *
     * @return int Count of items on current page
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Implementation of ArrayAccess: Check if offset exists
     *
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    /**
     * Implementation of ArrayAccess: Get item at offset
     *
     * @param mixed $offset
     * @return T|null
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    /**
     * Implementation of ArrayAccess: Set item at offset
     * Note: PaginationResult is read-only, so this throws an exception
     *
     * @param mixed $offset
     * @param mixed $value
     * @throws BadMethodCallException
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new BadMethodCallException('PaginationResult is read-only');
    }

    /**
     * Implementation of ArrayAccess: Unset item at offset
     * Note: PaginationResult is read-only, so this throws an exception
     *
     * @param mixed $offset
     * @throws BadMethodCallException
     */
    public function offsetUnset(mixed $offset): void
    {
        throw new BadMethodCallException('PaginationResult is read-only');
    }
}
