<?php
namespace dashboard\MyWidgets\Simplibills;
use JobRouter\Api\Dashboard\v1\Widget;

class Simplibills extends Widget{
	
    public function getTitle(){
        return 'Ueberfaellige und unbezahlte Rechnungen';
    }
	
	public function getDimensions() {

        return [
            'minHeight' => 3,
            'minWidth' => 2,
            'maxHeight' => 3,
            'maxWidth' => 2,
        ];
    }

    
    public function isAuthorized(){
        return $this->getUser()->isInJobFunction('AR-CA');
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
                "offene Mahnungen",
                "Buchhaltung IFSC",
                "Lieferantenanlage",
                "ausstehene Zahlungen", 
                "Gebuchte Rechnungen"
            ])
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
        $query = "WITH RankedRows AS (
                    SELECT
                        documentrevision_id,
                        DOKUMENTENID,
                        STATUS,
                        RECHNUNGSFAELLIGKEIT,
                        ROW_NUMBER() OVER (PARTITION BY DOKUMENTENID ORDER BY documentrevision_id DESC) AS RowNum,
                        MAX(documentrevision_id) OVER (PARTITION BY DOKUMENTENID) AS MaxRevisionID
                    FROM RECHNUGNEN
                    )
                SELECT
                    STATUS,
                    COUNT(STATUS) AS COUNTROW
                FROM RankedRows
                WHERE documentrevision_id = MaxRevisionID
                AND RECHNUNGSFAELLIGKEIT < CURDATE()
                AND (STATUS = 'Gebucht' OR STATUS = 'Zahlungsfreigabe')
                GROUP BY STATUS;";
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
        $query = "WITH RankedRows AS (
                    SELECT
                        documentrevision_id,
                        DOKUMENTENID,
                        STATUS,
                        RECHNUNGSFAELLIGKEIT,
                        ROW_NUMBER() OVER (PARTITION BY DOKUMENTENID ORDER BY documentrevision_id DESC) AS RowNum,
                        MAX(documentrevision_id) OVER (PARTITION BY DOKUMENTENID) AS MaxRevisionID
                    FROM RECHNUGNEN 
                    )
                SELECT
                h.STEP AS STEP,
                j.STEPLABEL,
                COUNT(h.STEP) AS COUNTROW
                FROM RankedRows r
                LEFT JOIN RE_HEAD h ON r.DOKUMENTENID = h.DOKUMENTENID
                LEFT JOIN JRINCIDENTS j ON h.step_id = j.process_step_id AND j.processname = 'RECHNUNGSBEARBEITUNG' AND (j.STATUS = 0 OR j.STATUS = 1)
                INNER JOIN JRINCIDENT i ON j.processid = i.processid AND i.`status`= 0
                WHERE documentrevision_id = MaxRevisionID AND h.FAELLIGKEIT < CURDATE()
                AND r.STATUS = 'Bearbeitung'
                GROUP BY h.STEP;";
        $result = $JobDB->query($query);

        $bearbeitung = array_fill(0, 8, 0);
        $stepMapping = [ "1" => 0, "2" => 1, "3" => 2, "4" => 3, "7" => 3, "5" => 4, "15" => 5, "17" => 6, "30" => 7];

        while ($row = $JobDB->fetchRow($result)) {
            $step = $row["STEP"];
            if (isset($stepMapping[$step])) {
                $index = $stepMapping[$step];
                $bearbeitung[$index] += (int) $row["COUNTROW"];
            }
        }
	    return $bearbeitung;
    }
}
		