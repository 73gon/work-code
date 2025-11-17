<?php

namespace dashboard\MyWidgets\SimplifyTable;

use JobRouter\Api\Dashboard\v1\Widget;
use DateTime;
use DateTimeZone;

class SimplifyTable extends Widget
{

  public function getTitle()
  {
    return 'Umlauftabelle';
  }

  public function getDimensions()
  {

    return [
      'minHeight' => 8,
      'minWidth' => 6,
      'maxHeight' => 8,
      'maxWidth' => 6,
    ];
  }

  /*
  public function isAuthorized()
  {
    return $this->getUser()->isInJobFunction('Widgets');
  }
  */

  public function getData()
  {
    return [
      'columns' => json_encode($this->getColumns()),
      'dropdownOptions' => json_encode($this->getDropdownOptions()),
      'tableData' => json_encode($this->getTableData()),
    ];
  }

  /**
   * Define all table columns with their properties
   */
  public function getColumns()
  {
    return [
      ['id' => 'id', 'label' => 'ID', 'type' => 'number', 'align' => 'center'],
      ['id' => 'status', 'label' => 'Status', 'type' => 'status', 'align' => 'center'],
      ['id' => 'incident', 'label' => 'Vorgang', 'type' => 'text', 'align' => 'left'],
      ['id' => 'entryDate', 'label' => 'Eingangsdatum', 'type' => 'date', 'align' => 'left'],
      ['id' => 'stepLabel', 'label' => 'Schritt', 'type' => 'text', 'align' => 'left'],
      ['id' => 'startDate', 'label' => 'Startdatum (Schritt)', 'type' => 'date', 'align' => 'left'],
      ['id' => 'jobFunction', 'label' => 'Rolle', 'type' => 'text', 'align' => 'left'],
      ['id' => 'fullName', 'label' => 'Bearbeiter', 'type' => 'text', 'align' => 'left'],
      ['id' => 'documentId', 'label' => 'DokumentId', 'type' => 'text', 'align' => 'left'],
      ['id' => 'companyName', 'label' => 'Gesellschaft', 'type' => 'text', 'align' => 'left'],
      ['id' => 'fund', 'label' => 'Fonds', 'type' => 'text', 'align' => 'left'],
      ['id' => 'creditorName', 'label' => 'Kreditor', 'type' => 'text', 'align' => 'left'],
      ['id' => 'invoiceType', 'label' => 'Rechnungstyp', 'type' => 'text', 'align' => 'left'],
      ['id' => 'invoiceNumber', 'label' => 'Rechnungsnummer', 'type' => 'text', 'align' => 'left'],
      ['id' => 'invoiceDate', 'label' => 'Rechnungsdatum', 'type' => 'date', 'align' => 'left'],
      ['id' => 'grossAmount', 'label' => 'Bruttobetrag', 'type' => 'currency', 'align' => 'left'],
      ['id' => 'dueDate', 'label' => 'Fälligkeit', 'type' => 'date', 'align' => 'left'],
      ['id' => 'orderId', 'label' => 'Auftragsnummer', 'type' => 'text', 'align' => 'left'],
      ['id' => 'paymentAmount', 'label' => 'Zahlbetrag', 'type' => 'currency', 'align' => 'left'],
      ['id' => 'paymentDate', 'label' => 'Zahldatum', 'type' => 'date', 'align' => 'left'],
      ['id' => 'duration', 'label' => 'Dauer', 'type' => 'text', 'align' => 'left'],
      ['id' => 'invoice', 'label' => 'Rechnung', 'type' => 'text', 'align' => 'left'],
      ['id' => 'protocol', 'label' => 'Protokoll', 'type' => 'text', 'align' => 'left'],
      ['id' => 'chargeable', 'label' => 'Weiterbelasten', 'type' => 'text', 'align' => 'center'],
    ];
  }

  /**
   * Define dropdown options for filters
   */
  public function getDropdownOptions()
  {
    $JobDB = $this->getJobDB();
    
    // Fetch distinct steps from database
    $schrittQuery = "SELECT DISTINCT step, steplabel FROM V_UEBERSICHTEN_WIDGET";
    $schrittResult = $JobDB->query($schrittQuery);
    $schrittOptions = [];
    while ($row = $JobDB->fetchRow($schrittResult)) {
      $schrittOptions[] = ['id' => $row['step'], 'label' => $row['steplabel']];
    }
    
    return [
      'status' => [
      ['id' => 'Gruen', 'label' => 'Gruen'],
      ['id' => 'Rot', 'label' => 'Rot'],
      ['id' => 'Beendet', 'label' => 'Beendet'],
      ],
      'schritt' => $schrittOptions,
      'laufzeit' => [
      ['id' => '0-5 Tage', 'label' => '0-5 Tage'],
      ['id' => '6-10 Tage', 'label' => '6-10 Tage'],
      ['id' => '11-20 Tage', 'label' => '11-20 Tage'],
      ['id' => '21+ Tage', 'label' => '21+ Tage']
      ],
      'coor' => [
      ['id' => 'Ja', 'label' => 'Ja'],
      ['id' => 'Nein', 'label' => 'Nein'],
      ['id' => 'Ausstehend', 'label' => 'Ausstehend']
      ],
      'gesellschaft' => [
      ['id' => 'Firma A GmbH', 'label' => 'Firma A GmbH'],
      ['id' => 'Firma B AG', 'label' => 'Firma B AG'],
      ['id' => 'Firma C KG', 'label' => 'Firma C KG'],
      ['id' => 'Firma D SE', 'label' => 'Firma D SE'],
      ['id' => 'Firma E GmbH & Co. KG', 'label' => 'Firma E GmbH & Co. KG'],
      ['id' => 'Firma F International', 'label' => 'Firma F International'],
      ['id' => 'Firma G Holdings', 'label' => 'Firma G Holdings'],
      ['id' => 'Firma H Industries', 'label' => 'Firma H Industries']
      ],
      'fonds' => [
      ['id' => 'Fonds A', 'label' => 'Fonds A'],
      ['id' => 'Fonds B', 'label' => 'Fonds B'],
      ['id' => 'Fonds C', 'label' => 'Fonds C'],
      ['id' => 'Fonds D', 'label' => 'Fonds D'],
      ['id' => 'Fonds E', 'label' => 'Fonds E'],
      ['id' => 'Fonds Internationaldwadwdadawd', 'label' => 'Fonds Internationaldwadwdadawd'],
      ['id' => 'Fonds Portfolio', 'label' => 'Fonds Portfolio'],
      ['id' => 'Fonds Strategic', 'label' => 'Fonds Strategic']
      ],
    ];
  }

  /**
   * Fetch and return table data
   *
   */
  public function getTableData()
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
      $data[] = [
        'id' => ['id' => $row['processid'], 'label' => $row['processid']],
        'status' => ['id' => $row['dauerschritt'], 'label' => $row['dauerschritt']],
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
