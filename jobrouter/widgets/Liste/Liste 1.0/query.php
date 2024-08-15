<?PHP

require_once('C:\inetpub\wwwroot\jobrouter\includes\central.php');

$JobDB = DBFactory::getJobDB();

$columns = $_GET['columns'];
$filter = $_GET['filter'];
$limit = $_GET['limit'];
$offset = $_GET['offset'];
$sort = $_GET['sort'];
$sortID = $_GET['sortID'];
$request = "SELECT * FROM JRINCIDENTS WHERE ";
$entries = array(
					array(),
					array(),
				);
for($i = 0; $i < count($columns) - 1; $i++){
	$request = $request .$columns[$i] ." LIKE '"  .$filter[$i] ."%' AND ";
}
$request = $request .$columns[count($columns) - 1] ." LIKE '"  .$filter[$i] ."%' ORDER BY " .$columns[$sortID]  ." " .$sort ." LIMIT " .$limit ." OFFSET " .$offset;
$result = $JobDB->query($request);

$count = 0;
while ($row = $JobDB->fetchRow($result)) {
    for ($i = 0; $i < count($columns); $i++) {
        $entries[$i][$count] = $row[$columns[$i]];
    }
    $count++;
}
echo json_encode($entries);
?>