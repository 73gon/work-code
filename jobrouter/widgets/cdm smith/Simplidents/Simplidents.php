<?php
namespace dashboard\MyWidgets\Simplidents;
use JobRouter\Api\Dashboard\v1\Widget;

class Simplidents extends Widget{


    public function getTitle(){
        return 'Aktuelle Vorgaenge';
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
                "Buchhaltung IFSC",
                "Einkauf",
                "ausstehene Zahlungen",
                "Lieferantenanlage",
                "Lieferantenanlage IFSC",
                "Lieferantenanlage Compliance",
                "offene Mahnungen"
            ]),
            'einheit' => $this->getEinheit()
        ];
    }

	public function getIncidents(){
        $JobDB = $this->getJobDB();
        $temp = "
                    SELECT j.STEP, COUNT(j.STEP) AS STEP_COUNT, j.steplabel
                    FROM JRINCIDENTS j
                    INNER JOIN JRINCIDENT i ON j.processid = i.processid
                    LEFT JOIN RE_HEAD r ON j.process_step_id = r.step_id
                    WHERE j.processname = 'RECHNUNGSBEARBEITUNG'
                    AND (j.STATUS = 0 OR j.STATUS = 1)
                    AND i.status = 0
                    AND j.STEP IN (1, 2, 3, 4, 17, 5, 807, 802, 30, 40, 50, 15)
                    GROUP BY j.STEP
                ";
        $result = $JobDB->query($temp);


        $incidents = array_fill(0, 11, 0);
        while($row = $JobDB->fetchRow($result)){
            switch ($row["STEP"]) {
                case "1":
                    $incidents[0] = $row["STEP_COUNT"];
                    break;
                case "2":
                    $incidents[1] = $row["STEP_COUNT"];
                    break;
                case "3":
                    $incidents[2] = $row["STEP_COUNT"];
                    break;
                case "4":
                case "7":
                    $incidents[3] = (String)((int)$incidents[3] + (int)$row["STEP_COUNT"]);
                    break;
                case "17":
                    $incidents[4] = $row["STEP_COUNT"];
                    break;
                case "5":
                    $incidents[5] = $row["STEP_COUNT"];
                    break;
                case "802":
                case "807":
                    $incidents[6] = (String)((int)$incidents[5] + (int)$row["STEP_COUNT"]);
                    break;
                case "30":
                    $incidents[7] = $row["STEP_COUNT"];
                    break;
                case "40":
                    $incidents[8] = $row["STEP_COUNT"];
                    break;
                case "50":
                    $incidents[9] = $row["STEP_COUNT"];
                    break;
                case "15":
                    $incidents[10] = $row["STEP_COUNT"];
                    break;
                default:
                    break;
            }
        }
        array_unshift($incidents, (string)array_sum($incidents));

	    return json_encode($incidents);
    }

    public function getEinheit(){
        $JobDB = $this->getJobDB();
        $query = "
                    SELECT r.EINHEITSNAME, r.EINHEITSNUMMER
                    FROM JRINCIDENTS j
                    INNER JOIN JRINCIDENT i ON j.processid = i.processid
                    LEFT JOIN RE_HEAD r ON j.process_step_id = r.step_id
                    WHERE j.processname = 'RECHNUNGSBEARBEITUNG'
                    AND (j.STATUS = 0 OR j.STATUS = 1)
                    AND i.status = 0
                    AND j.STEP IN (1, 2, 3, 4, 17, 5, 807, 802, 30, 40, 50, 15)
                    AND r.EINHEITSNUMMER IS NOT NULL AND r.EINHEITSNUMMER != ''
                    GROUP BY r.EINHEITSNUMMER
                ";
        $result = $JobDB->query($query);
        $einheit = [
            'einheit' => [],
            'einheitsnummer' => []
        ];
        while($row = $JobDB->fetchRow($result)){
            $einheit['einheit'][] = $row["EINHEITSNAME"] . ' | ' . $row["EINHEITSNUMMER"];
            $einheit['einheitsnummer'][] = $row["EINHEITSNUMMER"];
        }
        array_unshift($einheit['einheit'], "Alle");
        array_unshift($einheit['einheitsnummer'], "Alle");
        return json_encode($einheit);
    }
}
