# Lizenz-Widget für JobRouter

## Übersicht

Das Lizenz-Widget zeigt das Ablaufdatum Ihrer JobRouter-Lizenz an und bietet eine Verwaltung von Benachrichtigungs-E-Mails. Die Konfiguration ist rollenbasiert: Nur Nutzer mit der Rolle `LizenzAdmin` können E-Mail-Benachrichtigungen bearbeiten.

## Funktionen

- Anzeige des Lizenzablaufdatums
- Rollenbasierte E-Mail-Verwaltung (nur `LizenzAdmin` kann bearbeiten)
- Hinzufügen, Bearbeiten, Aktivieren/Deaktivieren und Löschen von Benachrichtigungs-E-Mails
- Rollenbasierte E-Mails werden automatisch synchronisiert und können nicht bearbeitet oder gelöscht werden

## Anleitung

### 1. Widget zum Dashboard hinzufügen

- Kopieren Sie den Widget-Ordner in Ihr JobRouter-Dashboard-Verzeichnis unter `jobrouter/dashboard/MyWidgets/`.
- Stellen Sie sicher, dass folgende Dateien vorhanden sind:
  - `Lizenz.php`
  - `template.hbs`
  - `query.php`

### 2. Datenbank

- Die Tabelle `simplifyLicenseWidget` für die E-Mail-Verwaltung wird automatisch erstellt und gepflegt.
- Es ist keine manuelle Datenbank-Konfiguration nötig.

### 3. Rollenverwaltung

- Nur Nutzer mit der Rolle `LizenzAdmin` können E-Mail-Benachrichtigungen konfigurieren.
- Rollenbasierte E-Mails werden automatisch aus den Nutzern mit der Rolle `LizenzAdmin` synchronisiert.

### 4. E-Mail-Verwaltung

- Neue E-Mail hinzufügen: Adresse eingeben und auf **Hinzufügen** klicken.
- E-Mail bearbeiten: Stift-Symbol anklicken, ändern und speichern.
- Aktivieren/Deaktivieren: Umschalter verwenden.
- Löschen: Papierkorb-Symbol anklicken (nicht möglich bei rollenbasierten E-Mails).

- Wenn die Lizenz innerhalb der nächsten 7 Tage abläuft, erhalten alle konfigurierten E-Mail-Adressen automatisch eine Benachrichtigung.
