<?php

namespace dashboard\MyWidgets\Lizenz;

error_reporting(E_ALL);
ini_set('display_errors', '1');

use JobRouter\Api\Dashboard\v1\Widget;
use DateTime;


class Lizenz extends Widget
{


    public function getTitle()
    {
        return 'Ablaufdatum JR-Lizenz';
    }

    public function getDimensions()
    {

        return [
            'minHeight' => 2,
            'minWidth' => 2,
            'maxHeight' => 3,
            'maxWidth' => 3,
        ];
    }

    public function getData()
    {

        $isInJobFunction = $this->getUser()->isInJobFunction('LizenzAdmin');
        if (empty($isInJobFunction))  $canEdit = false;
        else $canEdit = true;
        
        return [
            'lizenz' => $this->getLizenz(),
            'canEdit' => $canEdit,
        ];
    }

    private function ensureTableExists(): void
    {
        try {
            // Ensure LizenzAdmin role exists
            $this->ensureLizenzAdminRoleExists();

            if (!$this->tableExists('simplifyLicenseWidget')) {
                $this->createLicenseWidgetTable();
            }
            // Always sync role-based emails when table exists
            $this->syncRoleBasedEmails();
        } catch (\Exception $e) {
            error_log("Lizenz.php: Fehler beim Erstellen der Tabelle: " . $e->getMessage());
        }
    }

    private function syncRoleBasedEmails(): void
    {
        try {
            $JobDB = $this->getJobDB();

            // Get current LizenzAdmin emails from database
            $adminQuery = "SELECT DISTINCT u.email
                          FROM jruserjob j
                          JOIN jrusers u ON u.username = j.username
                          WHERE j.jobfunction = 'LizenzAdmin' AND u.email IS NOT NULL AND u.email != ''";
            $adminResult = $JobDB->query($adminQuery);

            $currentAdminEmails = [];
            if ($adminResult !== false) {
                while ($row = $JobDB->fetchRow($adminResult)) {
                    if (!empty($row['email'])) {
                        $currentAdminEmails[] = $row['email'];
                    }
                }
            }

            // Remove role-based emails that are no longer LizenzAdmin
            $deleteQuery = "DELETE FROM simplifyLicenseWidget WHERE is_role_based = 1";
            if (!empty($currentAdminEmails)) {
                $emailList = "'" . implode("','", array_map('addslashes', $currentAdminEmails)) . "'";
                $deleteQuery .= " AND email NOT IN ($emailList)";
            }
            $JobDB->exec($deleteQuery);

            // Add new LizenzAdmin emails
            foreach ($currentAdminEmails as $email) {
                $checkQuery = "SELECT COUNT(*) as count FROM simplifyLicenseWidget WHERE email = '" . addslashes($email) . "'";
                $result = $JobDB->query($checkQuery);
                $row = $JobDB->fetchRow($result);

                if ($row['count'] == 0) {
                    // Email doesn't exist, add it as role-based
                    $insertQuery = "INSERT INTO simplifyLicenseWidget (email, active, is_role_based) VALUES ('" . addslashes($email) . "', 1, 1)";
                    $JobDB->exec($insertQuery);
                } else {
                    // Email exists, mark it as role-based
                    $updateQuery = "UPDATE simplifyLicenseWidget SET is_role_based = 1 WHERE email = '" . addslashes($email) . "'";
                    $JobDB->exec($updateQuery);
                }
            }
        } catch (\Exception $e) {
            error_log("Lizenz.php: Fehler beim Synchronisieren der rollenbasierten E-Mails: " . $e->getMessage());
        }
    }

    public function getLizenz()
    {
        $this->ensureTableExists();

        $path = __DIR__ . '/../../../license/jr_license.xml';

        $xml = simplexml_load_file($path);

        $rudContent = (string) $xml->rud;
        try {
            if (empty($rudContent)) {
                throw new \Exception("RUD-Inhalt (Ablaufdatum) ist leer.");
            }

            $expirationDate = new DateTime($rudContent);
            $expirationDate->setTime(0, 0, 0);

            $currentDate = new DateTime('today');

            if ($expirationDate >= $currentDate) {
                $interval = $currentDate->diff($expirationDate);
                $daysRemaining = (int)$interval->format('%a');

                if ($daysRemaining <= 7) {
                    $emails = $this->getEmailsFromDatabase();

                    if (!empty($emails)) {
                        $subject = 'JobRouter Lizenz läuft bald ab';

                        $messageBody = "Sehr geehrte Damen und Herren,\n\n";
                        $messageBody .= "die JobRouter-Lizenz läuft am " . $expirationDate->format('d.m.Y') . " ab.\n";
                        $messageBody .= "Es verbleiben noch " . $daysRemaining . " Tag(e).\n\n";
                        $messageBody .= "Bitte kümmern Sie sich rechtzeitig um eine Verlängerung.\n\n";
                        $messageBody .= "Mit freundlichen Grüßen,\nIhr JobRouter System";

                        $headers = 'From: jobrouter@simplify-services.de' . "\r\n" .
                            'Content-Type: text/plain; charset=UTF-8' . "\r\n" .
                            'X-Mailer: PHP/' . phpversion();

                        foreach ($emails as $email) {
                            $this->sendExpirationEmail($email, $subject, $messageBody, $headers, $rudContent);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("Lizenz.php: Fehler bei der Prüfung des Lizenzablaufdatums: " . $e->getMessage() . " - Ursprünglicher RUD-Inhalt: '" . $rudContent . "'");
        }

        return json_encode($rudContent);
    }

    private function getEmailsFromDatabase(): array
    {
        try {
            $JobDB = $this->getJobDB();

            // Get all active emails from the unified table
            $query = "SELECT email FROM simplifyLicenseWidget WHERE active = 1";
            $result = $JobDB->query($query);

            $emails = [];
            if ($result !== false) {
                while ($row = $JobDB->fetchRow($result)) {
                    $emails[] = $row['email'];
                }
            }

            return $emails;
        } catch (\Exception $e) {
            error_log("Lizenz.php: Fehler beim Abrufen der E-Mails aus der Datenbank: " . $e->getMessage());
            return [];
        }
    }

    private function sendExpirationEmail(string $to, string $subject, string $messageBody, string $headers, string $rudContentForLog): void
    {
        try {
            $JobDB = $this->getJobDB();

            $currentDateTime = (new DateTime())->format('Y-m-d H:i:s');

            $fromEmail = 'jobrouter@simplify-services.de';
            $fromName = 'JobRouter System';
            $mailType = 1;

            $insertQuery = "INSERT INTO JRMAIL (
                to_email,
                from_email,
                from_name,
                subject,
                emailtext,
                mailtype,
                indate,
                send_begin_date
            ) VALUES (
                '$to',
                '$fromEmail',
                '$fromName',
                '$subject',
                '$messageBody',
                " . intval($mailType) . ",
                '$currentDateTime',
                '$currentDateTime'
            )";

            $result = $JobDB->query($insertQuery);

            if ($result !== false) {
                error_log("Lizenz.php: Ablaufbenachrichtigung erfolgreich in JRMAIL-Tabelle eingetragen für Lizenzdatum: {$rudContentForLog}");
            } else {
                error_log("Lizenz.php: Fehler beim Eintragen der Ablaufbenachrichtigung in JRMAIL-Tabelle für Lizenzdatum: {$rudContentForLog}");
            }
        } catch (\Exception $e) {
            error_log("Lizenz.php: Fehler beim Senden der Ablaufbenachrichtigung über JRMAIL für Lizenzdatum: {$rudContentForLog}. Exception: " . $e->getMessage());
        }
    }

    public function getDatabaseType(): string
    {
        $jobDB = $this->getJobDB();
        try {
            $result = $jobDB->query("SELECT VERSION()");
            $row = $jobDB->fetchAll($result);
            if (is_string($row[0]["VERSION()"])) {
                return "MySQL";
            }
        } catch (\Exception $e) {
        }

        try {
            $result = $jobDB->query("SELECT @@VERSION");
            $row = $jobDB->fetchAll($result);
            if (is_string(reset($row[0]))) {
                return "MSSQL";
            }
        } catch (\Exception $e) {
        }
        throw new \Exception("Database could not be detected");
    }

    private function tableExists(string $tableName): bool
    {
        $JobDB = $this->getJobDB();
        $dbType = $this->getDatabaseType();

        if ($dbType === 'MySQL') {
            $sql = "SELECT COUNT(*) as count FROM information_schema.tables
                    WHERE table_schema = DATABASE() AND table_name = '$tableName'";
        } else { // MSSQL
            $sql = "SELECT COUNT(*) as count FROM information_schema.tables
                    WHERE table_name = '$tableName'";
        }

        $result = $JobDB->query($sql);
        if ($result === false) {
            return false;
        }

        $row = $JobDB->fetchRow($result);
        return $row['count'] > 0;
    }

    private function createLicenseWidgetTable(): void
    {
        $JobDB = $this->getJobDB();
        $dbType = $this->getDatabaseType();

        if ($dbType === 'MySQL') {
            $sql = "CREATE TABLE simplifyLicenseWidget (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                active TINYINT(1) DEFAULT 1,
                is_role_based TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        } else { // MSSQL
            $sql = "CREATE TABLE simplifyLicenseWidget (
                id INT IDENTITY(1,1) PRIMARY KEY,
                email NVARCHAR(255) NOT NULL,
                active BIT DEFAULT 1,
                is_role_based BIT DEFAULT 0,
                created_at DATETIME DEFAULT GETDATE(),
                updated_at DATETIME DEFAULT GETDATE(),
                CONSTRAINT unique_email UNIQUE (email)
            )";
        }

        $JobDB->exec($sql);
    }

    private function ensureLizenzAdminRoleExists(): void
    {
        try {
            $JobDB = $this->getJobDB();

            $checkQuery = "SELECT COUNT(*) as count FROM jrjobfunctions WHERE jobfunction = 'LizenzAdmin'";
            $result = $JobDB->query($checkQuery);

            if ($result !== false) {
                $row = $JobDB->fetchRow($result);
                if ($row['count'] == 0) {
                    $insertQuery = "INSERT INTO jrjobfunctions (jobfunction, description) VALUES ('LizenzAdmin', 'Administrator for License Management')";
                    $JobDB->exec($insertQuery);
                    error_log("Lizenz.php: LizenzAdmin role created in jrjobfunctions table");
                }
            }
        } catch (\Exception $e) {
            error_log("Lizenz.php: Fehler beim Überprüfen/Erstellen der LizenzAdmin-Rolle: " . $e->getMessage());
        }
    }
}
