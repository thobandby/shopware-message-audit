# Messenger Audit

Lokale Entwicklungsumgebung für das Shopware-Plugin `MessengerHistoryDashboard`.

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
