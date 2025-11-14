<?php

namespace dashboard\MyWidgets\Simplidone;

use JobRouter\Api\Dashboard\v1\Widget;
use DateTime;
use DateTimeZone;

class Simplidone extends Widget
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
      ['id' => 'status', 'label' => 'Status', 'type' => 'status', 'align' => 'left'],
      ['id' => 'vorgang', 'label' => 'Vorgang', 'type' => 'text', 'align' => 'left'],
      ['id' => 'eingangsdatum', 'label' => 'Eingangsdatum', 'type' => 'date', 'align' => 'left'],
      ['id' => 'schritt', 'label' => 'Schritt', 'type' => 'text', 'align' => 'left'],
      ['id' => 'startdatum', 'label' => 'Startdatum', 'type' => 'date', 'align' => 'left'],
      ['id' => 'rolle', 'label' => 'Rolle', 'type' => 'text', 'align' => 'left'],
      ['id' => 'bearbeiter', 'label' => 'Bearbeiter', 'type' => 'text', 'align' => 'left'],
      ['id' => 'dokumentId', 'label' => 'DokumentId', 'type' => 'text', 'align' => 'left'],
      ['id' => 'gesellschaft', 'label' => 'Gesellschaft', 'type' => 'text', 'align' => 'left'],
      ['id' => 'fonds', 'label' => 'Fonds', 'type' => 'text', 'align' => 'left'],
      ['id' => 'kreditor', 'label' => 'Kreditor', 'type' => 'text', 'align' => 'left'],
      ['id' => 'rechnungstyp', 'label' => 'Rechnungstyp', 'type' => 'text', 'align' => 'left'],
      ['id' => 'rechnungsnummer', 'label' => 'Rechnungsnummer', 'type' => 'text', 'align' => 'left'],
      ['id' => 'rechnungsdatum', 'label' => 'Rechnungsdatum', 'type' => 'date', 'align' => 'left'],
      ['id' => 'bruttobetrag', 'label' => 'Bruttobetrag', 'type' => 'currency', 'align' => 'left'],
      ['id' => 'faelligkeit', 'label' => 'Fälligkeit', 'type' => 'date', 'align' => 'left'],
      ['id' => 'auftragsnummer', 'label' => 'Auftragsnummer', 'type' => 'text', 'align' => 'left'],
      ['id' => 'zahlbetrag', 'label' => 'Zahlbetrag', 'type' => 'currency', 'align' => 'left'],
      ['id' => 'zahldatum', 'label' => 'Zahldatum', 'type' => 'date', 'align' => 'left'],
      ['id' => 'dauer', 'label' => 'Dauer', 'type' => 'text', 'align' => 'left'],
      ['id' => 'rechnung', 'label' => 'Rechnung', 'type' => 'text', 'align' => 'left'],
      ['id' => 'protokoll', 'label' => 'Protokoll', 'type' => 'text', 'align' => 'left'],
      ['id' => 'weiterbelasten', 'label' => 'Weiterbelasten', 'type' => 'text', 'align' => 'center'],
    ];
  }

  /**
   * Define dropdown options for filters
   */
  public function getDropdownOptions()
  {
    return [
      'status' => ['Aktiv', 'Inaktiv', 'Ausstehend', 'Abgeschlossen'],
      'schritt' => ['Schritt 1', 'Schritt 2', 'Schritt 4', 'Schritt 5', 'Schritt 12', 'Schritt 150'],
      'laufzeit' => ['0-5 Tage', '6-10 Tage', '11-20 Tage', '21+ Tage'],
      'coor' => ['Ja', 'Nein', 'Ausstehend'],
      'gesellschaft' => ['Firma A GmbH', 'Firma B AG', 'Firma C KG', 'Firma D SE', 'Firma E GmbH & Co. KG', 'Firma F International', 'Firma G Holdings', 'Firma H Industries'],
      'fonds' => ['Fonds A', 'Fonds B', 'Fonds C', 'Fonds D', 'Fonds E', 'Fonds Internationaldwadwdadawd', 'Fonds Portfolio', 'Fonds Strategic'],
    ];
  }

  /**
   * Fetch and return table data
   * TODO: Replace with actual database query
   */
  public function getTableData()
  {
    // TODO: Implement actual data fetching from database
    // For now, returning empty array - data will be fetched via AJAX or similar
    return [];

    /*
    // Example structure when implementing:
    $data = [];

    // Fetch from database
    // $result = $this->db->query("SELECT * FROM your_table");

    // while ($row = $result->fetch()) {
    //   $data[] = [
    //     'id' => $row['id'],
    //     'status' => $row['status'],
    //     'vorgang' => $row['vorgang'],
    //     'eingangsdatum' => $row['eingangsdatum'],
    //     'schritt' => $row['schritt'],
    //     'startdatum' => $row['startdatum'],
    //     'rolle' => $row['rolle'],
    //     'bearbeiter' => $row['bearbeiter'],
    //     'dokumentId' => $row['dokumentId'],
    //     'gesellschaft' => $row['gesellschaft'],
    //     'kreditor' => $row['kreditor'],
    //     'rechnungstyp' => $row['rechnungstyp'],
    //     'rechnungsnummer' => $row['rechnungsnummer'],
    //     'rechnungsdatum' => $row['rechnungsdatum'],
    //     'bruttobetrag' => $row['bruttobetrag'],
    //     'faelligkeit' => $row['faelligkeit'],
    //     'auftragsnummer' => $row['auftragsnummer'],
    //     'zahlbetrag' => $row['zahlbetrag'],
    //     'zahldatum' => $row['zahldatum'],
    //     'dauer' => $row['dauer'],
    //     'rechnung' => $row['rechnung'],
    //     'protokoll' => $row['protokoll'],
    //     'weiterbelasten' => $row['weiterbelasten'],
    //     'fonds' => $row['fonds'],
    //     'laufzeit' => $row['laufzeit'],
    //     'coor' => $row['coor'],
    //   ];
    // }

    return $data;
    */
  }
}
