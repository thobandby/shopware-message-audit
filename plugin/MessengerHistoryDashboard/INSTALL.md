# Installation

## Voraussetzungen

- Shopware 6.6
- PHP 8.2+
- Messenger-Transport `async`

## Plugin installieren

```bash
bin/console plugin:refresh
bin/console plugin:install --activate MessengerHistoryDashboard
bin/console cache:clear
```

## Administration

- Aufruf im Admin unter `Einstellungen > Erweiterungen > Messenger Audit`
- Wenn der Menüeintrag fehlt: Browser-Cache leeren und Admin neu laden

## Demo prüfen

```bash
bin/console mh:demo:seed
bin/console messenger:consume async --time-limit=5 --no-debug
```

Danach prüfen:

- `GET /api/_action/mh/messages`
- `GET /api/_action/mh/metrics`
- Admin-Modul `Messenger Audit`

## Betrieb

- Retry benötigt einen laufenden Worker
- Alte Audit-Einträge können im Modul bereinigt werden
