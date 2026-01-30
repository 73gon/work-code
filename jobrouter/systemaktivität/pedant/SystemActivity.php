<?php
class pedantSystemActivity extends AbstractSystemActivityAPI
    {
    // Constants
    private const VALID_FLAGS = ['normal', 'check_extraction', 'skip_review', 'force_skip'];
    private const MAX_ATTACHMENTS = 3;
    private const DEFAULT_MAX_FILE_SIZE_MB = 20;
    private const SUCCESS_HTTP_CODES = [200, 201];
    private const RETRY_HTTP_CODES = [404, 500, 502, 503, 0];
    private const FALSE_STATES = ['processing', 'failed', 'uploaded', ''];
    private const VALID_INVOICE_STATUSES = ['reviewed', 'exported', 'rejected', 'archived'];

    // Properties
    private string $recipientOutputFileName = "pedantRecipientOutput.csv";
    private string $vendorOutputFileName = "pedantVendorOutput.csv";
    private string $costCenterOutputFileName = "pedantCostCenterOutput.csv";
    private string $demoURL = "https://api.demo.pedant.ai";
    private string $productiveURL = "https://api.pedant.ai";
    private string $entityURL = "https://entity.api.pedant.ai";
    private int $maxFileSize = 20;

    // Cached input parameters
    private ?bool $isDemo = null;
    private ?string $apiKey = null;

    /**
     * Makes an API request using cURL.
     *
     * @param string $url The URL to request.
     * @param string $method HTTP method (GET, POST, etc.).
     * @param array|null $postFields POST fields for the request.
     * @param array $headers HTTP headers.
     * @return array{response: string, httpCode: int} The response and HTTP code.
     * @throws JobRouterException If the request fails.
     */
    private function makeApiRequest(string $url, string $method = 'GET', ?array $postFields = null, array $headers = []): array
        {
        $curl = curl_init();
        if ($curl === false) {
            throw new JobRouterException('Failed to initialize cURL');
            }

        $defaultHeaders = ['X-API-KEY: ' . $this->getApiKey()];
        $allHeaders = array_merge($defaultHeaders, $headers);

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $allHeaders,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0
        ];

        if ($postFields !== null) {
            $options[CURLOPT_POSTFIELDS] = $postFields;
            }

        curl_setopt_array($curl, $options);
        $response = curl_exec($curl);

        if ($response === false) {
            $curlError = curl_error($curl);
            $curlErrno = curl_errno($curl);
            curl_close($curl);
            $this->logError('cURL request failed', null, ['url' => $url, 'curl_error' => $curlError, 'curl_errno' => $curlErrno]);
            throw new JobRouterException('cURL request failed: ' . $curlError);
            }

        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        return ['response' => $response, 'httpCode' => $httpCode];
        }

    /**
     * Gets the cached API key or resolves it.
     */
    private function getApiKey(): string
        {
        if ($this->apiKey === null) {
            $this->apiKey = $this->resolveInputParameter('api_key') ?? '';
            }
        return $this->apiKey;
        }

    /**
     * Gets whether demo mode is enabled (cached).
     */
    private function isDemo(): bool
        {
        if ($this->isDemo === null) {
            $this->isDemo = $this->resolveInputParameter('demo') == '1';
            }
        return $this->isDemo;
        }

    /**
     * Gets the base API URL based on demo mode.
     */
    private function getBaseUrl(): string
        {
        return $this->isDemo() ? $this->demoURL : $this->productiveURL;
        }

    /**
     * Gets the entity API URL based on demo mode.
     */
    private function getEntityUrl(): string
        {
        return $this->isDemo() ? $this->demoURL : $this->entityURL;
        }

    /**
     * Logs an error message with context information.
     *
     * @param string $message The error message to log.
     * @param Exception|null $exception Optional exception for additional context.
     * @param array $context Optional additional context data.
     */
    private function logError(string $message, ?Exception $exception = null, array $context = []): void
        {
        $logMessage = '[Pedant] ' . $message;
        if ($exception) {
            $logMessage .= ' | Exception: ' . $exception->getMessage();
            $logMessage .= ' | File: ' . $exception->getFile() . ':' . $exception->getLine();
            }
        if (!empty($context)) {
            $logMessage .= ' | Context: ' . json_encode($context);
            }
        error_log($logMessage);
        }

    public function getActivityName()
        {
        return 'Pedant';
        }


    public function getActivityDescription()
        {
        return READ_DESC;
        }


    public function getDialogXml()
        {
        return file_get_contents(__DIR__ . '/dialog.xml');
        }

    protected function pedant(): void
        {
        try {
            $this->maxFileSize = $this->resolveInputParameter('maxFileSize') ?: self::DEFAULT_MAX_FILE_SIZE_MB;
            $this->setResubmission($this->resolveInputParameter('new') ? 17520 : $this->resolveInputParameter('intervalOld'), $this->resolveInputParameter('new') ? 'h' : 'm');

            if (!date_default_timezone_get()) {
                date_default_timezone_set('Europe/Berlin');
                }

            if (!$this->getSystemActivityVar('UPLOADCOUNTER')) {
                $this->setSystemActivityVar('UPLOADCOUNTER', 0);
                }

            if ($this->getSystemActivityVar('FILEID')) {
                $this->checkFile();
                }

            if (!$this->getSystemActivityVar('FILEID')) {
                $this->uploadFile();
                }
            } catch (JobRouterException $e) {
            $this->logError('Pedant processing failed', $e);
            throw $e;
            } catch (Exception $e) {
            $this->logError('Unexpected error in pedant method', $e);
            throw new JobRouterException('Pedant processing error: ' . $e->getMessage());
            }
        }

    protected function importVendorCSV(): void
        {
        try {
            $this->importVendor();
            $this->markActivityAsCompleted();
            } catch (JobRouterException $e) {
            $this->logError('Import vendor CSV failed', $e);
            throw $e;
            } catch (Exception $e) {
            $this->logError('Unexpected error in importVendorCSV', $e);
            throw new JobRouterException('Vendor CSV import error: ' . $e->getMessage());
            }
        }

    protected function importRecipientCSV(): void
        {
        try {
            $this->importRecipient();
            $this->markActivityAsCompleted();
            } catch (JobRouterException $e) {
            $this->logError('Import recipient CSV failed', $e);
            throw $e;
            } catch (Exception $e) {
            $this->logError('Unexpected error in importRecipientCSV', $e);
            throw new JobRouterException('Recipient CSV import error: ' . $e->getMessage());
            }
        }

    protected function importCostCenterCSV(): void
        {
        try {
            $this->importCostCenter();
            $this->markActivityAsCompleted();
            } catch (JobRouterException $e) {
            $this->logError('Import cost center CSV failed', $e);
            throw $e;
            } catch (Exception $e) {
            $this->logError('Unexpected error in importCostCenterCSV', $e);
            throw new JobRouterException('Cost center CSV import error: ' . $e->getMessage());
            }
        }

    protected function fetchData(): void
        {
        try {
            $this->markActivityAsPending();

            $interval = $this->resolveInputParameter('interval');
            $worktime = $this->resolveInputParameter('worktime');
            $weekend = $this->resolveInputParameter('weekend');

            list($startTime, $endTime) = array_map('intval', explode(',', $worktime));
            list($currentHour, $currentDayOfWeek) = [(int) (new DateTime())->format('G'), (int) (new DateTime())->format('w')];

            if ($weekend) {
                if ($currentHour >= $startTime && $currentHour < $endTime) {
                    $this->setResubmission($interval, 'm');
                    } else {
                    $hoursToStart = ($currentHour < $startTime) ? $startTime - $currentHour : 24 - $currentHour + $startTime;
                    $this->setResubmission($hoursToStart, 'h');
                    }
                } else {
                if ($currentDayOfWeek >= 1 && $currentDayOfWeek <= 5) {
                    if ($currentHour >= $startTime && $currentHour < $endTime) {
                        $this->setResubmission($interval, 'm');
                        } else {
                        $hoursToStart = ($currentHour < $startTime) ? $startTime - $currentHour : 24 - $currentHour + $startTime;
                        $this->setResubmission($hoursToStart, 'h');
                        }
                    } else {
                    $hoursToStart = ($currentHour < $startTime) ? $startTime - $currentHour : 24 - $currentHour + $startTime;
                    if ($currentDayOfWeek == 6) {
                        $hoursToStart += 24;
                        }
                    $this->setResubmission($hoursToStart, 'h');
                    }
                }
            $this->fetchInvoices();
            } catch (JobRouterException $e) {
            $this->logError('Fetch data failed', $e);
            throw $e;
            } catch (Exception $e) {
            $this->logError('Unexpected error in fetchData', $e);
            throw new JobRouterException('Fetch data error: ' . $e->getMessage());
            }
        }


    protected function uploadFile(): void
        {
        try {
            $file = $this->getUploadPath() . $this->resolveInputParameter('inputFile');

            if (!file_exists($file)) {
                throw new JobRouterException('Upload file does not exist: ' . $file);
                }

            $fileExtension = pathinfo($file, PATHINFO_EXTENSION);
            $fileSizeB = filesize($file);

            if ($fileSizeB === false) {
                throw new JobRouterException('Failed to get file size for: ' . $file);
                }

            $fileSizeMB = $fileSizeB / (1024 * 1024);
            if ($fileSizeMB > $this->maxFileSize) {
                throw new JobRouterException("File size exceeds the maximum limit of $this->maxFileSize MB. Actual size: $fileSizeMB MB.");
                }

            $baseUrl = $this->getBaseUrl();
            $url = $baseUrl . (strtolower($fileExtension) == 'xml' ? "/v2/external/documents/invoices/upload" : ($this->resolveInputParameter('zugferd') == '1' ? "/v1/external/documents/invoices/upload" : "/v2/external/documents/invoices/upload"));

            $flag = $this->resolveInputParameter('flag');
            if (strtolower($fileExtension) == 'xml') {
                $flagXML = $this->resolveInputParameter('flagXML');
                if (!empty($flagXML)) {
                    $flag = $flagXML;
                    if (!in_array($flag, self::VALID_FLAGS)) {
                        throw new JobRouterException('Invalid input parameter value for FLAGXML: ' . $flag);
                        }
                    }
                }

            if (!in_array($flag, self::VALID_FLAGS)) {
                throw new JobRouterException('Invalid input parameter value for FLAG: ' . $flag);
                }

            $action = $flag;

            $responseData = $this->makeApiRequest(
                $url,
                'POST',
                [
                    'file' => new CURLFILE($file),
                    'recipientInternalNumber' => $this->resolveInputParameter('internalNumber'),
                    'action' => $action,
                    'note' => $this->resolveInputParameter('note'),
                ]
            );

            $response = $responseData['response'];
            $httpcode = $responseData['httpCode'];

            $data = json_decode($response, TRUE);

            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                $this->logError('Failed to parse upload response JSON', null, ['json_error' => json_last_error_msg(), 'response' => substr($response, 0, 500)]);
                throw new JobRouterException('Failed to parse API response: ' . json_last_error_msg());
                }

            $maxCounter = $this->resolveInputParameter('maxCounter');
            $counter = $this->getSystemActivityVar('UPLOADCOUNTER');

            if ($counter >= $maxCounter && !in_array($httpcode, array_merge(self::SUCCESS_HTTP_CODES, self::RETRY_HTTP_CODES))) {
                $this->setSystemActivityVar('UPLOADCOUNTER', 0);
                $this->logError('Upload failed after max retries', null, ['counter' => $counter, 'httpcode' => $httpcode]);
                throw new JobRouterException('Error occurred during upload after maximum retries (' . $counter . '). HTTP Error Code: ' . $httpcode);
                } else {
                $this->setSystemActivityVar('UPLOADCOUNTER', ++$counter);
                }

            try {
                $this->storeOutputParameter('counterSummary', "Upload attempts: {$counter}, HTTP Code: {$httpcode}");
                } catch (Exception $e) {
                $this->logError('Failed to store counterSummary output parameter', $e);
                }

            if (!isset($data['files'][0])) {
                $this->logError('Invalid upload response structure', null, ['response' => $response]);
                throw new JobRouterException('Invalid API response: missing files data');
                }

            $fileId = $data['files'][0]['fileId'] ?? null;
            $invoiceId = $data['files'][0]['invoiceId'] ?? null;
            $type = $data['files'][0]['type'] ?? null;

            if (empty($fileId)) {
                throw new JobRouterException('Upload response missing fileId');
                }

            $this->storeOutputParameter('fileID', $fileId);
            $this->storeOutputParameter('invoiceID', $invoiceId);
            $this->setSystemActivityVar('FILEID', $fileId);
            $this->setSystemActivityVar('FETCHCOUNTER', 0);
            $this->setSystemActivityVar('TYPE', $type);
            $this->setSystemActivityVar('COUNTER404', 0);
            } catch (JobRouterException $e) {
            throw $e;
            } catch (Exception $e) {
            $this->logError('Unexpected error in uploadFile', $e);
            throw new JobRouterException('Upload error: ' . $e->getMessage());
            }
        }
    protected function checkFile(): void
        {
        try {
            if (!empty($this->resolveInputParameter('vendorTable'))) {
                $this->importVendor();
                }

            $baseURL = $this->getBaseUrl();
            $fileId = $this->getSystemActivityVar('FILEID');
            $type = $this->getSystemActivityVar('TYPE'); // Keep original type (e_invoice or invoice)
            $urlType = $type == 'e_invoice' ? 'e-invoices' : 'invoices';
            $url = "$baseURL/v1/external/documents/$urlType?" . ($urlType == 'e-invoices' ? "documentId=$fileId" : "fileId=$fileId") . "&auditTrail=true";
            $maxCounter = $this->resolveInputParameter('maxCounter');

            $responseData = $this->makeApiRequest($url, 'GET');
            $response = $responseData['response'];
            $httpcode = $responseData['httpCode'];

            $counter = $this->getSystemActivityVar('FETCHCOUNTER');
            $counter404 = $this->getSystemActivityVar('COUNTER404');
            $resubTime = $this->resolveInputParameter('intervalOld');

            if ($counter >= $maxCounter && !in_array($httpcode, array_merge(self::SUCCESS_HTTP_CODES, self::RETRY_HTTP_CODES))) {
                $this->setSystemActivityVar('FETCHCOUNTER', 0);
                $this->logError('File extraction failed after max retries', null, ['counter' => $counter, 'httpcode' => $httpcode]);
                throw new JobRouterException('Error occurred during file extraction after maximum retries (' . $counter . '). HTTP Error Code: ' . $httpcode);
                } else {
                if ($httpcode == 404 && (300 / $resubTime) > $counter404) {
                    $this->setSystemActivityVar('COUNTER404', ++$counter404);
                    return;
                    } elseif (!in_array($httpcode, self::SUCCESS_HTTP_CODES)) {
                    $this->setSystemActivityVar('FETCHCOUNTER', ++$counter);
                    return;
                    }
                }

            try {
                $this->storeOutputParameter('counterSummary', "Fetch attempts: {$counter}, HTTP Code: {$httpcode}");
                } catch (Exception $e) {
                $this->logError('Failed to store counterSummary output parameter', $e);
                }

            $data = json_decode($response, TRUE);

            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                $this->logError('Failed to parse checkFile response JSON', null, ['json_error' => json_last_error_msg()]);
                throw new JobRouterException('Failed to parse API response: ' . json_last_error_msg());
                }

            if (!isset($data['data'][0])) {
                $this->logError('Invalid checkFile response structure', null, ['response' => substr($response, 0, 500)]);
                throw new JobRouterException('Invalid API response: missing data');
                }

            $dataItem = $data["data"][0];
            $check = false;

            try {
                $this->storeOutputParameter('tempJSON', json_encode($data));
                } catch (Exception $e) {
                $this->logError('Failed to store tempJSON output parameter', $e);
                }

            if (in_array($dataItem["status"], self::FALSE_STATES) === false) {
                $check = true;
                $this->storeList($data);
                }

            if ($check === true) {
                if ($type == "e_invoice") {
                    $pdfPath = $this->getSystemActivityVar('PDFPATH');
                    if ($pdfPath && file_exists($pdfPath)) {
                        try {
                            unlink($pdfPath);
                            } catch (Exception $e) {
                            $this->logError('Failed to delete PDF file', $e, ['path' => $pdfPath]);
                            }
                        }

                    $reportPath = $this->getSystemActivityVar('REPORTPATH');
                    if ($reportPath && file_exists($reportPath)) {
                        try {
                            unlink($reportPath);
                            } catch (Exception $e) {
                            $this->logError('Failed to delete report file', $e, ['path' => $reportPath]);
                            }
                        }

                    $index = 0;
                    while (true) {
                        $attachmentPath = $this->getSystemActivityVar('ATTACHMENTPATH' . $index);
                        if (!$attachmentPath) {
                            break;
                            }
                        if (file_exists($attachmentPath)) {
                            try {
                                unlink($attachmentPath);
                                } catch (Exception $e) {
                                $this->logError('Failed to delete attachment file', $e, ['path' => $attachmentPath]);
                                }
                            }
                        $index++;
                        }
                    }

                // Clean up audit trail CSV
                $auditTrailPath = $this->getSystemActivityVar('AUDITTRAILPATH');
                if ($auditTrailPath && file_exists($auditTrailPath)) {
                    try {
                        unlink($auditTrailPath);
                        } catch (Exception $e) {
                        $this->logError('Failed to delete audit trail CSV', $e, ['path' => $auditTrailPath]);
                        }
                    }

                $this->setResubmission(1, "s");
                $this->markActivityAsCompleted();
                }
            } catch (JobRouterException $e) {
            throw $e;
            } catch (Exception $e) {
            $this->logError('Unexpected error in checkFile', $e);
            throw new JobRouterException('Check file error: ' . $e->getMessage());
            }
        }

    protected function importVendor(): void
        {
        $csvFilePath = null;
        try {
            $table = $this->resolveInputParameter('vendorTable');
            $listfields = $this->resolveInputParameterListValues('importVendor');
            $fields = ['internalVendorNumber', 'vendorProfileName', 'company', 'street', 'zipCode', 'city', 'country', 'iban', 'taxNumber', 'vatNumber', 'recipientNumber', 'kvk', 'currency', 'blocked', 'sortCode', 'accountNumber'];

            $list = array();
            foreach ($listfields as $listindex => $listvalue) {
                $list[$listindex] = $listvalue;
                }
            ksort($list);

            if (empty($table)) {
                return;
                }

            $JobDB = $this->getJobDB();

            $temp = "SELECT ";
            $lastKey = null;
            foreach ($list as $listindex => $listvalue) {
                if (!empty($listvalue)) {
                    $lastKey = $listindex;
                    }
                }

            foreach ($list as $listindex => $listvalue) {
                if (!empty($listvalue)) {
                    $temp .= $listvalue . " AS " . $fields[$listindex - 1];
                    if ($listindex !== $lastKey) {
                        $temp .= ", ";
                        }
                    }
                }

            $temp .= " FROM " . $table;
            $result = $JobDB->query($temp);
            $payloads = [];

            while ($row = $JobDB->fetchRow($result)) {
                $data = [];
                foreach ($fields as $index => $field) {
                    $value = isset($row[$fields[$index]]) ? $row[$fields[$index]] : '';

                    if ($field === 'blocked') {
                        $truthy = ['yes', 'true', 'ja', '1'];

                        $value = $row[$fields[$index]] ?? '';
                        $normalized = is_string($value) ? strtolower(trim($value)) : strval($value);

                        $data[$field] = in_array($normalized, $truthy) ? 'TRUE' : 'FALSE';
                        } else {
                        $data[$field] = !empty($value) ? $value : '';
                        }
                    }

                $payloads[] = $data;
                }

            $csvData = [];
            $csvData[] = $fields;

            foreach ($payloads as $payload) {
                $rowData = [];
                foreach ($fields as $field) {
                    $rowData[] = isset($payload[$field]) ? $payload[$field] : '';
                    }
                $csvData[] = $rowData;
                }

            $csvFilePath = __DIR__ . '/' . $this->vendorOutputFileName;
            $csvFile = fopen($csvFilePath, 'w');

            if ($csvFile === false) {
                throw new JobRouterException('Failed to create vendor CSV file: ' . $csvFilePath);
                }

            foreach ($csvData as $row) {
                fputcsv($csvFile, $row);
                }

            fclose($csvFile);

            $url = $this->getEntityUrl() . "/v2/external/entities/vendors/import";

            $responseData = $this->makeApiRequest(
                $url,
                'POST',
                [
                    'internalNumber' => 'internalVendorNumber',
                    'profileName' => 'vendorProfileName',
                    'name' => 'company',
                    'street' => 'street',
                    'zipCode' => 'zipCode',
                    'city' => 'city',
                    'country' => 'country',
                    'iban' => 'iban',
                    'taxNumber' => 'taxNumber',
                    'vatNumber' => 'vatNumber',
                    'recipientNumber' => 'recipientNumber',
                    'kvk' => 'kvk',
                    'currency' => 'currency',
                    'blocked' => 'blocked',
                    'sortCode' => 'sortCode',
                    'accountNumber' => 'accountNumber',
                    'file' => new CURLFILE($csvFilePath)
                ]
            );

            $response = $responseData['response'];
            $httpcode = $responseData['httpCode'];
            if (!in_array($httpcode, self::SUCCESS_HTTP_CODES)) {
                $this->logError('Vendor import failed', null, ['httpcode' => $httpcode, 'response' => substr($response, 0, 500)]);
                throw new JobRouterException('Error occurred during vendor update. HTTP Error Code: ' . $httpcode);
                }

            if (file_exists($csvFilePath)) {
                unlink($csvFilePath);
                }
            } catch (JobRouterException $e) {
            if ($csvFilePath && file_exists($csvFilePath)) {
                unlink($csvFilePath);
                }
            throw $e;
            } catch (Exception $e) {
            if ($csvFilePath && file_exists($csvFilePath)) {
                unlink($csvFilePath);
                }
            $this->logError('Unexpected error in importVendor', $e);
            throw new JobRouterException('Vendor import error: ' . $e->getMessage());
            }
        }

    protected function importRecipient(): void
        {
        $csvFilePath = null;
        try {
            $table = $this->resolveInputParameter('recipientTable');
            $listfields = $this->resolveInputParameterListValues('importRecipient');
            $fields = ['internalRecipientNumber', 'recipientProfileName', 'country', 'city', 'zipCode', 'street', 'company', 'vatNumber', 'synonyms'];

            $list = array();
            foreach ($listfields as $listindex => $listvalue) {
                $list[$listindex] = $listvalue;
                }
            ksort($list);

            if (empty($table)) {
                return;
                }

            $JobDB = $this->getJobDB();

            $temp = "SELECT ";
            $lastKey = null;
            foreach ($list as $listindex => $listvalue) {
                if (!empty($listvalue)) {
                    $lastKey = $listindex;
                    }
                }

            foreach ($list as $listindex => $listvalue) {
                if (!empty($listvalue)) {
                    $temp .= $listvalue . " AS " . $fields[$listindex - 1];
                    if ($listindex !== $lastKey) {
                        $temp .= ", ";
                        }
                    }
                }

            $temp .= " FROM " . $table;
            $result = $JobDB->query($temp);
            $payloads = [];

            while ($row = $JobDB->fetchRow($result)) {
                $data = [];
                foreach ($fields as $index => $field) {
                    $value = isset($row[$fields[$index]]) ? $row[$fields[$index]] : '';
                    $data[$field] = !empty($value) ? $value : '';
                    }
                $payloads[] = $data;
                }

            $csvData = [$fields];

            foreach ($payloads as $payload) {
                $rowData = [];
                foreach ($fields as $field) {
                    $rowData[] = isset($payload[$field]) ? $payload[$field] : '';
                    }
                $csvData[] = $rowData;
                }

            $csvFilePath = __DIR__ . '/' . $this->recipientOutputFileName;
            $csvFile = fopen($csvFilePath, 'w');

            if ($csvFile === false) {
                throw new JobRouterException('Failed to create recipient CSV file: ' . $csvFilePath);
                }

            foreach ($csvData as $row) {
                fputcsv($csvFile, $row);
                }

            fclose($csvFile);

            $url = $this->getEntityUrl() . "/v2/external/entities/recipient-groups/import";

            $responseData = $this->makeApiRequest(
                $url,
                'POST',
                [
                    'internalNumber' => 'internalRecipientNumber',
                    'profileName' => 'recipientProfileName',
                    'country' => 'country',
                    'city' => 'city',
                    'zipCode' => 'zipCode',
                    'street' => 'street',
                    'name' => 'company',
                    'vatNumber' => 'vatNumber',
                    'synonyms' => 'synonyms',
                    'file' => new CURLFILE($csvFilePath)
                ]
            );

            $response = $responseData['response'];
            $httpcode = $responseData['httpCode'];
            if (!in_array($httpcode, self::SUCCESS_HTTP_CODES)) {
                $this->logError('Recipient import failed', null, ['httpcode' => $httpcode, 'response' => substr($response, 0, 500)]);
                throw new JobRouterException('Error occurred during recipient update. HTTP Error Code: ' . $httpcode);
                }

            if (file_exists($csvFilePath)) {
                unlink($csvFilePath);
                }
            } catch (JobRouterException $e) {
            if ($csvFilePath && file_exists($csvFilePath)) {
                unlink($csvFilePath);
                }
            throw $e;
            } catch (Exception $e) {
            if ($csvFilePath && file_exists($csvFilePath)) {
                unlink($csvFilePath);
                }
            $this->logError('Unexpected error in importRecipient', $e);
            throw new JobRouterException('Recipient import error: ' . $e->getMessage());
            }
        }

    protected function importCostCenter(): void
        {
        $csvFilePath = null;
        try {
            $table = $this->resolveInputParameter('costCenterTable');
            $listfields = $this->resolveInputParameterListValues('importCostCenter');
            $fields = ['internalCostCenterNumber', 'costCenterProfileName', 'recipientNumber'];

            $list = array();
            foreach ($listfields as $listindex => $listvalue) {
                $list[$listindex] = $listvalue;
                }
            ksort($list);

            if (empty($table)) {
                return;
                }

            $JobDB = $this->getJobDB();

            $temp = "SELECT ";
            $lastKey = null;
            foreach ($list as $listindex => $listvalue) {
                if (!empty($listvalue)) {
                    $lastKey = $listindex;
                    }
                }

            foreach ($list as $listindex => $listvalue) {
                if (!empty($listvalue)) {
                    $temp .= $listvalue . " AS " . $fields[$listindex - 1];
                    if ($listindex !== $lastKey) {
                        $temp .= ", ";
                        }
                    }
                }

            $temp .= " FROM " . $table;
            $result = $JobDB->query($temp);
            $payloads = [];

            while ($row = $JobDB->fetchRow($result)) {
                $data = [];
                foreach ($fields as $index => $field) {
                    $value = isset($row[$fields[$index]]) ? $row[$fields[$index]] : '';
                    $data[$field] = !empty($value) ? $value : '';
                    }
                $payloads[] = $data;
                }

            $csvData = [$fields];

            foreach ($payloads as $payload) {
                $rowData = [];
                foreach ($fields as $field) {
                    $rowData[] = isset($payload[$field]) ? $payload[$field] : '';
                    }
                $csvData[] = $rowData;
                }

            $csvFilePath = __DIR__ . '/' . $this->costCenterOutputFileName;
            $csvFile = fopen($csvFilePath, 'w');

            if ($csvFile === false) {
                throw new JobRouterException('Failed to create cost center CSV file: ' . $csvFilePath);
                }

            foreach ($csvData as $row) {
                fputcsv($csvFile, $row);
                }

            fclose($csvFile);

            $url = $this->getEntityUrl() . "/v1/external/entities/cost-centers/import";

            $responseData = $this->makeApiRequest(
                $url,
                'POST',
                [
                    'internalNumber' => 'internalCostCenterNumber',
                    'name' => 'costCenterProfileName',
                    'recipientNumber' => 'recipientNumber',
                    'file' => new CURLFILE($csvFilePath)
                ]
            );

            $response = $responseData['response'];
            $httpcode = $responseData['httpCode'];
            if (!in_array($httpcode, self::SUCCESS_HTTP_CODES)) {
                $this->logError('Cost center import failed', null, ['httpcode' => $httpcode, 'response' => substr($response, 0, 500)]);
                throw new JobRouterException('Error occurred during costCenter update. HTTP Error Code: ' . $httpcode);
                }

            if (file_exists($csvFilePath)) {
                unlink($csvFilePath);
                }
            } catch (JobRouterException $e) {
            if ($csvFilePath && file_exists($csvFilePath)) {
                unlink($csvFilePath);
                }
            throw $e;
            } catch (Exception $e) {
            if ($csvFilePath && file_exists($csvFilePath)) {
                unlink($csvFilePath);
                }
            $this->logError('Unexpected error in importCostCenter', $e);
            throw new JobRouterException('Cost center import error: ' . $e->getMessage());
            }
        }

    /**
     * Fetches invoices from the Pedant API and updates the resubmission date in the database.
     *
     * @throws Exception If the database type is unsupported or if the query fails.
     */
    protected function fetchInvoices(): void
        {
        try {
            $invoice_status = $this->resolveInputParameter('invoice_status');
            if (empty($invoice_status)) {
                $invoice_status = 'reviewed';
                }
            $statusValues = [];
            if (!empty($invoice_status)) {
                $parts = array_map('trim', explode(',', $invoice_status));
                $parts = array_values(array_filter($parts, static fn($status) => $status !== ''));

                foreach ($parts as $status) {
                    if (!in_array($status, self::VALID_INVOICE_STATUSES, true)) {
                        $this->logError('Invalid invoice status', null, ['status' => $status]);
                        throw new JobRouterException('Invalid invoice status: ' . $status);
                        }
                    $statusValues[] = $status;
                    }
                }

            $statusQuery = '';
            if (!empty($statusValues)) {
                $queryParts = [];
                foreach ($statusValues as $status) {
                    $queryParts[] = 'status=' . rawurlencode($status);
                    }
                $statusQuery = '?' . implode('&', $queryParts);
                }

            $baseURL = $this->getBaseUrl();
            $url_invoice = "$baseURL/v1/external/documents/invoices/to-export" . $statusQuery;
            $url_einvoice = "$baseURL/v1/external/documents/e-invoices/to-export" . $statusQuery;

            foreach ([$url_invoice, $url_einvoice] as $url) {
                try {
                    $responseData = $this->makeApiRequest($url, 'GET');
                    $response = $responseData['response'];
                    } catch (JobRouterException $e) {
                    $this->logError('cURL fetch invoices failed', $e, ['url' => $url]);
                    continue; // Continue to next URL instead of failing completely
                    }

                $data = json_decode($response, TRUE);

                if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                    $this->logError('Failed to parse fetch invoices response', null, ['json_error' => json_last_error_msg(), 'url' => $url]);
                    continue;
                    }

                if (!isset($data['data']) || !is_array($data['data'])) {
                    $this->logError('Invalid fetch invoices response structure', null, ['url' => $url]);
                    continue;
                    }

                $ids = $data["data"];
                $table_head = $this->resolveInputParameter('table_head');
                $stepID = $this->resolveInputParameter('stepID');
                $fileid = $this->resolveInputParameter('fileid');

                $currentTime = new DateTime();
                $currentTime->modify('+10 seconds');
                $formattedTime = $currentTime->format('Y-m-d H:i:s');

                foreach ($ids as $id) {
                    try {
                        $dbType = $this->getDatabaseType();
                        if ($dbType === "MySQL") {
                            $query = "
                        UPDATE jrincidents j
                        JOIN $table_head t ON t.step_id = j.process_step_id
                        SET j.resubmission_date = '$formattedTime'
                        WHERE t.step = $stepID AND t.$fileid = '$id';
                        ";
                            } elseif ($dbType === "MSSQL") {
                            $query = "
                        UPDATE j
                        SET j.resubmission_date = '$formattedTime'
                        FROM jrincidents AS j
                        JOIN $table_head AS t ON t.step_id = j.process_step_id
                        WHERE t.step = $stepID AND t.$fileid = '$id';
                        ";
                            } else {
                            $this->logError('Unsupported database type', null, ['dbType' => $dbType]);
                            throw new JobRouterException("Unsupported database type: " . $dbType);
                            }

                        $jobDB = $this->getJobDB();
                        $jobDB->exec($query);
                        } catch (Exception $e) {
                        $this->logError('Failed to update resubmission date for invoice', $e, ['id' => $id]);
                        // Continue processing other IDs
                        }
                    }
                }

            } catch (JobRouterException $e) {
            throw $e;
            } catch (Exception $e) {
            $this->logError('Unexpected error in fetchInvoices', $e);
            throw new JobRouterException('Fetch invoices error: ' . $e->getMessage());
            }
        }

    /**
     * Determines the database type based on the version query.
     *
     * @return string The database type, either "MySQL" or "MSSQL".
     * @throws Exception If the database type cannot be detected.
     */
    public function getDatabaseType(): string
        {
        $jobDB = $this->getJobDB();
        try {
            $result = $jobDB->query("SELECT VERSION()");
            $row = $jobDB->fetchAll($result);
            if (is_string($row[0]["VERSION()"])) {
                return "MySQL";
                }
            } catch (Exception $e) {
            $this->logError('MySQL version query failed', $e);
            }

        try {
            $result = $jobDB->query("SELECT @@VERSION");
            $row = $jobDB->fetchAll($result);
            if (is_string(reset($row[0]))) {
                return "MSSQL";
                }
            } catch (Exception $e) {
            $this->logError('MSSQL version query failed', $e);
            }

        $this->logError('Database type could not be detected');
        throw new JobRouterException("Database could not be detected");
        }

    /**
     * Converts an audit trail array to CSV format and saves it to a file.
     *
     * @param array $auditTrail The audit trail data array.
     * @param string $savePath The path to save the CSV file.
     * @param string $fileName The base name for the CSV file.
     * @return string The full path to the saved CSV file.
     */
    protected function convertAuditTrailToCSV(array $auditTrail, string $savePath, string $fileName): string
        {
        try {
            // Remove file extension from fileName
            $fileNameWithoutExtension = pathinfo($fileName, PATHINFO_FILENAME);

            $csvFilePath = $savePath . "/" . $fileNameWithoutExtension . "_AUDITTRAIL.csv";
            $csvFile = fopen($csvFilePath, 'w');

            if ($csvFile === false) {
                $this->logError('Failed to create audit trail CSV file', null, ['path' => $csvFilePath]);
                return '';
                }

            // CSV headers
            $headers = [
                'actionBy',
                'userName',
                'userId',
                'type',
                'subType',
                'field',
                'comment',
                'oldValue',
                'newValue',
                'createdAt',
                'updatedAt'
            ];
            fputcsv($csvFile, $headers);

            // Write each audit trail entry
            foreach ($auditTrail as $entry) {
                $oldValue = isset($entry['updates']['oldValue']) ? $entry['updates']['oldValue'] : '';
                $newValue = isset($entry['updates']['newValue']) ? $entry['updates']['newValue'] : '';

                // Handle complex objects in values by JSON encoding them
                if (is_array($oldValue) || is_object($oldValue)) {
                    $oldValue = json_encode($oldValue);
                    }
                if (is_array($newValue) || is_object($newValue)) {
                    $newValue = json_encode($newValue);
                    }

                $row = [
                    $entry['actionBy'] ?? '',
                    $entry['userName'] ?? '',
                    $entry['userId'] ?? '',
                    $entry['type'] ?? '',
                    $entry['subType'] ?? '',
                    $entry['field'] ?? '',
                    $entry['comment'] ?? '',
                    $oldValue,
                    $newValue,
                    $entry['createdAt'] ?? '',
                    $entry['updatedAt'] ?? ''
                ];
                fputcsv($csvFile, $row);
                }

            fclose($csvFile);
            return $csvFilePath;
            } catch (Exception $e) {
            $this->logError('Error converting audit trail to CSV', $e, ['savePath' => $savePath, 'fileName' => $fileName]);
            return '';
            }
        }

    /**
     * Stores the list of invoice details in the system activity.
     *
     * @param array $data The data containing invoice details.
     */
    public function storeList($data): void
        {
        try {
            $type = $this->getSystemActivityVar('TYPE');

            if (!isset($data['data'][0])) {
                $this->logError('Invalid data structure in storeList', null, ['data_keys' => array_keys($data)]);
                throw new JobRouterException('Invalid invoice data structure');
                }

            $dataItem = $data['data'][0];
            $eInvoiceFields = $dataItem['eInvoiceFields'] ?? [];
            $paymentInstructionsCreditTransfer = $eInvoiceFields['paymentInstructionsCreditTransfer'][0] ?? [];
            $document = $dataItem['document'] ?? [];
            $auditTrailItem = $dataItem['auditTrail'][0] ?? [];
            $taxRates = $dataItem['taxRates'] ?? [];
            $vatBreakdown = $eInvoiceFields['vatBreakdown'] ?? [];
            $recipientEntity = $dataItem['recipientEntity'] ?? [];
            $vendorEntity = $dataItem['vendorEntity'] ?? [];

            $attributes1 = $this->resolveOutputParameterListAttributes('recipientDetails'); //recipientDetails
            $values1 = ($type == "e_invoice") ? [
                'recipientCompanyName' => $eInvoiceFields['recipientName'],
                'recipientName' => $eInvoiceFields['recipientContactPersonName'],
                'recipientStreet' => $eInvoiceFields['recipientPostalAddressAddressLines'][0],
                'recipientZipCode' => $eInvoiceFields['recipientPostalAddressPostCode'],
                'recipientCity' => $eInvoiceFields['recipientPostalAddressCity'],
                'recipientCountry' => $eInvoiceFields['recipientPostalAddressCountryCode'],
                'recipientVatNumber' => $eInvoiceFields['recipientVatIdentifier'] ?? '',
                'recipientInternalNumber' => $recipientEntity['internalNumber'] ?? ''
            ] : [
                'recipientCompanyName' => $dataItem["recipientCompanyName"] ?? '',
                'recipientName' => $dataItem["recipientName"] ?? '',
                'recipientStreet' => $dataItem["recipientStreet"] ?? '',
                'recipientZipCode' => $dataItem["recipientZipCode"] ?? '',
                'recipientCity' => $dataItem["recipientCity"] ?? '',
                'recipientCountry' => $dataItem["recipientCountry"] ?? '',
                'recipientVatNumber' => $dataItem["recipientVatNumber"] ?? '',
                'recipientInternalNumber' => $dataItem["recipientEntity"]["internalNumber"] ?? ''
            ];

            foreach ($attributes1 as $attribute) {
                try {
                    $this->setTableValue($attribute['value'], $values1[$attribute['id']] ?? '');
                    } catch (Exception $e) {
                    $this->logError('Failed to set recipient table value', $e, ['attribute' => $attribute['id']]);
                    }
                }

            $attributes2 = $this->resolveOutputParameterListAttributes('vendorDetails'); //vendorDetails
            $values2 = ($type == "e_invoice") ? [
                'vendorBankNumber' => $paymentInstructionsCreditTransfer['paymentAccountIdentifierId'][0],
                'vendorVatNumber' => $eInvoiceFields['vendorVatIdentifier'],
                'vendorTaxNumber' => $eInvoiceFields['vendorTaxRegistrationIdentifier'],
                'vendorCompanyName' => $eInvoiceFields['vendorName'],
                'vendorStreet' => $eInvoiceFields['vendorPostalAddressAddressLines'][0],
                'vendorZipCode' => $eInvoiceFields['vendorPostalAddressPostCode'],
                'vendorCity' => $eInvoiceFields['vendorPostalAddressCity'],
                'vendorCountry' => $eInvoiceFields['vendorPostalAddressCountryCode'],
                'vendorEmail' => $eInvoiceFields['vendorContactEmailAddress'] ?? '',
                'vendorDeliveryPeriod' => '',
                'vendorAccountNumber' => '',
                'vendorInternalNumber' => $vendorEntity['internalNumber'] ?? ''
            ] : [
                'vendorBankNumber' => $dataItem["bankNumber"] ?? '',
                'vendorVatNumber' => $dataItem["vat"] ?? '',
                'vendorTaxNumber' => $dataItem["taxNumber"] ?? '',
                'vendorCompanyName' => $dataItem["vendorCompanyName"] ?? '',
                'vendorStreet' => $dataItem["vendorStreet"] ?? '',
                'vendorZipCode' => $dataItem["vendorZipCode"] ?? '',
                'vendorCity' => $dataItem["vendorCity"] ?? '',
                'vendorCountry' => $dataItem["vendorCountry"] ?? '',
                'vendorDeliveryPeriod' => $dataItem["deliveryPeriod"] ?? '',
                'vendorAccountNumber' => $dataItem["accountNumber"] ?? '',
                'vendorEmail' => $dataItem["vendorEmail"] ?? '',
                'vendorInternalNumber' => $dataItem["vendorEntity"]["internalNumber"] ?? '',
                'vendorCompanyRegistrationNumber' => $dataItem['KVKNumber'] ?? ''
            ];

            foreach ($attributes2 as $attribute) {
                try {
                    $this->setTableValue($attribute['value'], $values2[$attribute['id']] ?? '');
                    } catch (Exception $e) {
                    $this->logError('Failed to set vendor table value', $e, ['attribute' => $attribute['id']]);
                    }
                }

            $attributes3 = $this->resolveOutputParameterListAttributes('invoiceDetails'); //invoiceDetails

            $values3 = ($type == "e_invoice") ? [
                'taxRate1' => count($vatBreakdown) > 1 ? $vatBreakdown[0]["vatCategoryTaxableAmount"] . ";" . $vatBreakdown[0]["vatCategoryTaxAmount"] . ";" . $vatBreakdown[0]["vatCategoryRate"] : '',
                'taxRate2' => count($vatBreakdown) > 1 ? $vatBreakdown[1]["vatCategoryTaxableAmount"] . ";" . $vatBreakdown[1]["vatCategoryTaxAmount"] . ";" . $vatBreakdown[1]["vatCategoryRate"] : '',
                'taxRate3' => count($vatBreakdown) > 2 ? $vatBreakdown[2]["vatCategoryTaxableAmount"] . ";" . $vatBreakdown[2]["vatCategoryTaxAmount"] . ";" . $vatBreakdown[2]["vatCategoryRate"] : '',
                'taxRate4' => count($vatBreakdown) > 3 ? $vatBreakdown[3]["vatCategoryTaxableAmount"] . ";" . $vatBreakdown[3]["vatCategoryTaxAmount"] . ";" . $vatBreakdown[3]["vatCategoryRate"] : '',
                'taxRate5' => count($vatBreakdown) > 4 ? $vatBreakdown[4]["vatCategoryTaxableAmount"] . ";" . $vatBreakdown[4]["vatCategoryTaxAmount"] . ";" . $vatBreakdown[4]["vatCategoryRate"] : '',
                'invoiceNumber' => $eInvoiceFields['invoiceNumber'],
                'date' => date("Y-m-d", strtotime(str_replace(".", "-", $eInvoiceFields['invoiceIssueDate']))) . ' 00:00:00.000',
                'netAmount' => $eInvoiceFields['sumOfInvoiceLineNetAmount'],
                'taxAmount' => $eInvoiceFields['invoiceTotalVatAmount'],
                'grossAmount' => $eInvoiceFields['invoiceTotalAmountWithVat'],
                'taxRate' => count($vatBreakdown) == 1 ? $vatBreakdown[0]['vatCategoryRate'] : '',
                'projectNumber' => $eInvoiceFields['projectReferenceId'],
                'purchaseOrder' => '',
                'purchaseDate' => '',
                'hasDiscount' => '',
                'refund' => '',
                'discountPercentage' => '',
                'discountAmount' => '',
                'discountDate' => '',
                'invoiceType' => $eInvoiceFields['invoiceTypeCode'],
                'type' => $type,
                'note' => $document['note'],
                'status' => $dataItem['status'],
                'currency' => $eInvoiceFields['invoiceCurrencyCode'],
                'resolvedIssuesCount' => $dataItem['resolvedIssuesCount'],
                'hasProcessingIssues' => $dataItem['hasProcessingIssues'],
                'deliveryDate' => date("Y-m-d", strtotime(str_replace(".", "-", $eInvoiceFields['deliveryInformationActualDeliveryDate']))) . ' 00:00:00.000',
            ] : [
                'taxRate1' => count($taxRates) > 0 ? ($taxRates[0]["subNetAmount"] ?? '') . ";" . ($taxRates[0]["subTaxAmount"] ?? '') . ";" . ($taxRates[0]["subTaxRate"] ?? '') : '',
                'taxRate2' => count($taxRates) > 1 ? ($taxRates[1]["subNetAmount"] ?? '') . ";" . ($taxRates[1]["subTaxAmount"] ?? '') . ";" . ($taxRates[1]["subTaxRate"] ?? '') : '',
                'taxRate3' => count($taxRates) > 2 ? ($taxRates[2]["subNetAmount"] ?? '') . ";" . ($taxRates[2]["subTaxAmount"] ?? '') . ";" . ($taxRates[2]["subTaxRate"] ?? '') : '',
                'taxRate4' => count($taxRates) > 3 ? ($taxRates[3]["subNetAmount"] ?? '') . ";" . ($taxRates[3]["subTaxAmount"] ?? '') . ";" . ($taxRates[3]["subTaxRate"] ?? '') : '',
                'taxRate5' => count($taxRates) > 4 ? ($taxRates[4]["subNetAmount"] ?? '') . ";" . ($taxRates[4]["subTaxAmount"] ?? '') . ";" . ($taxRates[4]["subTaxRate"] ?? '') : '',
                'invoiceNumber' => $dataItem["invoiceNumber"] ?? '',
                'date' => date("Y-m-d", strtotime(str_replace(".", "-", $dataItem["issueDate"] ?? ''))) . ' 00:00:00.000',
                'netAmount' => $dataItem["netAmount"] ?? '',
                'taxAmount' => $dataItem["taxAmount"] ?? '',
                'grossAmount' => $dataItem["amount"] ?? '',
                'taxRate' => $dataItem["taxRate"] ?? '',
                'projectNumber' => $dataItem["projectNumber"] ?? '',
                'purchaseOrder' => $dataItem["purchaseOrder"] ?? '',
                'purchaseDate' => $dataItem["purchaseDate"] ?? '',
                'hasDiscount' => $dataItem["hasDiscount"] ?? '',
                'refund' => $dataItem["refund"] ?? '',
                'discountPercentage' => $dataItem["discountPercentage"] ?? '',
                'discountAmount' => $dataItem["discountAmount"] ?? '',
                'discountDate' => $dataItem["discountDate"] ?? '',
                'invoiceType' => $dataItem["invoiceType"] ?? '',
                'type' => $type,
                'note' => $dataItem["file"]["note"] ?? '',
                'status' => $dataItem["status"] ?? '',
                'currency' => $dataItem["currency"] ?? '',
                'resolvedIssuesCount' => $dataItem["resolvedIssuesCount"] ?? '',
                'hasProcessingIssues' => $dataItem["hasProcessingIssues"] ?? '',
                'deliveryDate' => date("Y-m-d", strtotime(str_replace(".", "-", $dataItem["deliveryDate"] ?? ''))) . ' 00:00:00.000',
            ];

            foreach ($attributes3 as $attribute) {
                try {
                    $this->setTableValue($attribute['value'], $values3[$attribute['id']] ?? '');
                    } catch (Exception $e) {
                    $this->logError('Failed to set invoice table value', $e, ['attribute' => $attribute['id']]);
                    }
                }

            $attributes4 = $this->resolveOutputParameterListAttributes('auditTrailDetails'); //auditTrailDetails
            $auditTrail = $type == "e_invoice" ? $auditTrailItem : $dataItem["auditTrail"];

            $isAutomatic = count($auditTrail) <= 2;
            $userInformationIndex = $isAutomatic ? 0 : 1;

            $values4 = [
                'auditTrailUserName' => $auditTrail[$userInformationIndex]['userName'],
                'auditTrailType' => $auditTrail[$userInformationIndex]['type'],
                'auditTrailSubType' => $auditTrail[$userInformationIndex]['subType'],
                'auditTrailComment' => $auditTrail[$userInformationIndex]['comment']
            ];

            foreach ($attributes4 as $attribute) {
                try {
                    $this->setTableValue($attribute['value'], $values4[$attribute['id']] ?? '');
                    } catch (Exception $e) {
                    $this->logError('Failed to set audit trail table value', $e, ['attribute' => $attribute['id']]);
                    }
                }

            $attributes5 = $this->resolveOutputParameterListAttributes('rejectionDetails'); //rejectionDetails

            $values5 = [
                'rejectReason' => $type == "e_invoice" ? $dataItem['rejectReason'] : $dataItem['rejectReason'],
                'rejectionCode' => $type == "e_invoice" ? '' : ($dataItem['rejectionType']['code'] ?? null),
                'rejectionType' => $type == "e_invoice" ? '' : ($dataItem['rejectionType']['type'] ?? null)
            ];
            if ($type == "e_invoice") {
                $violations = [];
                foreach ($dataItem['violations'] as $violation) {
                    $messages = implode(', ', $violation['messages']);
                    $violations[] = $violation['level'] . ': ' . $messages;
                    }
                $values5['violations'] = implode(" --- ", $violations);
                } else {
                $values5['violations'] = '';
                }

            foreach ($attributes5 as $attribute) {
                try {
                    $this->setTableValue($attribute['value'], $values5[$attribute['id']] ?? '');
                    } catch (Exception $e) {
                    $this->logError('Failed to set rejection table value', $e, ['attribute' => $attribute['id']]);
                    }
                }


            $attributes6 = $this->resolveOutputParameterListAttributes('attachments'); //attachments

            if ($type == "e_invoice") {
                //pdf
                $urlPDF = $dataItem['eInvoicePdfPath'];
                $tempPath = $this->getTempPath();
                $tempFileName = pathinfo($dataItem['fileName'], PATHINFO_FILENAME);
                $savePath = $tempPath . "/pedant";

                if (!is_dir($savePath)) {
                    mkdir($savePath, 0777, true);
                    }

                $dataPDF = false;
                try {
                    $responseData = $this->makeApiRequest($urlPDF, 'GET');
                    if (in_array($responseData['httpCode'], self::SUCCESS_HTTP_CODES)) {
                        $dataPDF = $responseData['response'];
                        } else {
                        $this->logError('Failed to download e-invoice PDF', null, ['url' => $urlPDF, 'httpcode' => $responseData['httpCode']]);
                        }
                    } catch (JobRouterException $e) {
                    $this->logError('Failed to download e-invoice PDF', $e, ['url' => $urlPDF]);
                    }

                $eInvoicePDF = $savePath . "/" . $tempFileName . "_PDF_.pdf";

                if ($dataPDF !== false) {
                    $writeResult = file_put_contents($eInvoicePDF, $dataPDF);
                    if ($writeResult === false) {
                        $this->logError('Failed to save e-invoice PDF', null, ['path' => $eInvoicePDF]);
                        }
                    }

                $this->setSystemActivityVar('PDFPATH', $eInvoicePDF);

                //report
                $urlReport = $dataItem['reportFilePath'];

                if (!is_dir($savePath)) {
                    mkdir($savePath, 0777, true);
                    }

                $dataReport = false;
                try {
                    $responseData = $this->makeApiRequest($urlReport, 'GET');
                    if (in_array($responseData['httpCode'], self::SUCCESS_HTTP_CODES)) {
                        $dataReport = $responseData['response'];
                        } else {
                        $this->logError('Failed to download e-invoice report', null, ['url' => $urlReport, 'httpcode' => $responseData['httpCode']]);
                        }
                    } catch (JobRouterException $e) {
                    $this->logError('Failed to download e-invoice report', $e, ['url' => $urlReport]);
                    }

                $eInvoiceReport = $savePath . "/" . $tempFileName . "_REPORT_.xml";

                if ($dataReport !== false) {
                    $writeResult = file_put_contents($eInvoiceReport, $dataReport);
                    if ($writeResult === false) {
                        $this->logError('Failed to save e-invoice report', null, ['path' => $eInvoiceReport]);
                        }
                    }

                $this->setSystemActivityVar('REPORTPATH', $eInvoiceReport);

                //attachments
                $attachments = $dataItem['attachments'] ?? [];
                $attachmentFiles = [];
                foreach ($attachments as $index => $url) {
                    if ($index >= self::MAX_ATTACHMENTS) {
                        break;
                        }

                    try {
                        $responseData = $this->makeApiRequest($url, 'GET');
                        if (!in_array($responseData['httpCode'], self::SUCCESS_HTTP_CODES)) {
                            $this->logError('Failed to download attachment', null, ['url' => $url, 'index' => $index, 'httpcode' => $responseData['httpCode']]);
                            continue;
                            }

                        $dataAttachment = $responseData['response'];

                        $attachmentPath = $savePath . "/" . $tempFileName . "_ATTACHMENT_" . ($index + 1) . ".pdf";
                        $writeResult = file_put_contents($attachmentPath, $dataAttachment);

                        if ($writeResult === false) {
                            $this->logError('Failed to save attachment', null, ['path' => $attachmentPath, 'index' => $index]);
                            continue;
                            }

                        $this->setSystemActivityVar('ATTACHMENTPATH' . $index, $attachmentPath);
                        $attachmentFiles[] = $attachmentPath;
                        } catch (Exception $e) {
                        $this->logError('Error processing attachment', $e, ['url' => $url, 'index' => $index]);
                        }
                    }

                $values6 = [
                    'e_invoicePDF' => $eInvoicePDF,
                    'e_invoiceReport' => $eInvoiceReport
                ];


                foreach ($attachmentFiles as $i => $attachmentPath) {
                    $values6['e_invoiceAttachments'][] = $attachmentPath;
                    }

                // Generate audit trail CSV
                $auditTrailData = $dataItem['auditTrail'] ?? [];
                if (!empty($auditTrailData)) {
                    $auditTrailCSVPath = $this->convertAuditTrailToCSV($auditTrailData, $savePath, $tempFileName);
                    $values6['auditTrailCSV'] = $auditTrailCSVPath;
                    $this->setSystemActivityVar('AUDITTRAILPATH', $auditTrailCSVPath);
                    } else {
                    $values6['auditTrailCSV'] = '';
                    }
                } else {
                $tempPath = $this->getTempPath();
                $tempFileName = $dataItem['file']['name'] ?? 'invoice';
                $savePath = $tempPath . "/pedant";

                if (!is_dir($savePath)) {
                    mkdir($savePath, 0777, true);
                    }

                // Generate audit trail CSV for regular invoices
                $auditTrailData = $dataItem['auditTrail'] ?? [];
                $auditTrailCSVPath = '';
                if (!empty($auditTrailData)) {
                    $auditTrailCSVPath = $this->convertAuditTrailToCSV($auditTrailData, $savePath, $tempFileName);
                    $this->setSystemActivityVar('AUDITTRAILPATH', $auditTrailCSVPath);
                    }

                $values6 = [
                    'e_invoicePDF' => '',
                    'e_invoiceReport' => '',
                    'e_invoiceAttachments' => [],
                    'auditTrailCSV' => $auditTrailCSVPath
                ];
                }

            foreach ($attributes6 as $attribute) {
                try {
                    $value = $values6[$attribute['id']] ?? '';
                    if ($attribute['subtable'] == "") {
                        $this->attachFile($attribute['value'], $value);
                        } else {
                        $attachments = is_array($value) ? $value : [$value];
                        foreach ($attachments as $attachment) {
                            $this->attachSubtableFile($attribute['subtable'], $this->getSubtableCount($attribute['subtable']) + 1, $attribute['value'], $attachment);
                            }
                        }
                    } catch (Exception $e) {
                    $this->logError('Failed to attach file', $e, ['attribute' => $attribute['id']]);
                    }
                }

            $attributes7 = $this->resolveOutputParameterListAttributes('positionDetails'); //positionDetails
            $lineItems = $dataItem['lineItems'];

            $values7 = [
                'positionNumber' => $lineItems['lineSubPositionNumber'],
                'singleNetPrice' => $lineItems['lineSubUnitPrice'],
                'singleNetAmount' => $lineItems['lineSubNetAmount'],
                'quantity' => $lineItems['lineSubQuantity'],
                'unitOfMeasureCode' => $lineItems['lineSubUnit'],
                'articleNumber' => [],
                'articleName' => [],
                'itemDescription' => $lineItems['lineSubDescription'],
                'vatRatePerLine' => $lineItems['lineSubVatPercent']
            ];

            if ($type == "e_invoice") {
                $invoiceLine = $eInvoiceFields['invoiceLine'] ?? [];

                foreach ($invoiceLine as $line) {
                    $values7['positionNumber'][] = $line['invoiceLineIdentifierId'];
                    $values7['singleNetPrice'][] = $line['priceDetailsItemNetPrice'];
                    $values7['singleNetAmount'][] = $line['invoiceTotalAmountWithoutVat'];
                    $values7['quantity'][] = $line['invoicedQuantity'];
                    $values7['unitOfMeasureCode'][] = $line['invoicedQuantityUnitOfMeasureCode'];
                    $values7['articleNumber'][] = $line['Artikelnummer'];
                    $values7['articleName'][] = $line['itemInformationItemName'];
                    $values7['itemDescription'][] = $line['itemInformationItemDescription'];
                    $values7['vatRatePerLine'][] = $line['lineVatInformationInvoicedItemVatRate'];
                    }
                }

            $invoiceLine = $eInvoiceFields['invoiceLine'] ?? [];

            foreach ($invoiceLine as $index => $line) {
                try {
                    $rowID = $this->getSubtableCount($attributes7[0]['subtable']) + 1;
                    foreach ($attributes7 as $attribute) {
                        $value = isset($values7[$attribute['id']][$index]) ? $values7[$attribute['id']][$index] : '';
                        $this->setSubtableValue($attribute['subtable'], $rowID + $index, $attribute['value'], $value);
                        }
                    } catch (Exception $e) {
                    $this->logError('Failed to set position subtable value', $e, ['index' => $index]);
                    }
                }

            $attributes8 = $this->resolveOutputParameterListAttributes('workflowDetails'); //workflowDetails

            $workflows = $dataItem['workflows'];

            $values8 = [
                'direkt' => !empty($workflows) && ($workflows[0]['name'] ?? '') === 'Direkt' ? 1 : 0
            ];

            foreach ($attributes8 as $attribute) {
                try {
                    $this->setTableValue($attribute['value'], $values8[$attribute['id']]);
                    } catch (Exception $e) {
                    $this->logError('Failed to set workflow table value', $e, ['attribute' => $attribute['id']]);
                    }
                }
            } catch (JobRouterException $e) {
            $this->logError('JobRouter error in storeList', $e);
            throw $e;
            } catch (Exception $e) {
            $this->logError('Unexpected error in storeList', $e);
            throw new JobRouterException('Store list error: ' . $e->getMessage());
            }
        }


    /**
     * Returns the UDL (User Defined List) for the given element ID.
     *
     * @param string $udl The UDL identifier.
     * @param string $elementID The element ID for which to get the UDL.
     * @return array The UDL as an array of name-value pairs.
     */
    public function getUDL($udl, $elementID)
        {
        if ($elementID == 'importVendor') {
            return [
                ['name' => '-', 'value' => ''],
                ['name' => INTERNALNUMBER, 'value' => '1'],
                ['name' => PROFILNAME, 'value' => '2'],
                ['name' => VENDORNAME, 'value' => '3'],
                ['name' => STREET, 'value' => '4'],
                ['name' => ZIPCODE, 'value' => '5'],
                ['name' => CITY, 'value' => '6'],
                ['name' => COUNTRY, 'value' => '7'],
                ['name' => BANKNUMBER, 'value' => '8'],
                ['name' => TAXNUMBER, 'value' => '9'],
                ['name' => VAT, 'value' => '10'],
                ['name' => RECIPIENTNUMBER, 'value' => '11'],
                ['name' => KVK, 'value' => '12'],
                ['name' => CURRENCY, 'value' => '13'],
                ['name' => BLOCKED, 'value' => '14'],
                ['name' => SORTCODE, 'value' => '15'],
                ['name' => ACCOUNTNUMBER, 'value' => '16'],
            ];
            }

        if ($elementID == 'importRecipient') {
            return [
                ['name' => '-', 'value' => ''],
                ['name' => INTERNALNUMBER, 'value' => '1'],
                ['name' => PROFILNAME, 'value' => '2'],
                ['name' => COUNTRY, 'value' => '3'],
                ['name' => CITY, 'value' => '4'],
                ['name' => ZIPCODE, 'value' => '5'],
                ['name' => STREET, 'value' => '6'],
                ['name' => RECIPIENTNAME, 'value' => '7'],
                ['name' => VAT, 'value' => '8'],
                ['name' => SYNONYMS, 'value' => '9']
            ];
            }

        if ($elementID == 'importCostCenter') {
            return [
                ['name' => '-', 'value' => ''],
                ['name' => INTERNALNUMBER, 'value' => '1'],
                ['name' => PROFILNAME, 'value' => '2'],
                ['name' => RECIPIENTNAME, 'value' => '3'],
            ];
            }

        if ($elementID == 'recipientDetails') {
            return [
                ['name' => '-', 'value' => ''],
                ['name' => RECIPIENTCOMPANYNAME, 'value' => 'recipientCompanyName'],
                ['name' => RECIPIENTNAME, 'value' => 'recipientName'],
                ['name' => STREET, 'value' => 'recipientStreet'],
                ['name' => ZIPCODE, 'value' => 'recipientZipCode'],
                ['name' => CITY, 'value' => 'recipientCity'],
                ['name' => COUNTRY, 'value' => 'recipientCountry'],
                ['name' => RECIPIENTVATNUMBER, 'value' => 'recipientVatNumber'],
                ['name' => INTERNALNUMBER, 'value' => 'recipientInternalNumber']
            ];
            }

        if ($elementID == 'vendorDetails') {
            return [
                ['name' => '-', 'value' => ''],
                ['name' => BANKNUMBER, 'value' => 'vendorBankNumber'],
                ['name' => VAT, 'value' => 'vendorVatNumber'],
                ['name' => TAXNUMBER, 'value' => 'vendorTaxNumber'],
                ['name' => VENDORCOMPANYNAME, 'value' => 'vendorCompanyName'],
                ['name' => STREET, 'value' => 'vendorStreet'],
                ['name' => ZIPCODE, 'value' => 'vendorZipCode'],
                ['name' => CITY, 'value' => 'vendorCity'],
                ['name' => COUNTRY, 'value' => 'vendorCountry'],
                ['name' => DELIVERYPERIOD, 'value' => 'vendorDeliveryPeriod'],
                ['name' => ACCOUNTNUMBER, 'value' => 'vendorAccountNumber'],
                ['name' => INTERNALNUMBER, 'value' => 'vendorInternalNumber'],
                ['name' => COMPANYREGISTRATIONNUMBER, 'value' => 'vendorCompanyRegistrationNumber'],
                ['name' => EMAIL, 'value' => 'vendorEmail']
            ];
            }

        if ($elementID == 'invoiceDetails') {
            return [
                ['name' => '-', 'value' => ''],
                ['name' => TAXRATE1, 'value' => 'taxRate1'],
                ['name' => TAXRATE2, 'value' => 'taxRate2'],
                ['name' => TAXRATE3, 'value' => 'taxRate3'],
                ['name' => TAXRATE4, 'value' => 'taxRate4'],
                ['name' => TAXRATE5, 'value' => 'taxRate5'],
                ['name' => INVOICENUMBER, 'value' => 'invoiceNumber'],
                ['name' => DATE, 'value' => 'date'],
                ['name' => NETAMOUNT, 'value' => 'netAmount'],
                ['name' => TAXAMOUNT, 'value' => 'taxAmount'],
                ['name' => GROSSAMOUNT, 'value' => 'grossAmount'],
                ['name' => TAXRATE, 'value' => 'taxRate'],
                ['name' => PROJECTNUMBER, 'value' => 'projectNumber'],
                ['name' => PURCHASEORDER, 'value' => 'purchaseOrder'],
                ['name' => PURCHASEDATE, 'value' => 'purchaseDate'],
                ['name' => HASDISCOUNT, 'value' => 'hasDiscount'],
                ['name' => REFUND, 'value' => 'refund'],
                ['name' => DISCOUNTPERCENTAGE, 'value' => 'discountPercentage'],
                ['name' => DISCOUNTAMOUNT, 'value' => 'discountAmount'],
                ['name' => DISCOUNTDATE, 'value' => 'discountDate'],
                ['name' => INVOICETYPE, 'value' => 'invoiceType'],
                ['name' => TYPEINV, 'value' => 'type'],
                ['name' => NOTE, 'value' => 'note'],
                ['name' => STATUS, 'value' => 'status'],
                ['name' => CURRENCY, 'value' => 'currency'],
                ['name' => RESOLVEDISSUES, 'value' => 'resolvedIssuesCount'],
                ['name' => HASPROCESSINGISSUES, 'value' => 'hasProcessingIssues'],
                ['name' => DELIVERYDATE, 'value' => 'deliveryDate'],
            ];
            }

        if ($elementID == 'positionDetails') {
            return [
                ['name' => '-', 'value' => ''],
                ['name' => POSITIONNUMBER, 'value' => 'positionNumber'],
                ['name' => SINGLENETPRICE, 'value' => 'singleNetPrice'],
                ['name' => NETAMOUNT, 'value' => 'singleNetAmount'],
                ['name' => QUANTITY, 'value' => 'quantity'],
                ['name' => UNITOFMEASURECODE, 'value' => 'unitOfMeasureCode'],
                ['name' => ARTICLENUMBER, 'value' => 'articleNumber'],
                ['name' => ARTICLENAME, 'value' => 'articleName'],
                ['name' => ITEMDESCRIPTION, 'value' => 'itemDescription'],
                ['name' => VATRATEPERINVOICELINE, 'value' => 'vatRatePerLine']
            ];
            }

        if ($elementID == 'auditTrailDetails') {
            return [
                ['name' => '-', 'value' => ''],
                ['name' => USERNAME, 'value' => 'auditTrailUserName'],
                ['name' => TYPE, 'value' => 'auditTrailType'],
                ['name' => SUBTYPE, 'value' => 'auditTrailSubType'],
                ['name' => COMMENT, 'value' => 'auditTrailComment']
            ];
            }

        if ($elementID == 'rejectionDetails') {
            return [
                ['name' => '-', 'value' => ''],
                ['name' => REJECTREASON, 'value' => 'rejectReason'],
                ['name' => CODE, 'value' => 'rejectionCode'],
                ['name' => TYPE, 'value' => 'rejectionType'],
                ['name' => VIOLATIONS, 'value' => 'violations']
            ];
            }

        if ($elementID == 'attachments') {
            return [
                ['name' => '-', 'value' => ''],
                ['name' => E_INVOICEPDF, 'value' => 'e_invoicePDF'],
                ['name' => E_INVOICEREPORT, 'value' => 'e_invoiceReport'],
                ['name' => E_INVOICEATTACHMENT1, 'value' => 'e_invoiceAttachments'],
                ['name' => AUDITTRAILCSV, 'value' => 'auditTrailCSV']
            ];
            }

        if ($elementID == 'workflowDetails') {
            return [
                ['name' => '-', 'value' => ''],
                ['name' => DIREKT, 'value' => 'direkt']
            ];
            }
        return null;
        }
    }
//v1.11

