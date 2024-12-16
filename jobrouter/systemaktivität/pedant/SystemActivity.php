<?php
class pedantSystemActivity extends AbstractSystemActivityAPI
{
    private $outputFileName = "pedantOutput.csv";
    private $demoURL  = "https://api.demo.pedant.ai";
    private $productiveURL = "https://api.pedant.ai";
    private $maxFileSize = 2; // in MegaBytes

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

    protected function pedant() //TODO runs one iteration more than needed
    {
        $this->setResubmission($this->resolveInputParameter('new') ? 17520 : $this->resolveInputParameter('intervalOld'), $this->resolveInputParameter('new') ? 'h' : 'm');
        date_default_timezone_set("Europe/Berlin");

        if ($this->getSystemActivityVar('FILEID')) {
            $this->checkFile();
        }

        if (!$this->getSystemActivityVar('FILEID')) {
                $this->uploadFile();
        }
    }

    protected function pedantData()
    {
        $this->postVendorDetails();
        $this->markActivityAsCompleted();
    }

    protected function fetchData()
    {
        $this->markActivityAsPending();

        $interval = $this->resolveInputParameter('interval');
        $worktime = $this->resolveInputParameter('worktime');
        $weekend = $this->resolveInputParameter('weekend');

        date_default_timezone_set('Europe/Berlin');
        list($startTime, $endTime) = array_map('intval', explode(',', $worktime));
        list($currentHour, $currentDayOfWeek) = [(int) (new DateTime())->format('G'), (int) (new DateTime())->format('w')];

        if($weekend){
            if ($currentHour >= $startTime && $currentHour < $endTime) {
                $this->setResubmission($interval, 'm');
            } else {
                $hoursToStart = ($currentHour < $startTime) ? $startTime - $currentHour : 24 - $currentHour + $startTime;
                $this->setResubmission($hoursToStart, 'h');
            }
        }else{
            if ($currentDayOfWeek >= 1 && $currentDayOfWeek <= 5) {
                if ($currentHour >= $startTime && $currentHour < $endTime) {
                    $this->setResubmission($interval, 'm');
                } else {
                    $hoursToStart = ($currentHour < $startTime) ? $startTime - $currentHour : 24 - $currentHour + $startTime;
                    $this->setResubmission($hoursToStart, 'h');
                }
            } else {
                $hoursToStart = ($currentHour < $startTime) ? $startTime - $currentHour : 24 - $currentHour + $startTime;
                if ($currentDayOfWeek == 6) {$hoursToStart += 24;}
                $this->setResubmission($hoursToStart, 'h');
            }
        }
        $this->fetchInvoices();
    }


    protected function uploadFile()
    {
        $curl = curl_init();
        $file = $this->getUploadPath() . $this->resolveInputParameter('inputFile');

        $fileSizeB = filesize($file);
        $fileSizeMB = $fileSizeB / (1024 * 1024);
        if ($fileSizeMB > $this->maxFileSize) {
            throw new JobRouterException("File size exceeds the maximum limit of $this->maxFileSize MB. Actual size: $fileSizeMB MB.");
        }

        $url = ($this->resolveInputParameter('demo') == '1' ? $this->demoURL : $this->productiveURL) . "/v2/external/documents/invoices/upload";

        $validFlags = ['normal', 'check_extraction', 'skip_review', 'force_skip'];
        $flag = $this->resolveInputParameter('flag');
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
        if ($httpcode != 200 && $httpcode != 201) {
            throw new JobRouterException('Error occurred during file upload. HTTP Error Code: ' . $httpcode);
        }
        curl_close($curl);
        
        $fileId =  $data['files'][0]['fileId'];
        $invoiceId = $data['files'][0]['invoiceId'];
        $type = $data['files'][0]['type'];

        $this->storeOutputParameter('fileID', $fileId);
        $this->storeOutputParameter('invoiceID', $invoiceId);
        $this->setSystemActivityVar('FILEID', $fileId);
        $this->setSystemActivityVar('COUNTER', 0);
        $this->setSystemActivityVar('TYPE', $type);
    }
    protected function checkFile()
    {
        if (!empty($this->resolveInputParameter('vendorTable'))) {
            $this->postVendorDetails();
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

        $counter = $this->getSystemActivityVar('COUNTER');

        if ($counter > $maxCounter && !in_array($httpcode, [200, 404, 503, 502, 500, 0])) {
            $this->setSystemActivityVar('COUNTER', 0);
            throw new JobRouterException('Error occurred during file extraction after maximum retries (' . $counter . '). HTTP Error Code: ' . $httpcode);
        } else {
            $this->setSystemActivityVar('COUNTER', ++$counter);
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
            if($type = "e_invoice"){
                unlink($this->getSystemActivityVar('PDFPATH'));
                unlink($this->getSystemActivityVar('REPORTPATH'));
    
                $index = 0;
                while (true) {
                    $attachmentPath = $this->getSystemActivityVar('ATTACHMENTPATH' . $index);
                    if (!$attachmentPath) {break;}
                    if (file_exists($attachmentPath)) {
                        unlink($attachmentPath);
                    }
                    $index++;
                }
            }
            $this->markActivityAsCompleted();
        }
    }

    protected function postVendorDetails()
    {
        $table = $this->resolveInputParameter('vendorTable');
        $listfields = $this->resolveInputParameterListValues('postVendor');
        $fields = ['internalVendorNumber', 'vendorProfileName', 'company', 'street', 'zipCode', 'city', 'country', 'iban', 'taxNumber', 'vatNumber', 'recipientNumber', 'kvk', 'currency'];

        $list = array();
        foreach ($listfields as $listindex => $listvalue) {
            $list[$listindex] = $listvalue;
        }
        ksort($list);

        if (empty($table)) {return;}

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
                    $data[$field] = isset($row[$fields[$index]]) && !empty($row[$fields[$index]]) ? $row[$fields[$index]] : '';
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

        $csvFilePath = __DIR__ . '/' .$this->outputFileName;
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
                'file'=> new CURLFILE($csvFilePath)
            ),
            CURLOPT_HTTPHEADER => array('x-api-key: ' .$this->resolveInputParameter('api_key')),
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

    public function getDatabaseType() {
        $jobDB = $this->getJobDB();
        try {
            $result = $jobDB->query("SELECT VERSION()");
            $row = $jobDB->fetchAll($result);
            if (is_string($row[0]["VERSION()"])) {
                return "MySQL";
            }
        } catch (Exception $e) {}
    
        try {
            $result = $jobDB->query("SELECT @@VERSION");
            $row = $jobDB->fetchAll($result);
            if (is_string(reset($row[0]))) {
                    return "MSSQL";
            }
        } catch (Exception $e) {}
        throw new Exception("Database could not be detected");
    }

    public function storeList($data)
    {
        $type = $this->getSystemActivityVar('TYPE');

        $dataItem = $data['data'][0];
        $eInvoiceFields = $dataItem['eInvoiceFields'];
        $paymentInstructionsCreditTransfer = $eInvoiceFields['paymentInstructionsCreditTransfer'][0];
        $document = $dataItem['document'];
        $vatBreakdown = $eInvoiceFields['vatBreakdown'][0];
        $auditTrailItem = $dataItem['auditTrail'][0];
        $taxRates = $dataItem['taxRates'];

        $attributes1 = $this->resolveOutputParameterListAttributes('recipientDetails'); //recipientDetails
        $values1 = ($type == "e_invoice") ? [
            'recipientCompanyName' => $eInvoiceFields['recipientName'],
            'recipientName' => $eInvoiceFields['recipientContactPersonName'],
            'recipientStreet' => $eInvoiceFields['recipientPostalAddressAddressLines'][0],
            'recipientZipCode' => $eInvoiceFields['recipientPostalAddressPostCode'],
            'recipientCity' => $eInvoiceFields['recipientPostalAddressCity'],
            'recipientCountry' => $eInvoiceFields['recipientPostalAddressCountryCode'],
            'recipientVatNumber' => $eInvoiceFields['recipientVatIdentifier'],
            'recipientInternalNumber' => ''
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
            'vendorBankNumber' => $paymentInstructionsCreditTransfer['paymentAccountIdentifierId'],
            'vendorVatNumber' => $eInvoiceFields['vendorVatIdentifier'],
            'vendorTaxNumber' => $eInvoiceFields['vendorTaxRegistrationIdentifier'],
            'vendorCompanyName' => $eInvoiceFields['vendorName'],
            'vendorStreet' => $eInvoiceFields['vendorPostalAddressAddressLines'][0],
            'vendorZipCode' => $eInvoiceFields['vendorPostalAddressPostCode'],
            'vendorCity' => $eInvoiceFields['vendorPostalAddressCity'],
            'vendorCountry' => $eInvoiceFields['vendorPostalAddressCountryCode'],
            'vendorDeliveryPeriod' => '',
            'vendorAccountNumber' => '',
            'vendorInternalNumber' => ''
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
            'vendorInternalNumber' => $dataItem["vendorEntity"]["internalNumber"]
        ];

        foreach ($attributes2 as $attribute) {
            $this->setTableValue($attribute['value'], $values2[$attribute['id']]);
        }

        $attributes3 = $this->resolveOutputParameterListAttributes('invoiceDetails'); //invoiceDetails

        $values3 = ($type == "e_invoice") ? [
            'taxRate1' => '',
            'taxRate2' => '',
            'taxRate3' => '',
            'taxRate4' => '',
            'taxRate5' => '',
            'invoiceNumber' => $eInvoiceFields['invoiceNumber'],
            'date' => date("Y-m-d", strtotime(str_replace(".", "-", $eInvoiceFields['invoiceIssueDate']))) . ' 00:00:00.000',
            'netAmount' => $eInvoiceFields['sumOfInvoiceLineNetAmount'],
            'taxAmount' => $eInvoiceFields['invoiceTotalVatAmount'],
            'grossAmount' => $eInvoiceFields['invoiceTotalAmountWithVat'],
            'taxRate' => $vatBreakdown['vatCategoryRate'],
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
            'resolvedIssuesCount' => '',
            'hasProcessingIssues' => '',
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
        if($type == "e_invoice"){  
            $violations = [];
            foreach ($dataItem['violations'] as $violation) {
                $messages = implode(', ', $violation['messages']);
                $violations[] = $violation['level'] . ': ' . $messages;
            }
            $values5['violations'] = implode(" --- ", $violations);
        }else{
            $values5['violations'] = '';
        }

        foreach ($attributes5 as $attribute) {
            $this->setTableValue($attribute['value'], $values5[$attribute['id']]);
        }


        $attributes6 = $this->resolveOutputParameterListAttributes('attachments'); //atttachments

        if($type == "e_invoice"){
            //pdf
            $urlPDF = $dataItem['eInvoicePdfPath'];
            $tempPath = $this->getTempPath();
            $tempFILEID = $this->getSystemActivityVar('FILEID');
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

            $eInvoicePDF = $savePath . "/" . $tempFILEID . "_PDF_.pdf";
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

            $eInvoiceReport= $savePath . "/" . $tempFILEID . "_REPORT_.xml";
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
                    $attachmentPath = $savePath . "/" . $tempFILEID . "_ATTACHMENT_" . ($index + 1) . ".pdf";
                    file_put_contents($attachmentPath, $dataAttachment);
                    $this->setSystemActivityVar('ATTACHMENTPATH' .$index, $attachmentPath);
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
        }else{
            $values6 = [
                'e_invoicePDF' => '',
                'e_invoiceReport' => '',
                'e_invoiceAttachments' => []
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
    }

    public function getUDL($udl, $elementID)
    {
        if ($elementID == 'postVendor') {
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
                ['name' => CURRENCY, 'value' => '13']
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
                ['name' => INTERNALNUMBER, 'value' => 'vendorInternalNumber']
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
                ['name' => E_INVOICEATTACHMENT1, 'value' => 'e_invoiceAttachments']
            ];
        }
        return null;
    }
}