<?php

namespace dashboard\MyWidgets\SimplifyTable;

use JobRouter\Api\Dashboard\v1\Widget;
use Exception;
use Throwable;

require_once(__DIR__ . '/../../../includes/central.php');

class Init extends Widget
    {
    public function getTitle(): string
        {
        return 'SimplifyTable Init';
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

    private function handleRequest(): array
    {
        $username = isset($_GET['username']) ? trim($_GET['username']) : '';

        return [
            'columns' => $this->getColumns(),
            'dropdownOptions' => $this->getDropdownOptions(),
            'userPreferences' => $this->loadUserPreferences($username),
        ];
    }

    /**
     * Define all table columns with their properties
     */
    private function getColumns(): array
        {
        return [
            ['id' => 'actions', 'label' => '', 'type' => 'actions', 'align' => 'center'],
            ['id' => 'status', 'label' => 'Status', 'type' => 'status', 'align' => 'center'],
            ['id' => 'entryDate', 'label' => 'Eingangsdatum', 'type' => 'date', 'align' => 'left'],
            ['id' => 'stepLabel', 'label' => 'Schritt', 'type' => 'text', 'align' => 'left'],
            ['id' => 'startDate', 'label' => 'Startdatum (Schritt)', 'type' => 'date', 'align' => 'left'],
            ['id' => 'jobFunction', 'label' => 'Rolle', 'type' => 'text', 'align' => 'left'],
            ['id' => 'fullName', 'label' => 'Bearbeiter', 'type' => 'text', 'align' => 'left'],
            ['id' => 'documentId', 'label' => 'DokumentId', 'type' => 'text', 'align' => 'left'],
            ['id' => 'companyName', 'label' => 'Gesellschaft', 'type' => 'text', 'align' => 'left'],
            ['id' => 'fund', 'label' => 'Fonds', 'type' => 'text', 'align' => 'left'],
            ['id' => 'creditorName', 'label' => 'Kreditor', 'type' => 'text', 'align' => 'left'],
            ['id' => 'invoiceType', 'label' => 'Rechnungstyp', 'type' => 'text', 'align' => 'left'],
            ['id' => 'invoiceNumber', 'label' => 'Rechnungsnummer', 'type' => 'text', 'align' => 'left'],
            ['id' => 'invoiceDate', 'label' => 'Rechnungsdatum', 'type' => 'date', 'align' => 'left'],
            ['id' => 'grossAmount', 'label' => 'Bruttobetrag', 'type' => 'currency', 'align' => 'left'],
            ['id' => 'dueDate', 'label' => 'Fälligkeit', 'type' => 'date', 'align' => 'left'],
            ['id' => 'orderId', 'label' => 'Auftragsnummer', 'type' => 'text', 'align' => 'left'],
            ['id' => 'paymentAmount', 'label' => 'Zahlbetrag', 'type' => 'currency', 'align' => 'left'],
            ['id' => 'paymentDate', 'label' => 'Zahldatum', 'type' => 'date', 'align' => 'left'],
            ['id' => 'chargeable', 'label' => 'Weiterbelasten', 'type' => 'text', 'align' => 'center'],
        ];
        }

    /**
     * Define dropdown options for filters
     */
    private function getDropdownOptions(): array
        {
        $JobDB = $this->getJobDB();

        // Fetch distinct steps from database
        $schrittQuery = "SELECT DISTINCT step, steplabel FROM V_UEBERSICHTEN_WIDGET";
        $schrittResult = $JobDB->query($schrittQuery);
        $schrittOptions = [];
        while ($row = $JobDB->fetchRow($schrittResult)) {
            $schrittOptions[] = ['id' => $row['step'], 'label' => $row['steplabel']];
            }

        // Fetch distinct companies for Gesellschaft dropdown
        $gesellschaftQuery = "
            SELECT DISTINCT mandantnr, mandantname
            FROM V_UEBERSICHTEN_WIDGET
            WHERE mandantname NOT IN ('', '-')
            AND mandantnr IS NOT NULL
            AND fondflag = 0
        ";
        $gesellschaftResult = $JobDB->query($gesellschaftQuery);
        $gesellschaftOptions = [];
        while ($row = $JobDB->fetchRow($gesellschaftResult)) {
            $gesellschaftOptions[] = ['id' => $row['mandantnr'], 'label' => $row['mandantname']];
            }

        // Fetch distinct funds for Fonds dropdown
        $fondsQuery = "
            SELECT DISTINCT fond_abkuerzung
            FROM V_UEBERSICHTEN_WIDGET
            WHERE fond_abkuerzung IS NOT NULL
            AND fond_abkuerzung != ''
        ";
        $fondsResult = $JobDB->query($fondsQuery);
        $fondsOptions = [];
        while ($row = $JobDB->fetchRow($fondsResult)) {
            $fondsOptions[] = ['id' => $row['fond_abkuerzung'], 'label' => $row['fond_abkuerzung']];
            }

        return [
            'status' => [
                ['id' => 'completed', 'label' => 'Beendet'],
                ['id' => 'aktiv_alle', 'label' => 'Aktiv Alle'],
                ['id' => 'faellig', 'label' => 'Aktiv Fällig'],
                ['id' => 'not_faellig', 'label' => 'Aktiv Nicht Fällig'],
            ],
            'schritt' => $schrittOptions,
            'laufzeit' => [
                ['id' => '0-5 Tage', 'label' => '0-5 Tage'],
                ['id' => '6-10 Tage', 'label' => '6-10 Tage'],
                ['id' => '11-20 Tage', 'label' => '11-20 Tage'],
                ['id' => '21+ Tage', 'label' => '21+ Tage'],
            ],
            'coor' => [
                ['id' => 'Ja', 'label' => 'Ja'],
                ['id' => 'Nein', 'label' => 'Nein'],
            ],
            'weiterbelasten' => [
                ['id' => 'Ja', 'label' => 'Ja'],
                ['id' => 'Nein', 'label' => 'Nein'],
            ],
            'gesellschaft' => $gesellschaftOptions,
            'fonds' => $fondsOptions,
        ];
        }

    /**
     * Determines the database type based on the version query.
     */
    private function getDatabaseType(): string
        {
        $jobDB = $this->getJobDB();

        try {
            $result = $jobDB->query("SELECT @@VERSION");
            if ($result) {
                return "MSSQL";
                }
            } catch (Exception $e) {
            }

        try {
            $result = $jobDB->query("SELECT VERSION()");
            if ($result) {
                return "MySQL";
                }
            } catch (Exception $e) {
            }

        throw new Exception("Database type could not be detected");
        }

    /**
     * Initialize the user preferences table if it doesn't exist
     */
    private function initializeUserPreferencesTable(): void
        {
        $JobDB = $this->getJobDB();
        $dbType = $this->getDatabaseType();

        $tableExists = false;
        try {
            if ($dbType === 'MySQL') {
                $checkQuery = "SHOW TABLES LIKE 'WIDGET_SIMPLIFYTABLE'";
                $result = $JobDB->query($checkQuery);
                $row = $JobDB->fetchRow($result);
                $tableExists = !empty($row);
                } else {
                $checkQuery = "SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'WIDGET_SIMPLIFYTABLE'";
                $result = $JobDB->query($checkQuery);
                $row = $JobDB->fetchRow($result);
                $tableExists = !empty($row);
                }
            } catch (Exception $e) {
            $tableExists = false;
            }

        if (!$tableExists) {
            if ($dbType === 'MySQL') {
                $createQuery = "
                    CREATE TABLE WIDGET_SIMPLIFYTABLE (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        username VARCHAR(255) NOT NULL,
                        filter TEXT,
                        column_order TEXT,
                        sort_column VARCHAR(100),
                        sort_direction VARCHAR(4),
                        current_page INT DEFAULT 1,
                        entries_per_page INT DEFAULT 25,
                        zoom_level FLOAT DEFAULT 1.0,
                        visible_columns TEXT,
                        visible_filters TEXT,
                        filter_presets TEXT,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        UNIQUE KEY unique_user (username)
                    )
                ";
                } else {
                $createQuery = "
                    CREATE TABLE WIDGET_SIMPLIFYTABLE (
                        id INT IDENTITY(1,1) PRIMARY KEY,
                        username NVARCHAR(255) NOT NULL,
                        filter NVARCHAR(MAX),
                        column_order NVARCHAR(MAX),
                        sort_column NVARCHAR(100),
                        sort_direction NVARCHAR(4),
                        current_page INT DEFAULT 1,
                        entries_per_page INT DEFAULT 25,
                        zoom_level FLOAT DEFAULT 1.0,
                        visible_columns NVARCHAR(MAX),
                        visible_filters NVARCHAR(MAX),
                        filter_presets NVARCHAR(MAX),
                        updated_at DATETIME DEFAULT GETDATE(),
                        CONSTRAINT unique_user UNIQUE (username)
                    )
                ";
                }
            $JobDB->exec($createQuery);
            }
        }

    /**
     * Load user preferences from database
     */
    private function loadUserPreferences(string $username): ?array
    {
        if ($username === '') {
            return null;
        }

        $this->initializeUserPreferencesTable();

        $JobDB = $this->getJobDB();
        $safeUsername = addslashes($username);

        $query = "SELECT * FROM WIDGET_SIMPLIFYTABLE WHERE username = '{$safeUsername}'";
        $result = $JobDB->query($query);
        $row = $JobDB->fetchRow($result);

        if ($row) {
            return [
                'filter' => $row['filter'] ? json_decode(stripslashes($row['filter']), true) : null,
                'column_order' => $row['column_order'] ? json_decode(stripslashes($row['column_order']), true) : null,
                'sort_column' => $row['sort_column'],
                'sort_direction' => $row['sort_direction'],
                'current_page' => (int) $row['current_page'],
                'entries_per_page' => (int) $row['entries_per_page'],
                'zoom_level' => isset($row['zoom_level']) ? (float) $row['zoom_level'] : 1.0,
                'visible_columns' => $row['visible_columns'] ? json_decode(stripslashes($row['visible_columns']), true) : null,
                'visible_filters' => $row['visible_filters'] ? json_decode(stripslashes($row['visible_filters']), true) : null,
                'filter_presets' => $row['filter_presets'] ? json_decode(stripslashes($row['filter_presets']), true) : null,
            ];
            }

        return null;
        }
    }

Init::execute();
