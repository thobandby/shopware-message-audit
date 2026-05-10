# Store Listing Material

## Kurzbeschreibung

Messenger Audit macht Symfony Messenger in Shopware 6 im Alltag beherrschbar. Das Plugin zeigt verarbeitete und fehlgeschlagene Nachrichten im Admin, liefert Kennzahlen fuer Stoerungen und ermoeglicht direkte Operator-Aktionen wie Retry, Ausblenden und Erledigen.

## Nutzenversprechen

- Fehler in Messenger-Prozessen schneller erkennen
- kritische Nachrichten gezielt filtern und untersuchen
- Operator-Aktionen ohne SQL oder Shell direkt im Admin ausfuehren
- Audit-Daten mit definierter Aufbewahrungsfrist aufraeumen

## Zielgruppe

- Shopware-Agenturen
- Betreiber mit asynchronen Prozessen
- Teams mit ERP-, Zahlungs- oder Bestellintegrationen ueber Messenger

## Funktionsumfang

- Dashboard mit Kennzahlen fuer Gesamtmenge, Fehler, verarbeitete und empfangene Nachrichten
- Listenansicht mit Filtern nach Bereich, Status, Zeitraum und Suche
- Separate Failed-Ansicht fuer priorisierte Fehlerbearbeitung
- Detailansicht mit Verlauf, Fehlerhistorie und Operator-Aktionen
- Cleanup alter Audit-Eintraege

## Kompatibilitaet

- Shopware: `6.6`
- PHP: `8.2+`
- Einsatzbereich: Self-hosted Shopware
- benoetigt Messenger-Transport `async`

## Support

- Anbieter: Thorsten Baumann
- E-Mail: `info@baumann-it-dienstleistungen.de`
- Website: `https://baumann-it-dienstleistungen.de`

## Lizenzmodell

- aktueller Stand des Pakets: MIT-lizenziert
- fuer ein kostenpflichtiges Store-Listing sollte das Lizenzmodell vorab bewusst festgelegt werden

## Vorschlag fuer die Store-Beschreibung

### Einleitung

Messenger Audit schafft Transparenz fuer asynchrone Prozesse in Shopware 6. Statt Messenger-Probleme erst ueber Logs, Datenbankabfragen oder Supportfaelle zu entdecken, erhalten Admin-Nutzer eine direkte Sicht auf Nachrichten, Fehler und moegliche Folgeaktionen.

### Typische Anwendungsfaelle

- fehlgeschlagene Zahlungs- oder Bestellnachrichten schnell identifizieren
- problematische Nachrichten erneut anstossen
- bereits gepruefte Fehlerfaelle sauber markieren
- alte Audit-Daten kontrolliert bereinigen

### Betriebshinweise

- Retry setzt einen aktiven Worker voraus
- Das Plugin ist fuer technische Admin-Nutzer gedacht
- Die Bereinigung von Alt-Daten sollte an eure Aufbewahrungsregeln angepasst werden

## Screenshot-Plan

Siehe [docs/store/SCREENSHOTS.md](./docs/store/SCREENSHOTS.md).
