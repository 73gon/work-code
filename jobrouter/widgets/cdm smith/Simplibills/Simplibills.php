<?php
namespace dashboard\MyWidgets\Simplibills;
use JobRouter\Api\Dashboard\v1\Widget;

class Simplibills extends Widget{

    public function getTitle(){
        return 'Ueberfaellige und unbezahlte Rechnungen';
    }

	public function getDimensions() {

        return [
            'minHeight' => 4,
            'minWidth' => 2,
            'maxHeight' => 4,
            'maxWidth' => 2,
        ];
    }


    public function isAuthorized(){
        return $this->getUser()->isInJobFunction('Widgets');
    }


    public function getData(){
        return [
            'incidents' => $this->getIncidents(),
            'labels' => json_encode([
                "Total",
                "Erfassung",
                "Pruefung",
                "Freigabe",
                "Buchhaltung DE",
                "Einkauf",
                "Buchhaltung IFSC",
                "Lieferantenanlage",
                "Lieferantenanlage IFSC",
                "Lieferantenanlage Compliance",
                "ausstehene Zahlungen",
                "Gebuchte Rechnungen"
            ]),
            'einheit' => $this->getEinheit()
        ];
    }

    public function getIncidents() {
        $bearbeitung = $this->getBearbeitung();
        $gebucht_zahlung = $this->getGebuchtAndZahlungsfreigabe();

        $incidents = array_merge($bearbeitung, $gebucht_zahlung);

        array_unshift($incidents, (string)array_sum($incidents));

        return json_encode($incidents);
    }
    public function getGebuchtAndZahlungsfreigabe(){
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

    public function getBearbeitung(){
        $JobDB = $this->getJobDB();
        $query = "
                WITH RankedRows AS (
                    SELECT documentrevision_id, DOKUMENTENID, STATUS, MAX(documentrevision_id) OVER (PARTITION BY DOKUMENTENID) AS MaxRevisionID
                    FROM RECHNUGNEN
                )
                SELECT h.STEP AS STEP, j.STEPLABEL, COUNT(h.STEP) AS COUNTROW
                FROM RankedRows r
                LEFT JOIN RE_HEAD h ON r.DOKUMENTENID = h.DOKUMENTENID
                LEFT JOIN JRINCIDENTS j ON h.step_id = j.process_step_id AND j.processname = 'RECHNUNGSBEARBEITUNG' AND j.STATUS IN (0, 1)
                INNER JOIN JRINCIDENT i ON j.processid = i.processid AND i.`status`= 0
                WHERE documentrevision_id = MaxRevisionID
                AND h.FAELLIGKEIT < CURDATE()
                AND r.STATUS = 'Bearbeitung'
                GROUP BY h.STEP
        ";
        $result = $JobDB->query($query);

        $bearbeitung = array_fill(0, 9, 0);
        $stepMapping = [ "1" => 0, "2" => 1, "3" => 2, "4" => 3, "7" => 3, "5" => 4, "17" => 5, "30" => 6, "40" => 7, "50" => 8];

        while ($row = $JobDB->fetchRow($result)) {
            $step = $row["STEP"];
            if (isset($stepMapping[$step])) {
                $index = $stepMapping[$step];
                $bearbeitung[$index] += (int) $row["COUNTROW"];
            }
        }
	    return $bearbeitung;
    }

    public function getEinheit(){
        $JobDB = $this->getJobDB();
        $query1 = "
                WITH MaxRevisions AS (
                    SELECT DOKUMENTENID, MAX(documentrevision_id) AS MaxRevisionID
                    FROM RECHNUGNEN
                    GROUP BY DOKUMENTENID
                ),
                FilteredRows AS (
                    SELECT r.EINHEIT, h.EINHEITSNAME, r.documentrevision_id, r.DOKUMENTENID, r.STATUS, r.RECHNUNGSFAELLIGKEIT
                    FROM RECHNUGNEN r
                    INNER JOIN MaxRevisions m ON r.DOKUMENTENID = m.DOKUMENTENID AND r.documentrevision_id = m.MaxRevisionID
                    INNER JOIN RE_HEAD h ON r.DOKUMENTENID = h.DOKUMENTENID
                    WHERE r.RECHNUNGSFAELLIGKEIT < CURDATE()
                    AND (r.STATUS = 'Gebucht' OR r.STATUS = 'Zahlungsfreigabe')
                    AND r.EINHEIT IS NOT NULL AND r.EINHEIT != ''
                )
                SELECT EINHEIT, EINHEITSNAME
                FROM FilteredRows
                GROUP BY EINHEIT
        ";
        $result = $JobDB->query($query1);
        $einheit1 = [
            'einheit' => [],
            'einheitsnummer' => []
        ];
        while($row = $JobDB->fetchRow($result)){
            $einheit1['einheit'][] = $row["EINHEITSNAME"] . " | " . $row["EINHEIT"];
            $einheit1['einheitsnummer'][] = $row["EINHEIT"];
        }

        $query2 = "
                WITH MaxRevisions AS (
                    SELECT DOKUMENTENID, MAX(documentrevision_id) AS MaxRevisionID
                    FROM RECHNUGNEN
                    GROUP BY DOKUMENTENID
                ),
                RankedRows AS (
                    SELECT r.documentrevision_id, r.DOKUMENTENID, r.STATUS, r.RECHNUNGSFAELLIGKEIT, m.MaxRevisionID
                    FROM RECHNUGNEN r
                    INNER JOIN MaxRevisions m ON r.DOKUMENTENID = m.DOKUMENTENID AND r.documentrevision_id = m.MaxRevisionID
                )
                SELECT h.EINHEITSNUMMER, h.EINHEITSNAME
                FROM RankedRows r
                LEFT JOIN RE_HEAD h ON r.DOKUMENTENID = h.DOKUMENTENID
                LEFT JOIN JRINCIDENTS j ON h.step_id = j.process_step_id AND j.processname = 'RECHNUNGSBEARBEITUNG' AND (j.STATUS = 0 OR j.STATUS = 1)
                INNER JOIN JRINCIDENT i ON j.processid = i.processid AND i.`status`= 0
                WHERE h.FAELLIGKEIT < CURDATE()
                AND r.STATUS = 'Bearbeitung'
                AND h.EINHEITSNUMMER IS NOT NULL
                AND h.EINHEITSNUMMER != ''
                GROUP BY h.EINHEITSNUMMER
        ";
        $result = $JobDB->query($query2);
        $einheit2 = [
            'einheit' => [],
            'einheitsnummer' => []
        ];
        while($row = $JobDB->fetchRow($result)){
            $einheit2["einheit"][] = $row["EINHEITSNAME"] . " | " . $row["EINHEITSNUMMER"];
            $einheit2["einheitsnummer"][] = $row["EINHEITSNUMMER"];
        }
        $einheit = [
            'einheit' => array_unique(array_merge($einheit1['einheit'], $einheit2['einheit'])),
            'einheitsnummer' => array_unique(array_merge($einheit1['einheitsnummer'], $einheit2['einheitsnummer']))
        ];
        sort($einheit);

        array_unshift($einheit, "Alle");
        return json_encode($einheit);
    }
}
