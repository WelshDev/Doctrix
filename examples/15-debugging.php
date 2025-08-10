<?php
/**
 * Query Debugging Examples
 * 
 * This file demonstrates how to use Doctrix's query debugging features
 * to analyze SQL, parameters, execution plans, and performance.
 */

use App\Repository\UserRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;

// Example 1: Basic Debug Output
// =============================
// Show SQL and parameters without executing
$userRepo->query()
    ->where('status', 'active')
    ->where('role', 'admin')
    ->debug();

// Output:
// ╔════════════════════════════════════════════════════════════════════╗
// ║                        QUERY DEBUG OUTPUT                          ║
// ╚════════════════════════════════════════════════════════════════════╝
// 
// 📝 SQL QUERY:
// ─────────────
// SELECT u 
// FROM App\Entity\User u 
// WHERE u.status = 'active' 
//   AND u.role = 'admin'
//
// 🔧 PARAMETERS:
// ──────────────
// Named:
//   :status = 'active'
//   :role = 'admin'

// Example 2: Debug with Execution Timing
// ======================================
// Execute query and show performance metrics
$userRepo->query()
    ->where('createdAt', '>=', new DateTime('-30 days'))
    ->orderBy('createdAt', 'DESC')
    ->limit(100)
    ->debug('text', true);  // true = execute query

// Additional output includes:
// ⚡ EXECUTION STATS:
// ───────────────────
//   Time: 45.23 ms
//   Memory: 2.34 MB
//   Peak Memory: 8.56 MB
//   Results: 87 rows

// Example 3: Debug with Execution Plan
// ====================================
// Show how the database will execute the query
$orderRepo->query()
    ->where('status', 'pending')
    ->whereIn('priority', ['high', 'urgent'])
    ->debug('text', true);

// Output includes execution plan:
// 📊 EXECUTION PLAN:
// ──────────────────
//   id: 1
//   select_type: SIMPLE
//   table: orders
//   type: ref
//   possible_keys: idx_status, idx_priority
//   key: idx_status
//   key_len: 32
//   ref: const
//   rows: 245
//   Extra: Using where; Using index

// Example 4: HTML Debug Output
// ============================
// Perfect for development environments
$productRepo->query()
    ->where('category', 'electronics')
    ->whereBetween('price', 100, 500)
    ->debug('html', true);

// Outputs formatted HTML with syntax highlighting
// and organized tables for parameters and execution plan

// Example 5: JSON Debug Output
// ============================
// Get debug info as JSON for logging or APIs
$debugInfo = $userRepo->query()
    ->where('verified', true)
    ->debug('json', false);

// Output:
// {
//   "sql": "SELECT u FROM User u WHERE u.verified = ?",
//   "parameters": {
//     "positional": {
//       "1": true
//     }
//   },
//   "formatted_sql": "SELECT u\nFROM User u\nWHERE u.verified = 1"
// }

// Example 6: Debug as Array
// =========================
// Get debug info as array for processing
$debugInfo = $userRepo->query()
    ->where('status', 'active')
    ->debug('array', true);

// Process the debug information
echo "Query took: " . $debugInfo['execution_time_ms'] . " ms\n";
echo "Memory used: " . $debugInfo['memory_used_mb'] . " MB\n";
echo "Results: " . $debugInfo['result_count'] . " rows\n";

if ($debugInfo['execution_time_ms'] > 100) {
    // Log slow query
    $logger->warning('Slow query detected', $debugInfo);
}

// Example 7: Debug with Complex Criteria
// ======================================
$userRepo->debugQuery([
    'status' => 'active',
    ['lastLogin', 'gte', new DateTime('-7 days')],
    ['or', [
        'role' => 'admin',
        'role' => 'moderator'
    ]]
], 'text', true);

// Example 8: Get Results with Debug
// =================================
// Execute query, show debug info, and return results
$users = $userRepo->query()
    ->where('country', 'US')
    ->orderBy('registeredAt', 'DESC')
    ->getWithDebug('text');

// Debug info is displayed, and results are returned
foreach ($users as $user) {
    echo $user->getName() . "\n";
}

// Example 9: Paginate with Debug
// ==============================
$paginatedResults = $userRepo->query()
    ->where('status', 'active')
    ->paginateWithDebug(1, 20, 'text');

// Shows debug info for the paginated query
echo "Page 1 of " . $paginatedResults->lastPage . "\n";

// Example 10: Repository Method with Debug
// ========================================
class UserRepository extends BaseRepository
{
    protected string $alias = 'u';
    
    public function findActiveUsersDebug(): array
    {
        return $this->fetchWithDebug(
            ['status' => 'active', 'verified' => true],
            ['createdAt' => 'DESC'],
            100,
            0,
            'text'
        );
    }
    
    public function countActiveUsersDebug(): int
    {
        return $this->countWithDebug(
            ['status' => 'active'],
            'text'
        );
    }
}

// Example 11: Debug in Development vs Production
// ==============================================
$debugFormat = $_ENV['APP_ENV'] === 'dev' ? 'html' : 'array';
$showDebug = $_ENV['APP_DEBUG'] === 'true';

if ($showDebug) {
    $debugInfo = $orderRepo->query()
        ->where('customerId', $customerId)
        ->debug($debugFormat, true);
    
    if ($_ENV['APP_ENV'] === 'prod') {
        // Log debug info in production
        $logger->debug('Query execution', $debugInfo);
    }
}

// Example 12: Performance Comparison
// ==================================
// Compare performance of different query approaches
echo "Testing query performance:\n\n";

// Approach 1: Multiple conditions
$start = microtime(true);
$result1 = $userRepo->query()
    ->where('status', 'active')
    ->where('country', 'US')
    ->where('verified', true)
    ->debug('array', true);
echo "Approach 1: " . $result1['execution_time_ms'] . " ms\n";

// Approach 2: Using IN clause
$start = microtime(true);
$result2 = $userRepo->query()
    ->whereIn('id', $activeUserIds)
    ->debug('array', true);
echo "Approach 2: " . $result2['execution_time_ms'] . " ms\n";

// Example 13: Debug Query Building
// ================================
// Debug at different stages of query building
$query = $productRepo->query();

// Add first condition and debug
$query->where('category', 'electronics');
echo "After category filter:\n";
$query->debug();

// Add price filter and debug again
$query->whereBetween('price', 100, 500);
echo "\nAfter price filter:\n";
$query->debug();

// Add ordering and debug final query
$query->orderBy('sales', 'DESC');
echo "\nFinal query:\n";
$query->debug('text', true);

// Example 14: Debugging Joins
// ===========================
$orderRepo->query()
    ->leftJoin('customer', 'c')
    ->leftJoin('c.address', 'a')
    ->where('a.country', 'US')
    ->where('o.status', 'shipped')
    ->debug('text', true);

// Shows JOIN clauses in SQL and their impact on execution plan

// Example 15: Custom Debug Handler
// ================================
class QueryDebugHandler
{
    private $slowQueryThreshold = 100; // ms
    private $logger;
    
    public function handleDebug(array $debugInfo): void
    {
        // Log slow queries
        if (isset($debugInfo['execution_time_ms']) && 
            $debugInfo['execution_time_ms'] > $this->slowQueryThreshold) {
            
            $this->logger->warning('Slow query detected', [
                'sql' => $debugInfo['formatted_sql'],
                'time' => $debugInfo['execution_time_ms'],
                'memory' => $debugInfo['memory_used_mb']
            ]);
        }
        
        // Alert on missing indexes
        if (isset($debugInfo['execution_plan']['basic'])) {
            foreach ($debugInfo['execution_plan']['basic'] as $row) {
                if (isset($row['Extra']) && 
                    strpos($row['Extra'], 'Using filesort') !== false) {
                    
                    $this->logger->notice('Query using filesort - consider adding index', [
                        'table' => $row['table'] ?? 'unknown',
                        'sql' => $debugInfo['sql']
                    ]);
                }
            }
        }
        
        // Track query patterns
        $this->trackQueryPattern($debugInfo);
    }
    
    private function trackQueryPattern(array $debugInfo): void
    {
        // Implementation for tracking common query patterns
        // Could be used to identify optimization opportunities
    }
}

// Use the debug handler
$debugHandler = new QueryDebugHandler();
$debugInfo = $userRepo->query()
    ->where('status', 'active')
    ->debug('array', true);
    
$debugHandler->handleDebug($debugInfo);

// Example 16: Conditional Debugging
// =================================
// Only debug when specific conditions are met
$query = $productRepo->query()
    ->where('category', $category);

// Debug only if query is complex
if (count($filters) > 3) {
    $query->debug('text', false);
}

$results = $query->get();

// Example 17: Debug with Profiler Integration
// ===========================================
if (class_exists('Symfony\Component\Stopwatch\Stopwatch')) {
    $stopwatch = new Stopwatch();
    
    $stopwatch->start('database_query');
    
    $debugInfo = $userRepo->query()
        ->where('status', 'active')
        ->debug('array', true);
    
    $event = $stopwatch->stop('database_query');
    
    echo sprintf(
        "Query: %.2f ms, PHP overhead: %.2f ms\n",
        $debugInfo['execution_time_ms'],
        $event->getDuration() - $debugInfo['execution_time_ms']
    );
}