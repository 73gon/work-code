<?php
class pedantSystemActivity extends AbstractSystemActivityAPI
    {
    // Constants
    private const DEBUG_MODE = true;
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
     * @param array|string|null $postFields POST fields for the request.
     * @param array $headers HTTP headers.
     * @return array{response: string, httpCode: int} The response and HTTP code.
     * @throws JobRouterException If the request fails.
     */
    private function makeApiRequest(string $url, string $method = 'GET', array|string|null $postFields = null, array $headers = []): array
        {
        $this->logDebug('makeApiRequest() called', ['url' => $url, 'method' => $method, 'hasPostFields' => $postFields !== null, 'headerCount' => count($headers)]);
        $curl = curl_init();
        if ($curl === false) {
            throw new JobRouterException('Failed to initialize cURL');
            }

        $defaultHeaders = ['X-API-KEY: ' . $this->getApiKey()];
        $allHeaders = array_merge($defaultHeaders, $headers);
        $this->logDebug('makeApiRequest() headers prepared', ['headerCount' => count($allHeaders)]);

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

        $this->logDebug('makeApiRequest() completed', ['url' => $url, 'httpCode' => $httpCode, 'responseLength' => strlen($response)]);
        return ['response' => $response, 'httpCode' => $httpCode];
        }

    /**
     * Gets the cached API key or resolves it.
     */
    private function getApiKey(): string
        {
        if ($this->apiKey === null) {
            $this->apiKey = $this->resolveInputParameter('api_key') ?? '';
            $this->logDebug('getApiKey() resolved fresh', ['maskedKey' => substr($this->apiKey, 0, 4) . '***']);
            } else {
            $this->logDebug('getApiKey() cache hit');
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
            $this->logDebug('isDemo() resolved fresh', ['isDemo' => $this->isDemo]);
            } else {
            $this->logDebug('isDemo() cache hit', ['isDemo' => $this->isDemo]);
            }
        return $this->isDemo;
        }

    /**
     * Gets the base API URL based on demo mode.
     */
    private function getBaseUrl(): string
        {
        $url = $this->isDemo() ? $this->demoURL : $this->productiveURL;
        $this->logDebug('getBaseUrl() resolved', ['url' => $url]);
        return $url;
        }

    /**
     * Gets the entity API URL based on demo mode.
     */
    private function getEntityUrl(): string
        {
        $url = $this->isDemo() ? $this->demoURL : $this->entityURL;
        $this->logDebug('getEntityUrl() resolved', ['url' => $url]);
        return $url;
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

    /**
     * Logs a debug message when DEBUG_MODE is enabled.
     *
     * @param string $message The debug message to log.
     * @param array $context Optional additional context data.
     */
    private function logDebug(string $message, array $context = []): void
        {
        if (!self::DEBUG_MODE) {
            return;
            }
        $logMessage = '[Pedant][DEBUG] ' . $message;
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
            $this->logDebug('pedant() called');
            $this->maxFileSize = $this->resolveInputParameter('maxFileSize') ?: self::DEFAULT_MAX_FILE_SIZE_MB;
            $this->logDebug('pedant() maxFileSize resolved', ['maxFileSize' => $this->maxFileSize]);
            $isNew = $this->resolveInputParameter('new');
            $intervalOld = $this->resolveInputParameter('intervalOld');
            $this->setResubmission($isNew ? 17520 : $intervalOld, $isNew ? 'h' : 'm');
            $this->logDebug('pedant() resubmission set', ['isNew' => $isNew, 'intervalOld' => $intervalOld, 'resubValue' => $isNew ? 17520 : $intervalOld, 'resubUnit' => $isNew ? 'h' : 'm']);

            if (!date_default_timezone_get()) {
                date_default_timezone_set('Europe/Berlin');
                $this->logDebug('pedant() timezone set to Europe/Berlin');
                }

            $uploadCounter = $this->getSystemActivityVar('UPLOADCOUNTER');
            if (!$uploadCounter) {
                $this->setSystemActivityVar('UPLOADCOUNTER', 0);
                $this->logDebug('pedant() UPLOADCOUNTER initialized to 0');
                } else {
                $this->logDebug('pedant() UPLOADCOUNTER exists', ['value' => $uploadCounter]);
                }

            $fileId = $this->getSystemActivityVar('FILEID');
            $this->logDebug('pedant() FILEID check', ['fileId' => $fileId]);
            if ($fileId) {
                $this->logDebug('pedant() branching to checkFile()');
                $this->checkFile();
                }

            if (!$this->getSystemActivityVar('FILEID')) {
                $this->logDebug('pedant() branching to uploadFile()');
                $this->uploadFile();
                }
            $this->logDebug('pedant() completed');
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
            $this->logDebug('importVendorCSV() called');
            $this->importVendor();
            $this->logDebug('importVendorCSV() importVendor completed, marking activity as completed');
            $this->markActivityAsCompleted();
            $this->logDebug('importVendorCSV() completed');
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
            $this->logDebug('importRecipientCSV() called');
            $this->importRecipient();
            $this->logDebug('importRecipientCSV() importRecipient completed, marking activity as completed');
            $this->markActivityAsCompleted();
            $this->logDebug('importRecipientCSV() completed');
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
            $this->logDebug('importCostCenterCSV() called');
            $this->importCostCenter();
            $this->logDebug('importCostCenterCSV() importCostCenter completed, marking activity as completed');
            $this->markActivityAsCompleted();
            $this->logDebug('importCostCenterCSV() completed');
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
            $this->logDebug('fetchData() called');
            $this->markActivityAsPending();

            $interval = $this->resolveInputParameter('interval');
            $worktime = $this->resolveInputParameter('worktime');
            $weekend = $this->resolveInputParameter('weekend');
            $this->logDebug('fetchData() parameters resolved', ['interval' => $interval, 'worktime' => $worktime, 'weekend' => $weekend]);

            list($startTime, $endTime) = array_map('intval', explode(',', $worktime));
            list($currentHour, $currentDayOfWeek) = [(int) (new DateTime())->format('G'), (int) (new DateTime())->format('w')];
            $this->logDebug('fetchData() time parsed', ['startTime' => $startTime, 'endTime' => $endTime, 'currentHour' => $currentHour, 'currentDayOfWeek' => $currentDayOfWeek]);

            if ($weekend) {
                if ($currentHour >= $startTime && $currentHour < $endTime) {
                    $this->logDebug('fetchData() weekend=true, within work hours, resubmission in minutes', ['interval' => $interval]);
                    $this->setResubmission($interval, 'm');
                    } else {
                    $hoursToStart = ($currentHour < $startTime) ? $startTime - $currentHour : 24 - $currentHour + $startTime;
                    $this->logDebug('fetchData() weekend=true, outside work hours, resubmission in hours', ['hoursToStart' => $hoursToStart]);
                    $this->setResubmission($hoursToStart, 'h');
                    }
                } else {
                if ($currentDayOfWeek >= 1 && $currentDayOfWeek <= 5) {
                    if ($currentHour >= $startTime && $currentHour < $endTime) {
                        $this->logDebug('fetchData() weekday, within work hours, resubmission in minutes', ['interval' => $interval]);
                        $this->setResubmission($interval, 'm');
                        } else {
                        $hoursToStart = ($currentHour < $startTime) ? $startTime - $currentHour : 24 - $currentHour + $startTime;
                        $this->logDebug('fetchData() weekday, outside work hours, resubmission in hours', ['hoursToStart' => $hoursToStart]);
                        $this->setResubmission($hoursToStart, 'h');
                        }
                    } else {
                    $hoursToStart = ($currentHour < $startTime) ? $startTime - $currentHour : 24 - $currentHour + $startTime;
                    if ($currentDayOfWeek == 6) {
                        $hoursToStart += 24;
                        }
                    $this->logDebug('fetchData() weekend day, resubmission in hours', ['hoursToStart' => $hoursToStart, 'currentDayOfWeek' => $currentDayOfWeek]);
                    $this->setResubmission($hoursToStart, 'h');
                    }
                }
            $this->logDebug('fetchData() calling fetchInvoices()');
            $this->fetchInvoices();
            $this->logDebug('fetchData() completed');
            } catch (JobRouterException $e) {
            $this->logError('Fetch data failed', $e);
            throw $e;
            } catch (Exception $e) {
            $this->logError('Unexpected error in fetchData', $e);
            throw new JobRouterException('Fetch data error: ' . $e->getMessage());
            }
        }


    protected function documentClassifier(): void
        {
        try {
            $this->logDebug('documentClassifier() called');
            $this->maxFileSize = $this->resolveInputParameter('maxFileSize') ?: self::DEFAULT_MAX_FILE_SIZE_MB;
            $dcInterval = $this->resolveInputParameter('dc_interval');
            $this->setResubmission($dcInterval, 'm');
            $this->logDebug('documentClassifier() params resolved', ['maxFileSize' => $this->maxFileSize, 'dc_interval' => $dcInterval]);

            $dcUploadCounter = $this->getSystemActivityVar('DC_UPLOADCOUNTER');
            if (!$dcUploadCounter) {
                $this->setSystemActivityVar('DC_UPLOADCOUNTER', 0);
                $this->logDebug('documentClassifier() DC_UPLOADCOUNTER initialized to 0');
                } else {
                $this->logDebug('documentClassifier() DC_UPLOADCOUNTER exists', ['value' => $dcUploadCounter]);
                }

            $dcDocumentId = $this->getSystemActivityVar('DC_DOCUMENTID');
            $this->logDebug('documentClassifier() DC_DOCUMENTID check', ['documentId' => $dcDocumentId]);
            if ($dcDocumentId) {
                $this->logDebug('documentClassifier() branching to checkDocumentClassifier()');
                $this->checkDocumentClassifier();
                }

            if (!$this->getSystemActivityVar('DC_DOCUMENTID')) {
                $this->logDebug('documentClassifier() branching to uploadDocumentClassifier()');
                $this->uploadDocumentClassifier();
                }
            $this->logDebug('documentClassifier() completed');
            } catch (JobRouterException $e) {
            $this->logError('Document classifier processing failed', $e);
            throw $e;
            } catch (Exception $e) {
            $this->logError('Unexpected error in documentClassifier', $e);
            throw new JobRouterException('Document classifier error: ' . $e->getMessage());
            }
        }

    protected function uploadDocumentClassifier(): void
        {
        try {
            $this->logDebug('uploadDocumentClassifier() called');
            $file = $this->getUploadPath() . $this->resolveInputParameter('inputFile');
            $this->logDebug('uploadDocumentClassifier() file path resolved', ['file' => $file]);

            if (!file_exists($file)) {
                throw new JobRouterException('Upload file does not exist: ' . $file);
                }

            $fileSizeB = filesize($file);
            if ($fileSizeB === false) {
                throw new JobRouterException('Failed to get file size for: ' . $file);
                }

            $fileSizeMB = $fileSizeB / (1024 * 1024);
            $this->logDebug('uploadDocumentClassifier() file size checked', ['fileSizeB' => $fileSizeB, 'fileSizeMB' => round($fileSizeMB, 2), 'maxFileSize' => $this->maxFileSize]);
            if ($fileSizeMB > $this->maxFileSize) {
                throw new JobRouterException("File size exceeds the maximum limit of $this->maxFileSize MB. Actual size: $fileSizeMB MB.");
                }

            $baseUrl = $this->getBaseUrl();
            $url = $baseUrl . '/v1/external/documents/document-classifiers/upload';

            $action = $this->resolveInputParameter('dc_action') ?: 'normal';
            $this->logDebug('uploadDocumentClassifier() API request prepared', ['url' => $url, 'action' => $action]);
            if (!in_array($action, self::VALID_FLAGS)) {
                throw new JobRouterException('Invalid input parameter value for DC_ACTION: ' . $action);
                }

            $responseData = $this->makeApiRequest(
                $url,
                'POST',
                [
                    'file' => new CURLFILE($file),
                    'action' => $action,
                ]
            );

            $response = $responseData['response'];
            $httpCode = $responseData['httpCode'];
            $this->logDebug('uploadDocumentClassifier() API response received', ['httpCode' => $httpCode, 'responseLength' => strlen($response)]);

            $maxCounter = $this->resolveInputParameter('dc_maxCounter');
            $counter = $this->getSystemActivityVar('DC_UPLOADCOUNTER');
            $this->logDebug('uploadDocumentClassifier() counter check', ['counter' => $counter, 'maxCounter' => $maxCounter]);

            if ($counter >= $maxCounter && !in_array($httpCode, array_merge(self::SUCCESS_HTTP_CODES, self::RETRY_HTTP_CODES))) {
                $this->setSystemActivityVar('DC_UPLOADCOUNTER', 0);
                $this->logError('Document classifier upload failed after max retries', null, ['counter' => $counter, 'httpCode' => $httpCode]);
                throw new JobRouterException('Error occurred during document classifier upload after maximum retries (' . $counter . '). HTTP Code: ' . $httpCode);
                } else {
                $this->setSystemActivityVar('DC_UPLOADCOUNTER', ++$counter);
                $this->logDebug('uploadDocumentClassifier() counter incremented', ['counter' => $counter]);
                }

            if (!in_array($httpCode, self::SUCCESS_HTTP_CODES)) {
                $this->logError('Document classifier upload returned non-success', null, ['httpCode' => $httpCode, 'response' => substr($response, 0, 500)]);
                $this->logDebug('uploadDocumentClassifier() non-success HTTP code, returning early', ['httpCode' => $httpCode]);
                return;
                }

            $data = json_decode($response, true);

            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                $this->logError('Failed to parse document classifier upload response JSON', null, ['json_error' => json_last_error_msg()]);
                throw new JobRouterException('Failed to parse API response: ' . json_last_error_msg());
                }

            $documentId = $data['documents'][0]['documentId'] ?? '';
            $this->logDebug('uploadDocumentClassifier() documentId parsed', ['documentId' => $documentId]);

            if (empty($documentId)) {
                throw new JobRouterException('Document classifier upload response missing documentId');
                }

            $this->storeOutputParameter('dc_documentId', $documentId);
            $this->setSystemActivityVar('DC_DOCUMENTID', $documentId);
            $this->setSystemActivityVar('DC_FETCHCOUNTER', 0);
            $this->logDebug('uploadDocumentClassifier() completed successfully', ['documentId' => $documentId]);
            } catch (JobRouterException $e) {
            throw $e;
            } catch (Exception $e) {
            $this->logError('Unexpected error in uploadDocumentClassifier', $e);
            throw new JobRouterException('Document classifier upload error: ' . $e->getMessage());
            }
        }

    protected function checkDocumentClassifier(): void
        {
        try {
            $this->logDebug('checkDocumentClassifier() called');
            $baseUrl = $this->getBaseUrl();
            $documentId = $this->getSystemActivityVar('DC_DOCUMENTID');
            $url = $baseUrl . '/v1/external/documents/document-classifiers?documentId=' . urlencode($documentId);
            $this->logDebug('checkDocumentClassifier() URL built', ['url' => $url, 'documentId' => $documentId]);

            $maxCounter = $this->resolveInputParameter('dc_maxCounter');

            $responseData = $this->makeApiRequest($url, 'GET');
            $response = $responseData['response'];
            $httpCode = $responseData['httpCode'];
            $this->logDebug('checkDocumentClassifier() API response received', ['httpCode' => $httpCode, 'responseLength' => strlen($response)]);

            $counter = $this->getSystemActivityVar('DC_FETCHCOUNTER');
            $this->logDebug('checkDocumentClassifier() counter check', ['counter' => $counter, 'maxCounter' => $maxCounter]);

            if ($counter >= $maxCounter && !in_array($httpCode, array_merge(self::SUCCESS_HTTP_CODES, self::RETRY_HTTP_CODES))) {
                $this->setSystemActivityVar('DC_FETCHCOUNTER', 0);
                $this->logError('Document classifier check failed after max retries', null, ['counter' => $counter, 'httpCode' => $httpCode]);
                throw new JobRouterException('Error occurred during document classifier check after maximum retries (' . $counter . '). HTTP Code: ' . $httpCode);
                } else {
                if (!in_array($httpCode, self::SUCCESS_HTTP_CODES)) {
                    $this->setSystemActivityVar('DC_FETCHCOUNTER', ++$counter);
                    $this->logDebug('checkDocumentClassifier() non-success HTTP code, incrementing counter and returning', ['counter' => $counter, 'httpCode' => $httpCode]);
                    return;
                    }
                }

            $data = json_decode($response, true);

            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                $this->logError('Failed to parse document classifier check response JSON', null, ['json_error' => json_last_error_msg()]);
                throw new JobRouterException('Failed to parse API response: ' . json_last_error_msg());
                }

            if (!isset($data['data'][0])) {
                $this->logError('Invalid document classifier check response structure', null, ['response' => substr($response, 0, 500)]);
                throw new JobRouterException('Invalid API response: missing data');
                }

            $dataItem = $data['data'][0];
            $status = $dataItem['status'] ?? '';
            $this->logDebug('checkDocumentClassifier() status parsed', ['status' => $status]);

            if (in_array($status, self::FALSE_STATES)) {
                $this->logDebug('checkDocumentClassifier() status is in FALSE_STATES, returning early', ['status' => $status]);
                return;
                }

            $this->storeOutputParameter('dc_documentId', $dataItem['documentId'] ?? '');
            $this->storeOutputParameter('dc_tempJSON', json_encode($data));

            $attributes = $this->resolveOutputParameterListAttributes('classificationDetails');
            $values = [
                'documentClassifierNumber' => $dataItem['documentClassifierNumber'] ?? '',
                'documentType' => $dataItem['documentType'] ?? '',
                'vendorCompanyName' => $dataItem['vendorCompanyName'] ?? '',
                'recipientCompanyName' => $dataItem['recipientCompanyName'] ?? '',
                'issueDate' => !empty($dataItem['issueDate']) ? date('d.m.Y', strtotime($dataItem['issueDate'])) : ''
            ];
            $this->logDebug('checkDocumentClassifier() classification values prepared', $values);

            foreach ($attributes as $attribute) {
                try {
                    $this->setTableValue($attribute['value'], $values[$attribute['id']] ?? '');
                    $this->logDebug('checkDocumentClassifier() set table value', ['attribute' => $attribute['id'], 'value' => $values[$attribute['id']] ?? '']);
                    } catch (Exception $e) {
                    $this->logError('Failed to set classification detail table value', $e, ['attribute' => $attribute['id']]);
                    }
                }

            $this->setResubmission(1, 's');
            $this->markActivityAsCompleted();
            $this->logDebug('checkDocumentClassifier() completed, activity marked as completed');
            } catch (JobRouterException $e) {
            throw $e;
            } catch (Exception $e) {
            $this->logError('Unexpected error in checkDocumentClassifier', $e);
            throw new JobRouterException('Document classifier check error: ' . $e->getMessage());
            }
        }

    protected function uploadFile(): void
        {
        try {
            $this->logDebug('uploadFile() called');
            $file = $this->getUploadPath() . $this->resolveInputParameter('inputFile');
            $this->logDebug('uploadFile() file path resolved', ['file' => $file]);

            if (!file_exists($file)) {
                throw new JobRouterException('Upload file does not exist: ' . $file);
                }

            $fileExtension = pathinfo($file, PATHINFO_EXTENSION);
            $fileSizeB = filesize($file);

            if ($fileSizeB === false) {
                throw new JobRouterException('Failed to get file size for: ' . $file);
                }

            $fileSizeMB = $fileSizeB / (1024 * 1024);
            $this->logDebug('uploadFile() file info', ['extension' => $fileExtension, 'fileSizeB' => $fileSizeB, 'fileSizeMB' => round($fileSizeMB, 2), 'maxFileSize' => $this->maxFileSize]);
            if ($fileSizeMB > $this->maxFileSize) {
                throw new JobRouterException("File size exceeds the maximum limit of $this->maxFileSize MB. Actual size: $fileSizeMB MB.");
                }

            $baseUrl = $this->getBaseUrl();
            $zugferd = $this->resolveInputParameter('zugferd');
            $url = $baseUrl . (strtolower($fileExtension) == 'xml' ? "/v2/external/documents/invoices/upload" : ($zugferd == '1' ? "/v1/external/documents/invoices/upload" : "/v2/external/documents/invoices/upload"));
            $this->logDebug('uploadFile() URL built', ['url' => $url, 'zugferd' => $zugferd]);

            $flag = $this->resolveInputParameter('flag');
            $this->logDebug('uploadFile() flag resolved', ['flag' => $flag, 'fileExtension' => $fileExtension]);
            if (strtolower($fileExtension) == 'xml') {
                $flagXML = $this->resolveInputParameter('flagXML');
                if (!empty($flagXML)) {
                    $flag = $flagXML;
                    $this->logDebug('uploadFile() XML flag override', ['flagXML' => $flagXML]);
                    if (!in_array($flag, self::VALID_FLAGS)) {
                        throw new JobRouterException('Invalid input parameter value for FLAGXML: ' . $flag);
                        }
                    }
                }

            if (!in_array($flag, self::VALID_FLAGS)) {
                throw new JobRouterException('Invalid input parameter value for FLAG: ' . $flag);
                }

            $action = $flag;
            $internalNumber = $this->resolveInputParameter('internalNumber');
            $note = $this->resolveInputParameter('note');
            $this->logDebug('uploadFile() API request prepared', ['action' => $action, 'internalNumber' => $internalNumber, 'note' => $note]);

            $responseData = $this->makeApiRequest(
                $url,
                'POST',
                [
                    'file' => new CURLFILE($file),
                    'recipientInternalNumber' => $internalNumber,
                    'action' => $action,
                    'note' => $note,
                ]
            );

            $response = $responseData['response'];
            $httpcode = $responseData['httpCode'];
            $this->logDebug('uploadFile() API response received', ['httpcode' => $httpcode, 'responseLength' => strlen($response)]);

            $data = json_decode($response, TRUE);

            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                $this->logError('Failed to parse upload response JSON', null, ['json_error' => json_last_error_msg(), 'response' => substr($response, 0, 500)]);
                throw new JobRouterException('Failed to parse API response: ' . json_last_error_msg());
                }

            $maxCounter = $this->resolveInputParameter('maxCounter');
            $counter = $this->getSystemActivityVar('UPLOADCOUNTER');
            $this->logDebug('uploadFile() counter check', ['counter' => $counter, 'maxCounter' => $maxCounter, 'httpcode' => $httpcode]);

            if ($counter >= $maxCounter && !in_array($httpcode, array_merge(self::SUCCESS_HTTP_CODES, self::RETRY_HTTP_CODES))) {
                $this->setSystemActivityVar('UPLOADCOUNTER', 0);
                $this->logError('Upload failed after max retries', null, ['counter' => $counter, 'httpcode' => $httpcode]);
                throw new JobRouterException('Error occurred during upload after maximum retries (' . $counter . '). HTTP Error Code: ' . $httpcode);
                } else {
                $this->setSystemActivityVar('UPLOADCOUNTER', ++$counter);
                $this->logDebug('uploadFile() counter incremented', ['counter' => $counter]);
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
            $this->logDebug('uploadFile() response parsed', ['fileId' => $fileId, 'invoiceId' => $invoiceId, 'type' => $type]);

            if (empty($fileId)) {
                throw new JobRouterException('Upload response missing fileId');
                }

            $this->storeOutputParameter('fileID', $fileId);
            $this->storeOutputParameter('invoiceID', $invoiceId);
            $this->setSystemActivityVar('FILEID', $fileId);
            $this->setSystemActivityVar('FETCHCOUNTER', 0);
            $this->setSystemActivityVar('TYPE', $type);
            $this->setSystemActivityVar('COUNTER404', 0);
            $this->logDebug('uploadFile() completed successfully', ['fileId' => $fileId, 'invoiceId' => $invoiceId, 'type' => $type]);
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
            $this->logDebug('checkFile() called');
            $vendorTable = $this->resolveInputParameter('vendorTable');
            if (!empty($vendorTable)) {
                $this->logDebug('checkFile() vendorTable is set, calling importVendor()', ['vendorTable' => $vendorTable]);
                $this->importVendor();
                }

            $baseURL = $this->getBaseUrl();
            $fileId = $this->getSystemActivityVar('FILEID');
            $type = $this->getSystemActivityVar('TYPE');
            $urlType = $type == 'e_invoice' ? 'e-invoices' : 'invoices';
            $url = "$baseURL/v1/external/documents/$urlType?" . ($urlType == 'e-invoices' ? "documentId=$fileId" : "fileId=$fileId") . "&auditTrail=true";
            $maxCounter = $this->resolveInputParameter('maxCounter');
            $this->logDebug('checkFile() URL built', ['url' => $url, 'fileId' => $fileId, 'type' => $type, 'urlType' => $urlType, 'maxCounter' => $maxCounter]);

            $responseData = $this->makeApiRequest($url, 'GET');
            $response = $responseData['response'];
            $httpcode = $responseData['httpCode'];
            $this->logDebug('checkFile() API response received', ['httpcode' => $httpcode, 'responseLength' => strlen($response)]);

            $counter = $this->getSystemActivityVar('FETCHCOUNTER');
            $counter404 = $this->getSystemActivityVar('COUNTER404');
            $resubTime = $this->resolveInputParameter('intervalOld');
            $this->logDebug('checkFile() counters', ['counter' => $counter, 'counter404' => $counter404, 'resubTime' => $resubTime]);

            if ($counter >= $maxCounter && !in_array($httpcode, array_merge(self::SUCCESS_HTTP_CODES, self::RETRY_HTTP_CODES))) {
                $this->setSystemActivityVar('FETCHCOUNTER', 0);
                $this->logError('File extraction failed after max retries', null, ['counter' => $counter, 'httpcode' => $httpcode]);
                throw new JobRouterException('Error occurred during file extraction after maximum retries (' . $counter . '). HTTP Error Code: ' . $httpcode);
                } else {
                if ($httpcode == 404 && (300 / $resubTime) > $counter404) {
                    $this->setSystemActivityVar('COUNTER404', ++$counter404);
                    $this->logDebug('checkFile() 404 received, incrementing counter404 and returning', ['counter404' => $counter404]);
                    return;
                    } elseif (!in_array($httpcode, self::SUCCESS_HTTP_CODES)) {
                    $this->setSystemActivityVar('FETCHCOUNTER', ++$counter);
                    $this->logDebug('checkFile() non-success HTTP code, incrementing counter and returning', ['counter' => $counter, 'httpcode' => $httpcode]);
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
            $status = $dataItem["status"] ?? '';
            $this->logDebug('checkFile() status parsed', ['status' => $status]);

            try {
                $this->storeOutputParameter('tempJSON', json_encode($data));
                } catch (Exception $e) {
                $this->logError('Failed to store tempJSON output parameter', $e);
                }

            if (in_array($dataItem["status"], self::FALSE_STATES) === false) {
                $check = true;
                $this->logDebug('checkFile() status not in FALSE_STATES, calling storeList()');
                $this->storeList($data);
                } else {
                $this->logDebug('checkFile() status is in FALSE_STATES, skipping storeList()', ['status' => $status]);
                }

            if ($check === true) {
                $this->logDebug('checkFile() check=true, proceeding with cleanup', ['type' => $type]);
                if ($type == "e_invoice") {
                    $pdfPath = $this->getSystemActivityVar('PDFPATH');
                    if ($pdfPath && file_exists($pdfPath)) {
                        try {
                            unlink($pdfPath);
                            $this->logDebug('checkFile() deleted PDF file', ['path' => $pdfPath]);
                            } catch (Exception $e) {
                            $this->logError('Failed to delete PDF file', $e, ['path' => $pdfPath]);
                            }
                        }

                    $reportPath = $this->getSystemActivityVar('REPORTPATH');
                    if ($reportPath && file_exists($reportPath)) {
                        try {
                            unlink($reportPath);
                            $this->logDebug('checkFile() deleted report file', ['path' => $reportPath]);
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
                                $this->logDebug('checkFile() deleted attachment file', ['path' => $attachmentPath, 'index' => $index]);
                                } catch (Exception $e) {
                                $this->logError('Failed to delete attachment file', $e, ['path' => $attachmentPath]);
                                }
                            }
                        $index++;
                        }
                    $this->logDebug('checkFile() e_invoice file cleanup done', ['attachmentCount' => $index]);
                    }

                // Clean up audit trail CSV
                $auditTrailPath = $this->getSystemActivityVar('AUDITTRAILPATH');
                if ($auditTrailPath && file_exists($auditTrailPath)) {
                    try {
                        unlink($auditTrailPath);
                        $this->logDebug('checkFile() deleted audit trail CSV', ['path' => $auditTrailPath]);
                        } catch (Exception $e) {
                        $this->logError('Failed to delete audit trail CSV', $e, ['path' => $auditTrailPath]);
                        }
                    }

                $this->setResubmission(1, "s");
                $this->markActivityAsCompleted();
                $this->logDebug('checkFile() completed, activity marked as completed');
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
            $this->logDebug('importVendor() called');
            $table = $this->resolveInputParameter('vendorTable');
            $listfields = $this->resolveInputParameterListValues('importVendor');
            $fields = ['internalVendorNumber', 'vendorProfileName', 'company', 'street', 'zipCode', 'city', 'country', 'iban', 'taxNumber', 'vatNumber', 'recipientNumber', 'kvk', 'currency', 'blocked', 'sortCode', 'accountNumber'];
            $this->logDebug('importVendor() parameters resolved', ['table' => $table, 'fieldCount' => count($fields)]);

            $list = array();
            foreach ($listfields as $listindex => $listvalue) {
                $list[$listindex] = $listvalue;
                }
            ksort($list);

            if (empty($table)) {
                $this->logDebug('importVendor() table is empty, returning early');
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
            $this->logDebug('importVendor() SQL query built and executed', ['query' => $temp]);

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
            $this->logDebug('importVendor() rows fetched', ['rowCount' => count($payloads)]);

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
            $this->logDebug('importVendor() CSV file created', ['csvFilePath' => $csvFilePath]);

            if ($csvFile === false) {
                throw new JobRouterException('Failed to create vendor CSV file: ' . $csvFilePath);
                }

            foreach ($csvData as $row) {
                fputcsv($csvFile, $row);
                }

            fclose($csvFile);

            $url = $this->getEntityUrl() . "/v2/external/entities/vendors/import";
            $this->logDebug('importVendor() API URL built', ['url' => $url]);

            $payload = [
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
            ];

            // Map API field names to CSV field names for checking values
            $fieldMapping = [
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
                'accountNumber' => 'accountNumber'
            ];

            if (!empty($payloads)) {
                $firstRecord = $payloads[0];
                $overrideFields = [];
                foreach ($fieldMapping as $apiField => $csvField) {
                    if ($apiField === 'blocked') {
                        continue;
                        }

                    $fieldValue = isset($firstRecord[$csvField]) ? $firstRecord[$csvField] : '';
                    $hasValue = !empty($fieldValue);

                    if ($hasValue) {
                        $payload['override' . ucfirst($apiField)] = 'true';
                        $overrideFields[] = $apiField;
                        }
                    }
                $this->logDebug('importVendor() override fields set', ['overrideFields' => $overrideFields]);
                }

            $responseData = $this->makeApiRequest(
                $url,
                'POST',
                $payload
            );

            $response = $responseData['response'];
            $httpcode = $responseData['httpCode'];
            $this->logDebug('importVendor() API response received', ['httpcode' => $httpcode, 'responseLength' => strlen($response)]);
            if (!in_array($httpcode, self::SUCCESS_HTTP_CODES)) {
                $this->logError('Vendor import failed', null, ['httpcode' => $httpcode, 'response' => substr($response, 0, 500)]);
                throw new JobRouterException('Error occurred during vendor update. HTTP Error Code: ' . $httpcode);
                }

            if (file_exists($csvFilePath)) {
                unlink($csvFilePath);
                $this->logDebug('importVendor() CSV file cleaned up');
                }
            $this->logDebug('importVendor() completed successfully');
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
            $this->logDebug('importRecipient() called');
            $table = $this->resolveInputParameter('recipientTable');
            $listfields = $this->resolveInputParameterListValues('importRecipient');
            $fields = ['internalRecipientNumber', 'recipientProfileName', 'country', 'city', 'zipCode', 'street', 'company', 'vatNumber', 'synonyms'];
            $this->logDebug('importRecipient() parameters resolved', ['table' => $table, 'fieldCount' => count($fields)]);

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
            $this->logDebug('importRecipient() rows fetched', ['rowCount' => count($payloads)]);

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
            $this->logDebug('importRecipient() CSV file created', ['csvFilePath' => $csvFilePath]);

            if ($csvFile === false) {
                throw new JobRouterException('Failed to create recipient CSV file: ' . $csvFilePath);
                }

            foreach ($csvData as $row) {
                fputcsv($csvFile, $row);
                }

            fclose($csvFile);

            $url = $this->getEntityUrl() . "/v2/external/entities/recipient-groups/import";
            $this->logDebug('importRecipient() API URL built', ['url' => $url]);

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
            $this->logDebug('importRecipient() API response received', ['httpcode' => $httpcode, 'responseLength' => strlen($response)]);
            if (!in_array($httpcode, self::SUCCESS_HTTP_CODES)) {
                $this->logError('Recipient import failed', null, ['httpcode' => $httpcode, 'response' => substr($response, 0, 500)]);
                throw new JobRouterException('Error occurred during recipient update. HTTP Error Code: ' . $httpcode);
                }

            if (file_exists($csvFilePath)) {
                unlink($csvFilePath);
                $this->logDebug('importRecipient() CSV file cleaned up');
                }
            $this->logDebug('importRecipient() completed successfully');
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
            $this->logDebug('importCostCenter() called');
            $table = $this->resolveInputParameter('costCenterTable');
            $listfields = $this->resolveInputParameterListValues('importCostCenter');
            $fields = ['internalCostCenterNumber', 'costCenterProfileName', 'recipientNumber'];
            $this->logDebug('importCostCenter() parameters resolved', ['table' => $table, 'fieldCount' => count($fields)]);

            $list = array();
            foreach ($listfields as $listindex => $listvalue) {
                $list[$listindex] = $listvalue;
                }
            ksort($list);

            if (empty($table)) {
                $this->logDebug('importCostCenter() table is empty, returning early');
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
            $this->logDebug('importCostCenter() SQL query built and executed', ['query' => $temp]);

            while ($row = $JobDB->fetchRow($result)) {
                $data = [];
                foreach ($fields as $index => $field) {
                    $value = isset($row[$fields[$index]]) ? $row[$fields[$index]] : '';
                    $data[$field] = !empty($value) ? $value : '';
                    }
                $payloads[] = $data;
                }
            $this->logDebug('importCostCenter() rows fetched', ['rowCount' => count($payloads)]);

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
            $this->logDebug('importCostCenter() CSV file created', ['csvFilePath' => $csvFilePath]);

            if ($csvFile === false) {
                throw new JobRouterException('Failed to create cost center CSV file: ' . $csvFilePath);
                }

            foreach ($csvData as $row) {
                fputcsv($csvFile, $row);
                }

            fclose($csvFile);

            $url = $this->getEntityUrl() . "/v1/external/entities/cost-centers/import";
            $this->logDebug('importCostCenter() API URL built', ['url' => $url]);

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
            $this->logDebug('importCostCenter() API response received', ['httpcode' => $httpcode, 'responseLength' => strlen($response)]);
            if (!in_array($httpcode, self::SUCCESS_HTTP_CODES)) {
                $this->logError('Cost center import failed', null, ['httpcode' => $httpcode, 'response' => substr($response, 0, 500)]);
                throw new JobRouterException('Error occurred during costCenter update. HTTP Error Code: ' . $httpcode);
                }

            if (file_exists($csvFilePath)) {
                unlink($csvFilePath);
                $this->logDebug('importCostCenter() CSV file cleaned up');
                }
            $this->logDebug('importCostCenter() completed successfully');
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
            $this->logDebug('fetchInvoices() called');
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
            $this->logDebug('fetchInvoices() status values parsed', ['invoice_status' => $invoice_status, 'statusValues' => $statusValues]);

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
            $this->logDebug('fetchInvoices() URLs built', ['url_invoice' => $url_invoice, 'url_einvoice' => $url_einvoice]);

            foreach ([$url_invoice, $url_einvoice] as $baseUrl) {
                $allIds = [];
                $pageCount = 1;
                $currentPage = 1;
                $this->logDebug('fetchInvoices() processing URL', ['baseUrl' => $baseUrl]);

                // Fetch first page to get pageCount
                $pageParam = (strpos($baseUrl, '?') !== false) ? '&page=1' : '?page=1';
                $url = $baseUrl . $pageParam;

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

                // Get pageCount from response
                $pageCount = isset($data['pageCount']) ? (int) $data['pageCount'] : 1;

                // Collect IDs from first page
                $allIds = array_merge($allIds, $data['data']);
                $this->logDebug('fetchInvoices() first page fetched', ['pageCount' => $pageCount, 'idsOnPage' => count($data['data'])]);

                // Fetch remaining pages if pageCount > 1
                for ($currentPage = 2; $currentPage <= $pageCount; $currentPage++) {
                    $pageParam = (strpos($baseUrl, '?') !== false) ? "&page=$currentPage" : "?page=$currentPage";
                    $url = $baseUrl . $pageParam;

                    try {
                        $responseData = $this->makeApiRequest($url, 'GET');
                        $response = $responseData['response'];
                        } catch (JobRouterException $e) {
                        $this->logError('cURL fetch invoices failed on page', $e, ['url' => $url, 'page' => $currentPage]);
                        continue; // Continue to next page
                        }

                    $data = json_decode($response, TRUE);

                    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                        $this->logError('Failed to parse fetch invoices response', null, ['json_error' => json_last_error_msg(), 'url' => $url, 'page' => $currentPage]);
                        continue;
                        }

                    if (!isset($data['data']) || !is_array($data['data'])) {
                        $this->logError('Invalid fetch invoices response structure', null, ['url' => $url, 'page' => $currentPage]);
                        continue;
                        }

                    $allIds = array_merge($allIds, $data['data']);
                    $this->logDebug('fetchInvoices() page fetched', ['page' => $currentPage, 'idsOnPage' => count($data['data'])]);
                    }

                $this->logDebug('fetchInvoices() all pages fetched', ['totalIds' => count($allIds)]);

                // Process all collected IDs
                $table_head = $this->resolveInputParameter('table_head');
                $stepID = $this->resolveInputParameter('stepID');
                $fileid = $this->resolveInputParameter('fileid');
                $this->logDebug('fetchInvoices() DB update params', ['table_head' => $table_head, 'stepID' => $stepID, 'fileid' => $fileid]);

                $currentTime = new DateTime();
                $currentTime->modify('+10 seconds');
                $formattedTime = $currentTime->format('Y-m-d H:i:s');

                foreach ($allIds as $id) {
                    try {
                        $dbType = $this->getDatabaseType();
                        if ($dbType === "MySQL") {
                            $query = "
                        UPDATE JRINCIDENTS j
                        JOIN $table_head t ON t.step_id = j.process_step_id
                        SET j.resubmission_date = '$formattedTime'
                        WHERE t.step = $stepID AND t.$fileid = '$id';
                        ";
                            } elseif ($dbType === "MSSQL") {
                            $query = "
                        UPDATE j
                        SET j.resubmission_date = '$formattedTime'
                        FROM JRINCIDENTS AS j
                        JOIN $table_head AS t ON t.step_id = j.process_step_id
                        WHERE t.step = $stepID AND t.$fileid = '$id';
                        ";
                            } else {
                            $this->logError('Unsupported database type', null, ['dbType' => $dbType]);
                            throw new JobRouterException("Unsupported database type: " . $dbType);
                            }

                        $jobDB = $this->getJobDB();
                        $jobDB->exec($query);
                        $this->logDebug('fetchInvoices() DB update executed', ['id' => $id, 'dbType' => $dbType]);
                        } catch (Exception $e) {
                        $this->logError('Failed to update resubmission date for invoice', $e, ['id' => $id]);
                        // Continue processing other IDs
                        }
                    }
                $this->logDebug('fetchInvoices() URL processing completed', ['baseUrl' => $baseUrl, 'processedIds' => count($allIds)]);
                }

            $this->logDebug('fetchInvoices() completed');
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
        $this->logDebug('getDatabaseType() called');
        $jobDB = $this->getJobDB();
        try {
            $result = $jobDB->query("SELECT VERSION()");
            $row = $jobDB->fetchAll($result);
            if (is_string($row[0]["VERSION()"])) {
                $this->logDebug('getDatabaseType() detected MySQL');
                return "MySQL";
                }
            } catch (Exception $e) {
            $this->logError('MySQL version query failed', $e);
            $this->logDebug('getDatabaseType() MySQL detection failed', ['error' => $e->getMessage()]);
            }

        try {
            $result = $jobDB->query("SELECT @@VERSION");
            $row = $jobDB->fetchAll($result);
            if (is_string(reset($row[0]))) {
                $this->logDebug('getDatabaseType() detected MSSQL');
                return "MSSQL";
                }
            } catch (Exception $e) {
            $this->logError('MSSQL version query failed', $e);
            $this->logDebug('getDatabaseType() MSSQL detection failed', ['error' => $e->getMessage()]);
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
            $this->logDebug('convertAuditTrailToCSV() called', ['entries' => count($auditTrail), 'savePath' => $savePath, 'fileName' => $fileName]);
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
            $this->logDebug('convertAuditTrailToCSV() completed', ['csvFilePath' => $csvFilePath, 'rowsWritten' => count($auditTrail)]);
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
            $this->logDebug('storeList() called', ['type' => $type]);

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
            $this->logDebug('storeList() recipientDetails resolved', ['attributeCount' => count($attributes1)]);
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

            $this->logDebug('storeList() recipientDetails stored');

            $attributes2 = $this->resolveOutputParameterListAttributes('vendorDetails'); //vendorDetails
            $this->logDebug('storeList() vendorDetails resolved', ['attributeCount' => count($attributes2)]);
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

            $this->logDebug('storeList() vendorDetails stored');

            $attributes3 = $this->resolveOutputParameterListAttributes('invoiceDetails'); //invoiceDetails
            $this->logDebug('storeList() invoiceDetails resolved', ['attributeCount' => count($attributes3)]);

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

            $this->logDebug('storeList() invoiceDetails stored');

            $attributes4 = $this->resolveOutputParameterListAttributes('auditTrailDetails'); //auditTrailDetails
            $this->logDebug('storeList() auditTrailDetails resolved', ['attributeCount' => count($attributes4)]);
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

            $this->logDebug('storeList() auditTrailDetails stored');

            $attributes5 = $this->resolveOutputParameterListAttributes('rejectionDetails'); //rejectionDetails
            $this->logDebug('storeList() rejectionDetails resolved', ['attributeCount' => count($attributes5)]);

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


            $this->logDebug('storeList() rejectionDetails stored');

            $attributes6 = $this->resolveOutputParameterListAttributes('attachments'); //attachments
            $this->logDebug('storeList() attachments resolved', ['attributeCount' => count($attributes6)]);

            if ($type == "e_invoice") {
                //pdf
                $urlPDF = $dataItem['eInvoicePdfPath'];
                $this->logDebug('storeList() downloading e-invoice PDF', ['urlPDF' => $urlPDF]);
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
                $this->logDebug('storeList() e-invoice PDF saved', ['path' => $eInvoicePDF]);

                //report
                $urlReport = $dataItem['reportFilePath'];
                $this->logDebug('storeList() downloading e-invoice report', ['urlReport' => $urlReport]);

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
                $this->logDebug('storeList() e-invoice report saved', ['path' => $eInvoiceReport]);

                //attachments
                $attachments = $dataItem['attachments'] ?? [];
                $this->logDebug('storeList() processing e-invoice attachments', ['count' => count($attachments)]);
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

            $this->logDebug('storeList() attachments file processing completed');

            $attributes7 = $this->resolveOutputParameterListAttributes('positionDetails'); //positionDetails
            $this->logDebug('storeList() positionDetails resolved', ['attributeCount' => count($attributes7)]);
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

            $this->logDebug('storeList() positionDetails stored', ['lineItemCount' => count($invoiceLine)]);

            $attributes8 = $this->resolveOutputParameterListAttributes('workflowDetails'); //workflowDetails
            $this->logDebug('storeList() workflowDetails resolved', ['attributeCount' => count($attributes8)]);

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
            $this->logDebug('storeList() completed successfully');
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
        $this->logDebug('getUDL() called', ['udl' => $udl, 'elementID' => $elementID]);
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

        if ($elementID == 'classificationDetails') {
            return [
                ['name' => '-', 'value' => ''],
                ['name' => DC_CLASSIFIERNUMBER, 'value' => 'documentClassifierNumber'],
                ['name' => DC_DOCUMENTTYPE, 'value' => 'documentType'],
                ['name' => DC_VENDORCOMPANYNAME, 'value' => 'vendorCompanyName'],
                ['name' => DC_RECIPIENTCOMPANYNAME, 'value' => 'recipientCompanyName'],
                ['name' => DC_ISSUEDATE, 'value' => 'issueDate']
            ];
            }

        return null;
        }
    }
//v2.2

