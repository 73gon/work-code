<?php

namespace dashboard\MyWidgets\SimplifyTable;

use JobRouter\Api\Dashboard\v1\Widget;
use Exception;
use Throwable;

require_once('../../../includes/central.php');

class Query extends Widget
{
    public function getTitle(): string
    {
        return 'SimplifyTable Query';
    }
    public static function execute(): void
    {
        try {
            $widget = new static();
            $response = $widget->handleRequest();
            header('Content-Type: application/json');
            echo json_encode($response);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Exception: ' . $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error: ' . $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
        }
    }

    private function getParam(string $key, $default = '')
    {
        return isset($_GET[$key]) ? trim($_GET[$key]) : $default;
    }

    private function handleRequest(): array
    {
        $page = max(1, (int) $this->getParam('page', 1));
        $perPage = max(1, min(100, (int) $this->getParam('perPage', 25)));
        $offset = ($page - 1) * $perPage;

        $sortColumn = $this->getParam('sortColumn', '');
        $sortDirection = strtolower($this->getParam('sortDirection', 'asc')) === 'desc' ? 'desc' : 'asc';

        $username = $this->getParam('username', '');

        $filters = [
            'kreditor' => $this->getParam('kreditor', ''),
            'weiterbelasten' => $this->getParam('weiterbelasten', ''),
            'rolle' => $this->getParam('rolle', ''),
            'rechnungstyp' => $this->getParam('rechnungstyp', ''),
            'bruttobetrag' => $this->getParam('bruttobetrag', ''),
            'dokumentId' => $this->getParam('dokumentId', ''),
            'bearbeiter' => $this->getParam('bearbeiter', ''),
            'rechnungsnummer' => $this->getParam('rechnungsnummer', ''),
            'schritt' => $this->getParam('schritt', ''),
            'status' => $this->getParam('status', ''),
            'laufzeit' => $this->getParam('laufzeit', ''),
            'coor' => $this->getParam('coor', ''),
            'rechnungsdatumFrom' => $this->getParam('rechnungsdatumFrom', ''),
            'rechnungsdatumTo' => $this->getParam('rechnungsdatumTo', ''),
        ];

        $gesellschaftList = $this->decodeListParam($this->getParam('gesellschaft', ''));
        $fondsList = $this->decodeListParam($this->getParam('fonds', ''));

        $sortMap = [
            'entryDate' => 'eingangsdatum',
            'stepLabel' => 'steplabel',
            'startDate' => 'indate',
            'jobFunction' => 'jobfunction',
            'fullName' => 'fullname',
            'documentId' => 'dokumentid',
            'companyName' => 'mandantname',
            'fund' => 'fond_abkuerzung',
            'creditorName' => 'kredname',
            'invoiceType' => 'rechnungstyp',
            'invoiceNumber' => 'rechnungsnummer',
            'invoiceDate' => 'rechnungsdatum',
            'grossAmount' => 'bruttobetrag',
            'dueDate' => 'eskalation',
            'orderId' => 'coor_orderid',
            'paymentAmount' => 'zahlbetrag',
            'paymentDate' => 'zahldatum',
            'runtime' => 'runtime',
            'status' => 'status',
        ];

        $orderSql = '';
        if (!empty($sortColumn) && array_key_exists($sortColumn, $sortMap)) {
            $orderSql = 'ORDER BY ' . $sortMap[$sortColumn] . ' ' . $sortDirection;
        } else {
            // SQL Server requires ORDER BY when using OFFSET/FETCH
            $orderSql = 'ORDER BY (SELECT NULL)';
        }

        $where = $this->buildWhereClauses($filters, $username, $gesellschaftList, $fondsList);
        $whereSql = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

        $JobDB = $this->getJobDB();

        $countQuery = "SELECT COUNT(*) as total FROM V_UEBERSICHTEN_WIDGET {$whereSql}";
        $countResult = $JobDB->query($countQuery);
        $totalRow = $JobDB->fetchRow($countResult);
        $total = $totalRow ? (int) $totalRow['total'] : 0;

        $dataQuery = "SELECT * FROM V_UEBERSICHTEN_WIDGET {$whereSql} {$orderSql} OFFSET {$offset} ROWS FETCH NEXT {$perPage} ROWS ONLY";
        error_log($dataQuery);
        $result = $JobDB->query($dataQuery);

        $data = [];
        while ($row = $JobDB->fetchRow($result)) {
            $data[] = $this->mapRow($row);
        }

        return [
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'data' => $data,
        ];
    }

    private function decodeListParam(string $value): array
    {
        if (empty($value)) {
            return [];
        }
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        return [$value];
    }

    private function buildWhereClauses(array $filters, string $username, array $gesellschaftList, array $fondsList): array
    {
        $where = [];

        if (!empty($username)) {
            $safeUser = addslashes($username);
            $where[] = "CONCAT(',', REPLACE(LOWER(berechtigung), ' ', ''), ',') LIKE CONCAT('%,', LOWER('{$safeUser}'), ',%')";
        }

        if (!empty($filters['kreditor'])) {
            $value = addslashes(strtolower($filters['kreditor']));
            $where[] = "LOWER(kredname) LIKE '%{$value}%'";
        }

        if (!empty($filters['weiterbelasten']) && $filters['weiterbelasten'] !== 'all') {
            error_log('Filter weiterbelasten: ' . $filters['weiterbelasten']);
            $value = addslashes($filters['weiterbelasten']);
            $where[] = "berechenbar = '{$value}'";
        }

        if (!empty($filters['rolle'])) {
            $value = addslashes(strtolower($filters['rolle']));
            $where[] = "LOWER(jobfunction) LIKE '%{$value}%'";
        }

        if (!empty($filters['rechnungstyp'])) {
            $value = addslashes(strtolower($filters['rechnungstyp']));
            $where[] = "LOWER(rechnungstyp) LIKE '%{$value}%'";
        }

        if (!empty($filters['bruttobetrag'])) {
            $value = addslashes($filters['bruttobetrag']);
            $where[] = "CAST(bruttobetrag AS VARCHAR(50)) LIKE '%{$value}%'";
        }

        if (!empty($filters['dokumentId'])) {
            $value = addslashes(strtolower($filters['dokumentId']));
            $where[] = "LOWER(dokumentid) LIKE '%{$value}%'";
        }

        if (!empty($filters['bearbeiter'])) {
            $value = addslashes(strtolower($filters['bearbeiter']));
            $where[] = "LOWER(fullname) LIKE '%{$value}%'";
        }

        if (!empty($filters['rechnungsnummer'])) {
            $value = addslashes(strtolower($filters['rechnungsnummer']));
            $where[] = "LOWER(rechnungsnummer) LIKE '%{$value}%'";
        }

        if (!empty($filters['schritt']) && $filters['schritt'] !== 'all') {
            $value = addslashes($filters['schritt']);
            $where[] = "step = '{$value}'";
        }

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $statusValue = strtolower($filters['status']);
            // SQL Server: compare eskalation (due date) with today
            $eskalationSql = "TRY_CONVERT(date, eskalation)";
            switch ($statusValue) {
                case 'beendet':
                case 'completed':
                    $where[] = "status = 'completed'";
                    break;
                case 'aktiv_alle':
                case 'aktiv alle':
                    // All active (rest) items - both fällig and nicht fällig
                    $where[] = "status = 'rest'";
                    break;
                case 'fällig':
                case 'faellig':
                case 'aktiv fällig':
                case 'aktiv faellig':
                    // Fällig: status = 'rest' AND eskalation <= today
                    $where[] = "(status = 'rest' AND {$eskalationSql} <= CAST(GETDATE() AS date))";
                    break;
                case 'nicht fällig':
                case 'nicht faellig':
                case 'not_faellig':
                case 'aktiv nicht fällig':
                case 'aktiv nicht faellig':
                    // Nicht Fällig: status = 'rest' AND eskalation > today
                    $where[] = "(status = 'rest' AND ({$eskalationSql} > CAST(GETDATE() AS date) OR eskalation IS NULL))";
                    break;
                default:
                    $value = addslashes($filters['status']);
                    $where[] = "status = '{$value}'";
            }
        }

        if (!empty($filters['laufzeit']) && $filters['laufzeit'] !== 'all') {
            $value = $filters['laufzeit'];
            // Calculate days from indate: DATEDIFF(day, indate, GETDATE())
            $daysSql = "DATEDIFF(day, indate, GETDATE())";

            switch ($value) {
                case '0-5 Tage':
                    $where[] = "({$daysSql} >= 0 AND {$daysSql} <= 5)";
                    break;
                case '6-10 Tage':
                    $where[] = "({$daysSql} >= 6 AND {$daysSql} <= 10)";
                    break;
                case '11-20 Tage':
                    $where[] = "({$daysSql} >= 11 AND {$daysSql} <= 20)";
                    break;
                case '21+ Tage':
                    $where[] = "({$daysSql} >= 21)";
                    break;
                default:
                    // Fallback: try to parse custom range like "X-Y Tage"
                    if (preg_match('/^(\d+)-(\d+)\s*Tage$/i', $value, $matches)) {
                        $min = (int)$matches[1];
                        $max = (int)$matches[2];
                        $where[] = "({$daysSql} >= {$min} AND {$daysSql} <= {$max})";
                    } elseif (preg_match('/^(\d+)\+\s*Tage$/i', $value, $matches)) {
                        $min = (int)$matches[1];
                        $where[] = "({$daysSql} >= {$min})";
                    }
            }
        }

        if (!empty($filters['coor']) && $filters['coor'] !== 'all') {
            $value = strtolower($filters['coor']);
            if ($value === 'ja') {
                $where[] = "coorflag = 1";
            } elseif ($value === 'nein') {
                $where[] = "coorflag = 0";
            }
        }

        if (!empty($filters['rechnungsdatumFrom'])) {
            $value = addslashes($filters['rechnungsdatumFrom']);
            $where[] = "rechnungsdatum >= '{$value}'";
        }

        if (!empty($filters['rechnungsdatumTo'])) {
            $value = addslashes($filters['rechnungsdatumTo']);
            $where[] = "rechnungsdatum <= '{$value}'";
        }

        if (!empty($gesellschaftList)) {
            $values = array_map(function ($item) {
                return "'" . addslashes($item) . "'";
            }, $gesellschaftList);
            $where[] = 'mandantname IN (' . implode(',', $values) . ')';
        }

        if (!empty($fondsList)) {
            $values = array_map(function ($item) {
                return "'" . addslashes($item) . "'";
            }, $fondsList);
            $where[] = 'fond_abkuerzung IN (' . implode(',', $values) . ')';
        }

        return $where;
    }

    private function mapRow(array $row): array
    {
        // Normalize keys to lowercase for consistent access
        $row = array_change_key_case($row, CASE_LOWER);

        $statusId = isset($row['status']) ? $row['status'] : '';
        $statusLabel = '';

        if ($statusId === 'completed') {
            $statusLabel = 'Beendet';
        } else if ($statusId === 'rest') {
            // Check if eskalation (due date) <= today
            $eskalationDate = isset($row['eskalation']) ? $row['eskalation'] : '';
            if (!empty($eskalationDate)) {
                $eskalation = strtotime($eskalationDate);
                $today = strtotime('today');
                if ($eskalation <= $today) {
                    $statusId = 'due';
                    $statusLabel = 'Faellig';
                } else {
                    $statusId = 'not_due';
                    $statusLabel = 'Nicht Faellig';
                }
            } else {
                // No eskalation date, default to not due
                $statusId = 'not_due';
                $statusLabel = 'Nicht Faellig';
            }
        } else {
            $statusLabel = $statusId;
        }

        return [
            'status' => $statusLabel,
            'entryDate' => isset($row['eingangsdatum']) ? $row['eingangsdatum'] : '',
            'stepLabel' => isset($row['steplabel']) ? $row['steplabel'] : '',
            'startDate' => isset($row['indate']) ? $row['indate'] : '',
            'jobFunction' => isset($row['jobfunction']) ? $row['jobfunction'] : '',
            'fullName' => isset($row['fullname']) ? $row['fullname'] : '',
            'documentId' => isset($row['dokumentid']) ? $row['dokumentid'] : '',
            'companyName' => isset($row['mandantname']) ? $row['mandantname'] : '',
            'fund' => isset($row['fond_abkuerzung']) ? $row['fond_abkuerzung'] : '',
            'creditorName' => isset($row['kredname']) ? $row['kredname'] : '',
            'invoiceType' => isset($row['rechnungstyp']) ? $row['rechnungstyp'] : '',
            'invoiceNumber' => isset($row['rechnungsnummer']) ? $row['rechnungsnummer'] : '',
            'invoiceDate' => isset($row['rechnungsdatum']) ? $row['rechnungsdatum'] : '',
            'grossAmount' => isset($row['bruttobetrag']) ? $row['bruttobetrag'] : '',
            'dueDate' => isset($row['eskalation']) ? $row['eskalation'] : '',
            'orderId' => isset($row['coor_orderid']) ? $row['coor_orderid'] : '',
            'paymentAmount' => isset($row['zahlbetrag']) ? $row['zahlbetrag'] : '',
            'paymentDate' => isset($row['zahldatum']) ? $row['zahldatum'] : '',
            'runtime' => isset($row['runtime']) ? $row['runtime'] : '',
            'invoice' => isset($row['dokumentid']) ? $row['dokumentid'] : '',
            'protocol' => isset($row['dokumentid']) ? $row['dokumentid'] : '',
            'chargeable' => isset($row['berechenbar']) ? $row['berechenbar'] : '',
            'kreditor' => isset($row['kredname']) ? $row['kredname'] : '',
            'weiterbelasten' => isset($row['berechenbar']) ? $row['berechenbar'] : '',
            'rolle' => isset($row['jobfunction']) ? $row['jobfunction'] : '',
            'bruttobetrag' => isset($row['bruttobetrag']) ? $row['bruttobetrag'] : '',
            'dokumentId' => isset($row['dokumentid']) ? $row['dokumentid'] : '',
            'bearbeiter' => isset($row['fullname']) ? $row['fullname'] : '',
            'rechnungsnummer' => isset($row['rechnungsnummer']) ? $row['rechnungsnummer'] : '',
            'gesellschaft' => isset($row['mandantname']) ? $row['mandantname'] : '',
            'fonds' => isset($row['fond_abkuerzung']) ? $row['fond_abkuerzung'] : '',
            'schritt' => isset($row['steplabel']) ? $row['steplabel'] : '',
            'laufzeit' => isset($row['runtime']) ? $row['runtime'] : '',
            'coor' => isset($row['coorflag']) ? $row['coorflag'] : (isset($row['coor_orderid']) ? $row['coor_orderid'] : ''),
            'rechnungsdatum' => isset($row['rechnungsdatum']) ? $row['rechnungsdatum'] : '',
        ];
    }
}

Query::execute();
