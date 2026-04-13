<?php
/**
 * HelperTrait — Utility methods and constants for the Pedant system activity.
 *
 * Contains getDatabaseType() for runtime DB detection
 * and shared constants used across multiple traits.
 */
trait HelperTrait
  {
  /**
   * Determines the database type based on the version query.
   *
   * @return string The database type, either "MySQL" or "MSSQL".
   * @throws Exception If the database type cannot be detected.
   */
  public function getDatabaseType(): string
    {
    $jobDB = $this->getJobDB();
    try {
      $result = $jobDB->query("SELECT VERSION()");
      $row = $jobDB->fetchAll($result);
      if (is_string($row[0]["VERSION()"])) {
        $this->logDebug('Database type detected', ['type' => 'MySQL']);
        return "MySQL";
        }
      } catch (Exception $e) {
      $this->logDebug('MySQL version query failed, trying MSSQL', ['error' => $e->getMessage()]);
      }

    try {
      $result = $jobDB->query("SELECT @@VERSION");
      $row = $jobDB->fetchAll($result);
      if (is_string(reset($row[0]))) {
        $this->logDebug('Database type detected', ['type' => 'MSSQL']);
        return "MSSQL";
        }
      } catch (Exception $e) {
      $this->logError('MSSQL version query also failed', $e);
      }

    $this->logError('Database type could not be detected');
    throw new JobRouterException("Database could not be detected");
    }
  }
