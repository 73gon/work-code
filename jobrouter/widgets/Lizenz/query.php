<?php

require_once('C:\inetpub\wwwroot\jobrouter\includes\central.php');

$JobDB = DBFactory::getJobDB();

// Set content type for JSON response
header('Content-Type: application/json');

try {
  // Get action from POST data
  $action = $_POST['action'] ?? '';

  switch ($action) {
    case 'getEmails':
      getEmails($JobDB);
      break;
    case 'addEmail':
      addEmail($JobDB);
      break;
    case 'updateEmail':
      updateEmail($JobDB);
      break;
    case 'deleteEmail':
      deleteEmail($JobDB);
      break;
    default:
      echo json_encode(['success' => false, 'message' => 'Ungültige Aktion']);
  }
} catch (Exception $e) {
  echo json_encode(['success' => false, 'message' => 'Serverfehler: ' . $e->getMessage()]);
}

function getEmails($JobDB)
{
  try {
    $query = "SELECT id, email, active, is_role_based FROM simplifyLicenseWidget ORDER BY is_role_based DESC, id ASC";
    $result = $JobDB->query($query);

    if ($result === false) {
      // Return empty array instead of error if query fails but table exists
      echo json_encode(['success' => true, 'emails' => []]);
      return;
    }

    $emails = [];
    while ($row = $JobDB->fetchRow($result)) {
      $emails[] = [
        'id' => (int)$row['id'],
        'email' => $row['email'],
        'active' => (bool)$row['active'],
        'is_role_based' => (bool)$row['is_role_based']
      ];
    }

    echo json_encode(['success' => true, 'emails' => $emails]);
  } catch (Exception $e) {
    // Return empty array instead of error for any exception
    echo json_encode(['success' => true, 'emails' => []]);
  }
}

function addEmail($JobDB)
{
  $email = trim($_POST['email'] ?? '');

  if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'E-Mail-Adresse ist erforderlich']);
    return;
  }

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Ungültige E-Mail-Adresse']);
    return;
  }

  try {
    // Check if email already exists
    $checkQuery = "SELECT COUNT(*) as count FROM simplifyLicenseWidget WHERE email = '$email'";
    $result = $JobDB->query($checkQuery);
    $row = $JobDB->fetchRow($result);

    if ($row['count'] > 0) {
      echo json_encode(['success' => false, 'message' => 'E-Mail-Adresse existiert bereits']);
      return;
    }

    $dbType = getDatabaseType($JobDB);
    if ($dbType === 'MySQL') {
      $insertQuery = "INSERT INTO simplifyLicenseWidget (email, active, is_role_based) VALUES ('$email', 1, 0)";
    } else {
      $insertQuery = "INSERT INTO simplifyLicenseWidget (email, active, is_role_based) VALUES ('$email', 1, 0)";
    }

    $result = $JobDB->exec($insertQuery);

    if ($result !== false) {
      echo json_encode(['success' => true, 'message' => 'E-Mail erfolgreich hinzugefügt']);
    } else {
      echo json_encode(['success' => false, 'message' => 'Fehler beim Hinzufügen der E-Mail']);
    }
  } catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Fehler beim Hinzufügen der E-Mail: ' . $e->getMessage()]);
  }
}

function updateEmail($JobDB)
{
  $id = intval($_POST['id'] ?? 0);
  $email = trim($_POST['email'] ?? '');
  $active = isset($_POST['active']) ? ($_POST['active'] === 'true' ? 1 : 0) : 1;

  if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Ungültige ID']);
    return;
  }

  try {
    // Check if this is a role-based email
    $roleCheckQuery = "SELECT is_role_based FROM simplifyLicenseWidget WHERE id = $id";
    $roleResult = $JobDB->query($roleCheckQuery);
    $roleRow = $JobDB->fetchRow($roleResult);

    if ($roleRow && $roleRow['is_role_based']) {
      echo json_encode(['success' => false, 'message' => 'Rollenbasierte E-Mails können nicht bearbeitet werden']);
      return;
    }

    if (empty($email)) {
      echo json_encode(['success' => false, 'message' => 'E-Mail-Adresse ist erforderlich']);
      return;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      echo json_encode(['success' => false, 'message' => 'Ungültige E-Mail-Adresse']);
      return;
    }

    // Check if email already exists for another record
    $checkQuery = "SELECT COUNT(*) as count FROM simplifyLicenseWidget WHERE email = '$email' AND id != $id";
    $result = $JobDB->query($checkQuery);
    $row = $JobDB->fetchRow($result);

    if ($row['count'] > 0) {
      echo json_encode(['success' => false, 'message' => 'E-Mail-Adresse existiert bereits']);
      return;
    }

    $dbType = getDatabaseType($JobDB);
    if ($dbType === 'MySQL') {
      $updateQuery = "UPDATE simplifyLicenseWidget SET email = '$email', active = $active, updated_at = NOW() WHERE id = $id AND is_role_based = 0";
    } else {
      $updateQuery = "UPDATE simplifyLicenseWidget SET email = '$email', active = $active, updated_at = GETDATE() WHERE id = $id AND is_role_based = 0";
    }

    $result = $JobDB->exec($updateQuery);

    if ($result !== false) {
      echo json_encode(['success' => true, 'message' => 'E-Mail erfolgreich aktualisiert']);
    } else {
      echo json_encode(['success' => false, 'message' => 'Fehler beim Aktualisieren der E-Mail']);
    }
  } catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Fehler beim Aktualisieren der E-Mail: ' . $e->getMessage()]);
  }
}

function deleteEmail($JobDB)
{
  $id = intval($_POST['id'] ?? 0);

  if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Ungültige ID']);
    return;
  }

  try {
    // Check if this is a role-based email
    $roleCheckQuery = "SELECT is_role_based FROM simplifyLicenseWidget WHERE id = $id";
    $roleResult = $JobDB->query($roleCheckQuery);
    $roleRow = $JobDB->fetchRow($roleResult);

    if ($roleRow && $roleRow['is_role_based']) {
      echo json_encode(['success' => false, 'message' => 'Rollenbasierte E-Mails können nicht gelöscht werden']);
      return;
    }

    $deleteQuery = "DELETE FROM simplifyLicenseWidget WHERE id = $id AND is_role_based = 0";
    $result = $JobDB->exec($deleteQuery);

    if ($result !== false) {
      echo json_encode(['success' => true, 'message' => 'E-Mail erfolgreich gelöscht']);
    } else {
      echo json_encode(['success' => false, 'message' => 'Fehler beim Löschen der E-Mail']);
    }
  } catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Fehler beim Löschen der E-Mail: ' . $e->getMessage()]);
  }
}

function getDatabaseType($JobDB)
{
  try {
    $result = $JobDB->query("SELECT VERSION()");
    $row = $JobDB->fetchAll($result);
    if (is_string($row[0]["VERSION()"])) {
      return "MySQL";
    }
  } catch (Exception $e) {
  }

  try {
    $result = $JobDB->query("SELECT @@VERSION");
    $row = $JobDB->fetchAll($result);
    if (is_string(reset($row[0]))) {
      return "MSSQL";
    }
  } catch (Exception $e) {
  }
  return "MySQL"; // Default fallback
}
