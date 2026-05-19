# Installation

## Voraussetzungen

- Shopware 6.6+
- PHP 8.2+
- Messenger-Transport `async`
- Admin-Benutzer mit Berechtigung `Plugins und Erweiterungen verwalten`

## Plugin installieren

```bash
bin/console plugin:refresh
bin/console plugin:install --activate MessengerHistoryDashboard
bin/console cache:clear
```

## Worker aktivieren

Das Plugin ersetzt den Standard-Shopware-Messenger nicht. Es erweitert ihn nur um Audit- und Detailinformationen.

Nach der Installation muss deshalb ein normaler Messenger-Worker laufen:

```bash
bin/console messenger:consume async low_priority
```

Fuer kurze Pruefungen lokal:

```bash
bin/console messenger:consume async low_priority --time-limit=5
```

Fuer den Produktivbetrieb sollte der Worker dauerhaft ueber euren Prozessmanager laufen, zum Beispiel `systemd`, `supervisord` oder die Hosting-Mechanik eurer Shopware-Umgebung.

## Administration

- Aufruf im Admin unter `Einstellungen > Erweiterungen > Status Audit`
- Wenn der Menueeintrag fehlt: Browser-Cache leeren, Admin neu laden und Rechte pruefen

## Betrieb

- Retry benoetigt einen laufenden Worker
- Das Standard-Shopware-Monitoring fuer Messenger, Queue und Failed Messages bleibt weiter gueltig
- Alte Audit-Eintraege koennen im Modul bereinigt werden
- Die Bereinigung nutzt den Endpoint `POST /api/_action/mh/messages/retention/cleanup`

## Fehlerbehebung

- Keine Daten sichtbar:
  Messenger-Worker und Transport-Konfiguration pruefen.
- Retry ohne Wirkung:
  Sicherstellen, dass ein normaler Shopware-Messenger-Worker laeuft.
- Menuepunkt fehlt:
  Browser-Cache loeschen, Administration neu laden, Benutzerrechte pruefen.
