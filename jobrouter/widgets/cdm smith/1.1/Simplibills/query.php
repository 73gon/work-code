<?PHP

require_once('../../../includes/central.php');

$einheit = $_GET['einheit'];

$incidents = getIncidents($einheit);
echo $incidents;
function getIncidents($einheit) {
    $bearbeitung = getBearbeitung($einheit);
    $gebucht_zahlung = getGebuchtAndZahlungsfreigabe($einheit);

    $incidents = array_merge($bearbeitung, $gebucht_zahlung);

    array_unshift($incidents, (string)array_sum($incidents));

    return json_encode($incidents);
}

function getGebuchtAndZahlungsfreigabe($einheit){
    $JobDB = DBFactory::getJobDB();
    $temp = "
                WITH RankedRows AS (
                    SELECT
                        documentrevision_id,
                        DOKUMENTENID,
                        STATUS,
                        RECHNUNGSFAELLIGKEIT,
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
            ";
    if($einheit != "Alle"){
        $temp = $temp."AND EINHEIT = '".$einheit."' GROUP BY STATUS";
    }else{
        $temp = $temp."GROUP BY STATUS";
    }

    $result = $JobDB->query($temp);    
    $gebucht_zahlung = ['Zahlungsfreigabe' => 0, 'Gebucht' => 0];
    while ($row = $JobDB->fetchRow($result)) {
        if (isset($gebucht_zahlung[$row["STATUS"]])) {
            $gebucht_zahlung[$row["STATUS"]] = $row["COUNTROW"];
        }
    }
    return array_values($gebucht_zahlung); 
}

function getBearbeitung($einheit){
    $JobDB = DBFactory::getJobDB();
    $temp = "
                WITH RankedRows AS (
                    SELECT
                        documentrevision_id,
                        DOKUMENTENID,
                        STATUS,
                        RECHNUNGSFAELLIGKEIT,
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
            ";
    if($einheit != "Alle"){
        $temp = $temp."AND h.EINHEITSNUMMER = '".$einheit."' GROUP BY h.STEP";
    }else{
        $temp = $temp."GROUP BY h.STEP";
    }
    $result = $JobDB->query($temp);

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
?>