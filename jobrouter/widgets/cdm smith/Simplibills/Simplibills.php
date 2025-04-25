<?php

namespace dashboard\MyWidgets\Simplibills;

use JobRouter\Api\Dashboard\v1\Widget;

class Simplibills extends Widget
{

    public function getTitle()
    {
        return 'Ueberfaellige und unbezahlte Rechnungen';
    }

    public function getDimensions()
    {

        return [
            'minHeight' => 4,
            'minWidth' => 2,
            'maxHeight' => 4,
            'maxWidth' => 2,
        ];
    }


    public function isAuthorized()
    {
        return $this->getUser()->isInJobFunction('Widgets');
    }


    public function getData()
    {
        return [
            'incidents' => $this->getIncidents(),
            'labels' => json_encode([
                "Total",
                "Erfassung",
                "Pruefung",
                "Freigabe",
                "Buchhaltung DE",
                "Buchhaltung Kredikarte",
                "Fuhrpark",
                "Einkauf",
                "Buchhaltung IFSC",
                "Lieferantenanlage",
                "Lieferantenanlage IFSC",
                "Lieferantenanlage Compliance",
                "ausstehende Zahlungen",
                "Gebuchte Rechnungen"
            ]),
            'einheit' => $this->getEinheit()
        ];
    }

    public function getIncidents()
    {
        $bearbeitung = $this->getBearbeitung();
        $gebucht_zahlung = $this->getGebuchtAndZahlungsfreigabe();

        $incidents = array_merge($bearbeitung, $gebucht_zahlung);

        array_unshift($incidents, (string)array_sum($incidents));

        return json_encode($incidents);
    }
    public function getGebuchtAndZahlungsfreigabe()
    {
        $JobDB = $this->getJobDB();
        $query = "
            WITH LatestRevisions AS (
                SELECT documentrevision_id, DOKUMENTENID, STATUS, RECHNUNGSFAELLIGKEIT
                FROM RECHNUGNEN
                WHERE RECHNUNGSFAELLIGKEIT < CURDATE()
                AND (STATUS = 'Gebucht' OR STATUS = 'Zahlungsfreigabe')
                AND documentrevision_id = (
                    SELECT MAX(documentrevision_id)
                    FROM RECHNUGNEN AS Sub
                    WHERE Sub.DOKUMENTENID = RECHNUGNEN.DOKUMENTENID
                )
            )
            SELECT STATUS, COUNT(*) AS COUNTROW
            FROM LatestRevisions
            GROUP BY STATUS
        ";
        $result = $JobDB->query($query);
        $gebucht_zahlung = ['Zahlungsfreigabe' => 0, 'Gebucht' => 0];
        while ($row = $JobDB->fetchRow($result)) {
            if (isset($gebucht_zahlung[$row["STATUS"]])) {
                $gebucht_zahlung[$row["STATUS"]] = $row["COUNTROW"];
            }
        }
        return array_values($gebucht_zahlung);
    }

    public function getBearbeitung()
    {
        $JobDB = $this->getJobDB();
        $query = "
                    WITH RankedRows AS (
                        SELECT documentrevision_id, DOKUMENTENID, STATUS, MAX(documentrevision_id) OVER (PARTITION BY DOKUMENTENID) AS MaxRevisionID
                        FROM RECHNUGNEN
                    )
                    SELECT j.STEPLABEL, COUNT(j.STEP) AS COUNTROW,
                        CASE
                            WHEN h.STEP = 4 AND h.ZAHLMETHODE = 'KREDITKARTE' THEN 444
                            ELSE h.STEP
                        END AS STEP
                    FROM RankedRows r
                    LEFT JOIN RE_HEAD h ON r.DOKUMENTENID = h.DOKUMENTENID
                    LEFT JOIN JRINCIDENTS j ON h.step_id = j.process_step_id
                        AND j.processname = 'RECHNUNGSBEARBEITUNG'
                        AND j.STATUS IN (0, 1)
                    INNER JOIN JRINCIDENT i ON j.processid = i.processid AND i.status = 0
                    WHERE r.documentrevision_id = r.MaxRevisionID
                        AND h.FAELLIGKEIT < CURDATE()
                        AND r.STATUS = 'Bearbeitung'
                    GROUP BY h.STEP,
                        CASE
                            WHEN h.STEP = 4 AND h.ZAHLMETHODE = 'Kreditkarte' THEN 444
                            ELSE 4
                        END
                ";
        $result = $JobDB->query($query);

        $bearbeitung = array_fill(0, 11, 0);
        $stepMapping = [
            "1" => 0,
            "2" => 1,
            "3" => 2,
            "4" => 3,
            "444" => 4,
            "7" => 5,
            "5" => 6,
            "17" => 7,
            "30" => 8,
            "40" => 9,
            "50" => 10
        ];

        while ($row = $JobDB->fetchRow($result)) {
            $step = $row["STEP"];
            if (isset($stepMapping[$step])) {
                $index = $stepMapping[$step];
                $bearbeitung[$index] += (int) $row["COUNTROW"];
            }
        }
        return $bearbeitung;
    }

    public function getEinheit()
    {
        $JobDB = $this->getJobDB();
        $query = "SELECT NAME, CODE FROM EINHEIT";
        $result = $JobDB->query($query);
        $einheit = [
            'einheit' => ["Alle"],
            'einheitsnummer' => ["Alle"]
        ];
        while ($row = $JobDB->fetchRow($result)) {
            $einheit['einheit'][] = "{$row['NAME']} | {$row['CODE']}";
            $einheit['einheitsnummer'][] = $row['CODE'];
        }
        return json_encode($einheit);
    }
}
