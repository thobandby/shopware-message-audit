# Screenshot-Plan

Dieser Ordner bereitet die Store-Screenshots inhaltlich vor. Reale Screenshots muessen noch aus einer laufenden Shopware-Instanz aufgenommen werden.

## Empfohlene Dateien

- `01-dashboard-overview.png`
- `02-filter-and-failed-list.png`
- `03-message-detail.png`
- `04-cleanup-and-metrics.png`

## Aufnahmereihenfolge

### 01 Dashboard Overview

- Seite: `Einstellungen > Erweiterungen > Status Audit`
- Fokus: KPI-Karten und Tabellenueberblick
- Caption-Vorschlag: `Uebersicht ueber Queue-Eintraege, Statuswechsel und aktuelle Kennzahlen`

### 02 Filter And Failed List

- Seite: `Status Audit > Kritische Eintraege`
- Fokus: Filter, Pagination, Kontextaktionen
- Caption-Vorschlag: `Kritische Eintraege gezielt filtern und priorisieren`

### 03 Message Detail

- Seite: Detailansicht eines kritischen Eintrags
- Fokus: Status, Fehlerhistorie, Transitionen, Operator-Aktionen
- Caption-Vorschlag: `Alle relevanten Details und Aktionen an einer Stelle`

### 04 Cleanup And Metrics

- Seite: Dashboard mit sichtbarer Cleanup-Sektion
- Fokus: Aufbewahrungsfrist und Nutzen der Bereinigung
- Caption-Vorschlag: `Audit-Daten kontrolliert bereinigen und die Datenbasis schlank halten`

## Aufnahmehinweise

- deutschsprachige Admin-Oberflaeche verwenden
- Demo-Daten vorab mit `./scripts/prepare-screenshot-data.sh` erzeugen
- Browser-Zoom auf `100 %`
- Screenshots ohne Browser-Chrome, wenn der Store-Crop das erlaubt
- vor Aufnahme persoenliche oder systeminterne Daten entfernen
