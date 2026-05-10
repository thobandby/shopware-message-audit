# Messenger Audit

Messenger Audit erweitert Shopware 6 um eine fokussierte Administrationsoberflaeche fuer Symfony Messenger. Das Plugin macht verarbeitete und fehlgeschlagene Nachrichten sichtbar, reduziert Suchaufwand bei Stoerungen und bietet direkte Operator-Aktionen fuer den Alltag.

## Nutzen

- schneller Ueberblick ueber verarbeitete und fehlgeschlagene Nachrichten
- Filter nach Bereich, Status, Zeitraum und Suchbegriff
- Detailansicht mit Verlauf, Fehlern und ausgefuehrten Operator-Aktionen
- Aktionen direkt im Admin: `Erneut senden`, `Ausblenden`, `Erledigen`
- Kennzahlen und Bereinigung alter Audit-Eintraege

## Voraussetzungen

- Shopware `6.6`
- PHP `8.2+`
- konfigurierter Messenger-Transport `async`
- laufender Worker fuer Retry-Verarbeitung

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

Eine ausfuehrlichere Schritt-fuer-Schritt-Anleitung steht in [INSTALL.md](./INSTALL.md).

## Administration

- Menuepfad: `Einstellungen > Erweiterungen > Messenger Audit`
- benoetigte Berechtigung: `Plugins und Erweiterungen verwalten`
- falls der Eintrag nicht sofort sichtbar ist: Administration neu laden

## Demo-Daten

```bash
bin/console mh:demo:seed
bin/console messenger:consume async --time-limit=5 --no-debug
```

## API

- `GET /api/_action/mh/messages`
- `GET /api/_action/mh/messages?status=failed`
- `GET /api/_action/mh/messages?topicGroup=Bestellungen`
- `GET /api/_action/mh/messages/{id}`
- `POST /api/_action/mh/messages/{id}/retry`
- `POST /api/_action/mh/messages/{id}/quarantine`
- `POST /api/_action/mh/messages/{id}/dismiss`
- `POST /api/_action/mh/messages/retention/cleanup`
- `GET /api/_action/mh/metrics`

## Support

- Hersteller: Thorsten Baumann
- E-Mail: `info@baumann-it-dienstleistungen.de`
- Quelle: `https://baumann-it-dienstleistungen.de`

## Kompatibilitaet

- freigegeben fuer selbst gehostete Shopware-Installationen
- getestet mit Shopware `6.6`
- getestet mit PHP `8.2+`
- benoetigt einen aktiven Messenger-Worker fuer Retry-Verarbeitung

## Hinweise fuer den Betrieb

- API und Admin-Modul sind fuer authentifizierte Shopware-Admin-Nutzer gedacht
- die Bereinigung loescht Audit-Daten aelter als die gewaehlte Aufbewahrungsfrist
- Retry protokolliert die Operator-Aktion und erzeugt eine neue Replay-Nachricht

## Release-Informationen

- Aenderungen pro Version: [CHANGELOG.md](./CHANGELOG.md)
- Lizenz: [LICENSE](./LICENSE)
- Store-Texte und Materialplanung: [STORE-LISTING.md](./STORE-LISTING.md)
