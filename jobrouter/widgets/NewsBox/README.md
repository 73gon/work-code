# NewsBox-Widget für JobRouter

## Übersicht

Das NewsBox-Widget zeigt Mitteilungen im JobRouter-Dashboard an und ermöglicht die Verwaltung von News-Einträgen. Die Konfiguration ist rollenbasiert: Nur Nutzer mit der Rolle `NewsBoxAdmin` oder der Benutzer `admin` können Nachrichten bearbeiten.

## Funktionen

- Anzeige aller Mitteilungen
- Hinzufügen, Bearbeiten und Löschen von News-Einträgen
- Setzen eines Löschdatums für automatische Entfernung

## Anleitung

### 1. Widget zum Dashboard hinzufügen

- Kopieren Sie den Widget-Ordner in Ihr JobRouter-Dashboard-Verzeichnis unter `jobrouter/dashboard/MyWidgets/`.
- Stellen Sie sicher, dass folgende Dateien vorhanden sind:
  - `NewsBox.php`
  - `template.hbs`
  - `query.php`

### 2. Datenbank

- Die Tabelle `newsBoxWidget` für die Verwaltung der Mitteilungen wird automatisch erstellt und gepflegt.
- Es ist keine manuelle Datenbank-Konfiguration nötig.

### 3. Rollenverwaltung

- Nur Nutzer mit der Rolle `NewsBoxAdmin` oder der Benutzer `admin` können Nachrichten hinzufügen, bearbeiten oder löschen.
- Nutzer mit der Rolle `NewsBoxUser` können das Widget sehen, aber keine Nachrichten bearbeiten.

### 4. News-Verwaltung

- Neue Nachricht hinzufügen: Button **Nachricht hinzufügen** anklicken, Felder ausfüllen und speichern.
- Nachricht bearbeiten: Stift-Symbol anklicken, ändern und speichern.
- Nachricht löschen: Papierkorb-Symbol anklicken und Löschvorgang bestätigen.
- Löschdatum setzen: Optionales Datum, ab dem die Nachricht automatisch entfernt wird.

### 5. Farbkonfiguration

Die Farben des Widgets können über CSS-Variablen in der `template.hbs` Datei angepasst werden. Die konfigurierbaren Farben befinden sich im `:root` Bereich und können mit Hexadezimal-Farbcodes geändert werden:

```css
:root {
  --newsbox-bg: #3b3e4d; /* Haupthintergrundfarbe */
  --newsbox-accent: #ffcc0d; /* Akzentfarbe (Buttons, Titel) */
  --newsbox-accent-hover: #ffd700; /* Akzentfarbe beim Hover */
  --newsbox-item-bg: #4a4d5c; /* Hintergrund der News-Items */
  --newsbox-item-bg-hover: #525566; /* News-Item Hover-Farbe */
  --newsbox-border: #ffcc0d; /* Rahmenfarbe */
  --newsbox-date: #b8bcc8; /* Datumsfarbe */
  --newsbox-edit-info: #999; /* "Zuletzt bearbeitet" Text */
  --newsbox-btn-danger: #ff6b6b; /* Löschen-Button Farbe */
  --newsbox-btn-danger-hover: #ff5252; /* Löschen-Button Hover */
  --newsbox-message: #e5e5e5; /* Nachrichtentext */
  --newsbox-toast-success-bg: #2e7d32; /* Erfolgs-Toast Hintergrund */
  --newsbox-toast-error-bg: #c62828; /* Fehler-Toast Hintergrund */
}
```

Beispiel für eine blaue Farbvariation:

```css
:root {
  --newsbox-bg: #1e3a8a;
  --newsbox-accent: #3b82f6;
  --newsbox-accent-hover: #60a5fa;
  --newsbox-item-bg: #1e40af;
  --newsbox-border: #3b82f6;
}
```
