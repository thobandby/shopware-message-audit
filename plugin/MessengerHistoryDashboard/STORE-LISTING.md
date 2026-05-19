# Store Listing Material

## Kurzbeschreibung

Status Audit macht technische Verarbeitung und fachliche Statuswechsel in Shopware 6 sichtbar. Das Plugin zeigt Queue-Eintraege, synchrone Bestell-, Zahlungs- und Lieferstatuswechsel im Admin, liefert Kennzahlen fuer Stoerungen und ermoeglicht direkte Operator-Aktionen wie Retry, Ausblenden und Erledigen.

Status Audit ersetzt dabei keine Shopware-Standardfunktionen fuer Queue, Failed Messages oder Messenger-Statistiken. Es ergaenzt den vorhandenen Messenger-Betrieb um mehr Transparenz und feinere Detailinformationen fuer Queue und Statusverlauf.

## Nutzenversprechen

- Fehler in Queue- und Statusprozessen schneller erkennen
- kritische Eintraege gezielt filtern und untersuchen
- Operator-Aktionen ohne SQL oder Shell direkt im Admin ausfuehren
- Audit-Daten mit definierter Aufbewahrungsfrist aufraeumen

## Zielgruppe

- Shopware-Agenturen
- Betreiber mit asynchronen Prozessen
- Teams mit ERP-, Zahlungs- oder Bestellintegrationen

## Funktionsumfang

- Dashboard mit Kennzahlen fuer Gesamtmenge, Fehler, Queue-Eintraege und Statuswechsel
- Listenansicht mit Filtern nach Bereich, Status, Zeitraum und Suche
- Separate Failed-Ansicht fuer priorisierte Fehlerbearbeitung
- Detailansicht mit Verlauf, Fehlerhistorie und Operator-Aktionen
- Cleanup alter Audit-Eintraege

## Kompatibilitaet

- Shopware: `6.6+`
- PHP: `8.2+`
- Einsatzbereich: Self-hosted Shopware
- benoetigt Messenger-Transport `async`
- benoetigt einen laufenden Messenger-Worker

## Support

- Anbieter: Thorsten Baumann
- E-Mail: `info@baumann-it-dienstleistungen.de`
- Website: `https://baumann-it-dienstleistungen.de`

## Lizenzmodell

- aktueller Stand des Pakets: MIT-lizenziert
- fuer ein kostenpflichtiges Store-Listing sollte das Lizenzmodell vorab bewusst festgelegt werden

## Vorschlag fuer die Store-Beschreibung

### Einleitung

Status Audit schafft Transparenz fuer asynchrone Prozesse und synchrone Statuswechsel in Shopware 6. Statt Probleme erst ueber Logs, Datenbankabfragen oder Supportfaelle zu entdecken, erhalten Admin-Nutzer eine direkte Sicht auf Eintraege, Fehler und moegliche Folgeaktionen.

### Typische Anwendungsfaelle

- fehlgeschlagene Zahlungs- oder Bestellverarbeitung schnell identifizieren
- problematische Queue-Eintraege erneut anstossen
- bereits gepruefte Fehlerfaelle sauber markieren
- alte Audit-Daten kontrolliert bereinigen

### Betriebshinweise

- Retry setzt einen aktiven Worker voraus
- das normale Shopware-Monitoring fuer Queue und Failed Messages bleibt weiterhin nutzbar
- Das Plugin ist fuer technische Admin-Nutzer gedacht
- Die Bereinigung von Alt-Daten sollte an eure Aufbewahrungsregeln angepasst werden

## Screenshot-Plan

Siehe [docs/store/SCREENSHOTS.md](./docs/store/SCREENSHOTS.md).
