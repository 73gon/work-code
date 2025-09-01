<?PHP

namespace dashboard\MyWidgets\Simplidone;

use JobRouter\Api\Dashboard\v1\Widget;
use Exception;
use DateTime;
use DateTimeZone;
use Error;
use Throwable;

require_once('../../../includes/central.php');

class query extends Widget
{
    public function getTitle(): string
    {
        return 'Simplidone Query';
    }

    public static function execute()
    {
        try {
            $widget = new static();

            $indate = isset($_GET['indate']) ? $_GET['indate'] : '';
            $outdate = isset($_GET['outdate']) ? $_GET['outdate'] : '';
            $username = isset($_GET['username']) ? $_GET['username'] : '';

            $indate = empty($indate) ? '2015-01-01' : $indate;
            $outdate = empty($outdate) ? date('Y-m-d') : $outdate;

            $all = $widget->getIncidents($indate, $outdate, $username);
            echo $all;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Exception: ' . $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
        } catch (Error $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Fatal Error: ' . $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Throwable: ' . $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
        }
    }

    public function getIncidents($indate, $outdate, $username)
    {
        if (!empty($username)) {
            $query = "SELECT * FROM JRUSERS WHERE username = '$username'";
            $result = $this->getJobDB()->query($query);
            $count = 0;
            while ($row = $this->getJobDB()->fetchRow($result)) {
                $count++;
            }
            if ($count === 0) {
                return "false";
            }
        }

        $steps = $this->getSteps($indate, $outdate, $username);

        $incidents = array_merge($steps);

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
            if ($incidents[$i][0] != "0" && isset($incidents[$i][1]) && isset($incidents[$i][2])) {
                if (!isset($incidents[1][1]) || $incidents[1][1] === 0) $incidents[1][1] = "0d: 0h: 0m";
                if (!isset($incidents[1][2]) || $incidents[1][2] === 0) $incidents[1][2] = "0d: 0h: 0m";

                if (preg_match('/^\d+d: \d+h: \d+m$/', $incidents[$i][1]) && preg_match('/^\d+d: \d+h: \d+m$/', $incidents[1][1])) {
                    $incidents[1][1] = $this->addTimes([$incidents[1][1], $incidents[$i][1]]);
                }
                if (preg_match('/^\d+d: \d+h: \d+m$/', $incidents[$i][2]) && preg_match('/^\d+d: \d+h: \d+m$/', $incidents[1][2])) {
                    $incidents[1][2] = $this->addTimes([$incidents[1][2], $incidents[$i][2]]);
                }
            }
        }
        if (!isset($incidents[1][1])) $incidents[1][1] = "-";
        if (!isset($incidents[1][2])) $incidents[1][2] = "-";

        return json_encode($incidents);
    }

    public function getSteps($indate, $outdate, $username)
    {
        $JobDB = $this->getJobDB();
        $query = "
            SELECT j.steplabel, j.step AS STEP, j.indate, j.outdate
            FROM JRINCIDENTS j
            WHERE j.processname = 'ERPROZESS'
            AND j.STEP IN (1, 2, 4, 5, 12, 150)
            AND j.indate IS NOT NULL
            AND j.outdate IS NOT NULL
            AND j.indate >= '" . $indate . "'";

        if (!empty($outdate)) {
            $query .= " AND j.indate <= '" . $outdate . "'";
        }

        if (!empty($username)) {
            $query .= " AND j.username LIKE '" . $username . "%'";
        }

        $result = $JobDB->query($query);

        $stepData = [];
        $stepMap = [
            "1" => 0,
            "2" => 1,
            "4" => 2,
            "5" => 3,
            "12" => 4,
            "150" => 5
        ];

        // Initialize step data
        foreach ($stepMap as $step => $index) {
            $stepData[$index] = ['total_seconds' => 0, 'count' => 0];
        }

        $defaultTimeZone = new DateTimeZone('Europe/Berlin');

        while ($row = $JobDB->fetchRow($result)) {
            $step = $row["STEP"];
            if (isset($stepMap[$step])) {
                $index = $stepMap[$step];
                try {
                    $inDateTime = new DateTime($row['indate'], $defaultTimeZone);
                    $outDateTime = new DateTime($row['outdate'], $defaultTimeZone);

                    $businessSeconds = $this->calculateBusinessSeconds($inDateTime, $outDateTime);

                    $stepData[$index]['total_seconds'] += $businessSeconds;
                    $stepData[$index]['count']++;
                } catch (Exception $e) {
                    continue;
                }
            }
        }

        // Prepare results array
        $incidents = [];

        foreach ($stepData as $index => $data) {
            $count = $data['count'];
            $totalSeconds = $data['total_seconds'];

            if ($count > 0) {
                $avgSeconds = $totalSeconds / $count;
                $incidents[$index] = [
                    $count,
                    $this->calculateTime($totalSeconds),
                    $this->calculateTime($avgSeconds)
                ];
            } else {
                $incidents[$index] = [0, "0d: 0h: 0m", "0d: 0h: 0m"];
            }
        }

        return $incidents;
    }

    public function addTimes($times)
    {
        $totalSeconds = 0;

        foreach ($times as $time) {
            if (is_string($time) && preg_match('/^\d+d: \d+h: \d+m$/', $time)) {
                list($days, $hours, $minutes) = sscanf($time, "%dd: %dh: %dm");
                if (is_numeric($days) && is_numeric($hours) && is_numeric($minutes)) {
                    $totalSeconds += $minutes * 60 + $hours * 3600 + $days * 86400;
                }
            } elseif (is_numeric($time)) {
                $totalSeconds += $time;
            }
        }

        $days = floor($totalSeconds / 86400);
        $hours = floor(($totalSeconds % 86400) / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);

        return sprintf("%dd: %dh: %dm", $days, $hours, $minutes);
    }

    public function calculateBusinessSeconds(DateTime $start, DateTime $end): int
    {
        if ($end <= $start) {
            return 0;
        }

        $businessSeconds = 0;
        $current = clone $start;

        while ($current < $end) {
            $dayOfWeek = (int)$current->format('N');

            $nextDay = (clone $current)->modify('+1 day')->setTime(0, 0, 0);
            $intervalEnd = min($end, $nextDay);

            if ($dayOfWeek < 6) {
                if ($intervalEnd > $current) {
                    $secondsThisSegment = $intervalEnd->getTimestamp() - $current->getTimestamp();
                    $businessSeconds += max(0, $secondsThisSegment);
                }
            }
            $current = $nextDay;
            if ($current >= $end) {
                break;
            }
        }
        return $businessSeconds;
    }

    public function calculateTime($time)
    {
        $time = round($time);
        if ($time < 0) $time = 0;

        $days = floor($time / 86400);
        $hours = floor(($time % 86400) / 3600);
        $minutes = floor(($time % 3600) / 60);

        return sprintf("%dd: %dh: %dm", $days, $hours, $minutes);
    }
}

// Execute the widget when this file is accessed directly
query::execute();
