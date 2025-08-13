<?php

namespace WelshDev\Doctrix\Traits;

use Doctrine\ORM\QueryBuilder as DoctrineQueryBuilder;
use WelshDev\Doctrix\Debug\QueryDebugger;
use WelshDev\Doctrix\QueryBuilder\CriteriaParser;
use WelshDev\Doctrix\QueryBuilder\FilterChain;
use WelshDev\Doctrix\QueryBuilder\FluentQueryBuilder;
use WelshDev\Doctrix\QueryBuilder\JoinManager;

/**
 * Trait providing enhanced query capabilities
 *
 * Can be used by repositories or services to add advanced query features
 */
trait EnhancedQueryTrait
{
    /**
     * The entity alias used in queries
     *
     * @var string
     */
    protected string $alias = '';

    /**
     * Join definitions
     *
     * @var array<int, array{0: string, 1: string, 2: string}>
     */
    protected array $joins = [];

    /**
     * Stack of filter functions
     *
     * @var array<callable>
     */
    protected array $filterFunctions = [];

    /**
     * The criteria parser instance
     *
     * @var CriteriaParser|null
     */
    private ?CriteriaParser $criteriaParser = null;

    /**
     * The filter chain manager
     *
     * @var FilterChain|null
     */
    private ?FilterChain $filterChain = null;

    /**
     * The join manager
     *
     * @var JoinManager|null
     */
    private ?JoinManager $joinManager = null;

    /**
     * Fetch entities matching the given criteria
     *
     * @param array $criteria The search criteria
     * @param array|null $orderBy Optional ordering
     * @param int|null $limit Optional limit
     * @param int|null $offset Optional offset
     * @return array The matching entities
     */
    public function fetch(array $criteria = [], ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        $qb = $this->buildQuery($criteria, $orderBy);

        if ($limit !== null)
        {
            $qb->setMaxResults($limit);
        }

        if ($offset !== null)
        {
            $qb->setFirstResult($offset);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Fetch a single entity matching the given criteria
     *
     * @param array $criteria The search criteria
     * @param array|null $orderBy Optional ordering
     * @return object|null The matching entity or null
     */
    public function fetchOne(array $criteria = [], ?array $orderBy = null): ?object
    {
        $qb = $this->buildQuery($criteria, $orderBy);
        $qb->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Fetch all entities with optional ordering and limits
     *
     * @param array|null $orderBy Optional ordering
     * @param int|null $limit Optional limit
     * @param int|null $offset Optional offset
     * @return array All matching entities
     */
    public function fetchAll(?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        return $this->fetch([], $orderBy, $limit, $offset);
    }

    /**
     * Count entities matching the given criteria
     *
     * @param array $criteria The search criteria
     * @return int The count of matching entities
     */
    public function count(array $criteria = []): int
    {
        $qb = $this->buildQuery($criteria);
        $qb->select('COUNT(DISTINCT ' . $this->getAlias() . '.id)');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Build a query based on criteria
     *
     * @param array $criteria The search criteria
     * @param array|null $orderBy Optional ordering
     * @return DoctrineQueryBuilder The query builder
     */
    public function buildQuery(array $criteria = [], ?array $orderBy = null): DoctrineQueryBuilder
    {
        $qb = $this->createQueryBuilder($this->getAlias());

        // Apply joins
        $this->getJoinManager()->applyJoins($qb, $this->getJoins());

        // Parse and apply criteria
        $this->getCriteriaParser()->applyCriteria($qb, $criteria, $this->getAlias());

        // Apply filter functions
        $this->getFilterChain()->applyFilters($qb, $this->filterFunctions);

        // Apply ordering
        if ($orderBy !== null)
        {
            foreach ($orderBy as $field => $direction)
            {
                // Handle dot notation in field names
                if (strpos($field, '.') === false && !str_starts_with($field, $this->getAlias() . '.'))
                {
                    $field = $this->getAlias() . '.' . $field;
                }
                $qb->addOrderBy($field, $direction);
            }
        }

        // Clear filter functions after use
        $this->filterFunctions = [];

        return $qb;
    }

    /**
     * Add a filter function to be applied to the next query
     *
     * @param callable $filterFunction The filter function receiving QueryBuilder
     * @return self For method chaining
     */
    public function addFilterFunction(callable $filterFunction): self
    {
        $this->filterFunctions[] = $filterFunction;

        return $this;
    }

    /**
     * Create a new fluent query builder instance
     *
     * @return FluentQueryBuilder The fluent query builder
     */
    public function query(): FluentQueryBuilder
    {
        return new FluentQueryBuilder($this);
    }

    /**
     * Get the repository alias
     *
     * @return string The alias
     */
    public function getAlias(): string
    {
        return $this->alias;
    }

    /**
     * Set the repository alias
     *
     * @param string $alias The alias
     * @return self
     */
    public function setAlias(string $alias): self
    {
        $this->alias = $alias;

        return $this;
    }

    /**
     * Get the configured joins
     *
     * @return array The joins configuration
     */
    public function getJoins(): array
    {
        return $this->joins;
    }

    /**
     * Set the joins configuration
     *
     * @param array $joins The joins
     * @return self
     */
    public function setJoins(array $joins): self
    {
        $this->joins = $joins;

        return $this;
    }

    /**
     * Abstract method that must be implemented by the using class
     *
     * @param string $alias
     * @return DoctrineQueryBuilder
     */
    abstract public function createQueryBuilder(string $alias): DoctrineQueryBuilder;

    /**
     * Debug a query with criteria
     *
     * @param array $criteria The criteria to apply
     * @param string $format Output format: 'text', 'html', 'json', 'array'
     * @param bool $execute Whether to execute the query
     * @return array Debug information
     *
     * @example
     * $repo->debugQuery(['status' => 'active'], 'text', true);
     */
    public function debugQuery(array $criteria = [], string $format = 'text', bool $execute = false): array
    {
        $qb = $this->buildQuery($criteria);

        return QueryDebugger::debug($qb, $format, $execute);
    }

    /**
     * Fetch with debug output
     *
     * @param array $criteria The criteria
     * @param array $orderBy Order by clauses
     * @param int|null $limit Result limit
     * @param int|null $offset Result offset
     * @param string $format Debug format
     * @return array Results
     */
    public function fetchWithDebug(
        array $criteria = [],
        array $orderBy = [],
        ?int $limit = null,
        ?int $offset = null,
        string $format = 'text',
    ): array {
        // Build and debug the query
        $qb = $this->buildQuery($criteria, $orderBy);

        if ($limit !== null)
        {
            $qb->setMaxResults($limit);
        }

        if ($offset !== null)
        {
            $qb->setFirstResult($offset);
        }

        // Show debug with execution
        QueryDebugger::debug($qb, $format, true);

        // Return results
        return $qb->getQuery()->getResult();
    }

    /**
     * Count with debug output
     *
     * @param array $criteria The criteria
     * @param string $format Debug format
     * @return int The count
     */
    public function countWithDebug(array $criteria = [], string $format = 'text'): int
    {
        $qb = $this->buildQuery($criteria);
        $qb->select('COUNT(DISTINCT ' . $this->getAlias() . '.id)');

        // Show debug with execution
        QueryDebugger::debug($qb, $format, true);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Get or create the criteria parser instance
     *
     * @return CriteriaParser The criteria parser
     */
    protected function getCriteriaParser(): CriteriaParser
    {
        if ($this->criteriaParser === null)
        {
            $this->criteriaParser = new CriteriaParser();
        }

        return $this->criteriaParser;
    }

    /**
     * Get or create the filter chain instance
     *
     * @return FilterChain The filter chain
     */
    protected function getFilterChain(): FilterChain
    {
        if ($this->filterChain === null)
        {
            $this->filterChain = new FilterChain();
        }

        return $this->filterChain;
    }

    /**
     * Get or create the join manager instance
     *
     * @return JoinManager The join manager
     */
    protected function getJoinManager(): JoinManager
    {
        if ($this->joinManager === null)
        {
            $this->joinManager = new JoinManager();
        }

        return $this->joinManager;
    }
}
