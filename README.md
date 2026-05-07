# Messenger Audit

Lokale Entwicklungsumgebung für das Shopware-Plugin `MessengerHistoryDashboard`.

## Start

```bash
chmod +x scripts/*.sh
cp -n shopware/.env.local.dist shopware/.env.local
./scripts/bootstrap-shopware.sh
```

Danach:

- Storefront: `http://127.0.0.1:18000`
- Admin: `http://127.0.0.1:18000/admin`
- Login: `admin / shopware`

## Demo-Daten

```bash
./scripts/seed-mh-demo.sh
```

## Lokaler E2E

```bash
composer e2e-local
```

Optional:

- `BASE_URL=http://127.0.0.1:18000`
- `ADMIN_USER=admin`
- `ADMIN_PASSWORD=shopware`

## Plugin-Funktionen

- Messenger-Übersicht mit Filtern und Pagination
- Failed-View
- Detailansicht mit Verlauf, Fehlern und Aktionen
- Retry, Ausblenden, Erledigen
- Kennzahlen und Bereinigung alter Audit-Einträge
