# Suchis Autoload Inspector

Lokales WordPress-Adminwerkzeug unter **Werkzeuge → Autoload Inspector**.
**Version 0.2.0 – Testversion, vor Produktiveinsatz auf Staging prüfen.**
Voraussetzungen: WordPress 6.6+, PHP 7.4+, `manage_options`. Multisite ist noch nicht vollständig unterstützt.

## Öffentliche Anleitung

[Suchis Autoload Inspector – WordPress-Optionen verstehen und gezielt prüfen](https://www.derperformer.com/wordperessplugin/suchis-autoload-inspector-wordpress-optionen/)

Der Beitrag erklärt Autoload, Funktionen, Herkunftsheuristiken und Sicherheitsgrenzen der Testversion. Die URL ist als `Plugin URI` hinterlegt (WordPress-Link „Plugin-Website aufrufen“); zusätzlich steht in der Pluginzeile der Link „Anleitung“. Ein öffentlicher Plugin-Download wurde damit nicht eingerichtet.

## Funktionen

- Alle Options-Metadaten anzeigen; standardmäßig nur automatisch geladene Optionen.
- Herkunft: WordPress-Core, Plugin, Theme oder Unbekannt. Anbieter, Aktivitätsstatus und Grundlage der Zuordnung stehen separat in der Tabelle.
- Kombinierbare Filter nach Herkunft, Anbieter, Status, Autoload und Mindestgröße in Bytes.
- Suche über Optionsnamen, Herkunft, Anbieter, Status, Zuordnungshinweise, Autoload-Rohwert und Bytezahl. Mehrere Suchwörter sind UND-verknüpft.
- Native Browser-Autocomplete-Vorschläge für Optionsnamen und Anbieter aus dem gesamten Bestand, unabhängig von der aktuellen Seite. Auswahl eines Vorschlags setzt Suchtext; ein exakter Anbieterfilter ist separat verfügbar.
- Keine Suche in Optionswerten und keine Ausgabe von Optionswerten im HTML.
- Gesamt-Autoloadgröße, Trefferanzahl, gefilterte Größe und aufklappbare Gruppensummen mit Anteilen an der aktuellen Auswahl.
- Sortierung nach Größe oder Name, 50 Einträge je Seite, kompakte Navigation.
- Autoload einzeln an-/ausschalten; erkannte Core-Optionen und eigene Protokolloptionen sind geschützt.
- Letzte Änderung rückgängig machen, sofern der Autoload-Rohwert seitdem nicht verändert wurde.
- Dauerhafter, auf die letzten 100 Änderungen begrenzter Verlauf mit Zeit, Benutzer-ID und altem/neuem Rohwert; keine Optionswerte.
- Vollständige Optionstabelle als vertraulichen SQL-Download exportieren.
- Keine externen Dienste, Frontend-Skripte, Cronjobs oder automatischen Löschaktionen.

## Grenzen der Herkunftserkennung

WordPress speichert keinen zuverlässigen Eigentümer pro Option. Auch bekannte Präfixregeln sind **keine Eigentumsgarantie**. Ein Plugin kann gemeinsame Optionen verwenden oder Namen anderer Komponenten übernehmen.

1. Explizite Core-Namen und `${Tabellenpräfix}user_roles` werden als Core erkannt und geschützt. Die Liste ist bewusst konservativ und **nicht vollständig**. Unbekannt bedeutet nicht ungefährlich.
2. `theme_mods_{stylesheet}` wird mit installierten Themes abgeglichen; aktive Parent-/Child-Themes werden berücksichtigt.
3. Kleine explizite Plugin-Präfixliste: WooCommerce, Yoast SEO, Elementor, Inspector.
4. Abgleich installierter Plugins und MU-Plugins über Plugin-Slugs; solche Treffer werden ausdrücklich als Vermutung angezeigt.
5. Andere Optionen bleiben unbekannt. Insbesondere beweist ein `wp_`-Präfix keine Core-Herkunft. Transients sind kein eigenständiger Eigentümer.

Der Installationsstatus bezieht sich auf die zugeordnete Komponente, nicht auf die aktuelle Nutzung der Option. „Inaktiv“ oder „nicht installiert / prüfen“ ist niemals eine Löschfreigabe. Alte Optionen ohne bekannte Regel können keinem entfernten Plugin zuverlässig zugeordnet werden.

Eigene Präfixregeln können lokal über einen Filter ergänzt werden, beispielsweise in einem MU-Plugin:

```php
add_filter('sai_owner_rules', static function (array $rules): array {
    $rules['mein_plugin_'] = 'mein-plugin'; // Plugin-Verzeichnisname
    return $rules;
});
```

Bei überlappenden Regeln gewinnt die erste passende Regel. Kürzel nicht zu allgemein wählen.

## Sicherheit und Änderungen

Vor Änderungen vollständiges Backup von Dateien und Datenbank erstellen. Auf lokaler Kopie bzw. Staging testen. Eine Option kann bei ausgeschaltetem Autoload weiterhin gebraucht werden; Änderungen können zusätzliche Abfragen und verzögerte Funktionsfehler verursachen.

- Seitenaufrufe lesen nur Metadaten; keine Klassifikation wird in fremde Optionen geschrieben.
- Änderungen/Undo benötigen Berechtigung, POST und Nonce. Einzeländerungen zusätzlich Browserbestätigung.
- Die Datenbankänderung erfolgt bewusst als bedingtes UPDATE (**Compare-and-swap**) mit dem angezeigten Autoload-Rohwert. Bei zwischenzeitlich anderem Rohwert wird abgebrochen. Anders als ein unbedingtes API-Update überschreibt das keine abweichende Autoload-Einstellung.
- Danach werden Einzeloptions-, `alloptions`- und `notoptions`-Cache invalidiert. Plugin-spezifische Hooks werden durch das direkte UPDATE nicht ausgeführt.
- Undo stellt den ursprünglichen Rohwert exakt wieder her; alte 0.1-Protokolle ohne erwarteten Nachher-Wert werden aus Sicherheitsgründen abgelehnt.
- Änderung, Undo-Protokoll und Journal sind keine gemeinsame Datenbanktransaktion. Bei Schreibfehlern oder gleichzeitigen Administratoraktionen kann die letzte Undo-Historie fehlen/überschrieben werden. Ein zwischenzeitlich identisch zurückgesetzter Rohwert wird durch Compare-and-swap nicht erkannt.
- Die eigene Historie und das Journal werden mit Autoload aus gespeichert und beim Deinstallieren entfernt. Fremde Optionen bleiben erhalten.

## SQL-Export

Der Download enthält möglicherweise Passwörter, Tokens und API-Schlüssel: sicher aufbewahren, nicht ins Git-Repository legen oder öffentlich weitergeben. Kein dauerhafter Export im Webroot.

Hex-Literale vermeiden SQL-Modus-abhängiges String-Escaping und bewahren beliebige Bytes. Die Abfrage nutzt schlüsselbasierte 200-Zeilen-Blöcke. Ein Import aktualisiert/ergänzt vorhandene Zeilen, entfernt aber keine zusätzlichen Zeilen. Nur in eine passende Tabelle mit kompatiblem Schema importieren. Kein transaktionaler Snapshot: parallele Änderungen sind möglich. **Kein Ersatz für ein vollständiges Backup.** Wiederherstellung mit echten Sonderzeichen/Binärdaten und großen Werten ist vor Produktion noch separat zu prüfen.

## Tests

Ohne WordPress ausführbar:

```sh
php tests/smoke.php
find . -name '*.php' -not -path './.git/*' -print0 | xargs -0 -n1 php -l
```

Die Smoke-Tests verwenden minimale WordPress-Stubs. Sie prüfen Core-Zuordnung, Tabellenpräfix, unbekannte `wp_`-Namen, Pluginstatus, Theme-Zuordnung, HTML-Escaping, Summen, kombinierte Filter und Metadatensuche. Zusätzliche Tests mit einer In-Memory-Datenbankattrappe prüfen Änderungen, exaktes Undo, Konfliktabbruch, Cache-Invalidierung, Core-/Protokollschutz sowie Berechtigungs- und Nonce-Prüfung. Sie ersetzen keinen Integrationstest.

### Manuelle lokale Abnahme

1. Plugin aktivieren und Werkzeug öffnen. Mit der Datenbank vergleichen: `yes`, `on`, `auto-on`, `auto` zählen standardmäßig als Autoload (WordPress-API berücksichtigt Filter).
2. Nach einem Pluginnamen suchen, Vorschläge auswählen, Anbieter exakt filtern. Auch Treffer jenseits der ersten Seite prüfen.
3. Core-/Theme-/Unbekanntfilter, Status, Autoload aus/alle, Mindestgröße und Reset kombinieren; Seitennavigation muss Filter erhalten.
4. An einer eigens angelegten **entbehrlichen Testoption** Autoload aus und wieder an schalten. Rohwert in der DB und Cacheverhalten prüfen. Keine fremden Produktionsoptionen als Test verwenden.
5. Letzte Änderung rückgängig machen; ursprünglichen Rohwert prüfen. Vor Undo den Autoload-Wert manuell anders setzen: Undo muss abbrechen.
6. Gleiches Formular in zwei Tabs öffnen: Nach erster Änderung muss die zweite veraltete Anfrage scheitern. Core-Optionen und eigene Protokolle dürfen auch per manipuliertem POST nicht änderbar sein.
7. Ohne Berechtigung bzw. mit ungültiger Nonce müssen Export/Änderung/Undo abgewiesen werden.
8. Mit persistentem Objekt-Cache, Child-Theme und MU-Plugin wiederholen. Zeitversetzte Funktionen testen.
9. Export nur in eine separate Testdatenbank importieren; Optionswerte bytegenau vergleichen. Nicht ins Repository aufnehmen.

## Weitere Ausbaustufen

- WordPress-/MySQL-Integrationstests einschließlich Cache und echter Export-Wiederherstellung.
- Umfangreichere gepflegte Core- und Pluginregeln, Theme-spezifische Regeln und eigene Zuordnungsoberfläche.
- Serverseitige Paginierung und begrenzter AJAX-Suchindex: aktuell werden alle Metadaten geladen und Vorschläge im HTML ausgegeben; sehr große Optionstabellen können langsam werden.
- Atomare Protokollierung und zuverlässige Mehrbenutzer-Historie, optional Undo je Eintrag mit Konfliktprüfung.
- CSV-Metadatenexport ohne Werte, vollständige WordPress-i18n und erweiterte Barrierefreiheit.
- Keine Batch-Löschung oder automatische Optimierung allein aufgrund einer Heuristik.

## Dateien

- `suchis-autoload-inspector.php`: Bootstrap und Pluginmetadaten
- `includes/class-sai-admin.php`: Berechtigungen, Datenzugriff, Änderungen, Journal, Export
- `includes/class-sai-catalog.php`: Herkunftsregeln und installierte Komponenten
- `includes/class-sai-view.php`: Metadatensuche, Filter, Summen, Tabellenansicht
- `tests/`: eigenständig ausführbare Metadaten-/UI-Tests
- `uninstall.php`: entfernt ausschließlich eigene Protokolle

GPL-2.0-or-later. Autor: Christian Suchanek.
