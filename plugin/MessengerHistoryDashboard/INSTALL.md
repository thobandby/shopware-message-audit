# Installation

## Voraussetzungen

- Shopware 6.6
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

Nach der Installation muss deshalb ein normaler Messenger-Worker laufen. Das Plugin bringt dafuer einen eigenen Startbefehl mit:

```bash
bin/console mh:worker:consume
```

Fuer kurze Pruefungen lokal:

```bash
bin/console mh:worker:consume --time-limit=5
```

Fuer den Produktivbetrieb sollte der Worker dauerhaft ueber euren Prozessmanager laufen, zum Beispiel `systemd`, `supervisord` oder die Hosting-Mechanik eurer Shopware-Umgebung.

## Administration

- Aufruf im Admin unter `Einstellungen > Erweiterungen > Messenger Audit`
- Wenn der Menueeintrag fehlt: Browser-Cache leeren, Admin neu laden und Rechte pruefen

## Demo pruefen

```bash
bin/console mh:demo:seed
bin/console mh:worker:consume --time-limit=5
```

Danach pruefen:

- `GET /api/_action/mh/messages`
- `GET /api/_action/mh/metrics`
- Admin-Modul `Messenger Audit`

## Betrieb

- Retry benoetigt einen laufenden Worker
- Das Standard-Shopware-Monitoring fuer Messenger, Queue und Failed Messages bleibt weiter gueltig
- Alte Audit-Eintraege koennen im Modul bereinigt werden
- Die Bereinigung nutzt den Endpoint `POST /api/_action/mh/messages/retention/cleanup`

## Fehlerbehebung

- Keine Daten sichtbar:
  Messenger-Worker und Transport-Konfiguration pruefen.
- Retry ohne Wirkung:
  Sicherstellen, dass `mh:worker:consume` oder ein normaler Shopware-Messenger-Worker laeuft.
- Menuepunkt fehlt:
  Browser-Cache loeschen, Administration neu laden, Benutzerrechte pruefen.
