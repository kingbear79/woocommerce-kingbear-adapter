# WooCommerce KingBear Adapter

Plugin für WooCommerce, das Versandabwicklung und Sendungsverfolgung modular bereitstellt.

## Architektur
- **Plugin-Kern** lädt verfügbare Module nach dem Laden von WooCommerce, registriert einen Einstellungs-Tab und legt bei der Aktivierung den benötigten Cron-Job an.
- **Einstellungsseite**: Unter `WooCommerce → Einstellungen → KingBear Adapter` erscheint für jedes Modul ein eigener Abschnitt.
- **Automatische Releases**: Ein GitHub Actions Workflow erhöht bei jedem Merge auf den `main`-Branch automatisch die Version, erstellt ein Release und baut eine ZIP-Datei für WordPress Updates.

## Implementierte Module
### Sendungsverfolgung
Modul zur Synchronisation von Tracking-Informationen aus der DHL Paket API.

**Funktionen**
- Legt Tracking-Daten als `KB_Tracking` basierend auf `WC_Data` ab. Gespeicherte Felder: `shipment_id`, `tracking_id`, `status`, `status_timestamp`, `delivered`, `is_return`, `raw_data`, `events`, `do_tracking`.
- Erstellt bei Shiptastic Shipments mit Status `ready-for-shipping` automatisch ein Tracking-Objekt.
- Cron-Job (`kb_track_shipments`) läuft alle 30 Minuten und ruft die DHL Paket API (`request=d-get-piece-detail`) mit bis zu 15 Tracking-IDs pro Anfrage auf.
- Aktualisiert Status, Zeitstempel, Ereignisse sowie Zustell- (`delivered`) und Rücksende-Flag (`is_return`) und speichert das komplette Antwort-Objekt (`raw_data`).
- Admin-Seite unter `WooCommerce → Sendungsverfolgung` listet alle gespeicherten Sendungen mit Zeitstempel, Tracking-ID, Empfänger und Status; manuelle Aktualisierung per Button möglich.
- Logging über den WooCommerce-Logger; Level "Nur Fehler" oder "Fehler & Debug" ist über die Einstellungen wählbar.
- Option zum automatischen Entfernen aller Tracking-Daten bei Plugin-Deaktivierung.

**Einstellungen**
Zur Nutzung der DHL Paket API sind folgende Zugangsdaten erforderlich:
- `DHL API Key`
- `DHL API Secret`
- `DHL Benutzername`
- `DHL Passwort`

## Ausstehende Aufgaben
Abgleich mit den Anforderungen aus [DEFINITIONS.md](DEFINITIONS.md) zeigt folgende offene Punkte:
- Modul **Versandabwicklung** implementieren.
- Modul **Alexa** implementieren.
- Kommunikationsschnittstellen zwischen den Modulen bereitstellen.
- Bei Zustellung (`delivered = true`) den Status des zugehörigen Shiptastic Shipments auf `shipped` setzen.
- Erweiterte Fehlertoleranz und Fehlerbehandlung für alle Module.

