<?php

namespace WelshDev\Doctrix\Traits;

use Exception;
use Generator;

/**
 * Trait for processing large datasets in chunks
 *
 * Provides memory-efficient methods for iterating over large result sets
 */
trait ChunkProcessingTrait
{
    /**
     * Process query results in chunks
     *
     * @param int $size Chunk size
     * @param callable $callback Callback to process each chunk
     * @param array $criteria Optional criteria
     * @param array $orderBy Optional ordering
     * @return int Total number of processed records
     *
     * @example
     * $repo->chunk(1000, function($users) {
     *     foreach ($users as $user) {
     *         $this->processUser($user);
     *     }
     * });
     */
    public function chunk(
        int $size,
        callable $callback,
        array $criteria = [],
        array $orderBy = [],
    ): int {
        $offset = 0;
        $total = 0;

        // Default ordering to ensure consistent chunks
        if (empty($orderBy))
        {
            $orderBy = [$this->getAlias() . '.id' => 'ASC'];
        }

        do
        {
            // Fetch chunk
            $results = $this->fetch($criteria, $orderBy, $size, $offset);
            $count = count($results);

            if ($count > 0)
            {
                // Process chunk
                $result = $callback($results, $offset / $size + 1);

                // Allow callback to stop processing by returning false
                if ($result === false)
                {
                    break;
                }

                $total += $count;
                $offset += $size;
            }

            // Clear entity manager to free memory
            if (method_exists($this, 'getEntityManager'))
            {
                $this->getEntityManager()->clear();
            }
        }
        while ($count === $size);

        return $total;
    }

    /**
     * Process each entity individually with chunked fetching
     *
     * @param callable $callback Callback for each entity
     * @param int $chunkSize Size of chunks to fetch
     * @param array $criteria Optional criteria
     * @param array $orderBy Optional ordering
     * @return int Total number of processed entities
     *
     * @example
     * $repo->each(function($user, $index) {
     *     echo "Processing user $index: " . $user->getName() . "\n";
     * }, 100);
     */
    public function each(
        callable $callback,
        int $chunkSize = 1000,
        array $criteria = [],
        array $orderBy = [],
    ): int {
        $index = 0;

        return $this->chunk($chunkSize, function ($entities) use ($callback, &$index)
        {
            foreach ($entities as $entity)
            {
                $result = $callback($entity, $index++);

                // Allow callback to stop processing by returning false
                if ($result === false)
                {
                    return false;
                }
            }
        }, $criteria, $orderBy);
    }

    /**
     * Process results in chunks with a progress callback
     *
     * @param int $size Chunk size
     * @param callable $processor Callback to process each chunk
     * @param callable|null $progress Optional progress callback
     * @param array $criteria Optional criteria
     * @return int Total processed
     *
     * @example
     * $repo->chunkWithProgress(
     *     500,
     *     function($batch) {
     *         // Process batch
     *     },
     *     function($processed, $total) {
     *         echo "Processed $processed of $total\n";
     *     }
     * );
     */
    public function chunkWithProgress(
        int $size,
        callable $processor,
        ?callable $progress = null,
        array $criteria = [],
    ): int {
        // Get total count if progress callback provided
        $total = null;
        if ($progress)
        {
            $total = $this->count($criteria);
            $progress(0, $total);
        }

        $processed = 0;

        return $this->chunk($size, function ($batch) use ($processor, $progress, &$processed, $total)
        {
            $processor($batch);
            $processed += count($batch);

            if ($progress)
            {
                $progress($processed, $total);
            }
        }, $criteria);
    }

    /**
     * Lazy load entities for memory-efficient iteration
     * Returns a generator that fetches entities in chunks
     *
     * @param int $chunkSize Size of chunks to fetch
     * @param array $criteria Optional criteria
     * @param array $orderBy Optional ordering
     * @return Generator
     *
     * @example
     * foreach ($repo->lazy(100) as $user) {
     *     // Process user one at a time
     *     // But fetches 100 at a time for efficiency
     * }
     */
    public function lazy(
        int $chunkSize = 1000,
        array $criteria = [],
        array $orderBy = [],
    ): Generator {
        $offset = 0;

        // Default ordering to ensure consistent chunks
        if (empty($orderBy))
        {
            $orderBy = [$this->getAlias() . '.id' => 'ASC'];
        }

        do
        {
            // Fetch chunk
            $results = $this->fetch($criteria, $orderBy, $chunkSize, $offset);
            $count = count($results);

            // Yield each entity
            foreach ($results as $entity)
            {
                yield $entity;
            }

            $offset += $chunkSize;

            // Clear entity manager to free memory
            if (method_exists($this, 'getEntityManager') && $count > 0)
            {
                $this->getEntityManager()->clear();
            }
        }
        while ($count === $chunkSize);
    }

    /**
     * Process in batches with automatic transaction handling
     * Each batch is processed in a separate transaction
     *
     * @param int $batchSize Size of each batch
     * @param callable $processor Batch processor
     * @param array $criteria Optional criteria
     * @param bool $autoFlush Whether to automatically flush after each batch (default: true for backward compatibility)
     * @return array Results with successes and failures
     *
     * @example
     * $results = $repo->batchProcess(100, function($batch) {
     *     foreach ($batch as $entity) {
     *         $entity->setProcessed(true);
     *     }
     * });
     *
     * @example
     * // Without auto-flush (application controls flushing)
     * $results = $repo->batchProcess(100, function($batch) use ($em) {
     *     foreach ($batch as $entity) {
     *         $entity->setProcessed(true);
     *     }
     *     $em->flush(); // Application controls when to flush
     * }, [], false);
     */
    public function batchProcess(
        int $batchSize,
        callable $processor,
        array $criteria = [],
        bool $autoFlush = true,
    ): array {
        $results = [
            'total' => 0,
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        $batchNumber = 0;

        $this->chunk($batchSize, function ($batch) use ($processor, &$results, &$batchNumber, $autoFlush)
        {
            $batchNumber++;
            $em = $this->getEntityManager();

            try
            {
                // Only use transactions when auto-flushing
                if ($autoFlush)
                {
                    $em->beginTransaction();
                }

                // Process batch
                $processor($batch, $batchNumber);

                // Optionally flush and commit
                if ($autoFlush)
                {
                    $em->flush();
                    $em->commit();
                }

                $results['success'] += count($batch);
            }
            catch (Exception $e)
            {
                if ($autoFlush && $em->getConnection()->isTransactionActive())
                {
                    $em->rollback();
                }
                $results['failed'] += count($batch);
                $results['errors'][] = [
                    'batch' => $batchNumber,
                    'error' => $e->getMessage(),
                ];
            }

            $results['total'] += count($batch);
        }, $criteria);

        return $results;
    }

    /**
     * Map over all entities in chunks
     *
     * @param callable $callback Mapping function
     * @param int $chunkSize Chunk size
     * @param array $criteria Optional criteria
     * @return array Mapped results
     *
     * @example
     * $emails = $repo->map(fn($user) => $user->getEmail());
     */
    public function map(
        callable $callback,
        int $chunkSize = 1000,
        array $criteria = [],
    ): array {
        $results = [];

        $this->chunk($chunkSize, function ($batch) use ($callback, &$results)
        {
            foreach ($batch as $entity)
            {
                $results[] = $callback($entity);
            }
        }, $criteria);

        return $results;
    }
}
