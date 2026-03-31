<?php
/**
 * LoggerTrait — Structured logging system for the Pedant SystemActivity.
 *
 * Provides 4 log levels (DEBUG, INFO, WARNING, ERROR) with daily log files,
 * automatic 7-day retention cleanup, and incident-tagged log lines.
 *
 * Log files are stored in: __DIR__/../logs/log/DDMMYYYY.log
 */
trait LoggerTrait
  {
  private static ?array $logConfig = null;
  private ?string $cachedIncident = null;
  private ?string $logDirectory = null;

  private static array $LOG_LEVEL_MAP = [
    'debug' => 0,
    'info' => 1,
    'warning' => 2,
    'error' => 3,
  ];

  private static int $LOG_RETENTION_DAYS = 7;

  /**
   * Loads and caches the configuration from config.php.
   */
  private function getLogConfig(): array
    {
    if (self::$logConfig === null) {
      $configPath = dirname(__DIR__) . '/config.php';
      if (file_exists($configPath)) {
        self::$logConfig = include $configPath;
        } else {
        self::$logConfig = ['log_level' => 'info'];
        }
      }
    return self::$logConfig;
    }

  /**
   * Returns the configured log level as an integer.
   */
  private function getConfiguredLogLevel(): int
    {
    $config = $this->getLogConfig();
    $level = strtolower($config['log_level'] ?? 'info');
    return self::$LOG_LEVEL_MAP[$level] ?? 1;
    }

  /**
   * Returns the cached incident identifier for log tagging.
   */
  private function getIncident(): string
    {
    if ($this->cachedIncident === null) {
      try {
        $this->cachedIncident = $this->resolveInputParameter('incident') ?? 'NO_INCIDENT';
        } catch (\Exception $e) {
        $this->cachedIncident = 'NO_INCIDENT';
        }
      }
    return $this->cachedIncident;
    }

  /**
   * Returns the log directory path, creating it if needed.
   */
  private function getLogDirectory(): string
    {
    if ($this->logDirectory === null) {
      $this->logDirectory = dirname(__DIR__) . '/logs/log';
      }
    if (!is_dir($this->logDirectory)) {
      mkdir($this->logDirectory, 0755, true);
      }
    return $this->logDirectory;
    }

  /**
   * Returns the log file path for the current day.
   */
  private function getLogFilePath(): string
    {
    return $this->getLogDirectory() . '/' . date('dmY') . '.log';
    }

  /**
   * Core logging method. Writes a log line if the message level meets the configured threshold.
   *
   * Format: [YYYY-MM-DD HH:MM:SS] [LEVEL] [incident] Message | Context: {"key":"value"}
   */
  private function writeLog(string $level, string $message, ?array $context = null, ?\Exception $exception = null): void
    {
    $levelInt = self::$LOG_LEVEL_MAP[strtolower($level)] ?? 1;
    if ($levelInt < $this->getConfiguredLogLevel()) {
      return;
      }

    $timestamp = date('Y-m-d H:i:s');
    $incident = $this->getIncident();
    $levelTag = strtoupper($level);

    $logLine = "[$timestamp] [$levelTag] [$incident] $message";

    if ($exception) {
      $logLine .= ' | Exception: ' . $exception->getMessage();
      $logLine .= ' | File: ' . $exception->getFile() . ':' . $exception->getLine();
      }

    if (!empty($context)) {
      $logLine .= ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      }

    $logLine .= PHP_EOL;

    file_put_contents($this->getLogFilePath(), $logLine, FILE_APPEND | LOCK_EX);
    }

  /**
   * Log a DEBUG message — only written when log_level is 'debug'.
   * Use for: queries, variable dumps, full API responses, data after every change.
   */
  protected function logDebug(string $message, ?array $context = null): void
    {
    $this->writeLog('debug', $message, $context);
    }

  /**
   * Log an INFO message — written when log_level is 'debug' or 'info'.
   * Use for: workflow steps (method entered, data fetched, upload started, activity completed).
   */
  protected function logInfo(string $message, ?array $context = null): void
    {
    $this->writeLog('info', $message, $context);
    }

  /**
   * Log a WARNING message — written when log_level is 'debug', 'info', or 'warning'.
   * Use for: unexpected but non-breaking situations (retries, missing optional data, failed cleanups).
   */
  protected function logWarning(string $message, ?array $context = null): void
    {
    $this->writeLog('warning', $message, $context);
    }

  /**
   * Log an ERROR message — always written regardless of log_level.
   * Use for: exceptions, critical failures.
   */
  protected function logError(string $message, ?\Exception $exception = null, ?array $context = null): void
    {
    $this->writeLog('error', $message, $context, $exception);
    }

  /**
   * Deletes log files older than LOG_RETENTION_DAYS (7 days).
   * Call once per entry point execution.
   */
  protected function cleanOldLogs(): void
    {
    $logDir = $this->getLogDirectory();
    $files = glob($logDir . '/*.log');
    if ($files === false) {
      return;
      }

    $cutoff = time() - (self::$LOG_RETENTION_DAYS * 86400);
    foreach ($files as $file) {
      if (filemtime($file) < $cutoff) {
        unlink($file);
        }
      }
    }
  }
