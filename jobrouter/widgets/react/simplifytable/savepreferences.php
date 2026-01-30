<?php

namespace dashboard\MyWidgets\SimplifyTable;

use JobRouter\Api\Dashboard\v1\Widget;
use Exception;
use Throwable;

require_once(__DIR__ . '/../../../includes/central.php');

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

    private function getDatabaseType(): string
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

        $username = $this->getParam('username', '');
        $filter = $this->getParam('filter', null);
        $columnOrder = $this->getParam('column_order', null);
        $sortColumn = $this->getParam('sort_column', null);
        $sortDirection = $this->getParam('sort_direction', null);
        $currentPage = max(1, (int) $this->getParam('current_page', 1));
        $entriesPerPage = max(1, (int) $this->getParam('entries_per_page', 25));
        $zoomLevel = (float) $this->getParam('zoom_level', 1.0);
        $visibleColumns = $this->getParam('visible_columns', null);
        $visibleFilters = $this->getParam('visible_filters', null);
        $filterPresets = $this->getParam('filter_presets', null);

        $safeUsername = addslashes($username);
        $safeFilter = $filter ? $filter : null;
        $safeColumnOrder = $columnOrder ? $columnOrder : null;
        $safeSortColumn = $sortColumn ? addslashes($sortColumn) : null;
        $safeSortDirection = $sortDirection ? addslashes($sortDirection) : null;
        $safeVisibleColumns = $visibleColumns ? $visibleColumns : null;
        $safeVisibleFilters = $visibleFilters ? $visibleFilters : null;
        $safeFilterPresets = $filterPresets ? $filterPresets : null;

        $checkQuery = "SELECT id FROM WIDGET_SIMPLIFYTABLE WHERE username = '{$safeUsername}'";
        $result = $JobDB->query($checkQuery);
        $exists = $JobDB->fetchRow($result);

        if ($exists) {
            if ($dbType === 'MySQL') {
                $updateQuery = "
                    UPDATE WIDGET_SIMPLIFYTABLE SET
                        filter = " . ($safeFilter ? "'{$safeFilter}'" : "NULL") . ",
                        column_order = " . ($safeColumnOrder ? "'{$safeColumnOrder}'" : "NULL") . ",
                        sort_column = " . ($safeSortColumn ? "'{$safeSortColumn}'" : "NULL") . ",
                        sort_direction = " . ($safeSortDirection ? "'{$safeSortDirection}'" : "NULL") . ",
                        current_page = {$currentPage},
                        entries_per_page = {$entriesPerPage},
                        zoom_level = {$zoomLevel},
                        visible_columns = " . ($safeVisibleColumns ? "'{$safeVisibleColumns}'" : "NULL") . ",
                        visible_filters = " . ($safeVisibleFilters ? "'{$safeVisibleFilters}'" : "NULL") . ",
                        filter_presets = " . ($safeFilterPresets ? "'{$safeFilterPresets}'" : "NULL") . ",
                        updated_at = CURRENT_TIMESTAMP
                    WHERE username = '{$safeUsername}'
                ";
                } else {
                $updateQuery = "
                    UPDATE WIDGET_SIMPLIFYTABLE SET
                        filter = " . ($safeFilter ? "'{$safeFilter}'" : "NULL") . ",
                        column_order = " . ($safeColumnOrder ? "'{$safeColumnOrder}'" : "NULL") . ",
                        sort_column = " . ($safeSortColumn ? "'{$safeSortColumn}'" : "NULL") . ",
                        sort_direction = " . ($safeSortDirection ? "'{$safeSortDirection}'" : "NULL") . ",
                        current_page = {$currentPage},
                        entries_per_page = {$entriesPerPage},
                        zoom_level = {$zoomLevel},
                        visible_columns = " . ($safeVisibleColumns ? "'{$safeVisibleColumns}'" : "NULL") . ",
                        visible_filters = " . ($safeVisibleFilters ? "'{$safeVisibleFilters}'" : "NULL") . ",
                        filter_presets = " . ($safeFilterPresets ? "'{$safeFilterPresets}'" : "NULL") . ",
                        updated_at = GETDATE()
                    WHERE username = '{$safeUsername}'
                ";
                }
            $JobDB->exec($updateQuery);
            } else {
            $insertQuery = "
                INSERT INTO WIDGET_SIMPLIFYTABLE
                (username, filter, column_order, sort_column, sort_direction, current_page, entries_per_page, zoom_level, visible_columns, visible_filters, filter_presets)
                VALUES (
                    '{$safeUsername}',
                    " . ($safeFilter ? "'{$safeFilter}'" : "NULL") . ",
                    " . ($safeColumnOrder ? "'{$safeColumnOrder}'" : "NULL") . ",
                    " . ($safeSortColumn ? "'{$safeSortColumn}'" : "NULL") . ",
                    " . ($safeSortDirection ? "'{$safeSortDirection}'" : "NULL") . ",
                    {$currentPage},
                    {$entriesPerPage},
                    {$zoomLevel},
                    " . ($safeVisibleColumns ? "'{$safeVisibleColumns}'" : "NULL") . ",
                    " . ($safeVisibleFilters ? "'{$safeVisibleFilters}'" : "NULL") . ",
                    " . ($safeFilterPresets ? "'{$safeFilterPresets}'" : "NULL") . "
                )
            ";
            $JobDB->exec($insertQuery);
            }

        return [
            'success' => true,
            'message' => 'Preferences saved successfully',
        ];
        }
    }

SavePreferences::execute();
