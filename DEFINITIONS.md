# Beschreibung
Es soll ein Plugin für Wordpress erstellt werden, welches Funktionen und Schnittstellen zur Versandabwicklung zur Verfügung stellt. Das Plugin soll dabei auf Ressourcen von **WooCommerce**, **Germanized for WooCommerce** und **Germanized for WooCommerce: Shiptastic** zugreifen und die Daten daraus aufbereiten. Desweiteren soll es Informationen zur Sendungsverfolgung aus der **DHL Paket DE Sendungsverfolgung (Post & Paket Deutschland)-API** beziehen und mit den im WooCommerce-Shop bestehenden Daten verknüpfen.

# verwendete Ressourcen und Pakete
1. Wordpress (https://github.com/wordpress)
2. WooCommerce (https://github.com/woocommerce/woocommerce)
3. Germanized for Woocommerce (https://github.com/vendidero/woocommerce-germanized)
4. Shiptastic for WooCommerce (https://github.com/vendidero/shiptastic-for-woocommerce)
5. Shiptastic Integration for DHL (https://github.com/vendidero/shiptastic-integration-for-dhl)
6. DHL Paket API (https://developer.dhl.com/api-reference/dhl-paket-de-sendungsverfolgung-post-paket-deutschland#get-started-section/)

# Aufgabenbeschreibung
## 1 Allgemeine Funktionen des Plugins
- automatische Updates des Plugins bei Vorliegen eines neueren Releases in diesem Repository
    - bei jedem erfolgreichen Merge in den main-Branch soll ein neues Release automatisch erstellt werden
    - der Header des Plugins soll entsprechend des neuen Releases angepasst werden, um automatische Updates innerhalb von Wordpress zu ermöglichen
- modularer Aufbau
    - Das Plugin soll in die Module "Sendungsverfolgung", "Versandabwicklung" und "Alexa" geliedert werden
    - Die Module sollen Schnittstellen bereitstellen, über die sie untereinander kommunizieren können
- Bereitstellung eines Einstellungs-Tabs in den Woocommerce-Einstellungen (Admin-Bereich)
    - Alle Einstellungen des Plugins sollen in einem eigenen Tab unter WooCommerce->Einstellungen editierbar sein
    - für jedes Modul dieses Plugins soll im Einstellungs-Tab ein eigener Block erstellt werden
- Logging / Debugging
    - Das Plugin soll über das WooCommerce-Logging Informationen zu Fehlern und Debug-Informationen speichern (Wordpress-Admin->WooCommerce->Status->Protokolle)
    - Im Logging soll eindeutig erkennbar sein, welches Plugin-Modul den Eintrag erzeugt hat
    - Der Benutzer soll auf der Einstellungs-Seite des Plugins festlegen können, welche Informationen geloggt werden (nur Fehler oder auch Debugging-Infos)
- Fehlertoleranz
    - Das Plugin soll so aufgebaut sein, dass kritische Fehler abgefangen werden und nicht zum Totalausfall der Seite führen

## 2 Modul "Sendungsverfolgung"
Das Modul holt selbständig Informationen zu Sendungen aus der DHL Paket API. "Sendungen" sind Shipment-Objekte aus "Shiptastic for WooCommerce". Abgeleitet vom WC_Data-Objekt wird dazu eine eigene Speicher-Klasse mit der Bezeichnung "KB_Tracking" angelegt, in der Informationen zur Sendungsverfolgung gespeichert werden können. Die Klasse soll das WC_Data-Objekt um folgende Einträge erweitern:

| Eigenschaft | Datentyp | Quelle | Quellobjekt | Quellen-Schlüssel | Beschreibung |
| --- | --- | --- | --- | --- | --- |
| shipment_id | int | Shiptastic | Shipment | id | Dient der Zuordnung zu einem Shiptastic-Shipment |
| tracking_id | string | Shiptastic | Shipment | tracking_id | Die Tracking-ID der Sendung. Entspricht dem "piececode" in der DHL Paket API
| status | string | DHL Paket API Response | piece-shipment | status | Der aktuelle Sendungsstatus |
| status_timestamp | datetime | DHL Paket API Response | piece-shipment | status-timestamp | Der Zeitstempel des aktuellen Status |
| delivered | bool | DHL Paket API Response | piece-shipment | delivery-event-flag | Kennzeichnet, ob die Sendung zugestellt wurde. 'true' wenn 1, 'false' wenn 0 |
| is_return | bool | DHL Paket API Response | piece-shipment | ruecksendung | Kennzeichnet, ob es sich um eine Rücksendung handelt. 'true' wenn "true", 'false' wenn "false" |
| raw_data | array | DHL Paket API Response | piece-shipment | (komlettes Objekt) | Das piece-shipment-Objekt aus der Sendungsverfolgung der DHL-Paket-API als assoziatives Array |
| events | array(array) | DHL PAKET API Response | piece-shipment | piece-event-list | Die piece-event-list als Array von assoziativen Arrays |
| do_tracking | bool | (wird vom Code gesetzt, default 'true') | | | legt fest, ob der Status der Sendung beim nächsten Aufruf aktualisiert werden soll |

Die DHL Paket API erwartet verschiedene Parameter:
- 'dhl-api-key'
- 'dhl-api-secret'
- 'username'
- 'password'

Diese sollen auf der Einstellungs-Seite eingegeben werden können und sind obligatorisch. Sind sie nicht gesetzt, soll das Modul keine Aufrufe an die DHL Paket API ausführen.

Die DHL Paket API soll mit dem Parameter 'request=d-get-piece-detail' aufgerufen werden.

Eine Beispiel-Antwort der DHL Paket API befindet sich unter [docs/dhl/sample-response.xml](docs/dhl/sample-response.xml).

Der Vorgang soll wie folgt ablaufen:
1. Wenn eine neue Sendung über Shiptastic erstellt wird, wird ihr Status geprüft. Sobald der Status "ready-for-shipping" erreicht wird geprüft, ob bereits ein KB-Tracking-Object mit der gleichen shipment_id vorliegt. Falls nicht, wird ein entsprechendes Objekt erzeugt und der Wert von 'tracking_id' synchronisiert.
2. alle 30 Minuten wird der Sendungsverfolgungs-Prozess gestartet. Dazu wird die KB_Tracking-Objekte geladen und der Status der Sendungen über die DHL Paket API abgerufen, sofern der Wert für 'do_tracking' "true" ist. Die 'tracking_id'-Werte werden dazu als "piecode" an die DHL Tracking API übergeben. Es können max. 15 piecodes pro Aufruf (separiert durch Semikolon) übergeben werden, liegen mehr tracking_ids vor, werden mehrere Aufrufe ausgeführt. Das Ergebnis des API-Aufrufes wird ausgewertet und die KB_Tracking-Objekte entsprechend aktualisiert und gespeichert.
3. Sobald die Eigenschaft "delivered" eines KB_Tracking-Objektes "true" wird, wird der Status des zugehörigen Shiptastic-Shipment-Objektes auf "shipped" aktualisiert und gespeichert.

Im Admin-Bereich soll unter "Woocommerce" eine eigene Seite mit dem Titel "Sendungsverfolgung" angezeigt werden, auf der eine Liste der KB_Shipment-Objekte präsentiert wird - sortiert absteigend anhand des "status_timestamp". Die Liste soll folgende Spalten haben:
1. Zeitstempel: zeigt den Wert des "status_timestamp" an (Datum und Uhrzeit), formatiert entsprechend der Zeitzone des Benutzers
2. Tracking-ID: zeigt den Wert von "tracking_id" an
3. Empfänger: Name und Adresse des Empfängers, generiert aus den Daten von "raw_data". Die für die Anzeige der Adresse benötigten Einträge dort beginnen mit "pan-recipient"
4. Status: der Wert von "status"

Der nötige Eintrag für 'wp-cron' soll automatisch gesetzt werden, sofern er nicht existiert. Bei Deaktivierung des Plugins soll er gelöscht werden.

## 3 Modul "Versandabwicklung"
(wird ergänzt)
## 4 Modul "Alexa"
(wird ergänzt)
