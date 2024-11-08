<?php
class pedantSystemActivity extends AbstractSystemActivityAPI
{
    private $outputFileName = "pedantOutput.csv";
    private $tableName = "pedantSystemActivity";
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

    protected function pedant()
    {
        $this->checkFILEID();
        date_default_timezone_set("Europe/Berlin");
        if ($this->isFirstExecution()) {
            $this->setResubmission(10, 'm');
            $this->uploadFile();
        }

        if ($this->isPending()) {
            $this->checkFile();
        }
    }

    protected function pedantData()
    {
        $this->postVendorDetails();
        $this->markActivityAsCompleted();
    }


    protected function uploadFile()
    {

        $curl = curl_init();
        $file = $this->getUploadPath() . $this->resolveInputParameter('inputFile');
        $action = 'normal';

        $fileSizeB = filesize($file);
        $fileSizeMB = $fileSizeB / (1024 * 1024);
        if ($fileSizeMB > $this->maxFileSize) {
            throw new JobRouterException("File size exceeds the maximum limit of $this->maxFileSize MB. Actual size: $fileSizeMB MB.");
        }

        if($this->resolveInputParameter('demo') == '1'){
            $url = "$this->demoURL/v2/external/documents/invoices/upload";
        } else {
            $url = "$this->productiveURL/v1/external/documents/invoices/upload";//TODO does the new URL count for productive as well?
        }

        if ($this->resolveInputParameter('flag') == 'normal') {
            $action = 'normal';
        } else if ($this->resolveInputParameter('flag') == 'check_extraction') {
            $action = 'check_extraction';
            $this->setResubmission(10, 'm');
        } else if ($this->resolveInputParameter('flag') == 'skip_review') {
            $action = 'skip_review';
        } else if ($this->resolveInputParameter('flag') == 'force_skip') {
            $action = 'force_skip';
        } else {
            throw new Exception('Invalid input parameter value for FLAG: ' . $this->resolveInputParameter('flag'));
        }
        curl_setopt_array(
            $curl,
            array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => array(
                    'file' => new CURLFILE($file),
                    'recipientInternalNumber' => $this->resolveInputParameter('internalNumber'),
                    'action' => $action,
                    'note' => $this->resolveInputParameter('note'),
                ),
                CURLOPT_HTTPHEADER => array('X-API-KEY: ' . $this->resolveInputParameter('api_key')),
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0
            )
        );
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

        $jobDB = $this->getJobDB();
        $insert = "INSERT INTO $this->tableName (incident, fileid, counter)
                   VALUES(" .$this->resolveInputParameter('incident') .", '$fileId', 0)";
        $jobDB->exec($insert);
        $this->storeOutputParameter('fileID', $fileId);
        $this->storeOutputParameter('invoiceID', $invoiceId);
        $this->setSystemActivityVar('FILEID', $fileId);
        $this->setSystemActivityVar('TYPE', $type);
        $this->markActivityAsPending();
    }
    protected function checkFile()
    {
        if (!empty($this->resolveInputParameter('vendorTable'))) {
            $this->postVendorDetails();
        }

        if($this->resolveInputParameter('demo') == '1'){
            $url = "$this->demoURL/v1/external/documents/invoices?fileId=" . $this->getSystemActivityVar('FILEID') ."&auditTrail=true";
            if($this->getSystemActivityVar('TYPE') == 'e_invoice'){
                $url = "$this->demoURL/v1/external/documents/e-invoices?documentId=" . $this->getSystemActivityVar('FILEID') ."&auditTrail=true";
            }
        } else {
            $url = "$this->productiveURL/v1/external/documents/invoices?fileId=" . $this->getSystemActivityVar('FILEID') ."&auditTrail=true";
        }
        
        $jobDB = $this->getJobDB();
        if (date("H") >= 6 && date("H") <= 24) {
            $this->setResubmission(10, 'm');
        } else {
            $this->setResubmission(60, 'm');
        }

        $curl = curl_init();
        curl_setopt_array(
            $curl,
            array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array('X-API-KEY: ' . $this->resolveInputParameter('api_key')),
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0
            )
        );

        $response = curl_exec($curl);


        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $counterQuery = "SELECT counter FROM $this->tableName WHERE fileid = '" .$this->getSystemActivityVar('FILEID') . "'";
        $result = $jobDB->query($counterQuery);
        $row = $jobDB->fetchAll($result);
        if ($row[0]["counter"] > 10) {
            if ($httpcode != 200 && $httpcode != 404 && $httpcode != 503 && $httpcode != 502 && $httpcode != 500 && $httpcode != 0) {
                throw new JobRouterException('Error occurred during file extraction. HTTP Error Code: ' . $httpcode);
            }
        }else{
            $this->increaseCounter($this->getSystemActivityVar('FILEID'));
            $this->setResubmission(10, 'm');
        }


        if ($httpcode == 503 || $httpcode == 502 || $httpcode == 0 || $httpcode == 500) {
            $this->setResubmission(10, 'm');
        }
        curl_close($curl);

        $data = json_decode($response, TRUE);
        $dataItem = $data["data"][0];
        $file = $this->getSystemActivityVar('FILEID');
        $check = false;

        $falseStates = ['processing', 'failed', 'uploaded'];

        $temp = "SELECT fileid
                 FROM $this->tableName
                 WHERE incident = " . $this->resolveInputParameter('incident');
        $result = $jobDB->query($temp);
        $row = $jobDB->fetchAll($result);

        if ($row[0]["fileid"] != $file && $dataItem["status"] == "uploaded") {
            $this->storeOutputParameter('tempJSON', json_encode($data));

            $insert = "INSERT INTO $this->tableName (incident, fileid, counter)
                       VALUES(" . $this->resolveInputParameter('incident') . ", " . "'" .$dataItem["documentId"]  . "'" . ", 0)";
            $jobDB->exec($insert);
        }

        if ($dataItem["documentId"] == $file && in_array($dataItem["status"], $falseStates) === false) {
            if($this->getSystemActivityVar('TYPE') == 'e_invoice'){
                
                $url = $dataItem['eInvoicePdfPath'];
                $tempPath = $this->getTempPath();
                $tempFILEID = $this->getSystemActivityVar('FILEID');
                $savePath = $tempPath . "/pedant";

                if (!is_dir($savePath)) {
                    mkdir($savePath, 0777, true);
                }
                
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                
                $data = curl_exec($ch);

                $eInvoicePDF = $savePath . "/" . $tempFILEID . ".pdf";
                file_put_contents($eInvoicePDF, $data);
                $this->setSystemActivityVar('PDFPATH', $eInvoicePDF);
            
                curl_close($ch);

                $this->storeOutputParameter('invoicePDF', $eInvoicePDF);
            }
            $check = true;
            $this->storeList($data);
        }
        if ($check === true) {
            $delete = "DELETE FROM $this->tableName
                       WHERE fileid = '" . $this->getSystemActivityVar('FILEID') . "'";
            $jobDB->exec($delete);
            unlink($this->getSystemActivityVar('PDFPATH'));
            $this->markActivityAsCompleted();
        }
    }

    protected function isMySQL()
    {
        $jobDB = $this->getJobDB();
        $my = "SELECT @@VERSION AS versionName";
        $res = $jobDB->query($my);
        if ($res === false) {
            throw new JobRouterException($jobDB->getErrorMessage());
        }
        $row = $jobDB->fetchAll($res);
        if (substr($row[0]["versionName"], 0, 9) == "Microsoft") {
            return false;
        } else {
            return true;
        }
    }
    protected function checkFILEID()
    {
        $JobDB = $this->getJobDB();
        if ($this->isMySQL() === true) {
            $tableExists = "SELECT EXISTS (SELECT 1
                                           FROM information_schema.tables
                                           WHERE table_name = $this->tableName
                                          ) AS versionExists";
            $result = $JobDB->query($tableExists);
            $existing = $JobDB->fetchAll($result);
            return $this->checkID($existing[0]["versionExists"]);
        } else {
            $tableExists = "DECLARE @table_exists BIT;
 
                            IF OBJECT_ID('$this->tableName', 'U') IS NOT NULL
                                SET @table_exists = 1;
                            ELSE
                                SET @table_exists = 0;

                            SELECT @table_exists AS versionExists";
            $result = $JobDB->query($tableExists);
            $existing = $JobDB->fetchAll($result);
            return $this->checkID($existing[0]["versionExists"]);
        }
    }

    protected function checkID($var)
    {
        $JobDB = $this->getJobDB();
        $id = "SELECT *
               FROM $this->tableName
               WHERE incident = '" . $this->resolveInputParameter('incident') . "'";
        $table = "CREATE TABLE $this->tableName (
                  incident INT NOT NULL PRIMARY KEY,
                  fileid NVARCHAR(50) NOT NULL,
                  counter INT NOT NULL DEFAULT 0)";
        if ($var == 1) {
            $result = $JobDB->query($id);
            $count = 0;
            while ($row = $JobDB->fetchRow($result)) {
                $count++;
                $fileid = $row['fileid'];
            }
            if ($count == 0) {
                return false;
            } else {
                $this->setSystemActivityVar('FILEID', $fileid);
                $this->markActivityAsPending();
                return true;
            }
        } else {
            $JobDB->exec($table);
            return false;
        }
    }

    protected function increaseCounter($fileid){
        $JobDB = $this->getJobDB();
        $counter = "SELECT counter
                    FROM $this->tableName
                    WHERE fileid = '$fileid'";
        $result = $JobDB->query($counter);
        $row = $JobDB->fetchAll($result);
        $count = $row[0]["counter"] + 1;
        $update = "UPDATE $this->tableName
                   SET counter = $count
                   WHERE fileid = '$fileid'";
        $JobDB->exec($update);
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

        if($this->resolveInputParameter('demo') == '1'){
            $url = "$this->demoURL/v2/external/entities/vendors/import";
        } else {
            $url = 'https://entity.api.pedant.ai/v2/external/entities/vendors/import';
        }

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
            CURLOPT_HTTPHEADER => array(
                'x-api-key: ' .$this->resolveInputParameter('api_key')
            ),
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



    public function storeList($data)
    {
        $type = $this->getSystemActivityVar('TYPE');

        $dataItem = $data['data'][0];
        $eInvoiceFields = $dataItem['eInvoiceFields'];
        $paymentInstructionsCreditTransfer = $eInvoiceFields['paymentInstructionsCreditTransfer'][0];
        $document = $dataItem['document'];
        $vatBreakdown = $eInvoiceFields['vatBreakdown'][0];
        $auditTrailItem = $dataItem['auditTrail'][0];

        //recipientDetails
        $attributes1 = $this->resolveOutputParameterListAttributes('recipientDetails');
        $values1PDF = [
            'recipientCompanyName' => $dataItem["recipientCompanyName"],
            'recipientName' => $dataItem["recipientName"],
            'recipientStreet' => $dataItem["recipientStreet"],
            'recipientZipCode' => $dataItem["recipientZipCode"],
            'recipientCity' => $dataItem["recipientCity"],
            'recipientCountry' => $dataItem["recipientCountry"],
            'recipientVatNumber' => $dataItem["recipientVatNumber"],
            'recipientInternalNumber' => $dataItem["recipientEntity"]["internalNumber"]
        ];

        $values1XML = [
            'recipientCompanyName' => $eInvoiceFields['recipientName'],
            'recipientName' => $eInvoiceFields['recipientContactPersonName'],
            'recipientStreet' => $eInvoiceFields['recipientPostalAddressAddressLines'][0],
            'recipientZipCode' => $eInvoiceFields['recipientPostalAddressPostCode'],
            'recipientCity' => $eInvoiceFields['recipientPostalAddressCity'],
            'recipientCountry' => $eInvoiceFields['recipientPostalAddressCountryCode'],
            'recipientVatNumber' => $eInvoiceFields['recipientVatIdentifier'],
            'recipientInternalNumber' => ''
        ];

        $values1 = ($type == "e_invoice") ? $values1XML : $values1PDF;

        foreach ($attributes1 as $attribute) {
            $this->setTableValue($attribute['value'], $values1[$attribute['id']]);
        }

        //vendorDetails
        $attributes2 = $this->resolveOutputParameterListAttributes('vendorDetails');
        $values2PDF = [
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

        $values2XML = [
            'vendorBankNumber' => $paymentInstructionsCreditTransfer['paymentAccountIdentifierId'],
            'vendorVatNumber' => $eInvoiceFields['vendorVatIdentifier'],
            'vendorTaxNumber' => $eInvoiceFields['vendorTaxRegistrationIdentifier'],
            'vendorCompanyName' => $eInvoiceFields['vendorName'],
            'vendorStreet' => $eInvoiceFields['vendorPostalAddressAddressLines'][0],
            'vendorZipCode' => $eInvoiceFields['vendorPostalAddressPostCode'],
            'vendorCity' => $eInvoiceFields['vendorPostalAddressCity'],
            'vendorCountry' => $eInvoiceFields['vendorPost21alAddressCountryCode'],
            'vendorDeliveryPeriod' => '',
            'vendorAccountNumber' => '',
            'vendorInternalNumber' => ''
        ];

        $values2 = ($type == "e_invoice") ? $values2XML : $values2PDF;

        foreach ($attributes2 as $attribute) {
            $this->setTableValue($attribute['value'], $values2[$attribute['id']]);
        }

        //invoiceDetails
        $attributes3 = $this->resolveOutputParameterListAttributes('invoiceDetails');

        $values3PDF = [
            'taxRate1' => $dataItem["taxRates"][0]["subNetAmount"] . ";" . $dataItem["taxRates"][0]["subTaxAmount"] . ";" . $dataItem["taxRates"][0]["subTaxRate"],
            'taxRate2' => $dataItem["taxRates"][1]["subNetAmount"] . ";" . $dataItem["taxRates"][1]["subTaxAmount"] . ";" . $dataItem["taxRates"][1]["subTaxRate"],
            'taxRate3' => $dataItem["taxRates"][2]["subNetAmount"] . ";" . $dataItem["taxRates"][2]["subTaxAmount"] . ";" . $dataItem["taxRates"][2]["subTaxRate"],
            'taxRate4' => $dataItem["taxRates"][3]["subNetAmount"] . ";" . $dataItem["taxRates"][3]["subTaxAmount"] . ";" . $dataItem["taxRates"][3]["subTaxRate"],
            'taxRate5' => $dataItem["taxRates"][4]["subNetAmount"] . ";" . $dataItem["taxRates"][4]["subTaxAmount"] . ";" . $dataItem["taxRates"][4]["subTaxRate"],
            'taxRate6' => $dataItem["taxRates"][5]["subNetAmount"] . ";" . $dataItem["taxRates"][5]["subTaxAmount"] . ";" . $dataItem["taxRates"][5]["subTaxRate"],
            'taxRate7' => $dataItem["taxRates"][6]["subNetAmount"] . ";" . $dataItem["taxRates"][6]["subTaxAmount"] . ";" . $dataItem["taxRates"][6]["subTaxRate"],
            'taxRate8' => $dataItem["taxRates"][7]["subNetAmount"] . ";" . $dataItem["taxRates"][7]["subTaxAmount"] . ";" . $dataItem["taxRates"][7]["subTaxRate"],
            'taxRate9' => $dataItem["taxRates"][8]["subNetAmount"] . ";" . $dataItem["taxRates"][8]["subTaxAmount"] . ";" . $dataItem["taxRates"][8]["subTaxRate"],
            'taxRate10' => $dataItem["taxRates"][9]["subNetAmount"] . ";" . $dataItem["taxRates"][9]["subTaxAmount"] . ";" . $dataItem["taxRates"][9]["subTaxRate"],
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
            'note' => $dataItem["file"]["note"],
            'status' => $dataItem["status"],
            'currency' => $dataItem["currency"],
            'resolvedIssuesCount' => $dataItem["resolvedIssuesCount"],
            'hasProcessingIssues' => $dataItem["hasProcessingIssues"],
            'deliveryDate' => $dataItem["deliveryDate"],
        ];

        $values3XML = [
            'taxRate1' => '',
            'taxRate2' => '',
            'taxRate3' => '',
            'taxRate4' => '',
            'taxRate5' => '',
            'taxRate6' => '',
            'taxRate7' => '',
            'taxRate8' => '',
            'taxRate9' => '',
            'taxRate10' => '',
            'invoiceNumber' => $eInvoiceFields['invoiceNumber'],
            'date' => date("Y-m-d", strtotime(str_replace(".", "-", $eInvoiceFields['invoiceIssueDate']))) . ' 00:00:00.000',
            'netAmount' => $eInvoiceFields['sumOfInvoiceLineNetAmount'],
            'taxAmount' => $eInvoiceFields['invoiceTotalVatAmount'],
            'grossAmount' => $eInvoiceFields['invoiceTotalAmountWithVat'],
            'taxRate' => $vatBreakdown['vatCategoryRate'],
            'projectNumber' => $eInvoiceFields['projectReferenceId'],
            'purchaseOrder' => '',
            'purchaseDate' => '',
            'deliveryDate' => $eInvoiceFields['deliveryInformationActualDeliveryDate'],
            'hasDiscount' => '',
            'refund' => '',
            'discountPercentage' => '',
            'discountAmount' => '',
            'discountDate' => '',
            'invoiceType' => $eInvoiceFields['invoiceTypeCode'],
            'note' => $document['note'],
            'status' => $dataItem['status'],
            'currency' => $eInvoiceFields['invoiceCurrencyCode'],
            'resolvedIssuesCount' => '',
            'hasProcessingIssues' => '',
        ];

        $values3 = ($type == "e_invoice") ? $values3XML : $values3PDF;

        foreach ($attributes3 as $attribute) {
            $this->setTableValue($attribute['value'], $values3[$attribute['id']]);
        }

        //auditTrailDetails
        $attributes4 = $this->resolveOutputParameterListAttributes('auditTrailDetails');

        $values4PDF = [
            'auditTrailuserName' => $dataItem["auditTrail"][1]["userName"],
            'auditTrailtype' => $dataItem["auditTrail"][1]["type"],
            'auditTrailsubType' => $dataItem["auditTrail"][1]["subType"],
            'auditTrailcomment' => $dataItem["auditTrail"][1]["comment"]
        ];

        $values4XML = [
            'auditTrailuserName' =>  $auditTrailItem['userName'],
            'auditTrailtype' => $auditTrailItem['type'],
            'auditTrailsubType' => $auditTrailItem['subType'],
            'auditTrailcomment' => $auditTrailItem['comment']
        ];

        $values4 = ($type == "e_invoice") ? $values4XML : $values4PDF;

        foreach ($attributes4 as $attribute) {
            $this->setTableValue($attribute['value'], $values4[$attribute['id']]);
        }

        //rejectionDetails
        $attributes5 = $this->resolveOutputParameterListAttributes('rejectionDetails');

        $values5PDF = [
            'rejectReason' => $dataItem["rejectReason"],
            'rejectionCode' => isset($dataItem["rejectionType"]) ? $dataItem["rejectionType"]["code"] : null,
            'rejectionType' => isset($dataItem["rejectionType"]) ? $dataItem["rejectionType"]["type"] : null
        ];

        $values5XML = [
            'rejectReason' => $dataItem['rejectReason'],
            'rejectionCode' => '',
            'rejectionType' => ''
        ];

        $values5 = ($type == "e_invoice") ? $values5XML : $values5PDF;

        foreach ($attributes5 as $attribute) {
            $this->setTableValue($attribute['value'], $values5[$attribute['id']]);
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
                ['name' => INTERNALNUMBER, 'value' => 'internalNumber']
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
                ['name' => TAXRATE6, 'value' => 'taxRate6'],
                ['name' => TAXRATE7, 'value' => 'taxRate7'],
                ['name' => TAXRATE8, 'value' => 'taxRate8'],
                ['name' => TAXRATE9, 'value' => 'taxRate9'],
                ['name' => TAXRATE10, 'value' => 'taxRate10'],
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
                ['name' => TYPE, 'value' => 'rejectionType']
            ];
        }
        return null;
    }
}