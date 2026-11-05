# Sol Editor: Implementierungsübergabe, 2026-11-05

Implementierungsstand: lokaler Release-Kandidat mit belegten Tests, **noch nicht vollständig gemäß Gesamtkonzept freigabefähig**. Die erste automatische Workspace-Versionierung bleibt aus einem nachgewiesenen Core-Grund gesperrt. Gemeinsame Browser-Integration ist ausdrücklich nicht durchgeführt.

## Repo und Commit

- Ausschließlich MAIN/packages/visual_editor_enhancements geändert; Ausgangspunkt `2874dcd` / 1.4.4, vorher sauber.
- Lokaler Commit: `252cdaa3cab22656783029fcf740ddfe88577fd2` — `feat: add guarded collection editing and workspace-aware field choices`.
- Paket-Repo danach sauber. Keine Tags, Pushes, Deployments, Composer-Lock-Änderungen oder redaktionellen Runtime-Schreibvorgänge.
- MAIN-Lab bleibt beim geprüften Basiscommit `7952f99`. WT enthielt beim Abschluss bereits den fremden Parallel-Commit `f5c52b0`; dessen Dateien wurden nicht geändert.
- Einzige zusätzliche WT-Datei dieses Workers ist diese Übergabe. Keine Konzeptdokumente verändert, keine Subagents oder weiteren Worktrees erzeugt.
- Dokumentationsmetadaten/Changelog nennen vorläufig `1.5.0-rc.1`; keine Tag- oder Paketversion veröffentlicht.

## Implementiert

- `EditorRecordContext` authentifiziert über die bestehenden Route-/Endpoint-Grenzen, trennt requested UID, Compilation UID und Workspace, verweigert fremde Workspace-Zeilen und Löschplatzhalter. Core FormEngine prüft Seiten-/Content-Rechte passend zum Tabellentyp; zusätzlich Webmount, Sprache/Record-Zugriff und Seiten-Editlock. Workspace-Bearbeitungsrecht folgt dem tatsächlich geprüften Core-Verhalten `workspaceCheckStageForCurrent(0)`; gespeicherte Nichtnull-Stages werden nicht pauschal gesperrt.
- Vollständige FormEngine-TCA bleibt für displayCond-Abhängigkeiten erhalten. Rückgabe wird separat auf erlaubte Felder beschränkt. Resolved Type inklusive typeloser Tabellen mit `1`, columnsOverrides, TCEFORM, readonly/exclude, resolved Select-/Radio-/Country-Items und normalisierte Werte werden verwendet. Unbekannte gespeicherte Select-Werte bleiben sichtbar und unverfügbar.
- Tabs und Sections bleiben erhalten; Tab-Identität verwendet Indizes, gleiche Labels kollidieren nicht. Auswahl bleibt beim Refresh erhalten, verspätete Antworten ersetzen keinen anderen Datensatz. Cache berücksichtigt Datensatz, Kontextpfad, Sprache und Workspace.
- Unabhängige, standardmäßig ausgeschaltete Benutzereinstellung `tx_visualeditor_inlineRecords`, Install-Flag `inlineRecordsEnabled`, Page TSconfig `tx_visualeditorenhancements.inlineRecords`. Separater Sammlungseinstieg funktioniert auch bei deaktiviertem gewöhnlichem Field Chooser. Serverseitige Beziehungs-Kontextpfade autorisieren verschachtelte Kind-Einstellungen ohne globale Tabellenfreigabe. Output-Affordances verwenden die Identität ihres tatsächlichen Kindes; der Content-Element-Wrapper bleibt Refresh-Ziel.
- Bestehende Save-Route, eigener strikt validierter `inlineRecord`-Zweig. Keine clientseitigen DataHandler-Maps, Child-Table-/PID-/Sprach-/Workspace-/Live-UID-Parameter oder Mischanfragen. Beziehungen kommen aus TCA und FormEngine; Ownership, Match-Felder, Berechtigungen, Controls, Defaults, min/max, Revision und Ziele werden unter einem nicht blockierenden Core-Lock erneut geprüft. Stale Requests liefern 409 mit aktualisierter Collection.
- Eine scoped `InlineDataHandler`-Instanz pro Strukturaktion. Datamap-Fehler verhindern nachfolgende Commands. Parent/Child auf verschiedenen Connections werden verweigert; sonst Transaktion und Rollback. Workspace-Schreibvorgänge vergleichen zusätzlich die beteiligten Live-Zeilen vor Commit. Kein pauschales Atomicity-Versprechen für externe Hooks/Caches oder andere Datenbankverbindungen.
- Generischer Save-Guard verhindert direkte Inline-Neuzuweisung, Child-Owner-/Sortier-/Sprach-/Workspace-Felder, direkte Custom-Child-Erzeugung und Child-Commands. Normale Kindfeldänderungen und Elementbibliothek-Erzeugung/-Kopie bleiben getestet verfügbar. Kein `inlineLocalizeSynchronize` hinzugefügt.
- Core-Modal-Adapter mit standardmäßig aktivem Cancel, Text-Escaping, Abbruch bei Dismiss und Fokuswiederherstellung; native Bestätigung als dokumentierter Fallback. Keine Vendor-`_delete`-Änderung.
- Pending/Invalid/Saving/Unknown-State sperrt Strukturaktionen, einschließlich aggregiertem Backend-Save-State anderer Sprachframes. Kein automatisches Save und kein `saveEnded`-Erfolgsbarrieretrick. Doppelklicks sind während Requests gesperrt; Fehler werden nicht automatisch wiederholt. Wrapper-Refresh verwirft keine neu hinzugekommenen lokalen Textänderungen.
- Live-Erzeugung benötigt standardmäßig ein schreibbares Visibility-Feld und bleibt hidden. Es wird niemals automatisch `hidden=0` gestaged. Backend-Links stehen für erforderliche Medien, komplexe Felder, nicht gerenderte optionale Felder und verweigerte Operationen bereit.
- Bedien-/API-Dokumentation in `Documentation/Collections.rst`, README/Changelog, DE/EN-Einstellungs- und Aktionslabels. Sitemap auf `menu` umgestellt, damit der Dokumentationsrenderer keine doppelten Eltern meldet.

## Relevanter Core-Befund / verbleibender Blocker

Der alte Rechercheplan enthielt zwei Annahmen, die die echte funktionale Suite widerlegt hat:

1. `TcaInline::resolveRelatedRecords()` schreibt zuerst **Live-UIDs** in `databaseRow[field]`; die Workspace-Substitution findet danach für die Child-Kompilation statt. Der Server muss Kindkontexte wirklich kompilieren und deren Compilation UID verwenden. Nur das rohe Feld zu übernehmen beschädigte Live-Kind-Sortierung und Zeitstempel.
2. Core `DataHandler::createRelationHandlerInstance()` setzt `useLiveReferenceIds=true`, wodurch `RelationHandler::readList()` eingesendete Entwurfs-IDs wieder zu Live-IDs umschreibt. Die ausschließlich im Intent-Zweig benutzte `InlineDataHandler`-Unterklasse stellt die Relation-Factory in Workspaces auf `setUseLiveReferenceIds(false)`; Live-Parent-Pointer bleiben erhalten. Kein globaler XCLASS und keine Vendor-Datei wurde geändert.

Damit bestehen wiederholtes Erzeugen, Verwerfen neuer Entwürfe und Markieren bestehender Einträge zur Löschung **bei bereits versioniertem Parent und vorhandenen Kindversionen** mit bytegleichem Live-Parent und Live-Kind einschließlich Sorting/Timestamp.

Die **erste automatische Versionierung** ist dagegen nicht unterstützt: Ein gezielter Test mit einem echten Core-`version`-Command unter 14.3.10 zeigte bereits beim Versionieren die Änderung von Live-Kind-`sorting` von 10 auf 1 sowie `tstamp` von 0 auf EXEC_TIME. Das trat auch ohne verbundene Parent-Übersetzung auf. Dieser Diagnoseversuch lief ausschließlich auf temporären SQLite-Fixtures; der Versuchscode wurde nicht in der Suite behalten. Die produktive API verweigert fehlende vollständige Entwürfe vor Mutation und zeigt einen Backend-Fallback; sie korrigiert nicht Core-Versionierung im Backend. **Der vollständige Konzeptpunkt „erstes Workspace-Create aus live“ ist damit offen und darf nicht als bestanden gelten.** Keine Tests abgeschwächt, keine Live-Metadaten nachträglich per SQL zurückgeschrieben.

Weitere bewusste Fallbacks: FAL/MM, Selector/Unique-/Spezialkonfigurationen, Child-Type-Selektoren, System-/Content-Tabellen als generische Kinder, erforderliche komplexe Defaults, verbundene übersetzte Parents; Löschung mit Medien, verschachtelten Kindern oder verbundenen Übersetzungen. Unabhängige Übersetzungen sind im Code vorgesehen; der echte mehrsprachige Workspace-Browsernachweis fehlt noch.

## Tests und Belege

Lokal: PHP 8.5.12, Core 14.3.10, isolierte TYPO3-Testing-Framework-Installationen mit SQLite. Keine Passwörter oder produktiven Records verwendet.

| Prüfung | Ergebnis |
| --- | --- |
| Unit gesamt | 75 Tests, 140 Assertions, bestanden |
| Node/JavaScript gesamt | 50 Tests, 0 Fehler, 0 übersprungen |
| Functional gesamt | 42 Tests, 177 Assertions, 0 Fehler; 2 bestehende Core/Vendor-Deprecations |
| Letzter gezielter Inline-Lauf nach Response-/URL-Änderungen | 15 Tests, 76 Assertions; bestanden mit 1 Vendor-Deprecation |
| Playwright `components` gesamt | 11 bestanden, davon 7 neue Chooser-/Collection-Fälle |
| PHPStan Level 8 | keine Fehler, kein Baseline-/Ignore-Zusatz |
| PHP-Lint | alle gefundenen PHP-Dateien ohne Syntaxfehler |
| CGL dry-run | 0 von 57 Dateien zu ändern |
| `git diff --check` | bestanden |
| Dokumentationsvalidator | 10 RST-Dateien, 0 Warnungen |
| Docker render-guides | 10 Dokumente erfolgreich gerendert; final keine Warnungen/Fehler |

Die neue funktionale Suite prüft u.a. reale eingeschränkte Rechte mit/ohne Hidden-Feld, verweigerten Child-Tabellen ohne Listen-/Count-Leak, Page-Edit ohne Content-Edit, Editlock, Shared-/Nested-Ownership, min/max, Double-Click/Stale, Forge/Mixed Payloads, Feature/Preference off, generische Bypasses, Bibliothek-New/Copy, Lock-Konflikt, Rollback nach tatsächlichem Child-Write, fremden Workspace, echte Entwurfsidentität und bytegleiche Live-Zeilen nach wiederholten Workspace-Aktionen.

Die Komponentensuite nutzt echtes Lit und den echten Vendor-DataStore. Backend-Transport und Core-Modal-Adapter sind isoliert. Geprüft: Tabs/Sections, doppelte Tablabels, Refresh-Selektion, Nested Back, deaktivierter Chooser, Cancel/Escape, pending/invalid/saving, HTTP-Fehler ohne Retry, stale Responses und kein gestagtes Hidden-Unhide. **Die tatsächliche Core-Modal-Darstellung/Delegation in TYPO3 ist kein bereits bestandener Integrationstest.**

Logs dieser Sitzung: `/tmp/vee-unit-final.txt`, `/tmp/vee-js-final.txt`, `/tmp/vee-functional-final.txt`, `/tmp/vee-inline-final.txt`, `/tmp/vee-components-final.txt`, `/tmp/vee-phpstan.txt`, `/tmp/vee-lint-final.txt`, `/tmp/vee-cgl-final.txt`, `/tmp/vee-doc-render-final.txt`. Gerenderte Dokumentation wurde nach `/tmp/vee-docs-rendered-20261105/` verschoben, damit sie nicht ins Paket-Repo gerät.

Composer: Manifest gültig, aber der lokale ignorierte Dev-Lock enthält `typo3/cms-workspaces` noch nicht. `composer validate --strict --no-check-lock` meldet deshalb Lock-Warnungen. Kein Lock wurde verändert. Für die lokalen funktionalen Tests wurde nur `vendor/typo3/cms-workspaces` auf den vorhandenen MAIN-Vendor-Stand verlinkt und dessen PSR-4-Mapping im Testprozess ergänzt:

```sh
php -r '$loader = require "vendor/autoload.php"; $loader->addPsr4("TYPO3\\CMS\\Workspaces\\", __DIR__ . "/vendor/typo3/cms-workspaces/Classes/"); require "vendor/phpunit/phpunit/phpunit";' -- -c Build/phpunit/FunctionalTests.xml
```

Nach normaler Dev-Dependency-Auflösung genügt `Build/Scripts/runTests.sh -s functional`; das Skript unterstützt auch unit/js/phpstan/cgl und `-p` zur PHP-Versionprüfung. Keine neue Produktabhängigkeit auf Workspaces, nur eine Dev-Dependency.

Die zusätzlichen Skill-Extraktions-/Analyse-Helfer liefen auf dieser macOS-Bash-3-Umgebung nicht erfolgreich (Argumentvertrag bzw. `declare -A`); Validator und Docker-Rendering wurden separat erfolgreich durchgeführt. Eine vollständige Skill-Analyse wird nicht behauptet.

## Integration durch Parent

1. Den lokalen Paketcommit verwenden; er liegt bereits im gemeinsamen MAIN-Paketklon. Nicht erneut auf denselben Branch cherry-picken. Ausgehende Commits vor einem späteren Push gesondert prüfen.
2. Gemeinsam mit Desiderio und dem neuen Lab-Commit integrieren. Composer-/Lock-Arbeit ausschließlich koordiniert nach `lab-single-package-install.md`; Mutagen/Autoload/OPcache gemäß Projektanleitung synchronisieren. Dev-Workspaces-Dependency für reproduzierbare CI-Tests auflösen. Dieser Worker änderte keinen Lock.
3. Disposable Showcase-Seite des Parallelworkers verwenden. User-UC separat einschalten; gewöhnlichen Chooser zusätzlich auf disabled stellen, um Unabhängigkeit zu prüfen.
4. Admin und eingeschränkten Editor in Live und Workspace testen: Tab/Section/Child-Settings, echte Core-Modal-Cancel/Escape/Dismiss/Fokus, Hidden-Erhalt auch unter Autosave, Pending Text, wiederholte Aktionen und 409-Fälle. Seitenübersetzungen/übersetzte Entwürfe, Shared/Nested und Backend-Fallbacks real durchlaufen. Nur Testseiten-Records ändern.
5. Initiale Workspace-Versionierung als offenen Konzeptpunkt behandeln; der aktuelle Kandidat blockiert sie absichtlich. Keine Vollständigkeits-/Releasebehauptung für diesen Pfad. Vendor-Content-Element-Delete bleibt separat außerhalb der Änderung.
6. Keine echten Integrationstests, Tagging, Produktion, Seed oder Übersetzung wurden hier ausgeführt. Erst nach gemeinsamer Prüfung die finale Version wählen und die vom Parent autorisierte Release-Sequenz durchführen.

## Geänderte Paketdateien

```text
Build/Scripts/runTests.sh
CHANGELOG.md
Classes/Backend/Controller/PersistenceController.php
Classes/DataHandling/InlineDataHandler.php
Classes/Middleware/FieldOptionsMiddleware.php
Classes/Service/DataHandlerService.php
Classes/Service/EditorRecordContext.php
Classes/Service/FeatureFlags.php
Classes/Service/FieldChooserConfigurationService.php
Classes/Service/FieldOptionsService.php
Classes/Service/FrontendConfiguration.php
Classes/Service/InlineGenericSaveGuard.php
Classes/Service/InlineIntent.php
Classes/Service/InlineRecordConfiguration.php
Classes/Service/InlineRecordService.php
Configuration/TCA/Overrides/be_users.php
Documentation/Collections.rst
Documentation/Index.rst
Documentation/Sitemap.rst
Documentation/guides.xml
README.md
Resources/Private/Language/de.locallang_library.xlf
Resources/Private/Language/de.locallang_setup.xlf
Resources/Private/Language/locallang_library.xlf
Resources/Private/Language/locallang_setup.xlf
Resources/Public/JavaScript/Frontend/components/ve-field-chooser.js
Resources/Public/JavaScript/Frontend/element-context-affordance.js
Resources/Public/JavaScript/Frontend/element-refresh.js
Resources/Public/JavaScript/Frontend/index.js
Resources/Public/JavaScript/Frontend/inline-records.js
Resources/Public/JavaScript/Shared/config.js
Resources/Public/JavaScript/Shared/confirm-delete.js
Resources/Public/JavaScript/Shared/field-options-cache.js
Resources/Public/JavaScript/Shared/inline-record-actions.js
Tests/E2E/README.md
Tests/E2E/field-chooser-component.spec.js
Tests/Functional/Backend/Controller/InlineRecordTest.php
Tests/Functional/Fixtures/Extensions/inline_fixture/Configuration/TCA/Overrides/tt_content.php
Tests/Functional/Fixtures/Extensions/inline_fixture/Configuration/TCA/tx_vee_item.php
Tests/Functional/Fixtures/Extensions/inline_fixture/composer.json
Tests/Functional/Fixtures/Extensions/inline_fixture/ext_tables.sql
Tests/JavaScript/field-options-cache.test.js
Tests/JavaScript/inline-record-actions.test.js
Tests/Unit/Service/FrontendConfigurationTest.php
Tests/Unit/Service/InlineIntentTest.php
composer.json
```
