<?php

namespace dashboard\MyWidgets\Simplidone;

use JobRouter\Api\Dashboard\v1\Widget;
use DateTime;
use DateTimeZone;

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
        "Rechnungspruefung",
        "Beitragsfreigabe",
        "Finanzbuchhaltung",
        "Zahlsperre",
        "Dauerbuchung",
      ]),
      'einheit' => $this->getEinheit()
    ];
  }

  public function getIncidents()
  {
    $normalSteps = $this->getNormalSteps();

    $incidents = array_merge($normalSteps);

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
    // $incidents[1][0] = $total_count; // You might need to calculate total count explicitly


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
      $dayOfWeek = (int)$current->format('N'); // 1 (Mon) to 7 (Sun)

      $nextDay = (clone $current)->modify('+1 day')->setTime(0, 0, 0);
      $intervalEnd = min($end, $nextDay);

      if ($dayOfWeek < 6) { // Monday to Friday
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
          SELECT j.steplabel, j.step AS STEP, j.indate, j.outdate
          FROM JRINCIDENTS j
          WHERE j.processname = 'ERPROZESS'
          AND j.STEP IN (1, 2, 4, 5, 12, 150)
          AND j.indate IS NOT NULL
          AND j.outdate IS NOT NULL
          AND j.indate >= '" . (new DateTime('first day of January'))->format('Y-m-d H:i:s') . "'
      ";


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
      error_log(print_r($incidents, true));
      return $incidents;
  }

  /**
   * Formats a duration in seconds into a string "Xd: Yh: Zm".
   * Rounds seconds before calculation.
   */
  function calculateTime($time)
  {
    $time = round($time);
    if ($time < 0) $time = 0;

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
    return json_encode([]);
  }
}
