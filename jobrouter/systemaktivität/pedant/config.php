<?php
/**
 * Pedant SystemActivity Configuration
 *
 * log_level — Controls the verbosity of the logging system.
 *   Available levels (from most to least verbose):
 *
 *   'debug'   — Logs EVERYTHING: queries, variables, data after every change,
 *               full API request/response bodies. Log files grow fast (GBs).
 *               Use only for troubleshooting specific issues.
 *
 *   'info'    — Logs the workflow: which method was entered, what was fetched,
 *               upload/check status changes, import progress. Also logs
 *               all warnings and errors. Good for day-to-day monitoring.
 *
 *   'warning' — Logs unexpected situations that don't break anything: retry
 *               attempts, failed file cleanups, missing optional data.
 *               Also logs all errors.
 *
 *   'error'   — Logs only exceptions and critical failures. Minimal output.
 *               Use in stable production environments.
 */
return [
  'log_level' => 'info',
];
