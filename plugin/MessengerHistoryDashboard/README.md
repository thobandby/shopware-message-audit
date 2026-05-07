# Messenger Audit

Shopware 6 Plugin zur Sichtbarkeit und Bearbeitung von Messenger-Nachrichten.

## Funktionen

- Übersicht über verarbeitete und fehlgeschlagene Messenger-Nachrichten
- Filter nach Bereich, Status, Zeitraum und Suche
- Detailansicht mit Verlauf, Fehlern und Operator-Aktionen
- Aktionen: `Erneut senden`, `Ausblenden`, `Erledigen`
- Kennzahlen und Bereinigung älterer Audit-Einträge

## Voraussetzungen

- Shopware 6.6
- PHP 8.2+
- konfigurierter Messenger-Transport `async`
- laufender Worker für Retry-Verarbeitung

Beispiel:

```bash
bin/console messenger:consume async --time-limit=5 --no-debug
```

## Installation

Plugin nach `custom/plugins/MessengerHistoryDashboard` legen und installieren:

```bash
bin/console plugin:refresh
bin/console plugin:install --activate MessengerHistoryDashboard
bin/console cache:clear
```

## Administration

- Menüpfad: `Einstellungen > Erweiterungen > Messenger Audit`
- Falls der Eintrag nicht sofort sichtbar ist: Administration neu laden

## Demo-Daten

```bash
bin/console mh:demo:seed
bin/console messenger:consume async --time-limit=5 --no-debug
```

## API

- `GET /api/_action/mh/messages`
- `GET /api/_action/mh/messages?status=failed`
- `GET /api/_action/mh/messages?topicGroup=Bestellungen`
- `GET /api/_action/mh/messages?cleanupDays=30`
- `GET /api/_action/mh/messages/{id}`
- `POST /api/_action/mh/messages/{id}/retry`
- `POST /api/_action/mh/messages/{id}/quarantine`
- `POST /api/_action/mh/messages/{id}/dismiss`
- `GET /api/_action/mh/metrics`

## Hinweise

- Retry setzt einen laufenden Worker voraus
- Bereinigung älterer Einträge läuft über den Listenpfad mit `cleanupDays`
- API und Admin-Modul sind für authentifizierte Shopware-Admin-Nutzer gedacht
