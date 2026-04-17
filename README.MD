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
  - [Vorstellung von Funktion: fetchData](#vorstellung-von-funktion-fetchdata)
    - [Welche Aufgabe erfüllt fetchData?](#welche-aufgabe-erfüllt-fetchdata)
    - [Aufgaben der Systemaktivität (PHP-Code)](#aufgaben-der-systemaktivität-php-code-1)
  - [Vorstellung von Funktion: documentClassifier](#vorstellung-von-funktion-documentclassifier)
    - [Welche Aufgabe erfüllt documentClassifier?](#welche-aufgabe-erfüllt-documentclassifier)
    - [Aufgaben der Systemaktivität (PHP-Code)](#aufgaben-der-systemaktivität-php-code-2)
    - [Aufgaben der KI (Pedant.ai)](#aufgaben-der-ki-pedantai-1)
  - [Vorstellung der Import-Funktionen: importVendorCSV / importRecipientCSV / importCostCenterCSV](#vorstellung-der-import-funktionen-importvendorcsv--importrecipientcsv--importcostcentercsv)
    - [Welche Aufgabe erfüllen die Import-Funktionen?](#welche-aufgabe-erfüllen-die-import-funktionen)
    - [Aufgaben der Systemaktivität (PHP-Code)](#aufgaben-der-systemaktivität-php-code-3)
    - [Aufgaben der KI / Plattform (Pedant.ai)](#aufgaben-der-ki--plattform-pedantai)
    - [Unterschiede zwischen den Funktionen](#unterschiede-zwischen-den-funktionen)
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
  - [Definition der Dialogfelder](#definition-der-dialogfelder)
    - [Funktion Pedant](#funktion-pedant-1)
    - [Funktion FetchData](#funktion-fetchdata)
    - [Funktion documentClassifier](#funktion-documentclassifier)
    - [Funktion importVendorCSV / importRecipientCSV / importCostCenterCSV](#funktion-importvendorcsv--importrecipientcsv--importcostcentercsv)


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
    - _Datei:_ DataMapper.php
    - _Datei:_ DocumentClassifier.php
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
In unserem Fall lassen sich in der dialog.xml 5 Funktionen wiederfinden:
- pedant
- fetchData
- DocumentClassifier
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

- Zentraler Parameter-Speicher: Rückgabe eines Konfigurations-Arrays (return [...]), welches von der SystemActivity.php und den Traits eingebunden wird.

- Umgebungsspezifische Anpassungen: Ermöglicht es, Einstellungen (wie z.B. Speicherpfade oder Debug-Optionen) schnell zwischen Test- und Livesystem zu unterscheiden, indem nur diese eine Datei ausgetauscht wird.


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

- ApiTrait.php: Die unterste Kommunikationsebene. Hier erfolgt die technische Abwicklung der HTTP-Requests (cURL). Der Trait verwaltet Endpunkt-URLs, API-Header, die Authentifizierung via API-Key und die Basis-Fehlerbehandlung bei Übertragungsfehlern.

- DataMapperTrait.php: Fungiert als technisches "Übersetzungsbüro". Dieser Trait zerlegt die komplexen JSON-Antworten der API und verteilt die Werte (Positionen, Steuerbeträge, Audit-Trail) strukturiert in die JobRouter-Variablen und Untertabellen.

- LoggerTrait.php: Das zentrale Diagnose-Werkzeug. Er schreibt detaillierte Protokolle in vier Stufen (DEBUG, INFO, WARNING, ERROR). Dies ist die primäre Anlaufstelle für das Debugging im Live-Betrieb.

- HelperTrait.php: Enthält allgemeine Hilfsfunktionen. Ein Kernaspekt ist die automatische Erkennung des Datenbanktyps (MySQL oder MSSQL), um SQL-Abfragen innerhalb der Systemaktivität dynamisch an die jeweilige Umgebung anzupassen.

### Abhängigkeitsmatrix
Die folgende Tabelle zeigt, welche funktionalen Bausteine auf welche Infrastruktur-Traits zurückgreifen:

| Funktion (Trait)          | Benötigt ApiTrait? | Benötigt DataMapper? | Benötigt LoggerTrait? | Benötigt HelperTrait? |
| ------------------------- | ------------------ | -------------------- | --------------------- | --------------------- |
| InvoiceTrait (Rechnungen) | Ja                 | Ja                   | Ja                    | Ja                    |
| FetchTrait (Batch-Abruf)  | Ja                 | Ja                   | Ja                    | Nein                  |
| ImportTrait (Stammdaten)  | Ja                 | Ja                   | Ja                    | Nein                  |
| DocumentClassifierTrait   | Ja                 | Nein                 | Ja                    | Teilweise             |


## Definition der Dialogfelder

### Funktion Pedant

**Inputfelder:**

    > INPUTFILE - Nimmt das zu verarbeitende Dokument aus dem JobRouter-Prozess entgegen, das an die Pedant-API übermittelt werden soll.

    > API_KEY - Der Authentifizierungsschlüssel für die Pedant-API, welcher die Prozessinstanz dem entsprechenden Benutzerkonto zuordnet.

    > DEMO - Ein Checkbox-Feld, das bestimmt, ob die Anfrage an die Demo-Umgebung (api.demo.pedant.ai) oder an das Produktivsystem gesendet wird.

    > INTNUMBER - Eine interne Referenznummer, die an Pedant mitgegeben wird, um die Zuordnung des Mandanten in der Pedant-Plattform zu erleichtern.

    > MAXRETRIES - Legt fest, wie oft die Systemaktivität versuchen soll, den Status des Dokuments abzufragen, bevor der Schritt mit einem Fehler abbricht.

    > FLAG - Steuert den Verarbeitungsmodus der KI. Mögliche Werte sind:

        - normal: Standardverarbeitung.

        - check_extraction: Nur fehlerfreie Dateien werden abgeholt; erfordert ggf. eine manuelle Verifizierung durch den Nutzer.

        - skip_review: Die KI-Ergebnisse werden ohne menschliche Prüfung direkt zurückgegeben.

        - force_skip: Erzwingt das Überspringen bestimmter Validierungsschritte.

    > FLAGXML - Ein technisches Feld zur Übergabe zusätzlicher Steuerungsoptionen im XML-Format für die API-Kommunikation.  _//Bitte Beispielhaften Input nachtragen wie bei Flag//_

    > NEWVERSION - Eine Checkbox, die aktiviert wird, wenn die Wiedervorlagen-Logik automatisch auf 2 Jahre statt dem einem individuellen Wert gesetzt werden soll.

    > ZUGFERD - Aktiviert die gezielte Auswertung von ZUGFeRD-Metadaten, falls das Quelldokument strukturierte XML-Daten enthält.

    > INTERVALOLD - Die Wartezeit in Minuten für die Wiedervorlage, falls das Dokument noch nicht fertig verarbeitet wurde (nur aktiv, wenn NEWVERSION nicht gesetzt ist).

    > MAXFILESIZE - Bestimmt die maximal zulässige Dateigröße des INPUTFILE in Megabyte, um Timeouts beim Upload zu verhindern.

(◑‿◐) (◑‿◐) (◑‿◐) (◑‿◐)

**Outputfelder:**

    > FILEID - Speichert eine von Pedant zurückgegebene, eindeutige Dokumenten-ID, die für alle nachfolgenden Statusabfragen zwingend benötigt wird.

    > INVOICEID - Die von der Pedant-Plattform vergebene interne Rechnungs-ID nach der erfolgreichen Anlage des Datensatzes.

    > TEMPJSON - Speichert die vollständige, rohe JSON-Antwort der API zwischen, um sie für das Mapping oder Debugging verfügbar zu halten.

    > COUNTERSUMMARY - Enthält eine Zusammenfassung über die Anzahl der durchgeführten Verarbeitungsversuche oder Statusprüfungen.

    > RECIPIENTDETAILS - Ein individuell erstelltes Listenfeld (UDL), das die extrahierten Empfängerdaten (Name, Adresse, Bankverbindung des Rechnungsempfängers, etc.) aufnimmt.

    > VENDORDETAILS - Ein individuell erstelltes Listenfeld (UDL), das die extrahierten Kreditorendaten (Name, USt-ID, Anschrift des Absenders) speichert.

    > INVOICEDETAILS - Ein individuell erstelltes Listenfeld (UDL) für die Kopfdaten der Rechnung, wie Rechnungsnummer, Datum, Währung, Netto-, Steuer- und Bruttobeträge.

    > POSITIONDETAILS - Ein individuell erstelltes Listenfeld (UDL), welches die einzelnen Rechnungspositionen (Menge, Artikelbeschreibung, Einzelpreis, Gesamtpreis) in einer Untertabelle abbildet.

    > AUDITTRAILDETAILS - Ein individuell erstelltes Listenfeld (UDL), das die Historie der Bearbeitung auf der Pedant-Plattform (wer hat wann was geändert) protokolliert.

    > REJECTIONDETAILS - Ein individuell erstelltes Listenfeld (UDL), das im Falle einer Ablehnung den Grund (rejectReason), den Fehlercode und den Typ der Ablehnung speichert.

    > ATTACHMENTS - Ein individuell erstelltes Listenfeld (UDL) //::Ich muss Infos einholen::\\

    > WORKFLOWDETAILS - Gibt Informationen über den Workflow-Status zurück, beispielsweise ob das Dokument "direkt" (ohne manuellen Review) verarbeitet werden konnte.

( ؕؔʘ̥̥̥̥ ه ؔؕʘ̥̥̥̥ )? ( ؕؔʘ̥̥̥̥ ه ؔؕʘ̥̥̥̥ )? ( ؕؔʘ̥̥̥̥ ه ؔؕʘ̥̥̥̥ )? ( ؕؔʘ̥̥̥̥ ه ؔؕʘ̥̥̥̥ )?

### Funktion FetchData

**Inputfelder**

    > API_KEY - Der Authentifizierungsschlüssel für die Pedant-API, welcher die Prozessinstanz dem entsprechenden Benutzerkonto zuordnet.

    > INVOICE_STATUS - Bestimmt, welcher Bearbeitungsstatus bei Pedant abgefragt werden soll (z. B. reviewed, exported oder rejected), um nur relevante Dokumente für den Batch-Abruf zu berücksichtigen.

    > DEMO - Ein Checkbox-Feld, das bestimmt, ob die Anfrage an die Demo-Umgebung (api.demo.pedant.ai) oder an das Produktivsystem gesendet wird.

    > FILEID - Der Name der Spalte in der JobRouter-Prozesstabelle (TABLEHEAD), in welcher die Pedant-Datei-ID gespeichert ist. Dies wird benötigt, um die API-Antwort dem richtigen Datensatz zuzuordnen.

    > TABLEHEAD - Der Name der JobRouter-Datenbanktabelle (Prozesstabelle), in der die zu aktualisierenden Instanzen liegen.

    > STEPID - Die technische ID des Workflow-Schritts, auf dem die Dokumente aktuell in der Wiedervorlage warten und der durch den Batch-Abruf "geweckt" werden soll.

    > INTERVAL - Zeitspanne in Minuten, nach der die Systemaktivität fetchData erneut ausgeführt wird (Steuerung der Polling-Frequenz).

    > WORKTIME - Definiert das Zeitfenster (z. B. 08,18), in dem die Funktion aktiv nach Updates suchen soll. Außerhalb dieser Zeiten wird die Wiedervorlage automatisch auf den nächsten Arbeitstag verschoben.

    WEEKEND - Eine Checkbox, die bestimmt, ob die Verarbeitung auch an Wochenenden (Samstag/Sonntag) stattfinden soll oder ob die Resubmission direkt auf den Montag gesetzt wird.

_Für diese Funktion sind keine Outputparameter definiert, da sie direkt schreibend auf die JobRouter-Datenbank (JRINCIDENTS) zugreift, um die Wiedervorlage-Daten wartender Schritte zu aktualisieren._

### Funktion documentClassifier

**Inputfelder**

    > API_KEY - Der Authentifizierungsschlüssel für die Pedant-API, welcher die Prozessinstanz dem entsprechenden Benutzerkonto zuordnet.

    > DEMO - Ein Checkbox-Feld, das bestimmt, ob die Anfrage an die Demo-Umgebung (api.demo.pedant.ai) oder an das Produktivsystem gesendet wird.

    > INPUTFILE - Nimmt das zu verarbeitende Dokument aus dem JobRouter-Prozess entgegen, das an die Pedant-API übermittelt werden soll.

    > DC_ACTION - //::Ich muss Infos einholen::\\

    > DC_MAXRETRIES - Bestimmt die maximale Anzahl an Abfrageversuchen, die die Systemaktivität unternimmt, um ein Klassifizierungsergebnis abzurufen, bevor ein Error ausgegeben wird.

    > DC_INTERVAL - Die Wartezeit in Minuten für die Wiedervorlage des JobRouter-Schritts, falls die KI-Analyse zum Zeitpunkt der ersten Abfrage noch nicht abgeschlossen ist.

    > MAXFILESIZE - Legt die maximal erlaubte Dateigröße (in MB) für das hochzuladende Dokument fest, um die Stabilität der Übertragung zu gewährleisten.

ಠ_ರೃ ಠ_ರೃ ಠ_ರೃ ಠ_ರೃ

**Outputfelder**

    > DC_DOCUMENTID - Speichert die von der Classifier-API vergebene, eindeutige Identifikationsnummer des Dokuments für den weiteren Prozessverlauf.

    > DC_TEMPJSON - Nimmt die vollständige Rohantwort der KI im JSON-Format auf, um alle Details der Klassifizierung für nachgelagerte Logik oder Debugging-Zwecke vorzuhalten.

    > CLASSIFICATIONDETAILS - Ein individuell erstelltes Listenfeld (UDL), das die wesentlichen Ergebnisse der Analyse strukturiert zurückgibt. Dazu gehören in der Regel der erkannte Dokumenttyp (z. B. "Rechnung"), Firmennamen von Absender/Empfänger sowie das erkannte Belegdatum.


### Funktion importVendorCSV / importRecipientCSV / importCostCenterCSV

**Inputfelder**

    > API_KEY - Der Authentifizierungsschlüssel für die Pedant-API, welcher die Prozessinstanz dem entsprechenden Benutzerkonto zuordnet.

    > DEMO - Ein Checkbox-Feld, das bestimmt, ob die Anfrage an die Demo-Umgebung (api.demo.pedant.ai) oder an das Produktivsystem gesendet wird.

    > VENDORTABLE / RECIPIENTTABLE / COSTCENTERTABLE - Der Name der JobRouter-Datenbanktabelle (oder View), welche die Stammdaten für den Export enthält (z. B. eine zentrale SQL-Tabelle für Kreditoren).

    > IMPORTVENDOR / IMPORTRECIPIENT / IMPORTCOSTCENTER - Ein Listenfeld (UDL), das für das Feldmapping genutzt wird. Hier wird definiert, welche Spalte der JobRouter-Datenbank (Datenbank-Feld) welcher Information in der Pedant-API (API-Feld) entspricht. Dies ermöglicht eine flexible Zuordnung, ohne den SQL-Code anpassen zu müssen.

_Für diese Funktionen sind keine Outputparameter definiert, da es sich um reine Export-Funktionen handelt_
