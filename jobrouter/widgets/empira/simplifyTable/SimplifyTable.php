<?php

namespace dashboard\MyWidgets\SimplifyTable;

use JobRouter\Api\Dashboard\v1\Widget;

class SimplifyTable extends Widget
{

  public function getTitle()
  {
    return 'Umlauftabelle';
  }

  public function getDimensions()
  {

    return [
      'minHeight' => 8,
      'minWidth' => 6,
      'maxHeight' => 15,
      'maxWidth' => 6,
    ];
  }

  /*
  public function isAuthorized()
  {
    return $this->getUser()->isInJobFunction('Widgets');
  }
  */
 public function isMandatory()
{
    return true;
}
  public function getData()
  {
    $userPreferences = $this->loadUserPreferences();

    return [
      'columns' => json_encode($this->getColumns()),
      'dropdownOptions' => json_encode($this->getDropdownOptions()),
      'tableData' => json_encode([]),
      'currentUser' => $this->getUser()->getUsername(),
      'userPreferences' => json_encode($userPreferences),
    ];
  }

  /**
   * Define all table columns with their properties
   */
  public function getColumns()
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
  public function getDropdownOptions()
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
      ['id' => '21+ Tage', 'label' => '21+ Tage']
      ],
      'coor' => [
      ['id' => 'Ja', 'label' => 'Ja'],
      ['id' => 'Nein', 'label' => 'Nein']
      ],
      'weiterbelasten' => [
      ['id' => 'Ja', 'label' => 'Ja'],
      ['id' => 'Nein', 'label' => 'Nein']
      ],
      'gesellschaft' => $gesellschaftOptions,
      'fonds' => $fondsOptions,
    ];
  }

  /**
     * Determines the database type based on the version query.
     *
     * @return string The database type, either "MySQL" or "MSSQL".
     * @throws \Exception If the database type cannot be detected.
     */
    public function getDatabaseType()
	{
		$jobDB = $this->getJobDB();

		// MSSQL (sqlsrv driver)
		try {
			$result = $jobDB->query("SELECT @@VERSION");
			if ($result) {
				return "MSSQL";
			}
		} catch (\Exception $e) {
			// ignore
		}

		// MySQL
		try {
			$result = $jobDB->query("SELECT VERSION()");
			if ($result) {
				return "MySQL";
			}
		} catch (\Exception $e) {
			// ignore
		}

		throw new \Exception("Database type could not be detected");
	}

  /**
   * Initialize the user preferences table if it doesn't exist
   */
  public function initializeUserPreferencesTable()
  {
    $JobDB = $this->getJobDB();
    $dbType = $this->getDatabaseType();

    // Check if table exists
    $tableExists = false;
    try {
      if ($dbType === 'MySQL') {
        $checkQuery = "SHOW TABLES LIKE 'WIDGET_SIMPLIFYTABLE'";
        $result = $JobDB->query($checkQuery);
        $row = $JobDB->fetchRow($result);
        $tableExists = !empty($row);
      } else { // MSSQL
        $checkQuery = "SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'WIDGET_SIMPLIFYTABLE'";
        $result = $JobDB->query($checkQuery);
        $row = $JobDB->fetchRow($result);
        $tableExists = !empty($row);
      }
    } catch (\Exception $e) {
      $tableExists = false;
    }

    // Create table if it doesn't exist
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
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user (username)
          )
        ";
      } else { // MSSQL
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
  public function loadUserPreferences()
  {
    $this->initializeUserPreferencesTable();

    $JobDB = $this->getJobDB();
    $username = $this->getUser()->getUsername();
    $safeUsername = addslashes($username);

    $query = "SELECT * FROM WIDGET_SIMPLIFYTABLE WHERE username = '{$safeUsername}'";
    $result = $JobDB->query($query);
    $row = $JobDB->fetchRow($result);

    if ($row) {
      $preferences = [
        'filter' => $row['filter'] ? json_decode(stripslashes($row['filter']), true) : null,
        'column_order' => $row['column_order'] ? json_decode(stripslashes($row['column_order']), true) : null,
        'sort_column' => $row['sort_column'],
        'sort_direction' => $row['sort_direction'],
        'current_page' => (int)$row['current_page'],
        'entries_per_page' => (int)$row['entries_per_page'],
        'zoom_level' => isset($row['zoom_level']) ? (float)$row['zoom_level'] : 1.0,
      ];
      return $preferences;
    }
    return null;
  }

  /**
   * Fetch and return table data
   *
   */
  public function getTableData()
  {
    $JobDB = $this->getJobDB();
    $maxRows = 1000; // cap initial payload to speed up widget load

	$currentUsername = $this->getUser()->getUsername();

    $query = "
            SELECT *
            FROM V_UEBERSICHTEN_WIDGET
            WHERE CONCAT(',', REPLACE(LOWER(berechtigung), ' ', ''), ',') LIKE CONCAT('%,', LOWER('$currentUsername'), ',%')
        ";

    $result = $JobDB->query($query);
    $data = [];
    $count = 0;

    while ($row = $JobDB->fetchRow($result)) {
      // Determine status label based on logic
      $statusId = $row['status'];
      $statusLabel = '';

      if ($statusId === 'completed') {
        $statusLabel = 'Beendet';
      } else if ($statusId === 'rest') {
        // Check if eskalation (due date) <= today
        $eskalationDate = $row['eskalation'];
        if (!empty($eskalationDate)) {
          $eskalation = strtotime($eskalationDate);
          $today = strtotime('today');
          if ($eskalation <= $today) {
            $statusId = 'due';
            $statusLabel = 'Fällig';
          } else {
            $statusId = 'not_due';
            $statusLabel = 'Nicht Fällig';
          }
        } else {
          // No eskalation date, default to not due
          $statusId = 'not_due';
          $statusLabel = 'Nicht Fällig';
        }
      }

      $data[] = [
        'id' => ['id' => $row['processid'], 'label' => $row['processid']],
        'status' => ['id' => $statusId, 'label' => $statusLabel],
        'incident' => ['id' => $row['incident'], 'label' => $row['incident']],
        'entryDate' => ['id' => $row['eingangsdatum'], 'label' => $row['eingangsdatum']],
        'stepLabel' => ['id' => $row['step'], 'label' => $row['steplabel']],
        'startDate' => ['id' => $row['indate'], 'label' => $row['indate']],
        'jobFunction' => ['id' => $row['jobfunction'], 'label' => $row['jobfunction']],
        'fullName' => ['id' => $row['fullname'], 'label' => $row['fullname']],
        'documentId' => ['id' => $row['dokumentid'], 'label' => $row['dokumentid']],
        'companyName' => ['id' => $row['mandantnr'], 'label' => $row['mandantname']],
        'fund' => ['id' => $row['fond_abkuerzung'], 'label' => $row['fond_abkuerzung']],
        'creditorName' => ['id' => $row['kredname'], 'label' => $row['kredname']],
        'invoiceType' => ['id' => $row['rechnungstyp'], 'label' => $row['rechnungstyp']],
        'invoiceNumber' => ['id' => $row['rechnungsnummer'], 'label' => $row['rechnungsnummer']],
        'invoiceDate' => ['id' => $row['rechnungsdatum'], 'label' => $row['rechnungsdatum']],
        'grossAmount' => ['id' => $row['bruttobetrag'], 'label' => $row['bruttobetrag']],
        'dueDate' => ['id' => $row['eskalation'], 'label' => $row['eskalation']],
        'orderId' => ['id' => $row['coor_orderid'], 'label' => $row['coor_orderid']],
        'paymentAmount' => ['id' => $row['zahlbetrag'], 'label' => $row['zahlbetrag']],
        'paymentDate' => ['id' => $row['zahldatum'], 'label' => $row['zahldatum']],
        'runtime' => ['id' => isset($row['runtime']) ? $row['runtime'] : '', 'label' => isset($row['runtime']) ? $row['runtime'] : ''],
        'invoice' => ['id' => $row['dokumentid'], 'label' => $row['dokumentid']],
        'protocol' => ['id' => $row['dokumentid'], 'label' => $row['dokumentid']],
        'chargeable' => ['id' => $row['berechenbar'], 'label' => $row['berechenbar']],
      ];

      $count++;
      if ($count >= $maxRows) {
        break;
      }
    }
    return $data;
  }
}
