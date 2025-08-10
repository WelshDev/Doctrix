<?php

namespace WelshDev\Doctrix\Debug;

use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Logging\DebugStack;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Exception;

/**
 * Query debugger for analyzing and profiling queries
 *
 * Provides detailed information about query execution including:
 * - Generated SQL
 * - Bound parameters
 * - Execution time
 * - Memory usage
 * - Query execution plan (when available)
 */
class QueryDebugger
{
    /**
     * Debug output formats
     */
    public const FORMAT_TEXT = 'text';
    public const FORMAT_HTML = 'html';
    public const FORMAT_JSON = 'json';
    public const FORMAT_ARRAY = 'array';

    /**
     * Execute query with debug information
     *
     * @param QueryBuilder $queryBuilder
     * @param string $format Output format
     * @param bool $execute Whether to actually execute the query
     * @return array Debug information
     */
    public static function debug(QueryBuilder $queryBuilder, string $format = self::FORMAT_TEXT, bool $execute = true): array
    {
        $query = $queryBuilder->getQuery();
        $connection = $queryBuilder->getEntityManager()->getConnection();

        // Collect basic query info
        $debugInfo = [
            'sql' => self::getSql($query),
            'dql' => $query->getDQL(),
            'parameters' => self::getParameters($query),
            'formatted_sql' => self::getFormattedSql($query),
        ];

        // Execute with timing if requested
        if ($execute)
        {
            $executionInfo = self::executeWithProfiling($query, $connection);
            $debugInfo = array_merge($debugInfo, $executionInfo);
        }

        // Get execution plan if available
        $debugInfo['execution_plan'] = self::getExecutionPlan($connection, $debugInfo['sql'], $debugInfo['parameters']);

        // Format and output based on requested format
        self::output($debugInfo, $format);

        return $debugInfo;
    }

    /**
     * Get the SQL query string
     *
     * @param Query $query
     * @return string
     */
    private static function getSql(Query $query): string
    {
        return $query->getSQL();
    }

    /**
     * Get formatted SQL with parameters inline
     *
     * @param Query $query
     * @return string
     */
    private static function getFormattedSql(Query $query): string
    {
        $sql = $query->getSQL();
        $params = self::getParameters($query);

        // Replace positional parameters
        foreach ($params['positional'] as $key => $value)
        {
            $placeholder = '?';
            $replacement = self::formatValue($value);
            $sql = preg_replace('/\?/', $replacement, $sql, 1);
        }

        // Replace named parameters
        foreach ($params['named'] as $key => $value)
        {
            $placeholder = ':' . $key;
            $replacement = self::formatValue($value);
            $sql = str_replace($placeholder, $replacement, $sql);
        }

        return self::prettifySql($sql);
    }

    /**
     * Get query parameters
     *
     * @param Query $query
     * @return array
     */
    private static function getParameters(Query $query): array
    {
        $parameters = [
            'named' => [],
            'positional' => [],
            'types' => [],
        ];

        foreach ($query->getParameters() as $parameter)
        {
            $name = $parameter->getName();
            $value = $parameter->getValue();
            $type = $parameter->getType();

            if (is_numeric($name))
            {
                $parameters['positional'][$name] = $value;
            }
            else
            {
                $parameters['named'][$name] = $value;
            }

            if ($type !== null)
            {
                $parameters['types'][$name] = $type;
            }
        }

        return $parameters;
    }

    /**
     * Execute query with profiling information
     *
     * @param Query $query
     * @param Connection $connection
     * @return array
     */
    private static function executeWithProfiling(Query $query, Connection $connection): array
    {
        // Set up SQL logger
        $logger = new DebugStack();
        $oldLogger = $connection->getConfiguration()->getSQLLogger();
        $connection->getConfiguration()->setSQLLogger($logger);

        // Profile execution
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        try
        {
            $result = $query->getResult();
            $resultCount = is_array($result) ? count($result) : 0;
        }
        catch (Exception $e)
        {
            $connection->getConfiguration()->setSQLLogger($oldLogger);

            return [
                'error' => $e->getMessage(),
                'execution_time' => microtime(true) - $startTime,
                'memory_used' => memory_get_usage() - $startMemory,
            ];
        }

        $executionTime = microtime(true) - $startTime;
        $memoryUsed = memory_get_usage() - $startMemory;

        // Restore original logger
        $connection->getConfiguration()->setSQLLogger($oldLogger);

        // Get query statistics
        $stats = [];
        if (!empty($logger->queries))
        {
            $queryInfo = end($logger->queries);
            $stats = [
                'actual_sql' => $queryInfo['sql'] ?? '',
                'actual_params' => $queryInfo['params'] ?? [],
                'actual_types' => $queryInfo['types'] ?? [],
                'doctrine_execution_time' => $queryInfo['executionMS'] ?? 0,
            ];
        }

        return array_merge([
            'execution_time' => $executionTime,
            'execution_time_ms' => round($executionTime * 1000, 2),
            'memory_used' => $memoryUsed,
            'memory_used_mb' => round($memoryUsed / 1024 / 1024, 2),
            'result_count' => $resultCount,
            'peak_memory' => memory_get_peak_usage(),
            'peak_memory_mb' => round(memory_get_peak_usage() / 1024 / 1024, 2),
        ], $stats);
    }

    /**
     * Get query execution plan
     *
     * @param Connection $connection
     * @param string $sql
     * @param array $parameters
     * @return array|null
     */
    private static function getExecutionPlan(Connection $connection, string $sql, array $parameters): ?array
    {
        try
        {
            $driver = $connection->getDriver()->getName();

            // MySQL execution plan
            if (strpos($driver, 'mysql') !== false)
            {
                return self::getMySQLExecutionPlan($connection, $sql, $parameters);
            }

            // PostgreSQL execution plan
            if (strpos($driver, 'pgsql') !== false || strpos($driver, 'postgres') !== false)
            {
                return self::getPostgreSQLExecutionPlan($connection, $sql, $parameters);
            }

            // SQLite execution plan
            if (strpos($driver, 'sqlite') !== false)
            {
                return self::getSQLiteExecutionPlan($connection, $sql, $parameters);
            }
        }
        catch (Exception $e)
        {
            return ['error' => 'Could not retrieve execution plan: ' . $e->getMessage()];
        }

        return null;
    }

    /**
     * Get MySQL execution plan
     *
     * @param Connection $connection
     * @param string $sql
     * @param array $parameters
     * @return array
     */
    private static function getMySQLExecutionPlan(Connection $connection, string $sql, array $parameters): array
    {
        // Prepare EXPLAIN query
        $explainSql = 'EXPLAIN ' . $sql;

        // Bind parameters for explain
        $stmt = $connection->prepare($explainSql);
        foreach ($parameters['positional'] as $key => $value)
        {
            $stmt->bindValue($key, $value);
        }
        foreach ($parameters['named'] as $key => $value)
        {
            $stmt->bindValue($key, $value);
        }

        $result = $stmt->executeQuery();
        $plan = $result->fetchAllAssociative();

        // Try to get extended explain if available
        try
        {
            $extendedSql = 'EXPLAIN FORMAT=JSON ' . $sql;
            $stmt = $connection->prepare($extendedSql);
            foreach ($parameters['positional'] as $key => $value)
            {
                $stmt->bindValue($key, $value);
            }
            foreach ($parameters['named'] as $key => $value)
            {
                $stmt->bindValue($key, $value);
            }

            $result = $stmt->executeQuery();
            $jsonPlan = $result->fetchOne();

            return [
                'basic' => $plan,
                'extended' => json_decode($jsonPlan, true),
            ];
        }
        catch (Exception $e)
        {
            return ['basic' => $plan];
        }
    }

    /**
     * Get PostgreSQL execution plan
     *
     * @param Connection $connection
     * @param string $sql
     * @param array $parameters
     * @return array
     */
    private static function getPostgreSQLExecutionPlan(Connection $connection, string $sql, array $parameters): array
    {
        $explainSql = 'EXPLAIN (ANALYZE true, BUFFERS true, FORMAT JSON) ' . $sql;

        $stmt = $connection->prepare($explainSql);
        foreach ($parameters['positional'] as $key => $value)
        {
            $stmt->bindValue($key, $value);
        }
        foreach ($parameters['named'] as $key => $value)
        {
            $stmt->bindValue($key, $value);
        }

        $result = $stmt->executeQuery();
        $jsonPlan = $result->fetchOne();

        return json_decode($jsonPlan, true);
    }

    /**
     * Get SQLite execution plan
     *
     * @param Connection $connection
     * @param string $sql
     * @param array $parameters
     * @return array
     */
    private static function getSQLiteExecutionPlan(Connection $connection, string $sql, array $parameters): array
    {
        $explainSql = 'EXPLAIN QUERY PLAN ' . $sql;

        $stmt = $connection->prepare($explainSql);
        foreach ($parameters['positional'] as $key => $value)
        {
            $stmt->bindValue($key, $value);
        }
        foreach ($parameters['named'] as $key => $value)
        {
            $stmt->bindValue($key, $value);
        }

        $result = $stmt->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * Format a value for display in SQL
     *
     * @param mixed $value
     * @return string
     */
    private static function formatValue($value): string
    {
        if ($value === null)
        {
            return 'NULL';
        }

        if (is_bool($value))
        {
            return $value ? '1' : '0';
        }

        if (is_numeric($value))
        {
            return (string) $value;
        }

        if ($value instanceof DateTime)
        {
            return "'" . $value->format('Y-m-d H:i:s') . "'";
        }

        if (is_array($value))
        {
            return '(' . implode(', ', array_map([self::class, 'formatValue'], $value)) . ')';
        }

        if (is_object($value))
        {
            if (method_exists($value, 'getId'))
            {
                return (string) $value->getId();
            }
            if (method_exists($value, '__toString'))
            {
                return "'" . addslashes((string) $value) . "'";
            }
        }

        return "'" . addslashes((string) $value) . "'";
    }

    /**
     * Prettify SQL for better readability
     *
     * @param string $sql
     * @return string
     */
    private static function prettifySql(string $sql): string
    {
        $keywords = [
            'SELECT', 'FROM', 'WHERE', 'AND', 'OR', 'LEFT JOIN', 'INNER JOIN',
            'RIGHT JOIN', 'JOIN', 'ON', 'GROUP BY', 'ORDER BY', 'HAVING',
            'LIMIT', 'OFFSET', 'UNION', 'INSERT', 'UPDATE', 'DELETE', 'SET',
        ];

        $formatted = $sql;
        foreach ($keywords as $keyword)
        {
            $formatted = preg_replace('/\b' . $keyword . '\b/i', "\n" . $keyword, $formatted);
        }

        // Clean up multiple newlines
        $formatted = preg_replace('/\n+/', "\n", $formatted);

        // Indent for readability
        $lines = explode("\n", $formatted);
        $indented = [];
        $indent = 0;

        foreach ($lines as $line)
        {
            $trimmed = trim($line);
            if (empty($trimmed))
            {
                continue;
            }

            // Decrease indent for certain keywords
            if (preg_match('/^(FROM|WHERE|GROUP BY|ORDER BY|HAVING)/i', $trimmed))
            {
                $indent = 0;
            }

            $indented[] = str_repeat('  ', $indent) . $trimmed;

            // Increase indent after certain keywords
            if (preg_match('/^(SELECT|FROM|WHERE)/i', $trimmed))
            {
                $indent = 1;
            }
        }

        return implode("\n", $indented);
    }

    /**
     * Output debug information in specified format
     *
     * @param array $debugInfo
     * @param string $format
     * @return void
     */
    private static function output(array $debugInfo, string $format): void
    {
        switch ($format)
        {
            case self::FORMAT_HTML:
                self::outputHtml($debugInfo);
                break;
            case self::FORMAT_JSON:
                self::outputJson($debugInfo);
                break;
            case self::FORMAT_ARRAY:
                // No output for array format, just return
                break;
            case self::FORMAT_TEXT:
            default:
                self::outputText($debugInfo);
                break;
        }
    }

    /**
     * Output debug info as formatted text
     *
     * @param array $debugInfo
     * @return void
     */
    private static function outputText(array $debugInfo): void
    {
        echo "\n";
        echo "╔════════════════════════════════════════════════════════════════════╗\n";
        echo "║                        QUERY DEBUG OUTPUT                          ║\n";
        echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

        // SQL Query
        echo "📝 SQL QUERY:\n";
        echo "─────────────\n";
        echo $debugInfo['formatted_sql'] . "\n\n";

        // Parameters
        if (!empty($debugInfo['parameters']['named']) || !empty($debugInfo['parameters']['positional']))
        {
            echo "🔧 PARAMETERS:\n";
            echo "──────────────\n";

            if (!empty($debugInfo['parameters']['named']))
            {
                echo "Named:\n";
                foreach ($debugInfo['parameters']['named'] as $key => $value)
                {
                    echo "  :{$key} = " . self::formatValue($value) . "\n";
                }
            }

            if (!empty($debugInfo['parameters']['positional']))
            {
                echo "Positional:\n";
                foreach ($debugInfo['parameters']['positional'] as $key => $value)
                {
                    echo "  [{$key}] = " . self::formatValue($value) . "\n";
                }
            }
            echo "\n";
        }

        // Execution Stats
        if (isset($debugInfo['execution_time']))
        {
            echo "⚡ EXECUTION STATS:\n";
            echo "───────────────────\n";
            echo "  Time: {$debugInfo['execution_time_ms']} ms\n";
            echo "  Memory: {$debugInfo['memory_used_mb']} MB\n";
            echo "  Peak Memory: {$debugInfo['peak_memory_mb']} MB\n";

            if (isset($debugInfo['result_count']))
            {
                echo "  Results: {$debugInfo['result_count']} rows\n";
            }
            echo "\n";
        }

        // Execution Plan
        if (!empty($debugInfo['execution_plan']))
        {
            echo "📊 EXECUTION PLAN:\n";
            echo "──────────────────\n";

            if (isset($debugInfo['execution_plan']['basic']))
            {
                foreach ($debugInfo['execution_plan']['basic'] as $row)
                {
                    foreach ($row as $key => $value)
                    {
                        echo "  {$key}: {$value}\n";
                    }
                    echo "  ---\n";
                }
            }
            else
            {
                echo '  ' . json_encode($debugInfo['execution_plan'], JSON_PRETTY_PRINT) . "\n";
            }
            echo "\n";
        }

        // DQL
        if (!empty($debugInfo['dql']))
        {
            echo "🔤 DQL:\n";
            echo "──────\n";
            echo $debugInfo['dql'] . "\n\n";
        }

        echo "═══════════════════════════════════════════════════════════════════════\n\n";
    }

    /**
     * Output debug info as HTML
     *
     * @param array $debugInfo
     * @return void
     */
    private static function outputHtml(array $debugInfo): void
    {
        $html = <<<HTML
<style>
    .doctrix-debug {
        font-family: 'Courier New', monospace;
        background: #1e1e1e;
        color: #d4d4d4;
        padding: 20px;
        border-radius: 8px;
        margin: 20px 0;
    }
    .doctrix-debug h3 {
        color: #569cd6;
        border-bottom: 2px solid #569cd6;
        padding-bottom: 5px;
    }
    .doctrix-debug pre {
        background: #2d2d2d;
        padding: 15px;
        border-radius: 4px;
        overflow-x: auto;
    }
    .doctrix-debug .sql {
        color: #ce9178;
    }
    .doctrix-debug .param {
        color: #9cdcfe;
    }
    .doctrix-debug .stat {
        color: #b5cea8;
    }
    .doctrix-debug table {
        width: 100%;
        border-collapse: collapse;
        margin: 10px 0;
    }
    .doctrix-debug th {
        background: #2d2d2d;
        padding: 8px;
        text-align: left;
        color: #569cd6;
    }
    .doctrix-debug td {
        padding: 8px;
        border-bottom: 1px solid #3c3c3c;
    }
</style>
<div class="doctrix-debug">
    <h2>🔍 Doctrix Query Debug</h2>
HTML;

        // SQL
        $html .= '<h3>SQL Query</h3>';
        $html .= '<pre class="sql">' . htmlspecialchars($debugInfo['formatted_sql']) . '</pre>';

        // Parameters
        if (!empty($debugInfo['parameters']['named']) || !empty($debugInfo['parameters']['positional']))
        {
            $html .= '<h3>Parameters</h3>';
            $html .= '<table>';

            foreach ($debugInfo['parameters']['named'] as $key => $value)
            {
                $html .= '<tr><td class="param">:' . $key . '</td><td>' . htmlspecialchars(self::formatValue($value)) . '</td></tr>';
            }

            foreach ($debugInfo['parameters']['positional'] as $key => $value)
            {
                $html .= '<tr><td class="param">[' . $key . ']</td><td>' . htmlspecialchars(self::formatValue($value)) . '</td></tr>';
            }

            $html .= '</table>';
        }

        // Stats
        if (isset($debugInfo['execution_time']))
        {
            $html .= '<h3>Execution Statistics</h3>';
            $html .= '<table>';
            $html .= '<tr><td>Time:</td><td class="stat">' . $debugInfo['execution_time_ms'] . ' ms</td></tr>';
            $html .= '<tr><td>Memory:</td><td class="stat">' . $debugInfo['memory_used_mb'] . ' MB</td></tr>';
            $html .= '<tr><td>Peak Memory:</td><td class="stat">' . $debugInfo['peak_memory_mb'] . ' MB</td></tr>';

            if (isset($debugInfo['result_count']))
            {
                $html .= '<tr><td>Results:</td><td class="stat">' . $debugInfo['result_count'] . ' rows</td></tr>';
            }

            $html .= '</table>';
        }

        // Execution Plan
        if (!empty($debugInfo['execution_plan']) && isset($debugInfo['execution_plan']['basic']))
        {
            $html .= '<h3>Execution Plan</h3>';
            $html .= '<table>';

            $headers = array_keys($debugInfo['execution_plan']['basic'][0] ?? []);
            if (!empty($headers))
            {
                $html .= '<tr>';
                foreach ($headers as $header)
                {
                    $html .= '<th>' . htmlspecialchars($header) . '</th>';
                }
                $html .= '</tr>';

                foreach ($debugInfo['execution_plan']['basic'] as $row)
                {
                    $html .= '<tr>';
                    foreach ($row as $value)
                    {
                        $html .= '<td>' . htmlspecialchars($value) . '</td>';
                    }
                    $html .= '</tr>';
                }
            }

            $html .= '</table>';
        }

        $html .= '</div>';

        echo $html;
    }

    /**
     * Output debug info as JSON
     *
     * @param array $debugInfo
     * @return void
     */
    private static function outputJson(array $debugInfo): void
    {
        header('Content-Type: application/json');
        echo json_encode($debugInfo, JSON_PRETTY_PRINT);
    }
}
