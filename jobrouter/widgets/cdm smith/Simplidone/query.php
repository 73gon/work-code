<?PHP

require_once('../../../includes/central.php');

$JobDB = DBFactory::getJobDB();

$indate = $_GET['indate'];
$outdate = $_GET['outdate'];
$einheit = $_GET['einheit'];
$username = $_GET['username'];

$indate = empty($_GET['indate']) ? '2015-01-01' : $_GET['indate'];
$outdate = empty($_GET['outdate']) ? date('Y-m-d') : $_GET['outdate'];


$all = getIncidents($indate, $outdate, $einheit, $username);
echo $all;
function getIncidents($indate, $outdate, $einheit, $username)
{
    $normalSteps = getNormalSteps($indate, $outdate, $einheit, $username);
    $payments = getPayments($indate, $outdate, $einheit);

    $incidents = array_merge($normalSteps, $payments);

    $sum = array_fill(0, count($payments[0]), 0);
    for ($i = 0; $i < count($payments); $i++) {
        $sum[0] += $payments[$i][0];
    }

    array_unshift($incidents, $sum);
    array_unshift($incidents, ["Anzahl Rechnungen", "Summe Dauer", "Durchschnitt Dauer"]);

    for ($i = 0; $i < count($incidents); $i++) {
        if ($incidents[$i][0] == "0") {
            $incidents[$i][1] = "-";
            $incidents[$i][2] = "-";
        }
    }

    for ($i = 2; $i < count($incidents); $i++) {
        if ($incidents[$i][0] != "0") {
            $incidents[1][1] = addTimes([$incidents[1][1], $incidents[$i][1]]);
            $incidents[1][2] = addTimes([$incidents[1][2], $incidents[$i][2]]);
        }
    }

    return json_encode($incidents);
}

function getNormalSteps($indate, $outdate, $einheit, $username)
{
    $JobDB = DBFactory::getJobDB();

    $where = "
                j.STEP IN (1, 2, 3, 4, 17, 7, 5, 30, 40, 50, 15)
                AND (
                        (j.STEP = 15 AND r.STATUS = 'erledigt')
                        OR
                        (j.STEP != 15 AND r.STATUS = 'gezahlt')
                    )
                AND j.processname = 'RECHNUNGSBEARBEITUNG'
                AND j.indate IS NOT NULL
                AND j.outdate IS NOT NULL
                AND (j.indate >= '" . $indate . "' AND j.indate < '" . $outdate . "')
            ";

    if (!empty($username)) {
        $where .= " AND j.username LIKE '" . $username . "%'";
    }

    if ($einheit != "Alle") {
        $where .= " AND h.EINHEITSNUMMER = '" . $einheit . "'";
    }

    $query = "
                WITH ProcessedData AS (
                    SELECT j.STEP, TIMEDIFF(MAX(j.outdate), MIN(j.indate)) AS duration, COUNT(*) AS step_count
                    FROM JRINCIDENTS j
                        INNER JOIN RE_HEAD h ON j.process_step_id = h.step_id
                        INNER JOIN RECHNUGNEN r ON h.DOKUMENTENID = r.DOKUMENTENID
                        WHERE $where
                        GROUP BY j.STEP, r.DOKUMENTENID
                    )
                    SELECT STEP, SUM(TIME_TO_SEC(duration)) AS total_seconds, AVG(TIME_TO_SEC(duration)) AS avg_seconds, COUNT(STEP) AS amount
                    FROM ProcessedData
                    GROUP BY STEP
                    ORDER BY STEP ASC;
            ";
    $result = $JobDB->query($query);

    $incidents = array_fill(0, 11, array_fill(0, 3, 0));
    $stepMap = [
        "1" => 0,
        "2" => 1,
        "3" => 2,
        "4" => 3,
        "17" => 4,
        "7" => 5,
        "5" => 6,
        "30" => 7,
        "40" => 8,
        "50" => 9,
        "15" => 10
    ];

    while ($row = $JobDB->fetchRow($result)) {
        if (isset($stepMap[$row["STEP"]])) {
            $index = $stepMap[$row["STEP"]];
            $incidents[$index] = [$row["amount"], calculateTime($row["total_seconds"]), calculateTime($row["avg_seconds"])];
        }
    }
    return array_values($incidents);
}

function getPayments($indate, $outdate, $einheit)
{
    $JobDB = DBFactory::getJobDB();

    if ($einheit != "Alle") {
        $query = "
                    SELECT
                        CASE
                            WHEN r.RECHNUNGSFAELLIGKEIT >= j.enddate THEN
                                    TIMESTAMPDIFF(SECOND, j2.startdate, j.enddate)
                            ELSE NULL
                            END as notOverdue,
                        CASE
                            WHEN r.RECHNUNGSFAELLIGKEIT < j.enddate THEN
                                TIMESTAMPDIFF(SECOND, r.RECHNUNGSFAELLIGKEIT, j.enddate)
                            ELSE NULL
                            END AS overdue
                    FROM RECHNUGNEN r
                    LEFT JOIN JRINCIDENT j ON r.VORGANGZL = j.incident
                    LEFT JOIN JRINCIDENT j2 ON r.VORGANGSNUMMER = j2.incident
                    WHERE j.processname = 'RECHNUNGSBEARBEITUNG'
                    AND (r.VORGANGZL AND r.RECHNUNGSFAELLIGKEIT) IS NOT NULL
                    AND (j.startdate >= '" . $indate . "' AND j.startdate < '" . $outdate . "')
                    AND r.EINHEIT = '" . $einheit . "'
                    AND r.STATUS = 'gezahlt'
                    GROUP BY r.DOKUMENTENID
                ";
    } else {
        $query = "
                    SELECT
                        CASE
                            WHEN r.RECHNUNGSFAELLIGKEIT >= j.enddate THEN
                                    TIMESTAMPDIFF(SECOND, j2.startdate, j.enddate)
                            ELSE NULL
                            END as notOverdue,
                        CASE
                            WHEN r.RECHNUNGSFAELLIGKEIT < j.enddate THEN
                                TIMESTAMPDIFF(SECOND, r.RECHNUNGSFAELLIGKEIT, j.enddate)
                            ELSE NULL
                            END AS overdue
                    FROM RECHNUGNEN r
                    LEFT JOIN JRINCIDENT j ON r.VORGANGZL = j.incident
                    LEFT JOIN JRINCIDENT j2 ON r.VORGANGSNUMMER = j2.incident
                    WHERE j.processname = 'RECHNUNGSBEARBEITUNG'
                    AND (r.VORGANGZL AND r.RECHNUNGSFAELLIGKEIT) IS NOT NULL
                    AND (j.startdate >= '" . $indate . "' AND j.startdate < '" . $outdate . "')
                    AND r.STATUS = 'gezahlt'
                    GROUP BY r.DOKUMENTENID
                ";
    }
    $result = $JobDB->query($query);

    $overdue = $notOverdue = ['sum' => 0, 'avg' => 0, 'amount' => 0];

    while ($row = $JobDB->fetchRow($result)) {
        foreach (['notOverdue', 'overdue'] as $key) {
            if ($row[$key] !== null) {
                ${$key}['sum'] += $row[$key];
                ${$key}['amount']++;
            }
        }
    }
    $notOverdue['avg'] = $notOverdue['amount'] == 0 ? 0 : $notOverdue['sum'] / $notOverdue['amount'];
    $overdue['avg'] = $overdue['amount'] == 0 ? 0 : $overdue['sum'] / $overdue['amount'];

    return [
        [$notOverdue['amount'], calculateTime($notOverdue['sum']), calculateTime($notOverdue['avg'])],
        [$overdue['amount'], calculateTime($overdue['sum']),  calculateTime($overdue['avg'])]
    ];
}

function addTimes($times)
{
    $totalSeconds = 0;

    foreach ($times as $time) {
        list($days, $hours, $minutes, $seconds) = sscanf($time, "%dd: %dh: %dm: %ds");
        $totalSeconds += $seconds + $minutes * 60 + $hours * 3600 + $days * 86400;
    }

    $days = floor($totalSeconds / 86400);
    $totalSeconds %= 86400;
    $hours = floor($totalSeconds / 3600);
    $totalSeconds %= 3600;
    $minutes = floor($totalSeconds / 60);
    $seconds = $totalSeconds % 60;

    return sprintf("%dd: %dh: %dm: %ds", $days, $hours, $minutes, $seconds);
}

function calculateTime($time)
{
    return sprintf("%dd: %dh: %dm", $time / 86400, $time % 86400 / 3600, $time % 3600 / 60);
}
