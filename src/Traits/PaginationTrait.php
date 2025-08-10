<?php

namespace WelshDev\Doctrix\Traits;

use WelshDev\Doctrix\Pagination\PaginationResult;

/**
 * Trait providing pagination capabilities
 *
 * Requires the EnhancedQueryTrait to be used in the same class
 */
trait PaginationTrait
{
    /**
     * Fetch paginated results
     *
     * @param array $criteria The search criteria
     * @param int $page The page number (1-indexed)
     * @param int $perPage Number of items per page
     * @param array|null $orderBy Optional ordering
     * @return PaginationResult The pagination result
     */
    public function paginate(
        array $criteria = [],
        int $page = 1,
        int $perPage = 20,
        ?array $orderBy = null,
    ): PaginationResult {
        // Ensure valid page and perPage values
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        // Get total count
        $total = $this->count($criteria);

        // Calculate offset
        $offset = ($page - 1) * $perPage;

        // Fetch items for current page
        $items = $this->fetch($criteria, $orderBy, $perPage, $offset);

        return new PaginationResult($items, $total, $page, $perPage);
    }

    /**
     * Fetch a simple paginated result (just items and hasMore flag)
     * Useful for infinite scroll or load more implementations
     *
     * @param array $criteria The search criteria
     * @param int $page The page number (1-indexed)
     * @param int $perPage Number of items per page
     * @param array|null $orderBy Optional ordering
     * @return array{items: array, hasMore: bool}
     */
    public function simplePaginate(
        array $criteria = [],
        int $page = 1,
        int $perPage = 20,
        ?array $orderBy = null,
    ): array {
        // Ensure valid page and perPage values
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        // Calculate offset
        $offset = ($page - 1) * $perPage;

        // Fetch one extra item to check if there are more
        $items = $this->fetch($criteria, $orderBy, $perPage + 1, $offset);

        // Check if there are more items
        $hasMore = count($items) > $perPage;

        // Return only the requested number of items
        if ($hasMore)
        {
            $items = array_slice($items, 0, $perPage);
        }

        return [
            'items' => $items,
            'hasMore' => $hasMore,
        ];
    }
}
