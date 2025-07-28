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
