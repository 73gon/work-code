<?php
namespace dashboard\MyWidgets\Simplidents;
use JobRouter\Api\Dashboard\v1\Widget;

class Simplidents extends Widget{
	
	
    public function getTitle(){
        return 'Aktuelle Vorgaenge';
    }
	
	public function getDimensions() {

        return [
            'minHeight' => 2,
            'minWidth' => 2,
            'maxHeight' => 2,
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
                "Erfassung", 
                "Pruefung",
                "Freigabe", 
                "Buchhaltung DE", 
                "Buchhaltung IFSC", 
                "ausstehene Zahlungen", 
                "Lieferantenanlage", 
                "offene Mahnungen"
            ])
        ];
    }
	
	public function getIncidents(){
        $JobDB = $this->getJobDB();
        $temp = "SELECT s.STEP, s.STEPLABEL, COALESCE(COUNT(j.STEP), 0) AS STEP_COUNT
                    FROM (
                    SELECT '1' AS STEP, 'Erfassung' AS STEPLABEL
                    UNION ALL
                    SELECT '2', 'Prüfung'
                    UNION ALL
                    SELECT '3', 'Freigabe'
                    UNION ALL
                    SELECT '4', 'Buchhaltung'
                    UNION ALL
                    SELECT '7', 'Fuhrpark'
                    UNION ALL
                    SELECT '15', 'Mahnung'
                    UNION ALL
                    SELECT '17', 'Buchhaltung IFSC'
                    UNION ALL
                    SELECT '30', 'Lieferantenanlage'
                    UNION ALL 
                    SELECT '807', 'Zahllauf validieren'
                    UNION ALL 
                    SELECT '802', 'Zahlungsfreigabe'
                    ) AS s
                    LEFT JOIN JRINCIDENTS j ON s.STEP = j.STEP AND j.processname = 'RECHNUNGSBEARBEITUNG' AND (j.STATUS = 0 OR j.STATUS = 1)
                    INNER JOIN JRINCIDENT g ON j.processid = g.processid AND g.`status`= 0
                    GROUP BY s.STEP, s.STEPLABEL;";
        $result = $JobDB->query($temp);


        $incidents = array_fill(0, 8, 0);
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
                case "802":
                case "807":
                    $incidents[5] = (String)((int)$incidents[5] + (int)$row["STEP_COUNT"]);
                    break;
                case "30":
                    $incidents[6] = $row["STEP_COUNT"];
                    break;
                case "15":
                    $incidents[7] = $row["STEP_COUNT"];
                    break;
                default:
                    break;
            }
        }	
	    return json_encode($incidents);
    }
}