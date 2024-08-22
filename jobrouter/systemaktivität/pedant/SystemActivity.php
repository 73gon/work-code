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
            $url = "$this->demoURL/v1/external/documents/invoices/upload";
        } else {
            $url = "$this->productiveURL/v1/external/documents/invoices/upload";
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

        $jobDB = $this->getJobDB();
        $insert = "INSERT INTO $this->tableName (incident, fileid, counter)
                   VALUES(" .$this->resolveInputParameter('incident') .", '$fileId', 0)";
        $jobDB->exec($insert);
        $this->storeOutputParameter('fileID', $fileId);
        $this->storeOutputParameter('invoiceID', $invoiceId);
        $this->setSystemActivityVar('FILEID', $fileId);
        $this->markActivityAsPending();
    }
    protected function checkFile()
    {
        if (!empty($this->resolveInputParameter('vendorTable'))) {
            $this->postVendorDetails();
        }

        if($this->resolveInputParameter('demo') == '1'){
            $url = "$this->demoURL/v1/external/documents/invoices?fileId=" . $this->getSystemActivityVar('FILEID') ."&auditTrail=true";
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
        $file = $this->getSystemActivityVar('FILEID');
        $check = false;

        $falseStates = ['processing', 'failed', 'uploaded'];

        $temp = "SELECT fileid
                 FROM $this->tableName
                 WHERE incident = " . $this->resolveInputParameter('incident');
        $result = $jobDB->query($temp);
        $row = $jobDB->fetchAll($result);

        if ($row[0]["fileid"] != $file && $data["data"][0]["status"] == "uploaded") {
            $this->storeOutputParameter('tempJSON', json_encode($data));
            $insert = "INSERT INTO $this->tableName (incident, fileid, counter)
                       VALUES(" . $this->resolveInputParameter('incident') . ", " . "'" .$data["data"][0]["fileId"]  . "'" . ", 0)";
            $jobDB->exec($insert);
        }

        if ($data["data"][0]["fileId"] == $file && in_array($data["data"][0]["status"], $falseStates) === false) {
            $check = true;
            $this->storeList($data);
        }
        if ($check === true) {
            $delete = "DELETE FROM $this->tableName
                       WHERE fileid = '" . $this->getSystemActivityVar('FILEID') . "'";
            $jobDB->exec($delete);
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
                  fileid NVARCHAR(50) NOT NULL),
                  counter INT NOT NULL DEFAULT 0";
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
        $attributes1 = $this->resolveOutputParameterListAttributes('recipientDetails');
        $values1 = [
            0,
            $data["data"][0]["recipientCompanyName"],
            $data["data"][0]["recipientName"],
            $data["data"][0]["recipientStreet"],
            $data["data"][0]["recipientZipCode"],
            $data["data"][0]["recipientCity"],
            $data["data"][0]["recipientCountry"],
            $data["data"][0]["recipientVatNumber"],
            $data["data"][0]["recipientEntity"]["internalNumber"]
        ];
        foreach ($attributes1 as $attribute) {
            $this->setTableValue($attribute['value'], $values1[$attribute['id']]);
        }

        $attributes2 = $this->resolveOutputParameterListAttributes('vendorDetails');
        $values2 = [
            0,
            $data["data"][0]["bankNumber"],
            $data["data"][0]["vat"],
            $data["data"][0]["taxNumber"],
            $data["data"][0]["vendorCompanyName"],
            $data["data"][0]["vendorStreet"],
            $data["data"][0]["vendorZipCode"],
            $data["data"][0]["vendorCity"],
            $data["data"][0]["vendorCountry"],
            $data["data"][0]["deliveryDate"],
            $data["data"][0]["deliveryPeriod"],
            $data["data"][0]["accountNumber"],
            $data["data"][0]["vendorEntity"]["internalNumber"]
        ];
        foreach ($attributes2 as $attribute) {
            $this->setTableValue($attribute['value'], $values2[$attribute['id']]);
        }

        $attributes3 = $this->resolveOutputParameterListAttributes('invoiceDetails');

        $values3 = [0];
        for ($i = 0; $i < 10; $i++) {
            $values3[] = $data["data"][0]["taxRates"][$i]["subNetAmount"] . ";"
                . $data["data"][0]["taxRates"][$i]["subTaxAmount"] . ";"
                . $data["data"][0]["taxRates"][$i]["subTaxRate"];
        }

        $array = [
            $data["data"][0]["invoiceNumber"],
            date("Y-m-d", strtotime(str_replace(".", "-", $data["data"][0]["issueDate"]))) . ' 00:00:00.000',
            $data["data"][0]["netAmount"],
            $data["data"][0]["taxAmount"],
            $data["data"][0]["amount"],
            $data["data"][0]["taxRate"],
            $data["data"][0]["projectNumber"],
            $data["data"][0]["purchaseOrder"],
            $data["data"][0]["purchaseDate"],
            $data["data"][0]["hasDiscount"],
            $data["data"][0]["refund"],
            $data["data"][0]["discountPercentage"],
            $data["data"][0]["discountAmount"],
            $data["data"][0]["discountDate"],
            $data["data"][0]["invoiceType"],
            $data["data"][0]["file"]["note"],
            $data["data"][0]["status"],
            $data["data"][0]["currency"],
            $data["data"][0]["resolvedIssuesCount"],
            $data["data"][0]["hasProcessingIssues"],
        ];

        for ($i = 0; $i < count($array); $i++) {
            $values3[] = $array[$i];
        }


        foreach ($attributes3 as $attribute) {
            $this->setTableValue($attribute['value'], $values3[$attribute['id']]);
        }

        $attributes4 = $this->resolveOutputParameterListAttributes('auditTrailDetails');
        $values4 = [
            0,
            $data["data"][0]["auditTrail"][1]["userName"],
            $data["data"][0]["auditTrail"][1]["type"],
            $data["data"][0]["auditTrail"][1]["subType"],
            $data["data"][0]["auditTrail"][1]["comment"]
        ];
        foreach ($attributes4 as $attribute) {
            $this->setTableValue($attribute['value'], $values4[$attribute['id']]);
        }

        $attributes5 = $this->resolveOutputParameterListAttributes('rejectionDetails');
        $values5 = [
            0,
            $data["data"][0]["rejectReason"],
            isset($data["data"][0]["rejectionType"]) ? $data["data"][0]["rejectionType"]["code"] : null,
            isset($data["data"][0]["rejectionType"]) ? $data["data"][0]["rejectionType"]["type"] : null
        ];
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
                ['name' => RECIPIENTCOMPANYNAME, 'value' => '1'],
                ['name' => RECIPIENTNAME, 'value' => '2'],
                ['name' => STREET, 'value' => '3'],
                ['name' => ZIPCODE, 'value' => '4'],
                ['name' => CITY, 'value' => '5'],
                ['name' => COUNTRY, 'value' => '6'],
                ['name' => RECIPIENTVATNUMBER, 'value' => '7'],
                ['name' => INTERNALNUMBER, 'value' => '8']
            ];
        }

        if ($elementID == 'vendorDetails') {
            return [
                ['name' => '-', 'value' => ''],
                ['name' => BANKNUMBER, 'value' => '1'],
                ['name' => VAT, 'value' => '2'],
                ['name' => TAXNUMBER, 'value' => '3'],
                ['name' => VENDORCOMPANYNAME, 'value' => '4'],
                ['name' => STREET, 'value' => '5'],
                ['name' => ZIPCODE, 'value' => '6'],
                ['name' => CITY, 'value' => '7'],
                ['name' => COUNTRY, 'value' => '8'],
                ['name' => DELIVERYDATE, 'value' => '9'],
                ['name' => DELIVERYPERIOD, 'value' => '10'],
                ['name' => ACCOUNTNUMBER, 'value' => '11'],
                ['name' => INTERNALNUMBER, 'value' => '12']
            ];
        }

        if ($elementID == 'invoiceDetails') {
            return [
                ['name' => '-', 'value' => ''],
                ['name' => TAXRATE1, 'value' => '1'],
                ['name' => TAXRATE2, 'value' => '2'],
                ['name' => TAXRATE3, 'value' => '3'],
                ['name' => TAXRATE4, 'value' => '4'],
                ['name' => TAXRATE5, 'value' => '5'],
                ['name' => TAXRATE6, 'value' => '6'],
                ['name' => TAXRATE7, 'value' => '7'],
                ['name' => TAXRATE8, 'value' => '8'],
                ['name' => TAXRATE9, 'value' => '9'],
                ['name' => TAXRATE10, 'value' => '10'],
                ['name' => INVOICENUMBER, 'value' => '11'],
                ['name' => DATE, 'value' => '12'],
                ['name' => NETAMOUNT, 'value' => '13'],
                ['name' => TAXAMOUNT, 'value' => '14'],
                ['name' => GROSSAMOUNT, 'value' => '15'],
                ['name' => TAXRATE, 'value' => '16'],
                ['name' => PROJECTNUMBER, 'value' => '17'],
                ['name' => PURCHASEORDER, 'value' => '18'],
                ['name' => PURCHASEDATE, 'value' => '19'],
                ['name' => HASDISCOUNT, 'value' => '20'],
                ['name' => REFUND, 'value' => '21'],
                ['name' => DISCOUNTPERCENTAGE, 'value' => '22'],
                ['name' => DISCOUNTAMOUNT, 'value' => '23'],
                ['name' => DISCOUNTDATE, 'value' => '24'],
                ['name' => INVOICETYPE, 'value' => '25'],
                ['name' => NOTE, 'value' => '26'],
                ['name' => STATUS, 'value' => '27'],
                ['name' => CURRENCY, 'value' => '28'],
                ['name' => RESOLVEDISSUES, 'value' => '29'],
                ['name' => HASPROCESSINGISSUES, 'value' => '30']
            ];
        }

        if ($elementID == 'auditTrailDetails') {
            return [
                ['name' => '-', 'value' => ''],
                ['name' => USERNAME, 'value' => '1'],
                ['name' => TYPE, 'value' => '2'],
                ['name' => SUBTYPE, 'value' => '3'],
                ['name' => COMMENT, 'value' => '4']
            ];
        }

        if ($elementID == 'rejectionDetails') {
            return [
                ['name' => '-', 'value' => ''],
                ['name' => REJECTREASON, 'value' => '1'],
                ['name' => CODE, 'value' => '2'],
                ['name' => TYPE, 'value' => '3']
            ];
        }
        return null;
    }
}