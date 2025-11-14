<?php

if (!class_exists('DebugProfiler')) {
    class DebugProfiler
    {
        private static bool $initialized = false;
        private static bool $enabled = false;
        private static float $startTime = 0.0;
        private static array $queries = [];
        private static int $queryCount = 0;
        private static float $queriesTime = 0.0;

        public static function init(): void
        {
            if (self::$initialized) {
                return;
            }
            self::$startTime = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);
            self::$initialized = true;
        }

        public static function enable(bool $flag): void
        {
            self::$enabled = $flag;
        }

        public static function isEnabled(): bool
        {
            return self::$enabled;
        }

        public static function recordQuery(string $sql, float $duration, ?array $params = null): void
        {
            if (!self::$enabled) {
                return;
            }
            self::$queryCount++;
            self::$queriesTime += $duration;

            if (count(self::$queries) < 50) {
                self::$queries[] = [
                    'sql' => trim($sql),
                    'duration_ms' => round($duration * 1000, 3),
                    'params' => $params,
                ];
            }
        }

        public static function getSummary(): array
        {
            $execution = microtime(true) - self::$startTime;

            return [
                'execution_time_ms' => round($execution * 1000, 2),
                'queries_count' => self::$queryCount,
                'queries_time_ms' => round(self::$queriesTime * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / (1024 * 1024), 2),
                'peak_memory_mb' => round(memory_get_peak_usage(true) / (1024 * 1024), 2),
                'php_version' => PHP_VERSION,
                'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
                'server' => $_SERVER['HTTP_HOST'] ?? php_uname('n'),
                'generated_at' => date('Y-m-d H:i:s'),
                'queries' => self::$queries,
            ];
        }
    }

    DebugProfiler::init();
}

if (!class_exists('DebugPDO')) {
    class DebugPDO extends PDO
    {
        public function __construct($dsn, $username = null, $passwd = null, $options = [])
        {
            parent::__construct($dsn, $username, $passwd, $options);

            if (DebugProfiler::isEnabled()) {
                $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [DebugPDOStatement::class, []]);
            }
        }

        public function exec($statement): int|false
        {
            if (!DebugProfiler::isEnabled()) {
                return parent::exec($statement);
            }

            $start = microtime(true);
            try {
                return parent::exec($statement);
            } finally {
                DebugProfiler::recordQuery($statement, microtime(true) - $start);
            }
        }

        public function query(string $statement, ?int $mode = null, ...$fetch_mode_args)
        {
            if (!DebugProfiler::isEnabled()) {
                return $this->runParentQuery($statement, $mode, $fetch_mode_args);
            }

            $start = microtime(true);
            try {
                return $this->runParentQuery($statement, $mode, $fetch_mode_args);
            } finally {
                DebugProfiler::recordQuery($statement, microtime(true) - $start);
            }
        }

        private function runParentQuery(string $statement, ?int $mode, array $fetchArgs)
        {
            if ($mode === null) {
                return parent::query($statement);
            }

            if (empty($fetchArgs)) {
                return parent::query($statement, $mode);
            }

            return parent::query($statement, $mode, ...$fetchArgs);
        }
    }
}

if (!class_exists('DebugPDOStatement')) {
    class DebugPDOStatement extends PDOStatement
    {
        protected function __construct()
        {
        }

        public function execute(?array $params = null): bool
        {
            if (!DebugProfiler::isEnabled()) {
                return parent::execute($params ?? null);
            }

            $start = microtime(true);
            try {
                return parent::execute($params ?? null);
            } finally {
                DebugProfiler::recordQuery($this->queryString ?? '', microtime(true) - $start, $params);
            }
        }
    }
}


