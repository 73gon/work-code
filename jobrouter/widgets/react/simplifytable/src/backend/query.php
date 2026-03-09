<?php

namespace dashboard\MyWidgets\SimplifyTable;

use JobRouter\Api\Dashboard\v1\Widget;
use Exception;
use Throwable;

require_once(__DIR__ . '/../../../includes/central.php');

class Query extends Widget
    {
    public function getTitle(): string
        {
        return 'SimplifyTable Query';
        }

    // =============================================
    // FIELD & FILTER REGISTRY (add new fields here)
    // =============================================

    /**
     * Maps frontend column ID => DB column name.
     * Used by mapRow() for data mapping and as the sort map.
     *
     * To add a new column: add one line here + one line in init.php getColumns().
     */
    private function getFieldMap(): array
        {
        return [
            'incident' => 'incident',
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
            'chargeable' => 'berechenbar',
            'kostenuebernahme' => 'kostenuebernahme',
        ];
        }

    /**
     * Standard filter definitions: filterKey => [dbColumn, filterType].
     *
     * Supported types:
     *   text_like     - LOWER(col) LIKE '%value%'
     *   equality      - col = 'value'
     *   number_gte    - col >= value
     *   number_lte    - col <= value
     *   date_gte      - col >= 'value'
     *   date_lte      - col <= 'value'
     *   boolean_10    - Ja => col = 1, Nein => (col = 0 OR col IS NULL)
     *
     * To add a new filter: add one line here.
     */
    private function getFilterDefs(): array
        {
        return [
            'kreditor' => ['kredname', 'text_like'],
            'rolle' => ['jobfunction', 'text_like'],
            'rechnungstyp' => ['rechnungstyp', 'text_like'],
            'dokumentId' => ['dokumentid', 'text_like'],
            'bearbeiter' => ['fullname', 'text_like'],
            'rechnungsnummer' => ['rechnungsnummer', 'text_like'],
            'incident' => ['incident', 'text_like'],
            'weiterbelasten' => ['berechenbar', 'equality'],
            'bruttobetragFrom' => ['bruttobetrag', 'number_gte'],
            'bruttobetragTo' => ['bruttobetrag', 'number_lte'],
            'rechnungsdatumFrom' => ['rechnungsdatum', 'date_gte'],
            'rechnungsdatumTo' => ['rechnungsdatum', 'date_lte'],
            'kostenuebernahme' => ['kostenuebernahme', 'boolean_10'],
        ];
        }

    /**
     * Multi-select (IN list) filter definitions: filterKey => [dbColumn, castToInt].
     *
     * To add a new multi-select filter: add one line here.
     */
    private function getListFilterDefs(): array
        {
        return [
            'gesellschaft' => ['mandantnr', false],
            'fonds' => ['fond_abkuerzung', false],
            'schritt' => ['step', true],
        ];
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
        $export = $this->getParam('export', '') === '1';

        $sortColumn = $this->getParam('sortColumn', '');
        if ($sortColumn === 'historyLink') {
            $sortColumn = '';
            }
        $sortDirection = strtolower($this->getParam('sortDirection', 'asc')) === 'desc' ? 'desc' : 'asc';

        if (empty($sortColumn)) {
            $sortColumn = 'invoiceDate';
            $sortDirection = 'desc';
            }

        $username = $this->getParam('username', '');

        // Read all standard filter params from registry
        $filters = [];
        foreach (array_keys($this->getFilterDefs()) as $key) {
            $filters[$key] = $this->getParam($key, '');
            }
        // Special filters handled with custom WHERE logic
        $filters['status'] = $this->getParam('status', '');
        $filters['laufzeit'] = $this->getParam('laufzeit', '');
        $filters['coor'] = $this->getParam('coor', '');

        // Multi-select list filters from registry
        $listFilters = [];
        foreach (array_keys($this->getListFilterDefs()) as $key) {
            $listFilters[$key] = $this->decodeListParam($this->getParam($key, ''));
            }

        // Sort map = field map + status (status is computed but sortable by raw DB column)
        $sortMap = $this->getFieldMap();
        $sortMap['status'] = 'status';

        $orderSql = '';
        if (!empty($sortColumn) && array_key_exists($sortColumn, $sortMap)) {
            $orderSql = 'ORDER BY ' . $sortMap[$sortColumn] . ' ' . $sortDirection;
            } else {
            $orderSql = 'ORDER BY (SELECT NULL)';
            }

        $where = $this->buildWhereClauses($filters, $username, $listFilters);
        $whereSql = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

        $JobDB = $this->getJobDB();

        $countQuery = "SELECT COUNT(*) as total FROM V_UEBERSICHTEN_WIDGET {$whereSql}";
        $countResult = $JobDB->query($countQuery);
        $totalRow = $JobDB->fetchRow($countResult);
        $total = $totalRow ? (int) $totalRow['total'] : 0;

        if ($export) {
            $dataQuery = "SELECT * FROM V_UEBERSICHTEN_WIDGET {$whereSql} {$orderSql}";
            } else {
            $dataQuery = "SELECT * FROM V_UEBERSICHTEN_WIDGET {$whereSql} {$orderSql} OFFSET {$offset} ROWS FETCH NEXT {$perPage} ROWS ONLY";
            }
        $result = $JobDB->query($dataQuery);

        $data = [];
        while ($row = $JobDB->fetchRow($result)) {
            $data[] = $this->mapRow($row, $username);
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

    private function buildWhereClauses(array $filters, string $username, array $listFilters): array
        {
        $where = [];

        // Username access filter
        if (!empty($username)) {
            $safeUser = addslashes($username);
            $where[] = "berechtigung LIKE '%{$safeUser}%'";
            $where[] = "CONCAT(',', REPLACE(LOWER(berechtigung), ' ', ''), ',') LIKE CONCAT('%,', LOWER('{$safeUser}'), ',%')";
            }

        // Standard filters from registry definitions
        foreach ($this->getFilterDefs() as $key => $def) {
            $value = $filters[$key] ?? '';
            if ($value === '' || $value === 'all')
                continue;

            $dbCol = $def[0];
            $type = $def[1];

            switch ($type) {
                case 'text_like':
                    $safe = addslashes(strtolower($value));
                    $where[] = "LOWER({$dbCol}) LIKE '%{$safe}%'";
                    break;
                case 'equality':
                    $safe = addslashes($value);
                    $where[] = "{$dbCol} = '{$safe}'";
                    break;
                case 'number_gte':
                    $where[] = "{$dbCol} >= " . floatval($value);
                    break;
                case 'number_lte':
                    $where[] = "{$dbCol} <= " . floatval($value);
                    break;
                case 'date_gte':
                    $safe = addslashes($value);
                    $where[] = "{$dbCol} >= '{$safe}'";
                    break;
                case 'date_lte':
                    $safe = addslashes($value);
                    $where[] = "{$dbCol} <= '{$safe}'";
                    break;
                case 'boolean_10':
                    if (strtolower($value) === 'ja') {
                        $where[] = "{$dbCol} = 1";
                        } elseif (strtolower($value) === 'nein') {
                        $where[] = "({$dbCol} = 0 OR {$dbCol} IS NULL)";
                        }
                    break;
                }
            }

        // Multi-select list filters from registry
        foreach ($this->getListFilterDefs() as $key => $def) {
            $list = $listFilters[$key] ?? [];
            $list = array_filter($list, function ($item) {
                return !empty($item) && strtolower($item) !== 'all';
                });
            if (empty($list))
                continue;

            $dbCol = $def[0];
            $castToInt = $def[1];

            if ($castToInt) {
                $values = array_map('intval', $list);
                $where[] = "{$dbCol} IN (" . implode(',', $values) . ")";
                } else {
                $values = array_map(function ($item) {
                    return "'" . addslashes($item) . "'";
                    }, $list);
                $where[] = "{$dbCol} IN (" . implode(',', $values) . ")";
                }
            }

        // Special filter: status (complex switch with date comparison)
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $statusValue = strtolower($filters['status']);
            $eskalationSql = "TRY_CONVERT(date, eskalation)";
            switch ($statusValue) {
                case 'beendet':
                case 'completed':
                    $where[] = "status = 'completed'";
                    break;
                case 'aktiv_alle':
                case 'aktiv alle':
                    $where[] = "status = 'rest'";
                    break;
                case 'fällig':
                case 'faellig':
                case 'aktiv fällig':
                case 'aktiv faellig':
                    $where[] = "(status = 'rest' AND {$eskalationSql} <= CAST(GETDATE() AS date))";
                    break;
                case 'nicht fällig':
                case 'nicht faellig':
                case 'not_faellig':
                case 'aktiv nicht fällig':
                case 'aktiv nicht faellig':
                    $where[] = "(status = 'rest' AND ({$eskalationSql} > CAST(GETDATE() AS date) OR eskalation IS NULL))";
                    break;
                default:
                    $value = addslashes($filters['status']);
                    $where[] = "status = '{$value}'";
                }
            }

        // Special filter: laufzeit (DATEDIFF-based ranges)
        if (!empty($filters['laufzeit']) && $filters['laufzeit'] !== 'all') {
            $value = $filters['laufzeit'];
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
                    if (preg_match('/^(\d+)-(\d+)\s*Tage$/i', $value, $matches)) {
                        $min = (int) $matches[1];
                        $max = (int) $matches[2];
                        $where[] = "({$daysSql} >= {$min} AND {$daysSql} <= {$max})";
                        } elseif (preg_match('/^(\d+)\+\s*Tage$/i', $value, $matches)) {
                        $min = (int) $matches[1];
                        $where[] = "({$daysSql} >= {$min})";
                        }
                }
            }

        // Special filter: coor (boolean flag mapping)
        if (!empty($filters['coor']) && $filters['coor'] !== 'all') {
            $value = strtolower($filters['coor']);
            if ($value === 'ja') {
                $where[] = "coorflag = 1";
                } elseif ($value === 'nein') {
                $where[] = "coorflag = 0";
                }
            }

        return $where;
        }

    private function mapRow(array $row, string $username): array
        {
        $row = array_change_key_case($row, CASE_LOWER);

        // Standard field mapping from registry
        $mapped = [];
        foreach ($this->getFieldMap() as $frontendKey => $dbCol) {
            $mapped[$frontendKey] = $row[$dbCol] ?? '';
            }

        // Computed field: status label
        $processId = $row['processid'] ?? '';
        $statusId = $row['status'] ?? '';
        $statusLabel = '';

        if ($statusId === 'completed') {
            $statusLabel = 'Beendet';
            } else if ($statusId === 'rest') {
            $eskalationDate = $row['eskalation'] ?? '';
            if (!empty($eskalationDate)) {
                $eskalation = strtotime($eskalationDate);
                $today = strtotime('today');
                if ($eskalation <= $today) {
                    $statusLabel = 'Faellig';
                    } else {
                    $statusLabel = 'Nicht Faellig';
                    }
                } else {
                $statusLabel = 'Nicht Faellig';
                }
            } else {
            $statusLabel = $statusId;
            }

        $mapped['historyLink'] = $this->buildTrackingLink($processId, $username);
        $mapped['status'] = $statusLabel;
        $mapped['invoice'] = $row['dokumentid'] ?? '';
        $mapped['protocol'] = $row['dokumentid'] ?? '';

        // Kostenuebernahme: 1 → Ja, 0/null → Nein
        $mapped['kostenuebernahme'] = (($row['kostenuebernahme'] ?? 0) == 1) ? 'Ja' : 'Nein';

        // Legacy duplicate keys (German filter keys mapped to same DB values)
        $mapped['kreditor'] = $row['kredname'] ?? '';
        $mapped['weiterbelasten'] = $row['berechenbar'] ?? '';
        $mapped['rolle'] = $row['jobfunction'] ?? '';
        $mapped['bruttobetrag'] = $row['bruttobetrag'] ?? '';
        $mapped['dokumentId'] = $row['dokumentid'] ?? '';
        $mapped['bearbeiter'] = $row['fullname'] ?? '';
        $mapped['rechnungsnummer'] = $row['rechnungsnummer'] ?? '';
        $mapped['gesellschaft'] = $row['mandantname'] ?? '';
        $mapped['fonds'] = $row['fond_abkuerzung'] ?? '';
        $mapped['schritt'] = $row['steplabel'] ?? '';
        $mapped['laufzeit'] = $row['runtime'] ?? '';
        $mapped['coor'] = $row['coorflag'] ?? ($row['coor_orderid'] ?? '');
        $mapped['rechnungsdatum'] = $row['rechnungsdatum'] ?? '';

        return $mapped;
        }

    private function buildTrackingLink(string $processId, string $username): string
        {
        if ($processId === '' || $username === '') {
            return '';
            }

        $passphrase = '6unYY_z[&%z-S,t2';
        $jrKey = md5($processId . $passphrase . strtolower($username));

        return
            'https://jobrouter.empira-invest.com/jobrouter/index.php'
            . '?cmd=Tracking_ShowTracking'
            . '&jrprocessid=' . urlencode($processId)
            . '&display=popup'
            . '&jrkey=' . $jrKey;
        }
    }

Query::execute();
