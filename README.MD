# Allgemein

Dies ist die Dokumentation der Systemaktivität: "Pedant"

- _Version 2.2.2_

Inhaltsverzeichnis

- [Allgemein](#allgemein)
- [Wie funktioniert die Systemaktivität (allgemein)](#wie-funktioniert-die-systemaktivität-allgemein)
  - [Grundprinzip](#grundprinzip)
  - [Architektur: Trait-basiertes Design](#architektur-trait-basiertes-design)
  - [Der asynchrone Verarbeitungszyklus](#der-asynchrone-verarbeitungszyklus)
- [Übersicht der Datein](#übersicht-der-datein)
  - [Vorstellung von Datei: SystemActivity.php](#vorstellung-von-datei-systemactivityphp)
    - [Welche Aufgabe erfüllt SystemActivity.php?](#welche-aufgabe-erfüllt-systemactivityphp)
  - [Vorstellung von Datei: dialog.xml](#vorstellung-von-datei-dialogxml)
    - [Welche Aufgabe erfüllt dialog.xml?](#welche-aufgabe-erfüllt-dialogxml)
    - [Funktionsregistrierung](#funktionsregistrierung)
  - [Funktion: pedant](#funktion-pedant)
  - [Welche Aufgabe erfüllt Pedant?](#welche-aufgabe-erfüllt-pedant)
    - [Aufgaben der Systemaktivität (PHP-Code)](#aufgaben-der-systemaktivität-php-code)
    - [Aufgaben der KI (Pedant.ai)](#aufgaben-der-ki-pedantai)
    - [Dialogfelder von pedant](#dialogfelder-von-pedant)
  - [Vorstellung von Funktion: fetchData](#vorstellung-von-funktion-fetchdata)
    - [Welche Aufgabe erfüllt fetchData?](#welche-aufgabe-erfüllt-fetchdata)
    - [Aufgaben der Systemaktivität (PHP-Code)](#aufgaben-der-systemaktivität-php-code-1)
    - [Dialogfelder von fetchData](#dialogfelder-von-fetchdata)
  - [Vorstellung von Funktion: documentClassifier](#vorstellung-von-funktion-documentclassifier)
    - [Welche Aufgabe erfüllt documentClassifier?](#welche-aufgabe-erfüllt-documentclassifier)
    - [Aufgaben der Systemaktivität (PHP-Code)](#aufgaben-der-systemaktivität-php-code-2)
    - [Aufgaben der KI (Pedant.ai)](#aufgaben-der-ki-pedantai-1)
    - [Dialogfelder von documentClassifier](#dialogfelder-von-documentclassifier)
  - [Vorstellung der Import-Funktionen: importVendorCSV / importRecipientCSV / importCostCenterCSV](#vorstellung-der-import-funktionen-importvendorcsv--importrecipientcsv--importcostcentercsv)
    - [Welche Aufgabe erfüllen die Import-Funktionen?](#welche-aufgabe-erfüllen-die-import-funktionen)
    - [Aufgaben der Systemaktivität (PHP-Code)](#aufgaben-der-systemaktivität-php-code-3)
    - [Aufgaben der KI / Plattform (Pedant.ai)](#aufgaben-der-ki--plattform-pedantai)
    - [Unterschiede zwischen den Funktionen](#unterschiede-zwischen-den-funktionen)
    - [Dialogfelder der Import-Funktionen](#dialogfelder-der-import-funktionen)
  - [Vorstellung der Dateien: german.php / english.php](#vorstellung-der-dateien-germanphp--englishphp)
    - [Welche Aufgabe erfüllen german.php / english.php?](#welche-aufgabe-erfüllen-germanphp--englishphp)
    - [Aufgaben der Sprachdateien (Lokalisierung)](#aufgaben-der-sprachdateien-lokalisierung)
    - [Funktionsweise im System](#funktionsweise-im-system)
  - [Vorstellung von Datei: config.php](#vorstellung-von-datei-configphp)
    - [Welche Aufgabe erfüllt config.php?](#welche-aufgabe-erfüllt-configphp)
    - [Aufgaben der Datei config.php (Systemkonfiguration)](#aufgaben-der-datei-configphp-systemkonfiguration)
- [Technisches Zusammenspiel \& Details](#technisches-zusammenspiel--details)
  - [Die funktionalen Bausteine (Traits)](#die-funktionalen-bausteine-traits)
  - [Infrastruktur \& Hilfsmittel](#infrastruktur--hilfsmittel)
    - [Abhängigkeitsmatrix](#abhängigkeitsmatrix)
  - [Betrieb, Debugging \& Versionierung](#betrieb-debugging--versionierung)
    - [Logging](#logging)
    - [API-Umgebungen](#api-umgebungen)
    - [Versionsstand und Repository](#versionsstand-und-repository)

# Wie funktioniert die Systemaktivität (allgemein)

Die Systemaktivität fungiert als zentrale Schnittstelle zwischen JobRouter und den Diensten von Pedant.ai. Sie ermöglicht die automatisierte Verarbeitung von Dokumenten (insbesondere Rechnungen) sowie den Stammdatenaustausch direkt aus einem laufenden Workflow heraus.

## Grundprinzip

Die Aktivität ist als modulare PHP-Klasse implementiert, die auf der AbstractSystemActivityAPI von JobRouter basiert. Sie übernimmt die Aufgabe, Daten aus dem JobRouter-Prozess zu lesen, diese an die Pedant-API zu übermitteln und die von der KI extrahierten Ergebnisse strukturiert in den Prozess zurückzuschreiben.

## Architektur: Trait-basiertes Design

Um den Code wartbar und übersichtlich zu halten, wurde eine Trait-Architektur gewählt. Jede Kernaufgabe ist in einer eigenen Datei (Trait) gekapselt:

- ApiTrait: Regelt die technische Kommunikation (HTTPS/cURL) mit den Pedant-Endpunkten.

- DataMapperTrait: Überträgt die komplexen JSON-Antworten der KI in die entsprechenden JobRouter-Variablen und Untertabellen.

- LoggerTrait: Erstellt detaillierte Protokolle für jeden Verarbeitungsschritt, um die Fehlersuche zu vereinfachen.

- Spezialisierte Traits: (z.B. InvoiceTrait, DocumentClassifierTrait) enthalten die spezifische Business-Logik für die jeweiligen Funktionen.

## Der asynchrone Verarbeitungszyklus

Da die Analyse von Dokumenten durch eine KI Zeit in Anspruch nimmt, arbeiten viele Funktionen dieser Systemaktivität nach einem "Check-and-Wait"-Prinzip:

- Upload-Phase: Das Dokument wird an Pedant gesendet. Die Aktivität speichert eine eindeutige ID und setzt den Schritt auf Wiedervorlage.

- Wartephase: JobRouter pausiert die Aktivität für einen definierten Zeitraum.

- Abhol-Phase: Nach Ablauf der Zeit prüft die Aktivität automatisch, ob die Analyse fertiggestellt wurde. Wenn ja, werden die Daten importiert; wenn nein, erfolgt eine erneute Wartezeit.

# Übersicht der Datein

Im Ordner Pedant lassen sich verschiedene Datein finden.

- _Datei:_ SystemActivity.php
- _Datei:_ dialog.xml
- _Datei:_ config.php
- **Ordner:** Traits
  - _Datei:_ ApiTrait.php
  - _Datei:_ DataMapperTrait.php
  - _Datei:_ DocumentClassifierTrait.php
  - _Datei:_ FetchTrait.php
  - _Datei:_ HelperTrait.php
  - _Datei:_ ImportTrait.php
  - _Datei:_ InvoiceTrait.php
  - _Datei:_ LoggerTrait.php
- **Ordner:** languages
  - _Datei:_ german.php
  - _Datei:_ english.php

- **Ordner:** images
  - _Datei:_ toolbox_icon.svg

## Vorstellung von Datei: SystemActivity.php

Die Datei SystemActivity.php bildet das technische Grundgerüst und den Haupteinstiegspunkt für die gesamte JobRouter-Erweiterung. Sie fungiert als "Orchestrator", der alle spezialisierten Funktionen bündelt und die Kommunikation mit dem JobRouter-Framework sicherstellt.

### Welche Aufgabe erfüllt SystemActivity.php?

Ihre Hauptaufgabe ist die Strukturierung und Bereitstellung der Programmlogik gegenüber JobRouter. Während die fachliche Logik in den einzelnen Traits (z. B. InvoiceTrait) gekapselt ist, definiert SystemActivity.php die Klasse pedantSystemActivity, die von der JobRouter-Basisklasse erbt. Sie ist verantwortlich für das Initialisieren der Umgebung und das Routen der Anfragen an die korrekten Programmteile.

Hierbei findet eine strikte Aufgabenteilung statt:

- Klassen-Definition: Erstellt die Klasse pedantSystemActivity, die als Schnittstelle zum JobRouter-Server dient.

- Modul-Einbindung: Lädt alle notwendigen Abhängigkeiten und Traits via require_once (z. B. LoggerTrait.php, ApiTrait.php).

- Konstanten-Verwaltung: Definition globaler Parameter wie Erfolgs-HTTP-Codes (z. B. 200, 201), maximale Dateigrößen oder erlaubte Status-Flags.

- Einstiegspunkt-Routing: Leitet die Aufrufe aus dem JobRouter-Workflow (wie pedant, fetchData oder documentClassifier) an die entsprechenden Methoden innerhalb der Traits weiter.

## Vorstellung von Datei: dialog.xml

Die Datei dialog.xml ist das Konfigurations-Manifest der Systemaktivität. Sie dient als Bindeglied zwischen dem JobRouter-Framework und dem PHP-Code, indem sie die grafische Benutzeroberfläche (GUI) im JobRouter Designer definiert.

### Welche Aufgabe erfüllt dialog.xml?

Die Hauptaufgabe dieser Datei ist die Definition der Schnittstellen. Sie legt fest, welche Parameter ein Administrator im Workflow-Designer sehen kann, welche Daten in die Systemaktivität hineinfließen (**Input**) und welche Werte nach der Verarbeitung an den Workflow zurückgegeben werden (**Output**). Ohne diese Datei wäre die Systemaktivität innerhalb der JobRouter-Oberfläche nicht konfigurierbar oder sichtbar.

Aufgaben der Datei dialog.xml (Struktur/Konfiguration)

- Funktionsregistrierung: Deklaration der verfügbaren Methoden (z. B. pedant, fetchData, documentClassifier), damit diese im Designer ausgewählt werden können.

- Parameter-Definition: Festlegung aller Eingabefelder (z. B. API_KEY, INPUTFILE) und Ausgabefelder (z. B. VENDOR_NAME, TOTAL_AMOUNT).

- Datentyp-Validierung: Bestimmung, welche Art von Daten erwartet wird (z. B. file für Dokumente, varchar für Text, int für Zahlen oder checkbox für den Demo-Modus).

- UI-Gestaltung: Definition von Anzeigenamen (name) und Beschreibungen (description), die dem Administrator im JobRouter-Dialog angezeigt werden.

- Pflichtfeld-Steuerung: Festlegung, welche Parameter zwingend ausgefüllt werden müssen (required='yes'), damit die Aktivität korrekt funktioniert.

- Tabellen-Mapping: Konfiguration von Listen-Parametern (UDL), um die Rückgabe von Rechnungspositionen oder Klassifizierungsdetails in JobRouter-Untertabellen zu ermöglichen.

### Funktionsregistrierung

In unserem Fall lassen sich in der dialog.xml 6 Funktionen wiederfinden:

- pedant
- fetchData
- documentClassifier
- importVendorCSV
- importRecipientCSV
- importCostCenterCSV

## Funktion: pedant

Die Funktion pedant ist das Herzstück der Systemaktivität für die Rechnungsverarbeitung. Sie steuert den gesamten Lebenszyklus eines Dokuments von der Übermittlung aus JobRouter bis hin zur Rückgabe der extrahierten Daten.

## Welche Aufgabe erfüllt Pedant?

Die primäre Aufgabe dieser Funktion ist die automatisierte Extraktion von Rechnungsinformationen. Sie sorgt dafür, dass physische Dokumente (PDFs) in strukturierte Daten umgewandelt werden, die JobRouter direkt in Prozess- oder Untertabellen weiterverarbeiten kann.

Hierbei findet eine strikte Aufgabenteilung zwischen der lokalen Systemaktivität (PHP) und der externen KI (Pedant.ai) statt:

### Aufgaben der Systemaktivität (PHP-Code)

- Vorbereitung: Auslesen der API-Keys und Validierung der Eingabedatei (z.B. Dateigröße prüfen).

- Datenübertragung: Sicherer Versand des Dokuments per HTTPS/cURL an die Pedant-API.

- Prozesssteuerung: Verwaltung der Wiedervorlage (Resubmission) in JobRouter, damit der Workflow wartet, während die KI arbeitet.

- Zustandsüberwachung: Prüfung des aktuellen Verarbeitungsstatus (z.B. processing, reviewed).

- Datenmapping: Entgegennahme der JSON-Ergebnisse und technisches Schreiben dieser Werte in die JobRouter-Variablen und Untertabellen (via DataMapperTrait).

- Cleanup: Löschen von temporären Dateien und Aufräumen der Logs nach Abschluss.

### Aufgaben der KI (Pedant.ai)

- OCR-Verarbeitung: Optische Zeichenerkennung, um Text aus Bilddaten oder PDFs lesbar zu machen.

- Kontext-Analyse: Identifikation, welche Zahl ein Datum, eine Rechnungsnummer oder ein Bruttobetrag ist.

- Stammdatenabgleich: Erkennung von Kreditor (Vendor) und Debitor (Recipient) basierend auf dem Briefkopf.

- Positionsextraktion: Auslesen einzelner Rechnungspositionen inklusive Mengen, Einzelpreisen und Steuererläuterungen.

- Validierung: Prüfung der mathematischen Korrektheit (z.B. Summe der Positionen vs. Gesamtbetrag) und Erkennung von Unstimmigkeiten.

### Dialogfelder von pedant

**Inputfelder**

| Parameter      | Bedeutung                                                                  | Hinweis                                                            |
| -------------- | -------------------------------------------------------------------------- | ------------------------------------------------------------------ |
| `INPUTFILE`    | Nimmt das zu verarbeitende Dokument aus dem JobRouter-Prozess entgegen.    | Pflichtfeld                                                        |
| `API_KEY`      | Authentifizierungsschlüssel für die Pedant-API.                            | Pflichtfeld                                                        |
| `DEMO`         | Schaltet zwischen Demo- und Produktivumgebung um.                          | `1` = Demo                                                         |
| `INTNUMBER`    | Übergibt eine Mandanten- oder Empfängerreferenz an Pedant.                 | Unterstützt die Zuordnung                                          |
| `MAXRETRIES`   | Legt die maximale Anzahl an Upload- oder Check-Versuchen fest.             | Schutz vor Endlosschleifen                                         |
| `FLAG`         | Steuert den Verarbeitungsmodus.                                            | Erlaubt: `normal`, `check_extraction`, `skip_review`, `force_skip` |
| `FLAGXML`      | Überschreibt `FLAG`, wenn eine XML-Datei verarbeitet wird.                 | Nur für XML relevant                                               |
| `NEWVERSION`   | Setzt die Wiedervorlage auf ca. 2 Jahre.                                   | Technische Sonderlogik                                             |
| `ZUGFERD`      | Aktiviert den ZUGFeRD-bezogenen Uploadpfad.                                | Relevant für hybride E-Rechnungen                                  |
| `INTERVAL`     | Wiedervorlage in Minuten, wenn `NEWVERSION` nicht aktiv ist.               | Polling-Abstand                                                    |
| `NOTE`         | Optionaler Kommentar, der mit dem Dokument an Pedant übertragen wird.      | Optional                                                           |
| `INCIDENT`     | Kennzeichnet den Vorgang für das Logging.                                  | Erscheint in jeder Logzeile                                        |
| `MAXFILESIZE`  | Maximale Dateigröße in MB.                                                 | Standardwert ohne Eingabe: 20 MB                                   |
| `VENDORTABLE`  | Optionale JobRouter-Tabelle für einen Vendor-Import während `checkFile()`. | Spezialfall                                                        |
| `IMPORTVENDOR` | Mapping-Liste für den optionalen Vendor-Import.                            | Gehört zu `VENDORTABLE`                                            |

**Outputfelder**

| Parameter           | Bedeutung                                                                                                                 |
| ------------------- | ------------------------------------------------------------------------------------------------------------------------- |
| `FILEID`            | Von Pedant zurückgegebene Dokumenten-ID; wird später als `fileId` oder bei E-Rechnungen als `documentId` weiterverwendet. |
| `INVOICEID`         | Pedant-interne Rechnungs-ID.                                                                                              |
| `TEMPJSON`          | Vollständige Rohantwort der API als JSON.                                                                                 |
| `COUNTERSUMMARY`    | Zusammenfassung der Upload- und Check-Versuche.                                                                           |
| `RECIPIENTDETAILS`  | Extrahierte Empfängerdaten.                                                                                               |
| `VENDORDETAILS`     | Extrahierte Lieferantendaten.                                                                                             |
| `INVOICEDETAILS`    | Rechnungs-Kopfdaten wie Nummer, Datum und Beträge.                                                                        |
| `POSITIONDETAILS`   | Rechnungspositionen in einer Untertabelle.                                                                                |
| `AUDITTRAILDETAILS` | Bearbeitungshistorie aus Pedant.                                                                                          |
| `REJECTIONDETAILS`  | Ablehnungsgründe und Fehlerdetails.                                                                                       |
| `ATTACHMENTS`       | Zusatzdateien wie PDF, Report-XML, weitere Anhänge oder Audit-Trail-CSV.                                                  |
| `WORKFLOWDETAILS`   | Workflow-Kennzeichen, z. B. ob der Direkt-Workflow verwendet wurde.                                                       |

## Vorstellung von Funktion: fetchData

Die Funktion fetchData dient als zentraler Abhol-Dienst (Poller) für bereits verarbeitete Dokumente. Im Gegensatz zur pedant-Funktion, die ein einzelnes Dokument durch den Prozess begleitet, arbeitet fetchData im Batch-Modus. Sie prüft in regelmäßigen Abständen, welche Dokumente auf der Plattform fertig zur Abholung bereitstehen, und "weckt" die entsprechenden JobRouter-Instanzen auf.

### Welche Aufgabe erfüllt fetchData?

Die Hauptaufgabe ist die Synchronisation zwischen dem Fertigstellungsstatus bei Pedant und den wartenden Workflow-Schritten in JobRouter. Sie stellt sicher, dass Prozesse nicht unnötig lange im Status "Wiedervorlage" verbleiben, wenn die menschliche Prüfung (Review) oder die KI-Analyse bereits abgeschlossen wurde.

Hierbei findet eine strikte Aufgabenteilung statt:

### Aufgaben der Systemaktivität (PHP-Code)

- Zeitsteuerung (Scheduling): Berechnung des nächsten Abholzeitpunkts basierend auf Konfigurationsparametern wie Arbeitszeiten (worktime) und Wochenend-Regelungen (weekend).

- Batch-Abfrage: Systematisches Durchlaufen der Pedant-API-Listen (Pagination), um alle Dokumente mit Status wie reviewed, exported oder rejected zu finden.

- Datenbank-Abgleich: Identifikation der zugehörigen JobRouter-Prozessinstanz anhand der von Pedant zurückgegebenen IDs.

- Triggering: Ausführung von SQL-Updates (JRINCIDENTS), um das Wiedervorlage-Datum der betroffenen Schritte auf die aktuelle Zeit zu setzen. Dadurch wird der Workflow sofort fortgesetzt.

- Status-Filterung: Entscheidung, welche Dokumente aufgrund ihres Status (z.B. nur reviewed) für einen Import in Frage kommen.

### Dialogfelder von fetchData

**Inputfelder**

| Parameter        | Bedeutung                                                                           | Hinweis                                                                        |
| ---------------- | ----------------------------------------------------------------------------------- | ------------------------------------------------------------------------------ |
| `API_KEY`        | Authentifizierungsschlüssel für die Pedant-API.                                     | Pflichtfeld                                                                    |
| `INVOICE_STATUS` | Bestimmt, welche Dokumentstatus abgefragt werden.                                   | Erlaubt: `reviewed`, `exported`, `rejected`, `archived`; kommagetrennt möglich |
| `DEMO`           | Schaltet zwischen Demo- und Produktivumgebung um.                                   | Optional                                                                       |
| `FILEID`         | Name der Spalte in der JobRouter-Kopftabelle, in der die Pedant-ID gespeichert ist. | Nicht der eigentliche ID-Wert                                                  |
| `TABLEHEAD`      | Name der JobRouter-Prozesstabelle.                                                  | Für den SQL-Join erforderlich                                                  |
| `STEPID`         | Technische ID des wartenden Prozessschritts.                                        | Ziel der Reaktivierung                                                         |
| `INTERVAL`       | Polling-Abstand in Minuten.                                                         | Steuerung der Wiedervorlage                                                    |
| `WORKTIME`       | Arbeitszeitfenster, z. B. `6,18`.                                                   | Außerhalb des Fensters wird verschoben                                         |
| `WEEKEND`        | Aktiviert oder deaktiviert die Ausführung am Wochenende.                            | Beeinflusst die Wiedervorlage                                                  |

**Outputfelder**

Für `fetchData` sind keine Outputparameter definiert. Die Funktion arbeitet direkt auf der JobRouter-Datenbank und aktualisiert dort die Wiedervorlage wartender Schritte.

## Vorstellung von Funktion: documentClassifier

Die Funktion documentClassifier dient der automatisierten Einordnung und Metadaten-Extraktion von Dokumenten, noch bevor diese in eine spezifische Fachverarbeitung (wie die Rechnungsprüfung) gehen. Sie wird genutzt, um die Art eines Dokuments zu bestimmen und grundlegende Kopfdaten zu identifizieren.

### Welche Aufgabe erfüllt documentClassifier?

Die Hauptaufgabe dieser Funktion ist die Vorklassifizierung von Dokumenten. Sie entscheidet, um welchen Dokumententyp es sich handelt (z. B. Rechnung, Mahnung, Lieferschein) und extrahiert erste Identifikationsmerkmale wie Absender und Empfänger. Dies ermöglicht eine intelligente Steuerung des Workflows in JobRouter (Routing).

### Aufgaben der Systemaktivität (PHP-Code)

- Status-Management: Verwaltung des DC_UPLOADCOUNTER und der DC_DOCUMENTID, um zwischen der Upload-Phase und der Abfrage-Phase zu unterscheiden.

- Datei-Transfer: Übermittlung des Dokuments an den speziellen Classifier-Endpunkt der Pedant-API (/classifier).

- Steuerung der Wiedervorlage: Setzen der Wartezeit basierend auf dem Parameter dc_interval, falls das Ergebnis noch nicht vorliegt.

- Ergebnis-Mapping: Übertragung der Klassifizierungsergebnisse (z. B. documentType, vendorCompanyName) in die JobRouter-Ausgabeparameter und Untertabellen.

- Persistenz: Speicherung des vollständigen Antwort-JSONs in der Variable dc_tempJSON zur weiteren Verwendung im Prozess.

### Aufgaben der KI (Pedant.ai)

- Dokumententyp-Erkennung: Analyse des Layouts und Inhalts, um den Typ des Dokuments zu klassifizieren (z. B. "Invoice", "Credit Note").

- Entitäten-Identifikation: Erkennung von Firmennamen für Kreditor (Vendor) und Debitor (Recipient) allein auf Basis der Klassifizierungs-Logik.

- Datumsextraktion: Identifikation des Belegdatums (issueDate).

- ID-Vergabe: Erzeugung einer eindeutigen documentClassifierNumber für die Referenzierung.

### Dialogfelder von documentClassifier

**Inputfelder**

| Parameter       | Bedeutung                                         | Hinweis                                                                                |
| --------------- | ------------------------------------------------- | -------------------------------------------------------------------------------------- |
| `API_KEY`       | Authentifizierungsschlüssel für die Pedant-API.   | Pflichtfeld                                                                            |
| `DEMO`          | Schaltet zwischen Demo- und Produktivumgebung um. | Optional                                                                               |
| `INPUTFILE`     | Zu klassifizierendes Dokument.                    | Pflichtfeld                                                                            |
| `DC_ACTION`     | Upload-Aktion für den Classifier.                 | Erlaubt: `normal`, `check_extraction`, `skip_review`, `force_skip`; Standard: `normal` |
| `DC_MAXRETRIES` | Maximale Anzahl an Check-Versuchen.               | Schutz vor Endlosschleifen                                                             |
| `DC_INTERVAL`   | Wiedervorlage in Minuten.                         | Polling-Abstand                                                                        |
| `MAXFILESIZE`   | Maximale Dateigröße in MB.                        | Standardwert ohne Eingabe: 20 MB                                                       |

**Outputfelder**

| Parameter               | Bedeutung                                                           |
| ----------------------- | ------------------------------------------------------------------- |
| `DC_DOCUMENTID`         | Dokument-ID für spätere Statusabfragen.                             |
| `DC_TEMPJSON`           | Vollständige Rohantwort der API als JSON.                           |
| `CLASSIFICATIONDETAILS` | Strukturierte Klassifikation wie Dokumenttyp, Firmenname und Datum. |

## Vorstellung der Import-Funktionen: importVendorCSV / importRecipientCSV / importCostCenterCSV

Diese Funktionen dienen dem Abgleich von Stammdaten zwischen JobRouter und Pedant.ai. Da die technische Umsetzung für alle drei Entitätstypen (Kreditoren, Debitoren und Kostenstellen) auf einer gemeinsamen Logik basiert, werden sie hier zusammengefasst. Trotzedem werden bestehende Unterschiede zusammenfassend am Ende behandelt.

### Welche Aufgabe erfüllen die Import-Funktionen?

Die Hauptaufgabe dieser Funktionen ist die Synchronisation von Stammdaten. Durch den regelmäßigen Export von Daten aus JobRouter und den anschließenden Import in Pedant.ai wird sichergestellt, dass die KI aktuelle Informationen (z. B. Adressdaten, Bankverbindungen oder Kostenstellennummern) besitzt. Dies erhöht die Erkennungsrate und Zuordnungsqualität bei der Dokumentenanalyse signifikant.

### Aufgaben der Systemaktivität (PHP-Code)

- Datenextraktion: Durchführung einer SQL-Abfrage auf die JobRouter-Datenbank, um die aktuell hinterlegten Stammdaten aus den konfigurierten Tabellen zu lesen.

- CSV-Generierung: Umwandlung der Datenbank-Ergebnisse in eine temporäre CSV-Datei, die dem von Pedant geforderten Format entspricht.

- API-Kommunikation: Versand der CSV-Datei als "multipart/form-data" per POST-Request an den jeweiligen API-Endpunkt der Entität.

- Parameter-Handling: Auflösung der Konfiguration, welche Felder überschrieben werden dürfen (Overrides) und welche Tabellenspalten als Quelle dienen.

- Bereinigung: Sicheres Löschen der temporär erzeugten CSV-Dateien vom Server-Dateisystem nach Abschluss des Uploads.

### Aufgaben der KI / Plattform (Pedant.ai)

- Daten-Ingestion: Einlesen und Validieren der hochgeladenen CSV-Struktur.

- Datenbank-Update: Aktualisierung des internen Datenbestands für den jeweiligen API-Key.

- Optimierung der Erkennung: Nutzung der neuen Stammdaten als Referenzwerte, um bei zukünftigen OCR-Analysen z. B. einen Kreditor anhand seiner IBAN oder seines Namens zweifelsfrei zu identifizieren.

### Unterschiede zwischen den Funktionen

Obwohl der Ablauf identisch ist, unterscheiden sich die Funktionen in folgenden Punkten:

- Ziel-Endpunkte: Jede Funktion spricht einen spezifischen API-Endpunkt an (z. B. /vendors/import für Kreditoren, /recipients/import für Debitoren).

- Dateinamen: Die temporär erzeugten CSV-Dateien haben unterschiedliche Namen (z. B. pedantVendorOutput.csv, pedantRecipientOutput.csv), um Konflikte bei gleichzeitiger Ausführung zu vermeiden.

- Vendor-Speziallogik (Overrides): Nur die Funktion importVendorCSV verfügt über eine zusätzliche Logik für "Override-Fields". Damit kann gesteuert werden, ob die KI bestimmte Felder (wie PLZ, Ort oder Bankverbindung) bei einer Erkennung zwingend durch die hochgeladenen Stammdaten überschreiben soll.

- Konfigurations-Parameter: Die Funktionen nutzen unterschiedliche Input-Parameter aus der dialog.xml, um die Quell-Tabellen in JobRouter zu identifizieren (z. B. vendorTable vs. recipientTable).

### Dialogfelder der Import-Funktionen

**Inputfelder**

| Parameter                                               | Bedeutung                                                                    | Hinweis                                       |
| ------------------------------------------------------- | ---------------------------------------------------------------------------- | --------------------------------------------- |
| `API_KEY`                                               | Authentifizierungsschlüssel für die Pedant-API.                              | Pflichtfeld                                   |
| `DEMO`                                                  | Schaltet zwischen Demo- und Produktivumgebung um.                            | Optional                                      |
| `VENDORTABLE` / `RECIPIENTTABLE` / `COSTCENTERTABLE`    | Name der JobRouter-Tabelle oder View, aus der die Stammdaten gelesen werden. | Je nach Funktion unterschiedlich              |
| `IMPORTVENDOR` / `IMPORTRECIPIENT` / `IMPORTCOSTCENTER` | Mapping-Liste für die CSV-Erzeugung.                                         | Ordnet Datenbankspalten den Pedant-Feldern zu |

**Outputfelder**

Für die Import-Funktionen sind keine Outputparameter definiert. Sie dienen ausschließlich dem Export von Stammdaten nach Pedant.

## Vorstellung der Dateien: german.php / english.php

Diese Dateien dienen der Lokalisierung (Übersetzung) der Systemaktivität. Sie stellen sicher, dass die Benutzeroberfläche der Aktivität im JobRouter Designer in der jeweils vom Administrator gewählten Sprache angezeigt wird.

### Welche Aufgabe erfüllen german.php / english.php?

Die Hauptaufgabe dieser Dateien ist die Bereitstellung von Sprachkonstanten. Anstatt Texte (wie Beschreibungen von Eingabefeldern oder Fehlermeldungen) direkt "hart" in die dialog.xml zu schreiben, werden dort Platzhalter (Konstanten) verwendet. Die Übersetzungsdateien füllen diese Platzhalter mit dem tatsächlichen Text in der entsprechenden Sprache.

### Aufgaben der Sprachdateien (Lokalisierung)

- Text-Definition: Zuweisung von lesbaren Texten zu technischen Bezeichnern (z. B. wird aus der Konstante INPUTFILE in der german.php der Text "Eingabedatei").

- Beschreibungen: Bereitstellung von ausführlichen Hilfetexten für den Designer (z. B. Erklärungen, was ein bestimmter "Flag" bewirkt), die dem Administrator beim Drüberfahren mit der Maus angezeigt werden.

- Übersetzung der Rückgabewerte: Definition von Anzeigenamen für die Werte in Dropdown-Menüs oder Auswahllisten innerhalb der Systemaktivitäts-Konfiguration.

- Fehlermeldungs-Templates: Vorhalten von sprachspezifischen Textbausteinen, die im Log oder in der JobRouter-Oberfläche ausgegeben werden können.

### Funktionsweise im System

Wenn JobRouter die dialog.xml lädt, um die Systemaktivität anzuzeigen, prüft das System die Spracheinstellung des aktuell angemeldeten Benutzers.

Ist die Sprache auf Deutsch eingestellt, wird die german.php geladen.

Ist die Sprache auf Englisch eingestellt, wird die english.php geladen.

Alle in der dialog.xml verwendeten Großbuchstaben-Konstanten (z. B. READ_DESC) werden dann durch die entsprechenden Werte aus dem $const-Array der jeweiligen Datei ersetzt.

## Vorstellung von Datei: config.php

Die Datei config.php ist die zentrale Konfigurationsdatei für die Laufzeitumgebung der Systemaktivität. Während die dialog.xml die Einstellungen pro Workflow-Schritt regelt, definiert die config.php systemweite Parameter, die für alle Instanzen der Aktivität gleichermaßen gelten.

### Welche Aufgabe erfüllt config.php?

Die Hauptaufgabe dieser Datei ist die Bereitstellung von globalen Steuerungsparametern des Debuggings. Sie dient vor allem dazu, das Verhalten der Log-Informationen zu beeinflussen, ohne den PHP-Code selbst ändern zu müssen.

### Aufgaben der Datei config.php (Systemkonfiguration)

- Festlegung des Log-Levels: Definition, wie detailliert die Aktivität protokollieren soll (z. B. debug für die Entwicklung oder info für den Produktivbetrieb). Dies wird vom LoggerTrait ausgelesen.

- Unterstützte Werte: `debug`, `info`, `warning`, `error`.

- Standardwert der aktuellen Codebasis: `info`.

- Zentraler Parameter-Speicher: Rückgabe eines Konfigurations-Arrays (return [...]), welches von der SystemActivity.php und den Traits eingebunden wird.

# Technisches Zusammenspiel & Details

Die Architektur der Systemaktivität basiert auf dem Prinzip der Separation of Concerns (Trennung von Zuständigkeiten). Anstatt die gesamte Logik in einer einzigen, unübersichtlichen Datei zu bündeln, ist der Code in modulare Traits unterteilt. Diese lassen sich in zwei Kategorien gliedern:

## Die funktionalen Bausteine (Traits)

Diese Traits enthalten die fachliche Hauptlogik für die in der dialog.xml definierten Funktionen.

- InvoiceTrait.php (pedant): Steuert den Lebenszyklus der Rechnungsverarbeitung. Er verwaltet den Upload zur KI (uploadFile) und die zyklische Statusabfrage (checkFile), bis das Dokument finalisiert wurde.

- DocumentClassifierTrait.php (documentClassifier): Spezialisierter Baustein für die Dokumentenklassifizierung. Er kommuniziert mit der Classifier-API von Pedant, um Dokumenttypen (z. B. Lieferschein vs. Rechnung) zu bestimmen.

- FetchTrait.php (fetchData): Verantwortlich für den Batch-Abruf. Er identifiziert fertig verarbeitete Dokumente auf der Plattform und berücksichtigt dabei konfigurierte Arbeitszeiten und Wochenenden, um die Workflow-Schritte in JobRouter wieder zu aktivieren.

- ImportTrait.php (import...CSV): Regelt den Stammdatenabgleich. Er extrahiert Daten (Kreditoren, Kostenstellen etc.) aus JobRouter-Tabellen, generiert CSV-Dateien und übermittelt diese an die Pedant-API.

## Infrastruktur & Hilfsmittel

Diese Bausteine bilden das technische Fundament und werden von den funktionalen Traits im Hintergrund genutzt.

- ApiTrait.php: Die unterste Kommunikationsebene. Hier erfolgt die technische Abwicklung der HTTP-Requests (cURL). Der Trait verwaltet Endpunkt-URLs, API-Header, die Authentifizierung via API-Key, die Umschaltung zwischen Demo- und Produktivumgebung sowie die Basis-Fehlerbehandlung bei Übertragungsfehlern.

- DataMapperTrait.php: Fungiert als technisches "Übersetzungsbüro". Dieser Trait zerlegt die komplexen JSON-Antworten der API und verteilt die Werte (Positionen, Steuerbeträge, Audit-Trail) strukturiert in die JobRouter-Variablen und Untertabellen.

- LoggerTrait.php: Das zentrale Diagnose-Werkzeug. Er schreibt detaillierte Protokolle in vier Stufen (DEBUG, INFO, WARNING, ERROR), legt tägliche Logdateien unter `logs/log` an, versieht jede Zeile mit einem Incident-Tag und bereinigt alte Dateien automatisch.

- HelperTrait.php: Enthält allgemeine Hilfsfunktionen. Ein Kernaspekt ist die automatische Erkennung des Datenbanktyps (MySQL oder MSSQL), um SQL-Abfragen innerhalb der Systemaktivität dynamisch an die jeweilige Umgebung anzupassen.

### Abhängigkeitsmatrix

Die folgende Tabelle zeigt, welche funktionalen Bausteine auf welche Infrastruktur-Traits zurückgreifen:

| Funktion (Trait)          | Benötigt ApiTrait? | Benötigt DataMapper? | Benötigt LoggerTrait? | Benötigt HelperTrait? |
| ------------------------- | ------------------ | -------------------- | --------------------- | --------------------- |
| InvoiceTrait (Rechnungen) | Ja                 | Ja                   | Ja                    | Nein                  |
| FetchTrait (Batch-Abruf)  | Ja                 | Nein                 | Ja                    | Ja                    |
| ImportTrait (Stammdaten)  | Ja                 | Nein                 | Ja                    | Nein                  |
| DocumentClassifierTrait   | Ja                 | Nein                 | Ja                    | Nein                  |

## Betrieb, Debugging & Versionierung

### Logging

- Die Logdateien werden automatisch unter `pedant/logs/log/` angelegt.

- Der Dateiname entspricht immer dem aktuellen Tag im Format `DDMMYYYY.log`.

- Jede Logzeile enthält Zeitstempel, Log-Level, Incident und die eigentliche Nachricht. Optional werden Exception-Details und JSON-Kontext angehängt.

- Das Incident-Tag kommt aus dem Input-Parameter `INCIDENT`. Wenn dieser Wert nicht aufgelöst werden kann, wird `NO_INCIDENT` verwendet.

- Logdateien älter als 7 Tage werden beim Einstieg in die Hauptfunktionen automatisch gelöscht.

### API-Umgebungen

- Wenn `DEMO` aktiv ist, werden Rechnungs-, Fetch- und Classifier-Requests gegen `https://api.demo.pedant.ai` ausgeführt.

- Wenn `DEMO` aktiv ist, laufen auch Entity-Importe gegen `https://api.demo.pedant.ai`.

- In der Produktivumgebung laufen Rechnungs-, Fetch- und Classifier-Requests gegen `https://api.pedant.ai`.

- Entity-Importe laufen in der Produktivumgebung gegen `https://entity.api.pedant.ai`.

### Versionsstand und Repository

- Der aktuelle Versionsstand ist in dieser README und zusätzlich im Kopfkommentar der Datei `SystemActivity.php` dokumentiert.

- Frühere Stände lassen sich über die Commit-Historie sowie gegebenenfalls über Tags oder Releases im Versions-Repository nachvollziehen.
