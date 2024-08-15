<?PHP

require_once('../../../includes/central.php');

$JobDB = DBFactory::getJobDB();

$indate = $_GET['indate'];
$outdate = $_GET['outdate'];

$indate = empty($_GET['indate']) ? '2015-01-01' : $_GET['indate'];
$outdate = empty($_GET['outdate']) ? date('Y-m-d') : $_GET['outdate'];


$all = getAll($indate, $outdate);
echo $all;
function getAll($indate, $outdate)
{
    $normalSteps = getNormalSteps($indate, $outdate);
    $payments = getPayments($indate, $outdate);

    $incidents = array_merge($normalSteps, $payments);

    $sum = array_fill(0, count($incidents[0]), 0);
    for ($i = 0; $i < count($incidents); $i++) {
        $sum[0] += $incidents[$i][0];
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

function getNormalSteps($indate, $outdate)
{
    $JobDB = DBFactory::getJobDB();
    $query = "SELECT STEP AS step, 
                    CONCAT(FLOOR(SUM(TIME_TO_SEC(duration)) / 86400), 'd: ',
                        LPAD(FLOOR((SUM(TIME_TO_SEC(duration)) % 86400) / 3600), 2, '0'), 'h: ',
                        LPAD(FLOOR((SUM(TIME_TO_SEC(duration)) % 3600) / 60), 2, '0'), 'm: ',
                        LPAD(FLOOR(SUM(TIME_TO_SEC(duration)) % 60), 2, '0'), 's') AS sumTime, 
                CONCAT(FLOOR(AVG(TIME_TO_SEC(duration)) / 86400), 'd: ',
                        LPAD(FLOOR((AVG(TIME_TO_SEC(duration)) % 86400) / 3600), 2, '0'), 'h: ',
                        LPAD(FLOOR((AVG(TIME_TO_SEC(duration)) % 3600) / 60), 2, '0'), 'm: ',
                        LPAD(FLOOR(AVG(TIME_TO_SEC(duration)) % 60), 2, '0'), 's') AS avgTime,
                COUNT(STEP) AS amount
            FROM (
            SELECT DOKUMENTENID, STEP, indate, outdate, TIMEDIFF(outdate, indate) AS duration
            FROM (
                SELECT r2.DOKUMENTENID, j2.STEP, j2.indate, j2.outdate
                FROM RECHNUGNEN r2
                LEFT JOIN RE_HEAD h2 ON r2.DOKUMENTENID = h2.DOKUMENTENID
                LEFT JOIN JRINCIDENTS j2 ON h2.step_id = j2.process_step_id
                WHERE r2.STATUS = 'Gezahlt'
                AND j2.STEP IN (1, 2, 3, 4, 17, 30, 15)
                AND j2.processname = 'RECHNUNGSBEARBEITUNG'
                AND (j2.indate >= '".$indate."' AND j2.indate < '".$outdate."')
                GROUP BY r2.DOKUMENTENID, j2.STEP
                HAVING COUNT(*) = 1
            
                UNION ALL
                
                SELECT r1.DOKUMENTENID, j1.STEP, MIN(j1.indate) AS indate, MAX(j1.outdate) AS outdate
                FROM RECHNUGNEN r1
                LEFT JOIN RE_HEAD h1 ON r1.DOKUMENTENID = h1.DOKUMENTENID
                LEFT JOIN JRINCIDENTS j1 ON h1.step_id = j1.process_step_id
                WHERE r1.STATUS = 'Gezahlt'
                AND j1.STEP IN (1, 2, 3, 4, 17, 30, 15)
                AND j1.processname = 'RECHNUNGSBEARBEITUNG'
                AND (j1.indate >= '".$indate."' AND j1.indate < '".$outdate."')
                GROUP BY r1.DOKUMENTENID, j1.STEP
                HAVING COUNT(*) > 1
            ) AS allDates
            ) AS final
            GROUP BY STEP
            ORDER BY STEP ASC";
    $result = $JobDB->query($query);

    $incidents = array_fill(0, 7, array_fill(0, 3, 0));
    while ($row = $JobDB->fetchRow($result)) {
        switch ($row["step"]) {
            case "1":
                $incidents[0] = [$row["amount"], $row["sumTime"], $row["avgTime"]];
                break;
            case "2":
                $incidents[1] = [$row["amount"], $row["sumTime"], $row["avgTime"]];
                break;
            case "3":
                $incidents[2] = [$row["amount"], $row["sumTime"], $row["avgTime"]];
                break;
            case "4":
                $incidents[3] = [$row["amount"], $row["sumTime"], $row["avgTime"]];
                break;
            case "17":
                $incidents[4] = [$row["amount"], $row["sumTime"], $row["avgTime"]];
                break;
            case "30":
                $incidents[5] = [$row["amount"], $row["sumTime"], $row["avgTime"]];
                break;
            case "15":
                $incidents[6] = [$row["amount"], $row["sumTime"], $row["avgTime"]];
                break;
            default:
                break;
        }
    }
    return array_values($incidents);
}

function getPayments($indate, $outdate)
    {
        
    $JobDB = DBFactory::getJobDB();
    $query = "	
        SELECT
            CONCAT(FLOOR(SUM(TIME_TO_SEC(notOverdue)) / 86400), 'd: ',
                LPAD(FLOOR((SUM(TIME_TO_SEC(notOverdue)) % 86400) / 3600), 2, '0'), 'h: ',
                LPAD(FLOOR((SUM(TIME_TO_SEC(notOverdue)) % 3600) / 60), 2, '0'), 'm: ',
                LPAD(FLOOR(SUM(TIME_TO_SEC(notOverdue)) % 60), 2, '0'), 's') AS sumNichtFaellig, 
            CONCAT(FLOOR(AVG(TIME_TO_SEC(notOverdue)) / 86400), 'd: ',
                LPAD(FLOOR((AVG(TIME_TO_SEC(notOverdue)) % 86400) / 3600), 2, '0'), 'h: ',
                LPAD(FLOOR((AVG(TIME_TO_SEC(notOverdue)) % 3600) / 60), 2, '0'), 'm: ',
                LPAD(FLOOR(AVG(TIME_TO_SEC(notOverdue)) % 60), 2, '0'), 's') AS avgNichtFaellig,
            COUNT(notOverdue) AS amountNichtFaellig,
            CONCAT(FLOOR(SUM(TIME_TO_SEC(overdue)) / 86400), 'd: ',
                LPAD(FLOOR((SUM(TIME_TO_SEC(overdue)) % 86400) / 3600), 2, '0'), 'h: ',
                LPAD(FLOOR((SUM(TIME_TO_SEC(overdue)) % 3600) / 60), 2, '0'), 'm: ',
                LPAD(FLOOR(SUM(TIME_TO_SEC(overdue)) % 60), 2, '0'), 's') AS sumFaellig, 
            CONCAT(FLOOR(AVG(TIME_TO_SEC(overdue)) / 86400), 'd: ',
                LPAD(FLOOR((AVG(TIME_TO_SEC(overdue)) % 86400) / 3600), 2, '0'), 'h: ',
                LPAD(FLOOR((AVG(TIME_TO_SEC(overdue)) % 3600) / 60), 2, '0'), 'm: ',
                LPAD(FLOOR(AVG(TIME_TO_SEC(overdue)) % 60), 2, '0'), 's') AS avgFaellig,
            COUNT(overdue) AS amountFaellig
        FROM (	
            SELECT r.DOKUMENTENID, r.VORGANGZL , r.RECHNUNGSFAELLIGKEIT, j.startdate, j.enddate, r.STATUS,
                CASE 
                    WHEN r.RECHNUNGSFAELLIGKEIT < j.startdate THEN 
                            TIMEDIFF(j.enddate, j.startdate)
                    ELSE NULL
                    END as notOverdue,
                CASE
                    WHEN r.RECHNUNGSFAELLIGKEIT > j.startdate THEN 
                        TIMEDIFF(r.RECHNUNGSFAELLIGKEIT, j.startdate)
                    ELSE NULL
                    END AS overdue
            FROM RECHNUGNEN r
            LEFT JOIN JRINCIDENT j ON r.VORGANGZL = j.incident
            WHERE r.STATUS = 'Gezahlt'
            AND (r.VORGANGZL AND r.RECHNUNGSFAELLIGKEIT) IS NOT NULL
            AND (j.startdate >= '".$indate."' AND j.startdate < '".$outdate."')
            GROUP BY r.DOKUMENTENID
        ) AS allPayments";
    $result = $JobDB->query($query);

    $payments = array(["0", "0d: 0h: 0m: 0s", "0d: 0h: 0m: 0s"], ["0", "0d: 0h: 0m: 0s", "0d: 0h: 0m: 0s"]);
    while ($row = $JobDB->fetchRow($result)) {
        if ($row["amountFaellig"] != "0") {
            $payments[0] = [$row["amountFaellig"], $row["sumFaellig"], $row["avgFaellig"]];
        }
        if ($row["amountNichtFaellig"] != "0") {
            $payments[1] = [$row["amountNichtFaellig"], $row["sumNichtFaellig"], $row["avgNichtFaellig"]];
        }
    }
    return array_values($payments);
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
?>