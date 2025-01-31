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
            WITH MaxRevisions AS (
                SELECT
                    DOKUMENTENID,
                    MAX(documentrevision_id) AS MaxRevisionID
                FROM RECHNUGNEN
                GROUP BY DOKUMENTENID
            ),
            FilteredRows AS (
                SELECT
                    r.DOKUMENTENID,
                    r.STATUS,
                    r.RECHNUNGSFAELLIGKEIT
                FROM RECHNUGNEN r
                INNER JOIN MaxRevisions m
                    ON r.DOKUMENTENID = m.DOKUMENTENID
                AND r.documentrevision_id = m.MaxRevisionID
                WHERE r.RECHNUNGSFAELLIGKEIT < CURDATE()
                AND (r.STATUS = 'Gebucht' OR r.STATUS = 'Zahlungsfreigabe')
            )
            SELECT
                STATUS,
                COUNT(*) AS COUNTROW
            FROM FilteredRows
    ";
    if($einheit != "Alle"){
        $temp = $temp."WHERE EINHEIT = '".$einheit."' GROUP BY STATUS";
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
            WITH MaxRevisions AS (
                SELECT
                    DOKUMENTENID,
                    MAX(documentrevision_id) AS MaxRevisionID
                FROM RECHNUGNEN
                GROUP BY DOKUMENTENID
            ),
            FilteredRows AS (
                SELECT
                    r.documentrevision_id,
                    r.DOKUMENTENID,
                    r.STATUS,
                    r.RECHNUNGSFAELLIGKEIT
                FROM RECHNUGNEN r
                INNER JOIN MaxRevisions m
                    ON r.DOKUMENTENID = m.DOKUMENTENID
                AND r.documentrevision_id = m.MaxRevisionID
                WHERE r.STATUS = 'Bearbeitung'
            ),
            StepsData AS (
                SELECT
                    fr.DOKUMENTENID,
                    h.STEP,
                    h.FAELLIGKEIT,
                    j.STEPLABEL,
                    h.EINHEITSNUMMER
                FROM FilteredRows fr
                LEFT JOIN RE_HEAD h ON fr.DOKUMENTENID = h.DOKUMENTENID
                LEFT JOIN JRINCIDENTS j
                    ON h.step_id = j.process_step_id
                AND j.processname = 'RECHNUNGSBEARBEITUNG'
                AND (j.STATUS = 0 OR j.STATUS = 1)
                INNER JOIN JRINCIDENT i
                    ON j.processid = i.processid
                AND i.`status` = 0
                WHERE h.FAELLIGKEIT < CURDATE()
            )
            SELECT
                s.STEP AS STEP,
                s.STEPLABEL,
                COUNT(s.STEP) AS COUNTROW
            FROM StepsData s
    ";
    if($einheit != "Alle"){
        $temp = $temp."WHERE s.EINHEITSNUMMER = '".$einheit."' GROUP BY s.STEP";
    }else{
        $temp = $temp."GROUP BY s.STEP";
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
