<?PHP

require_once('../../../includes/central.php');

$einheit = $_GET['einheit'];
$username = $_GET['username'];

$incidents = getIncidents($einheit, $username);
echo $incidents;
function getIncidents($einheit, $username)
{
    $JobDB = DBFactory::getJobDB();
    if (!empty($username)) {
        $query = "SELECT * FROM JRUSERS WHERE username = '$username'";
        $result = $JobDB->query($query);
        $count = 0;
        while ($row = $JobDB->fetchRow($result)) {
            $count++;
        }
        if ($count === 0) {
            return "false";
        }
    }

    $bearbeitung = getBearbeitung($einheit, $username);
    $gebucht_zahlung = getGebuchtAndZahlungsfreigabe($einheit);

    $incidents = array_merge($bearbeitung, $gebucht_zahlung);

    array_unshift($incidents, (string)array_sum($incidents));

    return json_encode($incidents);
}

function getGebuchtAndZahlungsfreigabe($einheit)
{
    $JobDB = DBFactory::getJobDB();
    $temp = "
            WITH LatestRevisions AS (
                SELECT documentrevision_id, DOKUMENTENID, STATUS, RECHNUNGSFAELLIGKEIT, EINHEIT
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
    ";
    if ($einheit != "Alle") {
        $temp = $temp . "WHERE EINHEIT = '" . $einheit . "' GROUP BY STATUS";
    } else {
        $temp = $temp . "GROUP BY STATUS";
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

function getBearbeitung($einheit, $username)
{
    $JobDB = DBFactory::getJobDB();

    $where = "
            r.documentrevision_id = r.MaxRevisionID
            AND h.FAELLIGKEIT < CURDATE()
            AND r.STATUS = 'Bearbeitung'
    ";

    if (!empty($username)) {
        $where .= " AND j.username LIKE '" . $username . "%'";
    }

    if ($einheit != "Alle") {
        $where .= " AND h.EINHEITSNUMMER = '" . $einheit . "'";
    }

    $temp = "
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
            WHERE $where
            GROUP BY h.STEP,
                CASE
                    WHEN h.ZAHLMETHODE = 'Kreditkarte' THEN 444
                    ELSE 4
                END
    ";

    $result = $JobDB->query($temp);

    $bearbeitung = array_fill(0, 11, 0);
    $stepMapping = [
        "1" => 0,
        "2" => 1,
        "3" => 2,
        "4" => 3,
        "7" => 4,
        "5" => 5,
        "17" => 6,
        "30" => 7,
        "40" => 8,
        "50" => 9
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
