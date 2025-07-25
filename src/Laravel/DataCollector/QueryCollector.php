<?php

namespace WebmanTech\Debugbar\Laravel\DataCollector;

use DebugBar\DataCollector\PDO\PDOCollector;
use DebugBar\DataCollector\TimeDataCollector;
use Illuminate\Support\Str;

/**
 * @link https://github.com/barryvdh/laravel-debugbar/blob/master/src/DataCollector/QueryCollector.php
 *
 * Collects data about SQL statements executed with PDO
 */
class QueryCollector extends PDOCollector
{
    protected $timeCollector;
    protected $queries = [];
    protected $renderSqlWithParams = false;
    protected $findSource = false;
    protected $middleware = [];
    protected $durationBackground = true;
    protected $explainQuery = false;
    protected $explainTypes = ['SELECT']; // ['SELECT', 'INSERT', 'UPDATE', 'DELETE']; for MySQL 5.6.3+
    protected $showHints = false;
    protected $showCopyButton = false;
    protected $reflection = [];
    protected $backtraceExcludePaths = [
        '/vendor/laravel/framework/src/Illuminate/Support',
        '/vendor/laravel/framework/src/Illuminate/Database',
        '/vendor/laravel/framework/src/Illuminate/Events',
        '/vendor/october/rain',
        '/vendor/barryvdh/laravel-debugbar',
    ];

    /**
     * @param TimeDataCollector|null $timeCollector
     */
    public function __construct(?TimeDataCollector $timeCollector = null)
    {
        $this->timeCollector = $timeCollector;
    }

    /**
     * Renders the SQL of traced statements with params embedded
     *
     * @param boolean $enabled
     * @param string $quotationChar NOT USED
     */
    public function setRenderSqlWithParams($enabled = true, $quotationChar = "'")
    {
        $this->renderSqlWithParams = $enabled;
    }

    /**
     * Show or hide the hints in the parameters
     *
     * @param boolean $enabled
     */
    public function setShowHints($enabled = true)
    {
        $this->showHints = $enabled;
    }

    /**
     * Show or hide copy button next to the queries
     *
     * @param boolean $enabled
     */
    public function setShowCopyButton($enabled = true)
    {
        $this->showCopyButton = $enabled;
    }

    /**
     * Enable/disable finding the source
     *
     * @param bool $value
     * @param array $middleware
     */
    public function setFindSource($value, array $middleware)
    {
        $this->findSource = (bool)$value;
        $this->middleware = $middleware;
    }

    /**
     * Set additional paths to exclude from the backtrace
     *
     * @param array $excludePaths Array of file paths to exclude from backtrace
     */
    public function mergeBacktraceExcludePaths(array $excludePaths)
    {
        $this->backtraceExcludePaths = array_merge($this->backtraceExcludePaths, $excludePaths);
    }

    /**
     * Enable/disable the shaded duration background on queries
     *
     * @param bool $enabled
     */
    public function setDurationBackground($enabled)
    {
        $this->durationBackground = $enabled;
    }

    /**
     * Enable/disable the EXPLAIN queries
     *
     * @param bool $enabled
     * @param array|null $types Array of types to explain queries (select/insert/update/delete)
     */
    public function setExplainSource($enabled, $types)
    {
        $this->explainQuery = $enabled;
        // workaround ['SELECT'] only. https://github.com/barryvdh/laravel-debugbar/issues/888
//        if($types){
//            $this->explainTypes = $types;
//        }
    }

    /**
     *
     * @param string $query
     * @param array $bindings
     * @param float $time
     * @param \Illuminate\Database\Connection $connection
     */
    public function addQuery($query, $bindings, $time, $connection)
    {
        $explainResults = [];
        $time /= 1000;
        $endTime = microtime(true);
        $startTime = $endTime - $time;
        $hints = $this->performQueryAnalysis($query);

        $pdo = null;
        try {
            $pdo = $connection->getPdo();
        } catch (\Exception) {
            // ignore error for non-pdo laravel drivers
        }
        $bindings = $connection->prepareBindings($bindings);

        // Run EXPLAIN on this query (if needed)
        if ($this->explainQuery && $pdo && preg_match('/^\s*(' . implode('|', $this->explainTypes) . ') /i', $query)) {
            $statement = $pdo->prepare('EXPLAIN ' . $query);
            $statement->execute($bindings);
            $explainResults = $statement->fetchAll(\PDO::FETCH_CLASS);
        }

        $bindings = $this->getDataFormatter()->checkBindings($bindings);
        if (!empty($bindings) && $this->renderSqlWithParams) {
            foreach ($bindings as $key => $binding) {
                // This regex matches placeholders only, not the question marks,
                // nested in quotes, while we iterate through the bindings
                // and substitute placeholders by suitable values.
                $regex = is_numeric($key)
                    ? "/(?<!\?)\?(?=(?:[^'\\\']*'[^'\\']*')*[^'\\\']*$)(?!\?)/"
                    : "/:{$key}(?=(?:[^'\\\']*'[^'\\\']*')*[^'\\\']*$)/";

                // Mimic bindValue and only quote non-integer and non-float data types
                if (!is_int($binding) && !is_float($binding)) {
                    if ($pdo) {
                        try {
                            $binding = $pdo->quote((string)$binding);
                        } catch (\Exception) {
                            $binding = $this->emulateQuote($binding);
                        }
                    } else {
                        $binding = $this->emulateQuote($binding);
                    }
                }

                $query = preg_replace($regex, addcslashes($binding, '$'), (string)$query, 1);
            }
        }

        $source = [];

        if ($this->findSource) {
            try {
                $source = array_slice($this->findSource(), 0, 5);
            } catch (\Exception) {
            }
        }

        $this->queries[] = [
            'query' => $query,
            'type' => 'query',
            'bindings' => $this->getDataFormatter()->escapeBindings($bindings),
            'time' => $time,
            'source' => $source,
            'explain' => $explainResults,
            'connection' => $connection->getDatabaseName(),
            'driver' => $connection->getConfig('driver'),
            'hints' => $this->showHints ? $hints : null,
            'show_copy' => $this->showCopyButton,
        ];

        if ($this->timeCollector !== null) {
            $this->timeCollector->addMeasure(Str::limit($query, 100), $startTime, $endTime);
        }
    }

    /**
     * Mimic mysql_real_escape_string
     *
     * @param string $value
     * @return string
     */
    protected function emulateQuote($value)
    {
        $search = ["\\", "\x00", "\n", "\r", "'", '"', "\x1a"];
        $replace = ["\\\\", "\\0", "\\n", "\\r", "\'", '\"', "\\Z"];

        return "'" . str_replace($search, $replace, (string)$value) . "'";
    }

    /**
     * Explainer::performQueryAnalysis()
     *
     * Perform simple regex analysis on the code
     *
     * @package xplain (https://github.com/rap2hpoutre/mysql-xplain-xplain)
     * @author e-doceo
     * @copyright 2014
     * @version $Id$
     * @access public
     * @param string $query
     * @return string[]
     */
    protected function performQueryAnalysis($query)
    {
        // @codingStandardsIgnoreStart
        $hints = [];
        if (preg_match('/^\\s*SELECT\\s*`?[a-zA-Z0-9]*`?\\.?\\*/i', $query)) {
            $hints[] = 'Use <code>SELECT *</code> only if you need all columns from table';
        }
        if (preg_match('/ORDER BY RAND()/i', $query)) {
            $hints[] = '<code>ORDER BY RAND()</code> is slow, try to avoid if you can.
                You can <a href="http://stackoverflow.com/questions/2663710/how-does-mysqls-order-by-rand-work" target="_blank">read this</a>
                or <a href="http://stackoverflow.com/questions/1244555/how-can-i-optimize-mysqls-order-by-rand-function" target="_blank">this</a>';
        }
        if (str_contains($query, '!=')) {
            $hints[] = 'The <code>!=</code> operator is not standard. Use the <code>&lt;&gt;</code> operator to test for inequality instead.';
        }
        if (stripos($query, 'WHERE') === false && preg_match('/^(SELECT) /i', $query)) {
            $hints[] = 'The <code>SELECT</code> statement has no <code>WHERE</code> clause and could examine many more rows than intended';
        }
        if (preg_match('/LIMIT\\s/i', $query) && stripos($query, 'ORDER BY') === false) {
            $hints[] = '<code>LIMIT</code> without <code>ORDER BY</code> causes non-deterministic results, depending on the query execution plan';
        }
        if (preg_match('/LIKE\\s[\'"](%.*?)[\'"]/i', $query, $matches)) {
            $hints[] = 'An argument has a leading wildcard character: <code>' . $matches[1] . '</code>.
                The predicate with this argument is not sargable and cannot use an index if one exists.';
        }
        return $hints;

        // @codingStandardsIgnoreEnd
    }

    /**
     * Use a backtrace to search for the origins of the query.
     *
     * @return array
     */
    protected function findSource()
    {
        $stack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS | DEBUG_BACKTRACE_PROVIDE_OBJECT, 50);

        $sources = [];

        foreach ($stack as $index => $trace) {
            $sources[] = $this->parseTrace($index, $trace);
        }

        return array_filter($sources);
    }

    /**
     * Parse a trace element from the backtrace stack.
     *
     * @param int $index
     * @param array $trace
     * @return object|bool
     */
    protected function parseTrace($index, array $trace)
    {
        $frame = (object)[
            'index' => $index,
            'namespace' => null,
            'name' => null,
            'line' => $trace['line'] ?? '?',
        ];

        if (isset($trace['function']) && $trace['function'] == 'substituteBindings') {
            $frame->name = 'Route binding';

            return $frame;
        }

        if (
            isset($trace['class']) &&
            isset($trace['file']) &&
            !$this->fileIsInExcludedPath($trace['file'])
        ) {
            $file = $trace['file'];

            if (isset($trace['object']) && is_a($trace['object'], 'Twig_Template')) {
                [$file, $frame->line] = $this->getTwigInfo($trace);
            } elseif (str_contains($file, base_path() . '/storage')) {
                $hash = pathinfo($file, PATHINFO_FILENAME);

                if (!$frame->name = $this->findViewFromHash($hash)) {
                    $frame->name = $hash;
                }

                $frame->namespace = 'view';

                return $frame;
            } elseif (str_contains($file, 'Middleware')) {
                $frame->name = $this->findMiddlewareFromFile($file);

                if ($frame->name) {
                    $frame->namespace = 'middleware';
                } else {
                    $frame->name = $this->normalizeFilename($file);
                }

                return $frame;
            }

            $frame->name = $this->normalizeFilename($file);

            return $frame;
        }


        return false;
    }

    /**
     * Check if the given file is to be excluded from analysis
     *
     * @param string $file
     * @return bool
     */
    protected function fileIsInExcludedPath($file)
    {
        $normalizedPath = str_replace('\\', '/', $file);

        foreach ($this->backtraceExcludePaths as $excludedPath) {
            if (str_contains($normalizedPath, (string)$excludedPath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find the middleware alias from the file.
     *
     * @param string $file
     * @return string|null
     */
    protected function findMiddlewareFromFile($file)
    {
        $filename = pathinfo($file, PATHINFO_FILENAME);

        foreach ($this->middleware as $alias => $class) {
            if (str_contains((string)$class, $filename)) {
                return $alias;
            }
        }
    }

    /**
     * Find the template name from the hash.
     *
     * @param string $hash
     * @return null|string
     */
    protected function findViewFromHash($hash)
    {
        return null;

        $finder = app('view')->getFinder();

        if (isset($this->reflection['viewfinderViews'])) {
            $property = $this->reflection['viewfinderViews'];
        } else {
            $reflection = new \ReflectionClass($finder);
            $property = $reflection->getProperty('views');
            $property->setAccessible(true);
            $this->reflection['viewfinderViews'] = $property;
        }

        foreach ($property->getValue($finder) as $name => $path) {
            if (sha1((string)$path) == $hash || md5((string)$path) == $hash) {
                return $name;
            }
        }
    }

    /**
     * Get the filename/line from a Twig template trace
     *
     * @param array $trace
     * @return array The file and line
     */
    protected function getTwigInfo($trace)
    {
        $file = $trace['object']->getTemplateName();

        if (isset($trace['line'])) {
            foreach ($trace['object']->getDebugInfo() as $codeLine => $templateLine) {
                if ($codeLine <= $trace['line']) {
                    return [$file, $templateLine];
                }
            }
        }

        return [$file, -1];
    }

    /**
     * Shorten the path by removing the relative links and base dir
     *
     * @param string $path
     * @return string
     */
    protected function normalizeFilename($path)
    {
        if (file_exists($path)) {
            $path = realpath($path);
        }
        return str_replace(base_path(), '', $path);
    }

    /**
     * Collect a database transaction event.
     * @param string $event
     * @param \Illuminate\Database\Connection $connection
     * @return array
     */
    public function collectTransactionEvent($event, $connection)
    {
        $source = [];

        if ($this->findSource) {
            try {
                $source = $this->findSource();
            } catch (\Exception) {
            }
        }

        $this->queries[] = [
            'query' => $event,
            'type' => 'transaction',
            'bindings' => [],
            'time' => 0,
            'source' => $source,
            'explain' => [],
            'connection' => $connection->getDatabaseName(),
            'driver' => $connection->getConfig('driver'),
            'hints' => null,
            'show_copy' => false,
        ];
    }

    /**
     * Reset the queries.
     */
    public function reset()
    {
        $this->queries = [];
    }

    /**
     * {@inheritDoc}
     */
    public function collect()
    {
        $totalTime = 0;
        $queries = $this->queries;

        $statements = [];
        foreach ($queries as $query) {
            $totalTime += $query['time'];

            $statements[] = [
                'sql' => $this->getDataFormatter()->formatSql($query['query']),
                'type' => $query['type'],
                'params' => [],
                'bindings' => $query['bindings'],
                'hints' => $query['hints'],
                'show_copy' => $query['show_copy'],
                'backtrace' => array_values($query['source']),
                'duration' => $query['time'],
                'duration_str' => ($query['type'] == 'transaction') ? '' : $this->formatDuration($query['time']),
                'stmt_id' => $this->getDataFormatter()->formatSource(reset($query['source'])),
                'connection' => $query['connection'],
            ];

            // Add the results from the explain as new rows
            if ($query['driver'] === 'pgsql') {
                $explainer = trim(implode("\n", array_map(fn($explain) => $explain->{'QUERY PLAN'}, $query['explain'])));

                if ($explainer) {
                    $statements[] = [
                        'sql' => " - EXPLAIN: {$explainer}",
                        'type' => 'explain',
                    ];
                }
            } else {
                foreach ($query['explain'] as $explain) {
                    $statements[] = [
                        'sql' => " - EXPLAIN # {$explain->id}: `{$explain->table}` ({$explain->select_type})",
                        'type' => 'explain',
                        'params' => $explain,
                        'row_count' => $explain->rows,
                        'stmt_id' => $explain->id,
                    ];
                }
            }
        }

        if ($this->durationBackground) {
            if ($totalTime > 0) {
                // For showing background measure on Queries tab
                $start_percent = 0;

                foreach ($statements as $i => $statement) {
                    if (!isset($statement['duration'])) {
                        continue;
                    }

                    $width_percent = $statement['duration'] / $totalTime * 100;

                    $statements[$i] = array_merge($statement, [
                        'start_percent' => round($start_percent, 3),
                        'width_percent' => round($width_percent, 3),
                    ]);

                    $start_percent += $width_percent;
                }
            }
        }

        $nb_statements = array_filter($queries, fn($query) => $query['type'] === 'query');

        $data = [
            'nb_statements' => count($nb_statements),
            'nb_failed_statements' => 0,
            'accumulated_duration' => $totalTime,
            'accumulated_duration_str' => $this->formatDuration($totalTime),
            'statements' => $statements
        ];
        return $data;
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'queries';
    }

    /**
     * {@inheritDoc}
     */
    public function getWidgets()
    {
        return [
            "queries" => [
                "icon" => "database",
                "widget" => "PhpDebugBar.Widgets.LaravelSQLQueriesWidget",
                "map" => "queries",
                "default" => "[]"
            ],
            "queries:badge" => [
                "map" => "queries.nb_statements",
                "default" => 0
            ]
        ];
    }
}
