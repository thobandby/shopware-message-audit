# Status Audit

Status Audit erweitert Shopware 6 um eine fokussierte Administrationsoberflaeche fuer synchrone Statuswechsel und asynchrone Queue-Aktivitaet. Das Plugin macht Bestell-, Zahlungs- und Lieferstatus sichtbar, ergaenzt Queue- und Fehlerdetails und bietet direkte Operator-Aktionen fuer den Alltag.

Das Plugin ersetzt dabei nicht den normalen Shopware-Messenger, keine Standard-Queue und keine Shopware-Werkzeuge fuer Message Queue oder Failed Messages. Es legt sich additiv daneben und liefert zusaetzliche Audit- und Detailinformationen.

## Nutzen

- schneller Ueberblick ueber synchrone Statuswechsel und asynchrone Queue-Eintraege
- Filter nach Bereich, Status, Zeitraum und Suchbegriff
- Detailansicht mit Verlauf, Fehlern und ausgefuehrten Operator-Aktionen
- Aktionen direkt im Admin: `Erneut senden`, `Ausblenden`, `Erledigen`
- Kennzahlen und Bereinigung alter Audit-Eintraege

## Voraussetzungen

- Shopware `6.6+`
- PHP `8.2+`
- konfigurierter Messenger-Transport `async`
- laufender Worker fuer Retry-Verarbeitung und Lifecycle-Tracking

Beispiel:

```bash
bin/console messenger:consume async low_priority --time-limit=5
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

- Menuepfad: `Einstellungen > Erweiterungen > Status Audit`
- benoetigte Berechtigung: `Plugins und Erweiterungen verwalten`
- falls der Eintrag nicht sofort sichtbar ist: Administration neu laden

## Worker-Betrieb

Status Audit verwendet den normalen Shopware-Messenger-Worker. Fuer den Betrieb sollte daher ein regulaerer Worker fuer die relevanten Transports laufen, zum Beispiel:

```bash
bin/console messenger:consume async low_priority
```

Das normale Shopware-Monitoring fuer Queue, Failed Messages und Transport-Statistiken bleibt dabei unveraendert die fachliche Basis; Status Audit ergaenzt zusaetzlich synchrone Statuswechsel, tiefere Verlaeufe, Fehlerdetails und Operator-Aktionen.

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
- kompatibel mit Shopware `6.6+`
- getestet mit PHP `8.2+`
- benoetigt einen aktiven Messenger-Worker fuer Retry-Verarbeitung und Lifecycle-Tracking

## Hinweise fuer den Betrieb

- API und Admin-Modul sind fuer authentifizierte Shopware-Admin-Nutzer gedacht
- die Bereinigung loescht Audit-Daten aelter als die gewaehlte Aufbewahrungsfrist
- Retry protokolliert die Operator-Aktion und erzeugt eine neue Replay-Nachricht

## Release-Informationen

- Aenderungen pro Version: [CHANGELOG.md](./CHANGELOG.md)
- Lizenz: [LICENSE](./LICENSE)
- Store-Texte und Materialplanung: [STORE-LISTING.md](./STORE-LISTING.md)
