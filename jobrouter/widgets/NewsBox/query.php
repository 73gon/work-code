<?php

require_once('../../../includes/central.php');

$JobDB = DBFactory::getJobDB();

// Set content type for JSON response
header('Content-Type: application/json');

try {
    // Handle different types of requests
    if (isset($_POST['isNew']) && $_POST['isNew'] === 'true') {
        // Create new news item
        $author = trim($_POST['author'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $date = $_POST['date'] ?? '';
        $deleteDate = $_POST['deleteDate'] ?? null;

        if (empty($author) || empty($title) || empty($message) || empty($date)) {
            echo json_encode(['success' => false, 'message' => 'Alle Felder sind erforderlich']);
            exit;
        }

        // Convert date format from YYYY-MM-DD to database format
        $dateForDb = date('Y-m-d', strtotime($date));
        $deleteDateForDb = $deleteDate ? date('Y-m-d', strtotime($deleteDate)) : null;

        // Insert new news
        $query = "INSERT INTO newsBoxWidget (author, title, message, date, deleteDate) VALUES (" .
            $JobDB->quote($author) . ", " .
            $JobDB->quote($title) . ", " .
            $JobDB->quote($message) . ", " .
            $JobDB->quote($dateForDb) . ", " .
            ($deleteDateForDb ? $JobDB->quote($deleteDateForDb) : "NULL") . ")";

        $result = $JobDB->exec($query);

        if ($result !== false) {
            // Get the ID of the newly inserted record
            $dbType = getDatabaseType($JobDB);
            if ($dbType === 'mysql') {
                $idQuery = "SELECT LAST_INSERT_ID() as newId";
            } else {
                $idQuery = "SELECT SCOPE_IDENTITY() as newId";
            }

            $idResult = $JobDB->fetchRow($idQuery);
            $newId = $idResult ? $idResult['newId'] : null;

            echo json_encode(['success' => true, 'message' => 'Nachricht erfolgreich erstellt', 'newId' => $newId]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Fehler beim Erstellen der Nachricht']);
        }
    } elseif (isset($_POST['isEdit']) && $_POST['isEdit'] === 'true') {
        // Edit existing news item
        $editId = intval($_POST['editId'] ?? 0);
        $author = trim($_POST['author'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $date = $_POST['date'] ?? '';
        $deleteDate = $_POST['deleteDate'] ?? null;
        $lastEditBy = trim($_POST['lastEditBy'] ?? '');
        $lastEditDate = $_POST['lastEditDate'] ?? '';

        if ($editId <= 0 || empty($author) || empty($title) || empty($message) || empty($date) || empty($lastEditBy) || empty($lastEditDate)) {
            echo json_encode(['success' => false, 'message' => 'Ungültige Daten']);
            exit;
        }

        // Convert date format from YYYY-MM-DD to database format
        $dateForDb = date('Y-m-d', strtotime($date));
        $deleteDateForDb = $deleteDate ? date('Y-m-d', strtotime($deleteDate)) : null;
        $lastEditDateForDb = date('Y-m-d', strtotime($lastEditDate));

        // Update the news item directly using the ID
        $updateQuery = "UPDATE newsBoxWidget SET " .
            "author = " . $JobDB->quote($author) . ", " .
            "title = " . $JobDB->quote($title) . ", " .
            "message = " . $JobDB->quote($message) . ", " .
            "date = " . $JobDB->quote($dateForDb) . ", " .
            "deleteDate = " . ($deleteDateForDb ? $JobDB->quote($deleteDateForDb) : "NULL") . ", " .
            "lastEditBy = " . $JobDB->quote($lastEditBy) . ", " .
            "lastEditDate = " . $JobDB->quote($lastEditDateForDb) . " " .
            "WHERE id = " . $JobDB->quote($editId);

        $result = $JobDB->exec($updateQuery);

        if ($result !== false) {
            echo json_encode(['success' => true, 'message' => 'Nachricht erfolgreich aktualisiert']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Fehler beim Aktualisieren der Nachricht']);
        }
    } elseif (isset($_POST['isDelete']) && $_POST['isDelete'] === 'true') {
        // Delete news item
        $deleteId = intval($_POST['deleteId'] ?? 0);

        if ($deleteId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Ungültige ID']);
            exit;
        }

        // Delete the news item directly using the ID
        $deleteQuery = "DELETE FROM newsBoxWidget WHERE id = " . $JobDB->quote($deleteId);
        $result = $JobDB->exec($deleteQuery);

        if ($result !== false) {
            echo json_encode(['success' => true, 'message' => 'Nachricht erfolgreich gelöscht']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Fehler beim Löschen der Nachricht']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Ungültige Anfrage']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Serverfehler: ' . $e->getMessage()]);
}

function getDatabaseType($JobDB)
{
    // Try MySQL specific query first
    try {
        $result = $JobDB->fetchRow("SELECT VERSION() as version");
        if ($result && isset($result['version'])) {
            return 'mysql';
        }
    } catch (Exception $e) {
        // Not MySQL, try MSSQL
    }

    // Try MSSQL specific query
    try {
        $result = $JobDB->fetchRow("SELECT @@VERSION as version");
        if ($result && isset($result['version'])) {
            return 'mssql';
        }
    } catch (Exception $e) {
        // Default to MySQL if detection fails
    }

    return 'mysql'; // Default fallback
}
