<?php

namespace dashboard\MyWidgets\SimplifyTable;

use JobRouter\Api\Dashboard\v1\Widget;
use Exception;
use Throwable;

require_once('../../../includes/central.php');

class SavePreferences extends Widget
{
    public function getTitle(): string
    {
        return 'SimplifyTable Save Preferences';
    }

    public static function execute(): void
    {
        try {
            $widget = new static();
            $response = $widget->handleRequest();
            header('Content-Type: application/json');
            echo json_encode($response);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Exception: ' . $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error: ' . $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
        }
    }

    /**
     * Determines the database type based on the version query.
     */
    private function getDatabaseType()
    {
        $jobDB = $this->getJobDB();
        try {
            $result = $jobDB->query("SELECT VERSION()");
            $row = $jobDB->fetchAll($result);
            if (is_string($row[0]["VERSION()"])) {
                return "MySQL";
            }
        } catch (Exception $e) {
        }

        try {
            $result = $jobDB->query("SELECT @@VERSION");
            $row = $jobDB->fetchAll($result);
            if (is_string(reset($row[0]))) {
                return "MSSQL";
            }
        } catch (Exception $e) {
        }
        throw new Exception("Database could not be detected");
    }

    private function getParam(string $key, $default = '')
    {
        return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
    }

    private function handleRequest(): array
    {
        $JobDB = $this->getJobDB();
        $dbType = $this->getDatabaseType();

        // Get POST data
        $username = $this->getParam('username', '');
        $filter = $this->getParam('filter', null);
        $columnOrder = $this->getParam('column_order', null);
        $sortColumn = $this->getParam('sort_column', null);
        $sortDirection = $this->getParam('sort_direction', null);
        $currentPage = max(1, (int)$this->getParam('current_page', 1));
        $entriesPerPage = max(1, (int)$this->getParam('entries_per_page', 25));

        // Prepare data for SQL
        $safeUsername = addslashes($username);
        // Don't use addslashes on JSON strings - it double-escapes the quotes
        $safeFilter = $filter ? $filter : null;
        $safeColumnOrder = $columnOrder ? $columnOrder : null;
        $safeSortColumn = $sortColumn ? addslashes($sortColumn) : null;
        $safeSortDirection = $sortDirection ? addslashes($sortDirection) : null;

        // Check if user preferences already exist
        $checkQuery = "SELECT id FROM WIDGET_SIMPLIFYTABLE WHERE username = '{$safeUsername}'";
        $result = $JobDB->query($checkQuery);
        $exists = $JobDB->fetchRow($result);

        if ($exists) {
            // Update existing record
            if ($dbType === 'MySQL') {
                $updateQuery = "
                    UPDATE WIDGET_SIMPLIFYTABLE SET
                        filter = " . ($safeFilter ? "'{$safeFilter}'" : "NULL") . ",
                        column_order = " . ($safeColumnOrder ? "'{$safeColumnOrder}'" : "NULL") . ",
                        sort_column = " . ($safeSortColumn ? "'{$safeSortColumn}'" : "NULL") . ",
                        sort_direction = " . ($safeSortDirection ? "'{$safeSortDirection}'" : "NULL") . ",
                        current_page = {$currentPage},
                        entries_per_page = {$entriesPerPage},
                        updated_at = CURRENT_TIMESTAMP
                    WHERE username = '{$safeUsername}'
                ";
            } else { // MSSQL
                $updateQuery = "
                    UPDATE WIDGET_SIMPLIFYTABLE SET
                        filter = " . ($safeFilter ? "'{$safeFilter}'" : "NULL") . ",
                        column_order = " . ($safeColumnOrder ? "'{$safeColumnOrder}'" : "NULL") . ",
                        sort_column = " . ($safeSortColumn ? "'{$safeSortColumn}'" : "NULL") . ",
                        sort_direction = " . ($safeSortDirection ? "'{$safeSortDirection}'" : "NULL") . ",
                        current_page = {$currentPage},
                        entries_per_page = {$entriesPerPage},
                        updated_at = GETDATE()
                    WHERE username = '{$safeUsername}'
                ";
            }
            $JobDB->exec($updateQuery);
        } else {
            // Insert new record
            $insertQuery = "
                INSERT INTO WIDGET_SIMPLIFYTABLE
                (username, filter, column_order, sort_column, sort_direction, current_page, entries_per_page)
                VALUES (
                    '{$safeUsername}',
                    " . ($safeFilter ? "'{$safeFilter}'" : "NULL") . ",
                    " . ($safeColumnOrder ? "'{$safeColumnOrder}'" : "NULL") . ",
                    " . ($safeSortColumn ? "'{$safeSortColumn}'" : "NULL") . ",
                    " . ($safeSortDirection ? "'{$safeSortDirection}'" : "NULL") . ",
                    {$currentPage},
                    {$entriesPerPage}
                )
            ";
            $JobDB->exec($insertQuery);
        }

        return [
            'success' => true,
            'message' => 'Preferences saved successfully'
        ];
    }
}

SavePreferences::execute();
