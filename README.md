# Messenger Audit

Lokale Entwicklungsumgebung für das Shopware-Plugin `MessengerHistoryDashboard`.

## Repo-Struktur

- `plugin/MessengerHistoryDashboard/` enthaelt den auslieferbaren Plugin-Code
- `dist/` enthaelt das Release-ZIP
- `shopware/` ist nur die optionale lokale Test- und Installationsumgebung
- `tests/` enthaelt E2E- und plugin-nahe Tests
- `scripts/` enthaelt lokale Dev-, Test- und Release-Skripte

## Start

```bash
chmod +x scripts/*.sh
cp -n shopware/.env.local.dist shopware/.env.local
./scripts/bootstrap-shopware.sh
```

Danach:

- Storefront: entsprechend `APP_URL` in `shopware/.env.local`
- Admin: `APP_URL/admin`
- Login: `admin / shopware`

## Demo-Daten

```bash
./scripts/seed-mh-demo.sh
./scripts/prepare-screenshot-data.sh
```

## Lokaler E2E

```bash
composer e2e-local
```

Optional:

- `BASE_URL=http://127.0.0.1:8000`
- `ADMIN_USER=admin`
- `ADMIN_PASSWORD=shopware`

Standardverhalten:

- `composer e2e-local` liest `APP_URL` direkt aus `shopware/.env.local`
- nur wenn dort nichts gesetzt ist, faellt der Test auf `http://127.0.0.1:18000` zurueck

## Quality Gates

```bash
composer quality:all
```

oder direkt:

```bash
bash ./scripts/run-quality.sh
```

## Release-ZIP

```bash
composer release:zip
```

oder direkt:

```bash
bash ./scripts/build-release.sh
```

Das Skript baut `dist/MessengerHistoryDashboard-<version>.zip` aus dem Plugin-Ordner.

Optional mit lokalem Admin-Build vor dem ZIP:

```bash
BUILD_ADMIN=1 bash ./scripts/build-release.sh
```

## Lokaler Admin-Build

```bash
composer admin:build-local
```

Das Skript:

- startet den lokalen Shopware-Stack falls noetig
- installiert bei Bedarf `shopware/dev-tools` im lokalen `shopware/`-Workspace
- synchronisiert und installiert das Plugin
- baut die Administration mit Shopware-Tooling

Wenn die lokal installierten Dev-Tools spaeter wieder entfernt werden sollen:

```bash
composer admin:cleanup-dev-tools
```

## Screenshot-Daten

```bash
./scripts/prepare-screenshot-data.sh
```

Das Skript erzeugt Shopware-Demo-Daten fuer Bestellungen und seedet danach Messenger-Audit-Daten fuer Screenshots mit sichtbarer Aktivitaet.

## Plugin-Funktionen

- Messenger-Übersicht mit Filtern und Pagination
- Failed-View
- Detailansicht mit Verlauf, Fehlern und Aktionen
- Retry, Ausblenden, Erledigen
- Kennzahlen und Bereinigung alter Audit-Einträge
