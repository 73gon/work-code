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
            'maxHeight' => 5,
            'maxWidth' => 5,
        ];
    }

    public function isAuthorized()
    {
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
                "Fuhrpark",
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
                    WHERE j.STEP IN (1, 2, 3, 4, 17, 7, 5, 30, 40, 50, 15)
                    AND (
                            (j.STEP = 15 AND r.STATUS = 'erledigt')
                            OR
                            (j.STEP != 15 AND r.STATUS = 'gezahlt')
                        )
                    AND j.processname = 'RECHNUNGSBEARBEITUNG'
                    AND j.indate IS NOT NULL
                    AND j.outdate IS NOT NULL
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
                $incidents[$index] = [$row["amount"], $this->calculateTime($row["total_seconds"]), $this->calculateTime($row["avg_seconds"])];
            }
        }
        return array_values($incidents);
    }

    public function getPayments()
    {

        $JobDB = $this->getJobDB();
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
                AND r.STATUS = 'gezahlt'
                GROUP BY r.DOKUMENTENID
        ";
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
            [$notOverdue['amount'], $this->calculateTime($notOverdue['sum']), $this->calculateTime($notOverdue['avg'])],
            [$overdue['amount'], $this->calculateTime($overdue['sum']),  $this->calculateTime($overdue['avg'])]
        ];
    }

    function calculateTime($time)
    {
        return sprintf("%dd: %dh: %dm", $time / 86400, $time % 86400 / 3600, $time % 3600 / 60);
    }

    function addTimes($times)
    {
        $totalSeconds = 0;

        foreach ($times as $time) {
            list($days, $hours, $minutes) = sscanf($time, "%dd: %dh: %dm");
            $totalSeconds += $minutes * 60 + $hours * 3600 + $days * 86400;
        }

        $days = intdiv($totalSeconds, 86400);
        $hours = intdiv($totalSeconds % 86400, 3600);
        $minutes = intdiv($totalSeconds % 3600, 60);

        return sprintf("%dd: %dh: %dm", $days, $hours, $minutes);
    }

    public function getEinheit()
    {
        $JobDB = $this->getJobDB();
        $query = "SELECT NAME, CODE FROM EINHEIT";
        $result = $JobDB->query($query);
        $einheit = [
            'einheit' => ["Alle"],
            'einheitsnummer' => ["Alle"]
        ];
        while ($row = $JobDB->fetchRow($result)) {
            $einheit['einheit'][] = "{$row['NAME']} | {$row['CODE']}";
            $einheit['einheitsnummer'][] = $row['CODE'];
        }
        return json_encode($einheit);
    }
}
