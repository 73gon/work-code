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
            'duration' => 'dauer',
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

        if (!empty($filters['weiterbelasten'])) {
            $value = addslashes(strtolower($filters['weiterbelasten']));
            $where[] = "LOWER(berechenbar) LIKE '%{$value}%'";
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
            $where[] = "steplabel = '{$value}'";
        }

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $statusValue = strtolower($filters['status']);
            // Extract numeric part of faelligkeit ("123 Tage ") for comparisons
            $faelligkeitDaysSql = "TRY_CONVERT(int, NULLIF(LEFT(faelligkeit, CHARINDEX(' ', faelligkeit + ' ') - 1), ''))";
            switch ($statusValue) {
                case 'beendet':
                    $where[] = "status = 'completed'";
                    break;
                case 'fällig':
                case 'faellig':
                    // Matches derived "due" status or rest items with faelligkeit > 2
                    $where[] = "(status = 'due' OR (status = 'rest' AND {$faelligkeitDaysSql} > 2))";
                    break;
                case 'nicht fällig':
                case 'nicht faellig':
                    // Matches derived "not_due" status or rest items with faelligkeit <= 2
                    $where[] = "(status = 'not_due' OR (status = 'rest' AND {$faelligkeitDaysSql} <= 2))";
                    break;
                default:
                    $value = addslashes($filters['status']);
                    $where[] = "status = '{$value}'";
            }
        }

        if (!empty($filters['laufzeit']) && $filters['laufzeit'] !== 'all') {
            $value = addslashes($filters['laufzeit']);
            // stored as text ranges like '0-5 Tage'
            $where[] = "dauer = '{$value}'";
        }

        if (!empty($filters['coor']) && $filters['coor'] !== 'all') {
            $value = addslashes($filters['coor']);
            $where[] = "coor = '{$value}'";
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
        $statusId = $row['status'];
        $statusLabel = '';

        if ($statusId === 'completed') {
            $statusLabel = 'Beendet';
        } else if ($statusId === 'rest') {
            $faelligkeitDays = (int) filter_var($row['faelligkeit'], FILTER_SANITIZE_NUMBER_INT);
            if ($faelligkeitDays > 2) {
                $statusId = 'due';
                $statusLabel = 'Faellig';
            } else {
                $statusId = 'not_due';
                $statusLabel = 'Nicht Faellig';
            }
        } else {
            $statusLabel = $statusId;
        }

        return [
            'status' => $statusLabel,
            'entryDate' => $row['eingangsdatum'],
            'stepLabel' => $row['steplabel'],
            'startDate' => $row['indate'],
            'jobFunction' => $row['jobfunction'],
            'fullName' => $row['fullname'],
            'documentId' => $row['dokumentid'],
            'companyName' => $row['mandantname'],
            'fund' => $row['fond_abkuerzung'],
            'creditorName' => $row['kredname'],
            'invoiceType' => $row['rechnungstyp'],
            'invoiceNumber' => $row['rechnungsnummer'],
            'invoiceDate' => $row['rechnungsdatum'],
            'grossAmount' => $row['bruttobetrag'],
            'dueDate' => $row['eskalation'],
            'orderId' => $row['coor_orderid'],
            'paymentAmount' => $row['zahlbetrag'],
            'paymentDate' => $row['zahldatum'],
            'duration' => $row['dauer'],
            'invoice' => $row['dokumentid'],
            'protocol' => $row['dokumentid'],
            'chargeable' => $row['berechenbar'],
            'kreditor' => $row['kredname'],
            'weiterbelasten' => $row['berechenbar'],
            'rolle' => $row['jobfunction'],
            'bruttobetrag' => $row['bruttobetrag'],
            'dokumentId' => $row['dokumentid'],
            'bearbeiter' => $row['fullname'],
            'rechnungsnummer' => $row['rechnungsnummer'],
            'gesellschaft' => $row['mandantname'],
            'fonds' => $row['fond_abkuerzung'],
            'schritt' => $row['steplabel'],
            'laufzeit' => $row['dauer'],
            'coor' => isset($row['coor']) ? $row['coor'] : $row['coor_orderid'],
            'rechnungsdatum' => $row['rechnungsdatum'],
        ];
    }
}

Query::execute();
