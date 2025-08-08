# woocommerce-kingbear-adapter

Plugin für WooCommerce, das Versandabwicklung und Sendungsverfolgung modular bereitstellt.

## Aufbau
- **Plugin** lädt Module und stellt einen Einstellungs-Tab in den WooCommerce-Einstellungen bereit.
- **Module**: "Sendungsverfolgung" (implementiert), "Versandabwicklung" und "Alexa" (Platzhalter).

## Sendungsverfolgung
- Speichert Tracking-Daten als `KB_Tracking` basierend auf `WC_Data`.
- Cron-Job alle 30 Minuten ruft die DHL Paket API (`d-get-piece-detail`) mit bis zu 15 Tracking-IDs ab.
- Aktualisiert Status, Zeitstempel, Events sowie Zustell- und Rücksende-Flag.
- Bei Zustellung (Flag `delivered`), ist ein Update des zugehörigen Shiptastic-Shipment vorgesehen.
- Admin-Seite unter `WooCommerce → Sendungsverfolgung` listet alle Sendungen mit Zeitstempel, Tracking-ID, Empfänger und Status.

### Einstellungen
Unter `WooCommerce → Einstellungen → KingBear Adapter` können pro Modul Parameter gepflegt werden. Für die Sendungsverfolgung:
- DHL API Key & Secret
- DHL Benutzername & Passwort
- Logging: nur Fehler oder zusätzlich Debug-Infos

## Aufgabenübersicht
- Automatische Updates über GitHub-Releases (Plugin-Header-Version).
- Modularer Aufbau mit kommunizierenden Modulen.
- Logging über WooCommerce-Logger, Level im Backend konfigurierbar.
- Fehler sollen abgefangen werden, um Totalausfälle zu vermeiden.

