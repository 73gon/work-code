<?php

namespace dashboard\MyWidgets\Simplidone;

use JobRouter\Api\Dashboard\v1\Widget;

class Simplidone extends Widget
{

    public function getTitle()
    {
        return 'Durchschnittliche Bearbeitungsdauer';
    }

    public function getDimensions()
    {

        return [
            'minHeight' => 4,
            'minWidth' => 3,
            'maxHeight' => 4,
            'maxWidth' => 3,
        ];
    }

    public function isAuthorized(){
        return $this->getUser()->isInJobFunction('Widgets');
    }

    public function getData()
    {
        return [
            'incidents' => $this->getIncidents(),
            'labels' => json_encode([
                "Schritte",
                "Total",
                "Erfassung",
                "Pruefung",
                "Freigabe",
                "Buchhaltung DE",
                "Buchhaltung IFSC",
                "Einkauf",
                "Lieferantenanlage",
                "Lieferantenanlage IFSC",
                "Lieferantenanlage Compliance",
                "offene Mahnungen",
                "Ueberfaellige Rechnungen",
                "Fristgerechte Rechnungen",
            ]),
            'einheit' => $this->getEinheit()
        ];
    }

    public function getIncidents()
    {
        $normalSteps = $this->getNormalSteps();
        $payments = $this->getPayments();

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
                $incidents[1][1] = $this->addTimes([$incidents[1][1], $incidents[$i][1]]);
                $incidents[1][2] = $this->addTimes([$incidents[1][2], $incidents[$i][2]]);
            }
        }

        return json_encode($incidents);
    }

    public function getNormalSteps()
    {
        $JobDB = $this->getJobDB();
        $query = "
                WITH ProcessedData AS (
                    SELECT j.STEP, TIMEDIFF(MAX(j.outdate), MIN(j.indate)) AS duration, COUNT(*) AS step_count
                    FROM JRINCIDENTS j
                    INNER JOIN RE_HEAD h ON j.process_step_id = h.step_id
                    INNER JOIN RECHNUGNEN r ON h.DOKUMENTENID = r.DOKUMENTENID
                    WHERE j.STEP IN (1, 2, 3, 4, 17, 5, 30, 40, 50, 15)
                    AND j.processname = 'RECHNUNGSBEARBEITUNG'
                    AND j.indate IS NOT NULL
                    AND j.outdate IS NOT NULL
                    GROUP BY j.STEP, r.DOKUMENTENID
                ),
                AggregatedData AS (
                    SELECT STEP, SUM(TIME_TO_SEC(duration)) AS total_seconds, AVG(TIME_TO_SEC(duration)) AS avg_seconds, COUNT(STEP) AS amount
                    FROM ProcessedData
                    GROUP BY STEP
                )
                SELECT
                    STEP AS step,
                    CONCAT(
                        FLOOR(total_seconds / 86400), 'd: ',
                        LPAD(FLOOR((total_seconds % 86400) / 3600), 2, '0'), 'h: ',
                        LPAD(FLOOR((total_seconds % 3600) / 60), 2, '0'), 'm: ',
                        LPAD(FLOOR(total_seconds % 60), 2, '0'), 's'
                    ) AS sumTime,
                    CONCAT(
                        FLOOR(avg_seconds / 86400), 'd: ',
                        LPAD(FLOOR((avg_seconds % 86400) / 3600), 2, '0'), 'h: ',
                        LPAD(FLOOR((avg_seconds % 3600) / 60), 2, '0'), 'm: ',
                        LPAD(FLOOR(avg_seconds % 60), 2, '0'), 's'
                    ) AS avgTime,
                    amount
                FROM AggregatedData
                ORDER BY STEP ASC;
        ";
        $result = $JobDB->query($query);

        $incidents = array_fill(0, 10, array_fill(0, 3, 0));
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
                case "5":
                    $incidents[5] = [$row["amount"], $row["sumTime"], $row["avgTime"]];
                    break;
                case "30":
                    $incidents[6] = [$row["amount"], $row["sumTime"], $row["avgTime"]];
                    break;
                case "40":
                    $incidents[7] = [$row["amount"], $row["sumTime"], $row["avgTime"]];
                    break;
                case "50":
                    $incidents[8] = [$row["amount"], $row["sumTime"], $row["avgTime"]];
                    break;
                case "15":
                    $incidents[9] = [$row["amount"], $row["sumTime"], $row["avgTime"]];
                    break;
                default:
                    break;
            }
        }
        return array_values($incidents);
    }

    public function getPayments()
    {

        $JobDB = $this->getJobDB();
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
                    SELECT
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
                    AND (r.VORGANGZL AND r.RECHNUNGSFAELLIGKEIT) IS NOT NULL
                    GROUP BY r.DOKUMENTENID
                ) AS allPayments
        ";
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

    public function getEinheit(){
        $JobDB = $this->getJobDB();
        $query1 = "
                WITH filtered_rechnungen AS (
                    SELECT r.DOKUMENTENID, h.EINHEITSNUMMER, h.EINHEITSNAME
                    FROM RECHNUGNEN r
                    INNER JOIN RE_HEAD h ON r.DOKUMENTENID = h.DOKUMENTENID
                    INNER JOIN JRINCIDENTS j ON h.step_id = j.process_step_id
                        AND j.STEP IN (1, 2, 3, 4, 17, 5, 30, 40, 50, 15)
                        AND j.processname = 'RECHNUNGSBEARBEITUNG'
                    WHERE h.EINHEITSNUMMER IS NOT NULL
                    AND h.EINHEITSNUMMER != ''
                ),
                grouped_rechnungen AS (
                    SELECT DOKUMENTENID, EINHEITSNUMMER, EINHEITSNAME, COUNT(*) AS cnt
                    FROM filtered_rechnungen
                    GROUP BY DOKUMENTENID, EINHEITSNUMMER
                )
                SELECT EINHEITSNUMMER, EINHEITSNAME
                FROM grouped_rechnungen
                WHERE cnt = 1 OR cnt > 1
                GROUP BY EINHEITSNUMMER;
        ";
        $result = $JobDB->query($query1);
        $einheit1 = [
            'einheit' => [],
            'einheitsnummer' => []
        ];
        while($row = $JobDB->fetchRow($result)){
            $einheit1['einheit'][] = $row["EINHEITSNAME"] . " | " . $row["EINHEITSNUMMER"];
            $einheit1['einheitsnummer'][] = $row["EINHEITSNUMMER"];
        }

        $query2 = "
                    SELECT r.EINHEIT, h.EINHEITSNAME
                    FROM RECHNUGNEN r
                    INNER JOIN RE_HEAD h ON r.DOKUMENTENID = h.DOKUMENTENID
                    INNER JOIN JRINCIDENT j ON r.VORGANGZL = j.incident
                    AND (r.VORGANGZL AND r.RECHNUNGSFAELLIGKEIT) IS NOT NULL
                    WHERE r.EINHEIT IS NOT NULL AND r.EINHEIT != ''
                    GROUP BY r.EINHEIT

                ";
        $result = $JobDB->query($query2);
        $einheit2 = [
            'einheit' => [],
            'einheitsnummer' => []
        ];
        while($row = $JobDB->fetchRow($result)){
            $einheit1['einheit'][] = $row["EINHEITSNAME"] . " | " . $row["EINHEIT"];
            $einheit1['einheitsnummer'][] = $row["EINHEIT"];
        }
        $einheit = array_unique(array_merge($einheit1, $einheit2));
        sort($einheit);

        array_unshift($einheit, "Alle");
        return json_encode($einheit);
    }
}
