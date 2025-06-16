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
            'minHeight' => 1,
            'minWidth' => 1,
            'maxHeight' => 1,
            'maxWidth' => 1,
        ];
    }

    public function getData()
    {
        return [
            'lizenz' => $this->getLizenz()
        ];
    }

    public function getLizenz()
    {
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
                    $to = 'malik.mardan@simplify-services.de';
                    $subject = 'JobRouter Lizenz läuft bald ab';

                    $messageBody = "Sehr geehrte Damen und Herren,\n\n";
                    $messageBody .= "die JobRouter-Lizenz läuft am " . $expirationDate->format('d.m.Y') . " ab.\n";
                    $messageBody .= "Es verbleiben noch " . $daysRemaining . " Tag(e).\n\n";
                    $messageBody .= "Bitte kümmern Sie sich rechtzeitig um eine Verlängerung.\n\n";
                    $messageBody .= "Mit freundlichen Grüßen,\nIhr JobRouter System";

                    $headers = 'From: jobrouter@simplify-services.de' . "\r\n" .
                        'Content-Type: text/plain; charset=UTF-8' . "\r\n" .
                        'X-Mailer: PHP/' . phpversion();

                    $this->sendExpirationEmail($to, $subject, $messageBody, $headers, $rudContent);
                }
            }
        } catch (\Exception $e) {
            error_log("Lizenz.php: Fehler bei der Prüfung des Lizenzablaufdatums: " . $e->getMessage() . " - Ursprünglicher RUD-Inhalt: '" . $rudContent . "'");
        }

        return json_encode($rudContent);
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
}
