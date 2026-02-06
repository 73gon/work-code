<?php

namespace dashboard\MyWidgets\Simplidone;

use JobRouter\Api\Dashboard\v1\Widget;
use DateTime;
use DateTimeZone;

class Simplidone extends Widget
  {

  public function getTitle()
    {
    return 'Durchschnittliche Bearbeitungsdauer - (enthält nur Rechnungen mit Archivstatus gezahlt)';
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
        "Buchhaltung Kreditkarte",
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
      'einheit' => $this->getEinheit(),
      'usernames' => $this->getUsernames()
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
      if ($incidents[$i][0] != "0" && isset($incidents[$i][1]) && isset($incidents[$i][2])) {
        if (!isset($incidents[1][1]) || $incidents[1][1] === 0)
          $incidents[1][1] = "0d: 0h: 0m";
        if (!isset($incidents[1][2]) || $incidents[1][2] === 0)
          $incidents[1][2] = "0d: 0h: 0m";

        if (preg_match('/^\d+d: \d+h: \d+m$/', $incidents[$i][1]) && preg_match('/^\d+d: \d+h: \d+m$/', $incidents[1][1])) {
          $incidents[1][1] = $this->addTimes([$incidents[1][1], $incidents[$i][1]]);
          }
        if (preg_match('/^\d+d: \d+h: \d+m$/', $incidents[$i][2]) && preg_match('/^\d+d: \d+h: \d+m$/', $incidents[1][2])) {
          $incidents[1][2] = $this->addTimes([$incidents[1][2], $incidents[$i][2]]);
          }
        }
      }
    if (!isset($incidents[1][1]))
      $incidents[1][1] = "-";
    if (!isset($incidents[1][2]))
      $incidents[1][2] = "-";

    return json_encode($incidents);
    }

  /**
   * Calculates business time difference in seconds between two DateTime objects, excluding weekends.
   */
  private function calculateBusinessSeconds(DateTime $start, DateTime $end): int
    {
    if ($end <= $start) {
      return 0;
      }

    $businessSeconds = 0;
    $current = clone $start;

    while ($current < $end) {
      $dayOfWeek = (int) $current->format('N');

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

  public function getNormalSteps()
    {
    $JobDB = $this->getJobDB();
    $query = "
            SELECT j.indate, j.outdate,
              CASE
                WHEN j.STEP = 4 AND h.ZAHLMETHODE = 'Kreditkarte' THEN 444
                ELSE j.STEP
              END AS STEP
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
            AND j.indate >= '" . (new DateTime('first day of January'))->format('Y-m-d H:i:s') . "'
            GROUP BY j.STEP, r.DOKUMENTENID
        ";
    $result = $JobDB->query($query);

    $stepData = [];
    $stepMap = [
      "1" => 0,
      "2" => 1,
      "3" => 2,
      "4" => 3,
      "444" => 4,
      "17" => 5,
      "7" => 6,
      "5" => 7,
      "30" => 8,
      "40" => 9,
      "50" => 10,
      "15" => 11
    ];

    foreach ($stepMap as $step => $index) {
      $stepData[$index] = ['total_seconds' => 0, 'count' => 0];
      }

    $defaultTimeZone = new DateTimeZone('Europe/Berlin');

    while ($row = $JobDB->fetchRow($result)) {
      $step = $row["STEP"];
      if (isset($stepMap[$step])) {
        $index = $stepMap[$step];
        try {
          $indate = new DateTime($row['indate'], $defaultTimeZone);
          $outdate = new DateTime($row['outdate'], $defaultTimeZone);

          $businessSeconds = $this->calculateBusinessSeconds($indate, $outdate);

          $stepData[$index]['total_seconds'] += $businessSeconds;
          $stepData[$index]['count']++;
          } catch (\Exception $e) {
          continue;
          }
        }
      }

    $incidents = array_fill(0, 12, array_fill(0, 3, 0));

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
                AND j.startdate >= '" . (new DateTime('first day of January'))->format('Y-m-d H:i:s') . "'
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
      [$overdue['amount'], $this->calculateTime($overdue['sum']), $this->calculateTime($overdue['avg'])]
    ];
    }

  /**
   * Formats a duration in seconds into a string "Xd: Yh: Zm".
   * Rounds seconds before calculation.
   */
  function calculateTime($time)
    {
    $time = round($time);
    if ($time < 0)
      $time = 0;

    $days = floor($time / 86400);
    $hours = floor(($time % 86400) / 3600);
    $minutes = floor(($time % 3600) / 60);

    return sprintf("%dd: %dh: %dm", $days, $hours, $minutes);
    }

  function addTimes($times)
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

  public function getUsernames()
    {
    $JobDB = $this->getJobDB();
    $currentUser = $this->getUser()->getUsername();

    $query = "
      SELECT DISTINCT
          u.username,
          u.prename,
          u.lastname
      FROM FREIGABEMATRIX fm
      JOIN JRUSERS u
          ON u.username IN (
              fm.PRUEFER,
              fm.BL,
              fm.GBL,
              fm.GF
          )
      WHERE
          (
              fm.GF = '" . $currentUser . "'
              AND u.username IN (fm.GF, fm.GBL, fm.BL, fm.PRUEFER)
          )
          OR
          (
              fm.GBL = '" . $currentUser . "'
              AND u.username IN (fm.GBL, fm.BL, fm.PRUEFER)
          )
          OR
          (
              fm.BL = '" . $currentUser . "'
              AND u.username IN (fm.BL, fm.PRUEFER)
          )
          OR
          (
              fm.PRUEFER = '" . $currentUser . "'
              AND u.username = fm.PRUEFER
          )
      ORDER BY u.lastname, u.prename
    ";

    $result = $JobDB->query($query);
    $usernames = [];
    while ($row = $JobDB->fetchRow($result)) {
      $usernames[] = [
        'username' => $row['username'],
        'prename' => $row['prename'],
        'lastname' => $row['lastname']
      ];
      }
    return json_encode($usernames);
    }
  }
