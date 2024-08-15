<?PHP

require_once('C:\inetpub\wwwroot\jobrouter\includes\central.php');

$JobDB = DBFactory::getJobDB();

$columns = $_GET['columns'];
$filter = $_GET['filter'];
$request = "SELECT COUNT(*) FROM JRINCIDENTS WHERE ";
$amount = 0;
for($i = 0; $i < count($columns) - 1; $i++){
	$request = $request .$columns[$i] ." LIKE '"  .$filter[$i] ."%' AND ";
}
$request = $request .$columns[count($columns) - 1] ." LIKE '"  .$filter[$i] ."%' ORDER BY " .$columns[1]  ." DESC";
$result = $JobDB->query($request);
while ($row = $JobDB->fetchOne($result)) {
    $amount = $row;
}

echo $amount;
?>