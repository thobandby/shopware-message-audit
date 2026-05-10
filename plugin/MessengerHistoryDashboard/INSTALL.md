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

## Administration

- Aufruf im Admin unter `Einstellungen > Erweiterungen > Messenger Audit`
- Wenn der Menueeintrag fehlt: Browser-Cache leeren, Admin neu laden und Rechte pruefen

## Demo pruefen

```bash
bin/console mh:demo:seed
bin/console messenger:consume async --time-limit=5 --no-debug
```

Danach pruefen:

- `GET /api/_action/mh/messages`
- `GET /api/_action/mh/metrics`
- Admin-Modul `Messenger Audit`

## Betrieb

- Retry benoetigt einen laufenden Worker
- Alte Audit-Eintraege koennen im Modul bereinigt werden
- Die Bereinigung nutzt den Endpoint `POST /api/_action/mh/messages/retention/cleanup`

## Fehlerbehebung

- Keine Daten sichtbar:
  Messenger-Worker und Transport-Konfiguration pruefen.
- Retry ohne Wirkung:
  Sicherstellen, dass `messenger:consume async` laeuft.
- Menuepunkt fehlt:
  Browser-Cache loeschen, Administration neu laden, Benutzerrechte pruefen.
