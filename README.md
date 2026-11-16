# Suchis Autoload Inspector

WordPress-Adminwerkzeug zur Diagnose automatisch geladener Optionen (`wp_options`). **Entwicklungsstand: 0.1.0 / Testversion – zuerst lokal oder auf Staging verwenden.**

## Ziel und Bedienung

Unter **Werkzeuge → Autoload Inspector** werden Anzahl und Gesamtgröße automatisch geladener Optionen, die größten Einträge, ihre Autoload-Werte und eine *heuristische* Gruppierung nach Optionsnamen angezeigt. Suche und Sortierung helfen beim Eingrenzen. Ein aufklappbarer Warnhinweis erklärt Risiken und empfiehlt zuerst die Bereinigung nicht mehr verwendeter Plugins und Themes (ein Standardtheme als Reserve behalten).

- Ein vollständiger SQL-Export der Optionstabelle steht als Download zur Verfügung. **Er ist vertraulich**, denn Optionswerte können Passwörter und API-Schlüssel enthalten.
- Einzelne, nicht geschützte Autoload-Optionen können nach Bestätigung deaktiviert werden.
- Die jeweils letzte Änderung lässt sich über „Rückgängig“ wiederherstellen; das ursprüngliche Autoload-Feld wird exakt protokolliert.
- Das Plugin hat keinen Cronjob, keinen Frontend-Code, keine Lizenzprüfung und keinen externen Netzwerkzugriff.
- Es wird beim Deinstallieren sein eigenes Änderungsprotokoll entfernen, **nicht** die fremden Optionen.

**Wichtig:** Die aktuelle Oberfläche zeigt nur *automatisch geladene* Optionen; ausgeschaltete Einträge sind nur über den letzten Undo-Schritt wieder aktivierbar. Das vollständige gezielte Ein- und Ausschalten und eine dauerhafte Änderungsverlauf-Ansicht sind nächste Entwicklungsaufgaben. Diese Einschränkung ist absichtlich dokumentiert.

## Installation

1. Ordner `suchis-autoload-inspector` nach `wp-content/plugins/` kopieren oder das ZIP über **Plugins → Installieren → Plugin hochladen** installieren.
2. Plugin aktivieren, als Administrator **Werkzeuge → Autoload Inspector** öffnen.
3. Vor Änderungen **vollständiges Website-Backup** (Dateien und Datenbank) erstellen; optional zusätzlich den angebotenen Options-Export herunterladen.
4. Optionen analysieren, einzeln ändern, Website und zeitversetzte Funktionen testen.
5. Nach Abschluss Plugin bei Bedarf deaktivieren und löschen.

**Voraussetzungen:** WordPress 6.4+, PHP 7.4+, Berechtigung `manage_options`. Datenbankpräfix wird dynamisch über `$wpdb->options` ermittelt.

## Dateistruktur

- `suchis-autoload-inspector.php` – Plugin-Bootstrap und Metadaten
- `includes/class-sai-admin.php` – Administrationsansicht, SQL-Abfrage, Export, Änderungen und Undo
- `uninstall.php` – entfernt ausschließlich eigene Plugin-Optionen
- `README.md` – Projektdokumentation für Entwicklung mit VS Code / GitHub Copilot

## Datenmodell und Autoload

Die Datenbankspalte `autoload` kennt je nach WordPress-Version u.a. `yes`, `no`, `on`, `off`, `auto`, `auto-on`, `auto-off`. Als **automatisch geladen** gelten hier `yes`, `on`, `auto-on`, `auto`. Nicht alle Optionsnamen lassen sich eindeutig einem Plugin zuordnen. **Präfix ≠ Beweis, dass ein Plugin installiert oder entfernt ist.**

Das Ausschalten erfolgt über die WordPress-API `wp_set_option_autoload()`; Undo stellt den ursprünglichen DB-Wert wieder her und invalidiert den `alloptions`-Cache. Änderungen nur mit `manage_options`, POST, Nonce und expliziter Bestätigung. Die Anzeige von Werten wird bewusst vermieden, weil sie Geheimnisse enthalten können.

## Copilot: Priorisierte Entwicklungsaufgaben

1. **Vor Produktion:** SQL-Export auf Testinstallation mit Sonderzeichen, Binärdaten, sehr großen Optionswerten und Import in eine Testdatenbank prüfen. Der jetzige Export ist eine einfache zeilenbasierte Implementierung und kein Ersatz für `mysqldump` oder ein professionelles Backup-Plugin. SQL-Escaping/Zeichensatz besonders gründlich prüfen.
2. **Änderungen robuster machen:** `wp_set_option_autoload()`-Rückgabeverhalten bei unverändertem Wert untersuchen; History nur bei nachweislich erfolgreicher Änderung schreiben. Undo gegen konkurrierende Änderungen und Objekt-Cache testen; lieber API-konform arbeiten und keine fremden Updates überschreiben.
3. **Ein-/Ausschalten vervollständigen:** Alle Optionen mit `on/off`-Filter anzeigen, dabei nicht automatisch geladene Einträge separat abrufen. Geschützte WordPress-Kernoptionen und Plugin-eigene Optionen strikt ausschließen.
4. **Paginierung serverseitig:** Die aktuelle Version lädt alle Autoload-Metadaten für Suche/Sortierung; bei riesigen Tabellen auf `COUNT(*)`, `SUM(OCTET_LENGTH(...))`, `LIMIT` und `OFFSET` bzw. Keyset-Paginierung umstellen.
5. **Sichere Batch-Funktion:** Mehrfachauswahl nur mit Vorschau, Nonce, Bestätigung und atomarem Änderungsjournal; keine automatische Löschung.
6. **Heuristik verbessern:** Aktive Plugins, installierte Plugins und Themes aus WordPress-APIs einlesen; Präfix-Zuordnungen als unsicher markieren. Nicht mehr verwendete Plugin-Daten niemals automatisch löschen.
7. **Testabdeckung:** WordPress-Core-Optionen, WooCommerce, Multisite (zunächst nicht unterstützt), persistenter Objekt-Cache, Transients, Cron, PHP 7.4–8.4, WP 6.4+, große Tabellen, ungewöhnliche Tabellenpräfixe, CSRF und Rollenrechte.
8. **UX:** Größenformat in Byte/KiB/MiB, Gruppensummen, CSV-Metadatenexport ohne Optionswerte, Filter nach Status und Gruppen, mehrsprachige Strings mit WordPress-i18n.

## Sicherheit und Betriebsregeln

- Analyse ist **read-only**; beim Aufruf der Admin-Seite dürfen keine Änderungen erfolgen.
- Keine vollständigen Optionswerte im HTML, in Logs oder in Fehlermeldungen ausgeben.
- Export nie dauerhaft unter `uploads/` oder im Webroot ablegen.
- Der SQL-Export ist **nur eine zusätzliche Sicherung der Optionstabelle**, kein vollständiges Website-Backup.
- Eine Option kann auch bei deaktiviertem Autoload weiterhin gebraucht werden. Falsche Autoload-Einstellungen können zusätzliche Datenbankabfragen und verzögerte Fehler verursachen.
- Keine pauschalen Löschaktionen und keine automatisierte Entscheidung anhand von Präfixen.
- Uninstall darf keine Kundendaten löschen.

## Copilot-Startprompt

> Lies die README.md und den bestehenden Plugin-Code vollständig. Überprüfe zuerst Sicherheits- und Datenintegritätsrisiken, insbesondere SQL-Export, Autoload-API, Cache-Invalidierung, Undo und Berechtigungen. Implementiere danach das gezielte Ein- und Ausschalten mit Filter für alle Optionen. Halte Änderungen klein und nachvollziehbar. Verändere oder lösche niemals fremde WordPress-Optionen automatisch. Erstelle zu jeder Funktion eine kurze Testanleitung für eine lokale WordPress-Installation.

## Lizenz

GPL-2.0-or-later. Autor: Christian Suchanek.
