<?PHP

require_once('../../../includes/central.php');

$JobDB = DBFactory::getJobDB();

$indate = $_GET['indate'];
$outdate = $_GET['outdate'];
$einheit = $_GET['einheit'];
$username = $_GET['username'];

echo getIncidents();
function getTableData()
{
    $JobDB = $this->getJobDB();

    $currentUsername = $this->getUser()->getUsername();
    $query = "
            SELECT *
            FROM V_UEBERSICHTEN_WIDGET
            WHERE CONCAT(',', berechtigung, ',') LIKE CONCAT('%,', '$currentUsername', ',%')
        ";

    $result = $JobDB->query($query);

    while ($row = $JobDB->fetchRow($result)) {
      // Determine status label based on logic
      $statusId = $row['status'];
      $statusLabel = '';

      if ($statusId === 'completed') {
        $statusLabel = 'Beendet';
      } else if ($statusId === 'rest') {
        $faelligkeitDays = (int) filter_var($row['faelligkeit'], FILTER_SANITIZE_NUMBER_INT);
        if ($faelligkeitDays > 2) {
          $statusId = 'due';
          $statusLabel = 'Fällig';
        } else {
          $statusId = 'not_due';
          $statusLabel = 'Nicht Fällig';
        }
      }

      $data[] = [
        'id' => ['id' => $row['processid'], 'label' => $row['processid']],
        'status' => ['id' => $statusId, 'label' => $statusLabel],
        'incident' => ['id' => $row['incident'], 'label' => $row['incident']],
        'entryDate' => ['id' => $row['eingangsdatum'], 'label' => $row['eingangsdatum']],
        'stepLabel' => ['id' => $row['step'], 'label' => $row['steplabel']],
        'startDate' => ['id' => $row['indate'], 'label' => $row['indate']],
        'jobFunction' => ['id' => $row['jobfunction'], 'label' => $row['jobfunction']],
        'fullName' => ['id' => $row['fullname'], 'label' => $row['fullname']],
        'documentId' => ['id' => $row['dokumentid'], 'label' => $row['dokumentid']],
        'companyName' => ['id' => $row['mandantnr'], 'label' => $row['mandantname']],
        'fund' => ['id' => $row['fond_abkuerzung'], 'label' => $row['fond_abkuerzung']],
        'creditorName' => ['id' => $row['kredname'], 'label' => $row['kredname']],
        'invoiceType' => ['id' => $row['rechnungstyp'], 'label' => $row['rechnungstyp']],
        'invoiceNumber' => ['id' => $row['rechnungsnummer'], 'label' => $row['rechnungsnummer']],
        'invoiceDate' => ['id' => $row['rechnungsdatum'], 'label' => $row['rechnungsdatum']],
        'grossAmount' => ['id' => $row['bruttobetrag'], 'label' => $row['bruttobetrag']],
        'dueDate' => ['id' => $row['eskalation'], 'label' => $row['eskalation']],
        'orderId' => ['id' => $row['coor_orderid'], 'label' => $row['coor_orderid']],
        'paymentAmount' => ['id' => $row['zahlbetrag'], 'label' => $row['zahlbetrag']],
        'paymentDate' => ['id' => $row['zahldatum'], 'label' => $row['zahldatum']],
        'duration' => ['id' => $row['dauer'], 'label' => $row['dauer']],
        'invoice' => ['id' => $row['dokumentid'], 'label' => $row['dokumentid']],
        'protocol' => ['id' => $row['dokumentid'], 'label' => $row['dokumentid']],
        'chargeable' => ['id' => $row['berechenbar'], 'label' => $row['berechenbar']],
      ];
    }

    return $data;
  }
}
