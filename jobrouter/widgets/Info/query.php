<?PHP

require_once('C:\inetpub\wwwroot\jobrouter\includes\central.php');

$JobDB = DBFactory::getJobDB();

$indate = $_GET['indate'];
$outdate = $_GET['outdate'];

if($outdate == "" && $indate == "" ){
    $request = "SELECT COUNT(*) AS count FROM jrincidents";
}else if($outdate == ""){
    $request = "SELECT COUNT(*) AS count FROM jrincidents WHERE indate >= '" .$indate ."'";
}else if($indate == ""){
    $request = "SELECT COUNT(*) AS count FROM jrincidents WHERE outdate <= '" .$outdate ."'";
}else{
    $request = "SELECT COUNT(*) AS count FROM jrincidents WHERE indate >= '" .$indate ."' AND outdate <= '" .$outdate ."'";
}

$result = $JobDB->query($request);
while ($row = $JobDB->fetchRow($result)) {
        $amount = $row["count"];
}

echo $amount;
?>