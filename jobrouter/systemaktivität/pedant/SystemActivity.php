<?php
class pedantSystemActivity extends AbstractSystemActivityAPI
{
    private $recipientOutputFileName = "pedantRecipientOutput.csv";
    private $vendorOutputFileName = "pedantVendorOutput.csv";
    private $costCenterOutputFileName = "pedantCostCenterOutput.csv";
    private $demoURL  = "https://api.demo.pedant.ai";
    private $productiveURL = "https://api.pedant.ai";
    private $maxFileSize = 20;

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

    protected function pedant()
    {
        $this->maxFileSize = $this->resolveInputParameter('maxFileSize') ?: 20;
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
    }

    protected function importVendorCSV()
    {
        $this->importVendor();
        $this->markActivityAsCompleted();
    }

    protected function importRecipientCSV()
    {
        $this->importRecipient();
        $this->markActivityAsCompleted();
    }

    protected function importCostCenterCSV()
    {
        $this->importCostCenter();
        $this->markActivityAsCompleted();
    }

    protected function fetchData()
    {
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
    }


    protected function uploadFile()
    {
        $curl = curl_init();
        $file = $this->getUploadPath() . $this->resolveInputParameter('inputFile');
        $fileExtension = pathinfo($file, PATHINFO_EXTENSION);
        $fileSizeB = filesize($file);
        $fileSizeMB = $fileSizeB / (1024 * 1024);
        if ($fileSizeMB > $this->maxFileSize) {
            throw new JobRouterException("File size exceeds the maximum limit of $this->maxFileSize MB. Actual size: $fileSizeMB MB.");
        }

        $url = ($this->resolveInputParameter('demo') == '1' ? $this->demoURL : $this->productiveURL) .
            (strtolower($fileExtension) == 'xml' ? "/v2/external/documents/invoices/upload" : ($this->resolveInputParameter('zugferd') == '1' ? "/v1/external/documents/invoices/upload" : "/v2/external/documents/invoices/upload"));

        $validFlags = ['normal', 'check_extraction', 'skip_review', 'force_skip'];

        $flag = $this->resolveInputParameter('flag');
        if (strtolower($fileExtension) == 'xml') {
            $flagXML = $this->resolveInputParameter('flagXML');
            if (!empty($flagXML)) {
                $flag = $flagXML;
                if (!in_array($flag, $validFlags)) {
                    throw new Exception('Invalid input parameter value for FLAGXML: ' . $flag);
                }
            }
        }

        if (!in_array($flag, $validFlags)) {
            throw new Exception('Invalid input parameter value for FLAG: ' . $flag);
        }

        $action = $flag;

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => [
                'file' => new CURLFILE($file),
                'recipientInternalNumber' => $this->resolveInputParameter('internalNumber'),
                'action' => $action,
                'note' => $this->resolveInputParameter('note'),
            ],
            CURLOPT_HTTPHEADER => ['X-API-KEY: ' . $this->resolveInputParameter('api_key')],
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0
        ));
        $response = curl_exec($curl);

        $data = json_decode($response, TRUE);

        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        $maxCounter = $this->resolveInputParameter('maxCounter');
        $counter = $this->getSystemActivityVar('UPLOADCOUNTER');

        if ($counter > $maxCounter && !in_array($httpcode, [200, 201, 404, 503, 502, 500, 0])) {
            $this->setSystemActivityVar('UPLOADCOUNTER', 0);
            throw new JobRouterException('Error occurred during upload after maximum retries (' . $counter . '). HTTP Error Code: ' . $httpcode);
        } else {
            $this->setSystemActivityVar('UPLOADCOUNTER', ++$counter);
        }

        try {
            $this->storeOutputParameter('counterSummary', "Upload attempts: {$counter}, HTTP Code: {$httpcode}");
        } catch (Exception $e) {
        }

        curl_close($curl);

        $fileId =  $data['files'][0]['fileId'];
        $invoiceId = $data['files'][0]['invoiceId'];
        $type = $data['files'][0]['type'];

        $this->storeOutputParameter('fileID', $fileId);
        $this->storeOutputParameter('invoiceID', $invoiceId);
        $this->setSystemActivityVar('FILEID', $fileId);
        $this->setSystemActivityVar('FETCHCOUNTER', 0);
        $this->setSystemActivityVar('TYPE', $type);
        $this->setSystemActivityVar('COUNTER404', 0);
    }
    protected function checkFile()
    {
        if (!empty($this->resolveInputParameter('vendorTable'))) {
            $this->importVendor();
        }

        $baseURL = $this->resolveInputParameter('demo') == '1' ? $this->demoURL : $this->productiveURL;
        $fileId = $this->getSystemActivityVar('FILEID');
        $type = $this->getSystemActivityVar('TYPE') == 'e_invoice' ? 'e-invoices' : 'invoices';
        $url = "$baseURL/v1/external/documents/$type?" . ($type == 'e-invoices' ? "documentId=$fileId" : "fileId=$fileId") . "&auditTrail=true";
        $maxCounter = $this->resolveInputParameter('maxCounter');

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => ['X-API-KEY: ' . $this->resolveInputParameter('api_key')],
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0
        ));

        $response = curl_exec($curl);

        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        $counter = $this->getSystemActivityVar('FETCHCOUNTER');
        $counter404 = $this->getSystemActivityVar('COUNTER404');
        $resubTime = $this->resolveInputParameter('intervalOld');

        if ($counter > $maxCounter && !in_array($httpcode, [200, 404, 503, 502, 500, 0])) {
            $this->setSystemActivityVar('FETCHCOUNTER', 0);
            throw new JobRouterException('Error occurred during file extraction after maximum retries (' . $counter . '). HTTP Error Code: ' . $httpcode);
        } else {
            if ($httpcode == 404 && (300 / $resubTime) > $counter404) {
                $this->setSystemActivityVar('COUNTER404', ++$counter404);
            } elseif ($httpcode != 200) {
                $this->setSystemActivityVar('FETCHCOUNTER', ++$counter);
            }
        }

        try {
            $this->storeOutputParameter('counterSummary', "Fetch attempts: {$counter}, HTTP Code: {$httpcode}");
        } catch (Exception $e) {
        }

        curl_close($curl);

        $data = json_decode($response, TRUE);
        $dataItem = $data["data"][0];
        $check = false;

        $falseStates = ['processing', 'failed', 'uploaded', ''];

        if ($dataItem["status"] == "uploaded") {
            $this->storeOutputParameter('tempJSON', json_encode($data));
        }

        if (in_array($dataItem["status"], $falseStates) === false) {
            $check = true;
            $this->storeList($data);
        }

        if ($check === true) {
            if ($type = "e_invoice") {
                $pdfPath = $this->getSystemActivityVar('PDFPATH');
                if (file_exists($pdfPath)) {
                    unlink($pdfPath);
                }

                $reportPath = $this->getSystemActivityVar('REPORTPATH');
                if (file_exists($reportPath)) {
                    unlink($reportPath);
                }

                $index = 0;
                while (true) {
                    $attachmentPath = $this->getSystemActivityVar('ATTACHMENTPATH' . $index);
                    if (!$attachmentPath) {
                        break;
                    }
                    if (file_exists($attachmentPath)) {
                        unlink($attachmentPath);
                    }
                    $index++;
                }
            }

            // Clean up audit trail CSV
            $auditTrailPath = $this->getSystemActivityVar('AUDITTRAILPATH');
            if ($auditTrailPath && file_exists($auditTrailPath)) {
                unlink($auditTrailPath);
            }

            $this->setResubmission(1, "s");
            $this->markActivityAsCompleted();
        }
    }

    protected function importVendor()
    {
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
        $payload = [];

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

        foreach ($csvData as $row) {
            fputcsv($csvFile, $row);
        }

        fclose($csvFile);

        $url = $this->resolveInputParameter('demo') == '1' ? "$this->demoURL/v2/external/entities/vendors/import" : 'https://entity.api.pedant.ai/v2/external/entities/vendors/import';

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => array(
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
                'currency' => 'currrency',
                'blocked' => 'blocked',
                'sortCode' => 'sortCode',
                'accountNumber' => 'accountNumber',
                'file' => new CURLFILE($csvFilePath)
            ),
            CURLOPT_HTTPHEADER => array('x-api-key: ' . $this->resolveInputParameter('api_key')),
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0
        ));

        curl_exec($curl);

        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($httpcode != 200) {
            throw new JobRouterException('Error occurred during vendor update. HTTP Error Code: ' . $httpcode);
        }

        curl_close($curl);
        unlink($csvFilePath);
    }

    protected function importRecipient()
    {
        $table = $this->resolveInputParameter('recipientTable');
        $listfields = $this->resolveInputParameterListValues('importRecipient');
        $fields = ['internalRecipientNumber', 'recipientProfileName', 'country', 'city', 'zipCode', 'street', 'company', 'vatNumber'];

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
        $payload = [];

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

        foreach ($csvData as $row) {
            fputcsv($csvFile, $row);
        }

        fclose($csvFile);

        $url = $this->resolveInputParameter('demo') == '1' ? "$this->demoURL/v2/external/entities/recipient-groups/import" : 'https://entity.api.pedant.ai/v2/external/entities/recipient-groups/import';

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => array(
                'internalNumber' => 'internalRecipientNumber',
                'profileName' => 'recipientProfileName',
                'country' => 'country',
                'city' => 'city',
                'zipCode' => 'zipCode',
                'street' => 'street',
                'name' => 'company',
                'vatNumber' => 'vatNumber',
                'file' => new CURLFILE($csvFilePath)
            ),
            CURLOPT_HTTPHEADER => array('x-api-key: ' . $this->resolveInputParameter('api_key')),
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0
        ));

        curl_exec($curl);

        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($httpcode != 200) {
            throw new JobRouterException('Error occurred during recipient update. HTTP Error Code: ' . $httpcode);
        }

        curl_close($curl);
        unlink($csvFilePath);
    }

    protected function importCostCenter()
    {
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
        $payload = [];

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

        foreach ($csvData as $row) {
            fputcsv($csvFile, $row);
        }

        fclose($csvFile);

        $url = $this->resolveInputParameter('demo') == '1' ? "$this->demoURL/v1/external/entities/cost-centers/import" : 'https://entity.api.pedant.ai/v1/external/entities/cost-centers/import';

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => array(
                'internalNumber' => 'internalCostCenterNumber',
                'name' => 'costCenterProfileName',
                'recipientNumber' => 'recipientNumber',
                'file' => new CURLFILE($csvFilePath)
            ),
            CURLOPT_HTTPHEADER => array('x-api-key: ' . $this->resolveInputParameter('api_key')),
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0
        ));

        curl_exec($curl);

        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($httpcode != 200) {
            throw new JobRouterException('Error occurred during costCenter update. HTTP Error Code: ' . $httpcode);
        }

        curl_close($curl);
        unlink($csvFilePath);
    }

    /**
     * Fetches invoices from the Pedant API and updates the resubmission date in the database.
     *
     * @throws Exception If the database type is unsupported or if the query fails.
     */
    protected function fetchInvoices()
    {
        $curl = curl_init();

        $baseURL = $this->resolveInputParameter('demo') == '1' ? $this->demoURL : $this->productiveURL;
        $url_invoice = "$baseURL/v1/external/documents/invoices/to-export";
        $url_einvoice = "$baseURL/v1/external/documents/e-invoices/to-export";

        foreach ([$url_invoice, $url_einvoice] as $url) {
            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => ['X-API-KEY: ' . $this->resolveInputParameter('api_key')],
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0
            ));
            $response = curl_exec($curl);

            $data = json_decode($response, TRUE);
            $ids = $data["data"];
            $table_head = $this->resolveInputParameter('table_head');
            $stepID = $this->resolveInputParameter('stepID');

            $currentTime = new DateTime();
            $currentTime->modify('+10 seconds');
            $formattedTime = $currentTime->format('Y-m-d H:i:s');

            foreach ($ids as $id) {
                $dbType = $this->getDatabaseType();
                if ($dbType === "MySQL") {
                    $query = "
                UPDATE jrincidents j
                JOIN $table_head t ON t.step_id = j.process_step_id
                SET j.resubmission_date = '$formattedTime'
                WHERE t.step = $stepID AND t.T_FILEID = '$id';
                ";
                } elseif ($dbType === "MSSQL") {
                    $query = "
                UPDATE j
                SET j.resubmission_date = '$formattedTime'
                FROM jrincidents AS j
                JOIN $table_head AS t ON t.step_id = j.process_step_id
                WHERE t.step = $stepID AND t.T_FILEID = '$id';
                ";
                } else {
                    throw new Exception("Unsupported database type");
                }
                try {
                    $jobDB = $this->getJobDB();
                    $jobDB->exec($query);
                } catch (Exception $e) {
                    throw new Exception("Failed to execute query: " . $e->getMessage());
                }
            }
        }
        curl_close($curl);
    }

    /**
     * Determines the database type based on the version query.
     *
     * @return string The database type, either "MySQL" or "MSSQL".
     * @throws Exception If the database type cannot be detected.
     */
    public function getDatabaseType()
    {
        $jobDB = $this->getJobDB();
        try {
            $result = $jobDB->query("SELECT VERSION()");
            $row = $jobDB->fetchAll($result);
            if (is_string($row[0]["VERSION()"])) {
                return "MySQL";
            }
        } catch (Exception $e) {
        }

        try {
            $result = $jobDB->query("SELECT @@VERSION");
            $row = $jobDB->fetchAll($result);
            if (is_string(reset($row[0]))) {
                return "MSSQL";
            }
        } catch (Exception $e) {
        }
        throw new Exception("Database could not be detected");
    }

    /**
     * Converts an audit trail array to CSV format and saves it to a file.
     *
     * @param array $auditTrail The audit trail data array.
     * @param string $savePath The path to save the CSV file.
     * @param string $fileName The base name for the CSV file.
     * @return string The full path to the saved CSV file.
     */
    protected function convertAuditTrailToCSV($auditTrail, $savePath, $fileName)
    {
        // Remove file extension from fileName
        $fileNameWithoutExtension = pathinfo($fileName, PATHINFO_FILENAME);

        $csvFilePath = $savePath . "/" . $fileNameWithoutExtension . "_AUDITTRAIL.csv";
        $csvFile = fopen($csvFilePath, 'w');

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
    }

    /**
     * Stores the list of invoice details in the system activity.
     *
     * @param array $data The data containing invoice details.
     */
    public function storeList($data)
    {
        $type = $this->getSystemActivityVar('TYPE');

        $dataItem = $data['data'][0];
        $eInvoiceFields = $dataItem['eInvoiceFields'];
        $paymentInstructionsCreditTransfer = $eInvoiceFields['paymentInstructionsCreditTransfer'][0] ?? [];
        $document = $dataItem['document'];
        $vatBreakdown = $eInvoiceFields['vatBreakdown'][0];
        $auditTrailItem = $dataItem['auditTrail'][0];
        $taxRates = $dataItem['taxRates'];
        $vatBreakdown = $eInvoiceFields['vatBreakdown'] ?? [];
        $recipientEntity = $dataItem['recipientEntity'];
        $vendorEntity = $dataItem['vendorEntity'];

        $attributes1 = $this->resolveOutputParameterListAttributes('recipientDetails'); //recipientDetails
        $values1 = ($type == "e_invoice") ? [
            'recipientCompanyName' => $eInvoiceFields['recipientName'],
            'recipientName' => $eInvoiceFields['recipientContactPersonName'],
            'recipientStreet' => $eInvoiceFields['recipientPostalAddressAddressLines'][0],
            'recipientZipCode' => $eInvoiceFields['recipientPostalAddressPostCode'],
            'recipientCity' => $eInvoiceFields['recipientPostalAddressCity'],
            'recipientCountry' => $eInvoiceFields['recipientPostalAddressCountryCode'],
            'recipientVatNumber' => $eInvoiceFields['recipientVatIdentifier'],
            'recipientInternalNumber' => $recipientEntity['internalNumber']
        ] : [
            'recipientCompanyName' => $dataItem["recipientCompanyName"],
            'recipientName' => $dataItem["recipientName"],
            'recipientStreet' => $dataItem["recipientStreet"],
            'recipientZipCode' => $dataItem["recipientZipCode"],
            'recipientCity' => $dataItem["recipientCity"],
            'recipientCountry' => $dataItem["recipientCountry"],
            'recipientVatNumber' => $dataItem["recipientVatNumber"],
            'recipientInternalNumber' => $dataItem["recipientEntity"]["internalNumber"]
        ];

        foreach ($attributes1 as $attribute) {
            $this->setTableValue($attribute['value'], $values1[$attribute['id']]);
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
            'vendorEmail' => $eInvoiceFields['vendorContactEmailAddress'],
            'vendorDeliveryPeriod' => '',
            'vendorAccountNumber' => '',
            'vendorInternalNumber' => $vendorEntity['internalNumber']
        ] : [
            'vendorBankNumber' => $dataItem["bankNumber"],
            'vendorVatNumber' => $dataItem["vat"],
            'vendorTaxNumber' => $dataItem["taxNumber"],
            'vendorCompanyName' => $dataItem["vendorCompanyName"],
            'vendorStreet' => $dataItem["vendorStreet"],
            'vendorZipCode' => $dataItem["vendorZipCode"],
            'vendorCity' => $dataItem["vendorCity"],
            'vendorCountry' => $dataItem["vendorCountry"],
            'vendorDeliveryPeriod' => $dataItem["deliveryPeriod"],
            'vendorAccountNumber' => $dataItem["accountNumber"],
            'vendorEmail' => $dataItem["vendorEmail"],
            'vendorInternalNumber' => $dataItem["vendorEntity"]["internalNumber"],
            'vendorCompanyRegistrationNumber' => $dataItem['KVKNumber']
        ];

        foreach ($attributes2 as $attribute) {
            $this->setTableValue($attribute['value'], $values2[$attribute['id']]);
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
            'taxRate1' => $taxRates[0]["subNetAmount"] . ";" . $taxRates[0]["subTaxAmount"] . ";" . $taxRates[0]["subTaxRate"],
            'taxRate2' => $taxRates[1]["subNetAmount"] . ";" . $taxRates[1]["subTaxAmount"] . ";" . $taxRates[1]["subTaxRate"],
            'taxRate3' => $taxRates[2]["subNetAmount"] . ";" . $taxRates[2]["subTaxAmount"] . ";" . $taxRates[2]["subTaxRate"],
            'taxRate4' => $taxRates[3]["subNetAmount"] . ";" . $taxRates[3]["subTaxAmount"] . ";" . $taxRates[3]["subTaxRate"],
            'taxRate5' => $taxRates[4]["subNetAmount"] . ";" . $taxRates[4]["subTaxAmount"] . ";" . $taxRates[4]["subTaxRate"],
            'invoiceNumber' => $dataItem["invoiceNumber"],
            'date' => date("Y-m-d", strtotime(str_replace(".", "-", $dataItem["issueDate"]))) . ' 00:00:00.000',
            'netAmount' => $dataItem["netAmount"],
            'taxAmount' => $dataItem["taxAmount"],
            'grossAmount' => $dataItem["amount"],
            'taxRate' => $dataItem["taxRate"],
            'projectNumber' => $dataItem["projectNumber"],
            'purchaseOrder' => $dataItem["purchaseOrder"],
            'purchaseDate' => $dataItem["purchaseDate"],
            'hasDiscount' => $dataItem["hasDiscount"],
            'refund' => $dataItem["refund"],
            'discountPercentage' => $dataItem["discountPercentage"],
            'discountAmount' => $dataItem["discountAmount"],
            'discountDate' => $dataItem["discountDate"],
            'invoiceType' => $dataItem["invoiceType"],
            'type' => $type,
            'note' => $dataItem["file"]["note"],
            'status' => $dataItem["status"],
            'currency' => $dataItem["currency"],
            'resolvedIssuesCount' => $dataItem["resolvedIssuesCount"],
            'hasProcessingIssues' => $dataItem["hasProcessingIssues"],
            'deliveryDate' => date("Y-m-d", strtotime(str_replace(".", "-", $dataItem["deliveryDate"]))) . ' 00:00:00.000',
        ];

        foreach ($attributes3 as $attribute) {
            $this->setTableValue($attribute['value'], $values3[$attribute['id']]);
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
            $this->setTableValue($attribute['value'], $values4[$attribute['id']]);
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
            $this->setTableValue($attribute['value'], $values5[$attribute['id']]);
        }


        $attributes6 = $this->resolveOutputParameterListAttributes('attachments'); //attachments

        if ($type == "e_invoice") {
            //pdf
            $urlPDF = $dataItem['eInvoicePdfPath'];
            $tempPath = $this->getTempPath();
            $tempFileName = $dataItem['fileName'];
            $savePath = $tempPath . "/pedant";

            if (!is_dir($savePath)) {
                mkdir($savePath, 0777, true);
            }

            $ch = curl_init($urlPDF);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

            $dataPDF = curl_exec($ch);
            curl_close($ch);

            $eInvoicePDF = $savePath . "/" . $tempFileName . "_PDF_.pdf";
            file_put_contents($eInvoicePDF, $dataPDF);
            $this->setSystemActivityVar('PDFPATH', $eInvoicePDF);

            //report
            $urlReport = $dataItem['reportFilePath'];

            if (!is_dir($savePath)) {
                mkdir($savePath, 0777, true);
            }

            $ch = curl_init($urlReport);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

            $dataReport = curl_exec($ch);
            curl_close($ch);

            $eInvoiceReport = $savePath . "/" . $tempFileName . "_REPORT_.xml";
            file_put_contents($eInvoiceReport, $dataReport);
            $this->setSystemActivityVar('REPORTPATH', $eInvoiceReport);

            //attachments
            $attachments = $dataItem['attachments'];
            $attachmentFiles = [];
            foreach ($attachments as $index => $url) {
                if ($index >= 3) {
                    break;
                }
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

                $dataAttachment = curl_exec($ch);
                curl_close($ch);

                if ($dataAttachment !== false) {
                    $attachmentPath = $savePath . "/" . $tempFileName . "_ATTACHMENT_" . ($index + 1) . ".pdf";
                    file_put_contents($attachmentPath, $dataAttachment);
                    $this->setSystemActivityVar('ATTACHMENTPATH' . $index, $attachmentPath);
                    $attachmentFiles[] = $attachmentPath;
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
            $value = $values6[$attribute['id']];
            if ($attribute['subtable'] == "") {
                $this->attachFile($attribute['value'], $value);
            } else {
                $attachments = is_array($value) ? $value : [$value];
                foreach ($attachments as $attachment) {
                    $this->attachSubtableFile($attribute['subtable'], $this->getSubtableCount($attribute['subtable']) + 1, $attribute['value'], $attachment);
                }
            }
        }

        $attributes7 = $this->resolveOutputParameterListAttributes('positionDetails'); //positionDetails

        $values7 = [
            'positionNumber' => [],
            'singleNetPrice' => [],
            'singleNetAmount' => [],
            'quantity' => [],
            'unitOfMeasureCode' => [],
            'articleNumber' => [],
            'articleName' => [],
            'itemDescription' => [],
            'vatRatePerLine' => []
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

        foreach ($invoiceLine as $index => $line) {
            $rowID = $this->getSubtableCount($attributes7[0]['subtable']) + 1;
            foreach ($attributes7 as $attribute) {
                $value = $values7[$attribute['id']][$index];
                $this->setSubtableValue($attribute['subtable'], $rowID + $index, $attribute['value'], $value);
            }
        }

        $attributes8 = $this->resolveOutputParameterListAttributes('workflowDetails'); //workflowDetails

        $workflows = $dataItem['workflows'];

        $values8 = [
            'direkt' => !empty($workflows) && ($workflows[0]['name'] ?? '') === 'Direkt' ? 1 : 0
        ];

        foreach ($attributes8 as $attribute) {
            $this->setTableValue($attribute['value'], $values8[$attribute['id']]);
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
//Version 1.8.1
