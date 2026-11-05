# Sol Showcase/Login – Implementierungsübergabe

Stand: 2026-11-05. Umsetzung nach dem Astra-Ausführungskonzept, ohne Unteragenten.

## Ergebnis und Git

- Desiderio: lokaler Commit `f39b3b3d` auf `MAIN/packages/desiderio`, Basis `d0c72f38` (4.30.0). 144 Dateien, davon 132 YAML-Dateien. Keine fremden Änderungen aufgenommen; das Repository war zu Beginn sauber.
- Lab-Dateien liegen bewusst **uncommitted im WT** für die Integration durch den Parent. MAIN-Lab wurde nicht committed. Kein Push, Tag, Deploy, Composer- oder Lockfile-Eingriff, keine Änderung an `.lab-links`, VEE oder den Konzeptdokumenten.
- Release Candidate im Abschnitt Unreleased dokumentiert. Versionsmetadaten bleiben 4.30.0; keine vorhandenen lokalen 4.31-Tags gefunden. Versionsvergabe und Release bleiben beim Parent.

Desiderio-Dateien außer YAML:

- `Classes/Data/Showcase/ShowcaseVisualEditorInlinePage.php`: 23-Blöcke-Matrix mit vorhandenen Fixtures/Medien, aktuellen Preisen aus `ShowcasePricing::plans()`, Erklärtext und stabilem Collection-Feature-Schalter.
- `Classes/Data/StyleguideShowcasePages.php`: Registrierung direkt nach RTE-Kombinationen.
- `Classes/Data/Showcase/ShowcaseBlocks.php`, `Classes/Command/SeedStyleguidePagesCommand.php`: optionales `noSearch` und Persistenz von `no_search`.
- `Build/Scripts/audit-collection-limits.php`: reproduzierbarer, lesender SQL-Gate für Collections und erforderliche FAL-Felder.
- Neue Unit-Tests `ContentBlockLimitKeysTest`, `ShowcaseVisualEditorInlinePageTest`; neuer Funktionstest `SeedVisualEditorInlinePageFunctionalTest`; bestehende Seed-Erwartung um die zusätzliche Seite erweitert.
- `CHANGELOG.md`, `Documentation/Developer/{Index,VisualEditorInlineShowcase}.rst`.

WT-Lab-Dateien:

- `packages/site_package/Resources/Private/Language/{backend_login,de.backend_login}.xlf`
- `packages/site_package/Resources/Private/TemplateOverrides/typo3/cms-backend/Layouts/Login.fluid.html`
- `packages/site_package/Resources/Public/Backend/Css/login.css` (nur Kommentar)
- `packages/site_package/Resources/Private/Data/Translations/desiderio/{de,zh,hu}.json`
- `Tests/E2E/visual-editor-inline-page.spec.js`
- diese Übergabe.

## Lokaler Inhalt

`https://webconsulting-typo3-lab.ddev.site/content-types/visual-editor-inline/`

| Sprache | Seiten-UID | Inhaltselemente | nav_hide | no_search |
|---|---:|---:|---:|---:|
| en | 8430 | 23 | 1 | 1 |
| de | 8431 | 23 | 1 | 1 |
| zh | 8432 | 23 | 1 | 1 |
| hu | 8433 | 23 | 1 | 1 |

Elternseite 1093. Keine anderen Runtime-Seiten neu gesät oder übersetzt. Die Matrix enthält Karten/RTE, Accordion, zwei gemeinsame FAQ-Eltern, Tabs, Timeline, Stats, gemeinsame Label/Value-Tabellen, kompakte/spacious Listen, Changelog, zwei verschachtelte Tabellen, drei Preispläne mit Unterlisten, Team, Beispielzitate, Galerie, fiktive Logos, Rechtslinks und fünf Wochentage. Preise: **Community €0, Studio €590, Agency €1.990**, direkt aus der aktuellen gemeinsamen Quelle; kein alter „Starter“-Plan.

CLI-Unterstützung für `--pages` zuerst mit `help` geprüft, danach Dry-run mit genau einer neuen Seite/23 Blöcken, dann Seed nach Slug und Wiederholung nach UID. Abschließend wurden ausschließlich UID 8430 und ihre Kinder übersetzt. Die Vergleichsdateien `unrelated-before.tsv` und `unrelated-final.tsv` sind bytegleich: pro PID außerhalb 8430 dieselben `COUNT`, `SUM(uid)`, `SUM(tstamp)`, `SUM(deleted)` für alle `tt_content`-Zeilen.

## Wichtige Runtime-Abweichung

MAINs Composer-Metadaten nennen installiertes Desiderio **v4.29.0**. Der separate Clone steht auf 4.30.0 plus diesem Commit. `vendor/webconsulting/desiderio` ist eine eigenständige installierte Kopie; `.lab-links` enthält nur webcon-templates. Die neuen Paketdateien sind deshalb **noch nicht regulär in den Web-Runtime eingebunden**.

Für den lokalen, gezielten Seed wurde ausschließlich ein temporärer CLI-Autoloader verwendet:

`/tmp/ve-showcase-loader.php` (Host und Web-Container)

Er überschreibt die Klassenzuordnung nur für `SeedStyleguidePagesCommand`, `StyleguideShowcasePages` und die neue `ShowcaseVisualEditorInlinePage` auf die Dateien in `MAIN/packages/desiderio`. Keine Vendor-Datei, Paketregistrierung oder Composer-Datei wurde verändert. Aufruf:

```sh
# Alle ddev-Aufrufe aus MAIN, nicht aus dem WT oder Paketverzeichnis.
ddev exec php -d auto_prepend_file=/tmp/ve-showcase-loader.php vendor/bin/typo3 desiderio:styleguide:seed --pages=/content-types/visual-editor-inline --dry-run
ddev exec php -d auto_prepend_file=/tmp/ve-showcase-loader.php vendor/bin/typo3 desiderio:styleguide:seed --pages=/content-types/visual-editor-inline
ddev exec php -d auto_prepend_file=/tmp/ve-showcase-loader.php vendor/bin/typo3 desiderio:styleguide:seed --pages=8430
```

**Folge:** Die anonymen Seiten sind real getestet, aber die neuen YAML-Limits stehen im Web-Runtime erst nach der regulären Paketintegration zur Verfügung. Der Core-/Content-Blocks-Funktionstest lädt den Paket-Clone tatsächlich und beweist die generierten Limits. Für `tt_content` liegen diese in `types.<CType>.columnsOverrides.<field>.config`; nicht einfach die globalen Spalten auslesen.

Installierter Core und Test-Core: **14.3.10**, Content Blocks **2.4.10**, Host-/MAIN-PHP **8.5.12**. Parser und Core-Quellen lokal gelesen.

## Limits und Audit

130 YAML-Dateien hatten ignorierte `minItems`/`maxItems`-Schlüssel. Ausschließlich die Schreibweise wurde normalisiert, kein deklarierter Zahlenwert verringert. Vorheriger SQL-Gate: **175 Relationsdefinitionen, 3.698 Elternprüfungen, 0 Verletzungen**. Er schließt versteckte und übersetzte Live-Datensätze, gemeinsame Tabellen mit `tablenames`/`fieldname` und verschachtelte Kinder ein. Gelöschte Datensätze und Workspace-Versionen sind ausgeschlossen; Parent-Zählungen über mehrere Relationen sind keine Anzahl eindeutiger Datensätze.

Zusätzlich zeigte das echte Content-Blocks-Schema fünf bisher ignorierte `File.required: true`-Deklarationen. Vor der Umstellung auf `minitems: 1` wurde separat der tatsächliche FAL-Bestand geprüft:

| Elterntabelle/Feld | Eltern | Fehlende Datei |
|---|---:|---:|
| tt_content.audio_file (Audio Player) | 7 | 0 |
| tt_content.image (Card Overlay) | 7 | 0 |
| content_carousel_items.slide_image | 45 | 0 |
| file_download_items.file | 21 | 0 |
| gallery_items.image | 585 | 0 |

Keine Inhalte ergänzt, entfernt oder global migriert. Damit gilt die Pflichtbild-Beschränkung der Galerie auch wirklich. Zusammen mit den zwei zusätzlichen File-YAML-Dateien ergeben sich 132 geänderte YAML-Dateien.

Abschlussaudit nach dem lokalen Seed/Übersetzen:

- 175 Collections, **3.830** Elternprüfungen: en 1.460, de/zh/hu je 790.
- 9 erforderliche File-Relationsprüfungen, **1.233** Elternprüfungen: en 396, de/zh/hu je 279. Gemeinsame Tabellen werden pro Relation erneut geprüft.
- **0 Limitverletzungen** in allen 184 Prüfgruppen.
- Zusätzlicher Runtime-Orphan-Check für Seite 8430: **27 nichtleere Relationsgruppen, 448 Kindprüfungen, 0 verwaiste Kinder**.
- Funktionstest beweist zweimaligen gezielten Seed mit realem Content Blocks, richtige gemeinsame Zuordnung, 3×3 Tabellenzellen, 3×2 Vergleichswerte, drei Pläne, entfernte alte Nachkommen und unveränderte Fremdseite.

Lesender Audit-Aufruf aus MAIN:

```sh
ddev exec php packages/desiderio/Build/Scripts/audit-collection-limits.php > limits.json
```

Die Ausgabe enthält pro Relation das ausgeführte SQL, Grenzen, Sprachabdeckung und sämtliche Verletzungen. Auf jedem Zielsystem vor Aktivierung erneut ausführen. Kein Anlass für abgeschwächte Mindestzahlen oder erfundene Fülldaten im lokalen Bestand.

## Übersetzungen und Synchronisation

Je Sprache **16 neue Einträge**, ausschließlich append-only in `strings`; alle alten Schlüssel/Werte und die JSON-Struktur bleiben erhalten. Neue Größe: de 8.545, zh/hu je 8.776. WT und MAIN sind semantisch identisch. Keine Scope-Datei verändert.

```sh
ddev exec vendor/bin/typo3 sitepackage:content:translate --site=desiderio --pages=8430 --languages=de,zh,hu --dry-run --missing=-
ddev exec vendor/bin/typo3 sitepackage:content:translate --site=desiderio --pages=8430 --languages=de,zh,hu --fail-on-missing
ddev exec vendor/bin/typo3 sitepackage:content:translate --site=desiderio --pages=8430 --languages=de,zh,hu --dry-run --fail-on-missing
ddev exec vendor/bin/typo3 cache:flush
```

Schreibender Lauf: de 137 Zeilen/255 Felder, zh 134/254, hu 136/256 aktualisiert; pro Sprache eine Seite und 23 Inhaltselemente lokalisiert, **0 fehlende Texte**. Abschließender Dry-run: alle Sprachen **0 neue Seiten, 0 neue Datensätze, 0 Updates, 0 fehlende Texte**. Keine Solr-Vollindexierung vorgenommen; die neue Seite ist `no_search`.

Die vier oben genannten Login-Dateien wurden vom WT nach MAIN kopiert, nachdem jede Runtime-Datei mit dem unveränderten WT-HEAD verglichen wurde. Nur diese vier Dateien und die drei Übersetzungsdateien wurden synchronisiert. Die Memory-Ergänzungen wurden separat auf beide vorhandenen JSON-Dokumente angewandt und anschließend gegen den gesamten alten Schlüsselbestand geprüft. Exakte Pfade und SHA256 vor/nach stehen in `/tmp/ve-showcase-evidence/site-file-sync.json` und `translation-sync.json`.

Login: `credits.tagline` EN/DE, neutrales Marken-Credit, keine Firma oder Zugehörigkeitsbehauptung. Originales TYPO3-SVG bytegleich, Copyright/GPL erhalten. Sichtkontrolle EN/Desktop und DE/Mobil sowie E2E für beide Sprachen und Viewports; ein zugänglicher Bildname „TYPO3“, keine doppelte Produktnennung im Tagline-Span. Kein Test auf den exakten Marketing-Satz.

## Prüfungen und Rohbelege

Alle Rohbelege liegen in `/tmp/ve-showcase-evidence/`. Finale Läufe, nicht die früheren roten Zwischenstände verwenden.

```sh
# Im separaten Desiderio-Repo; Host explizit, damit der Runner nicht dessen eigenes DDEV wählt.
export PATH=/opt/homebrew/opt/php@8.5/bin:$PATH
DESIDERIO_RUN_ON_HOST=1 Build/Scripts/runTests.sh -s unit
DESIDERIO_RUN_ON_HOST=1 Build/Scripts/runTests.sh -s phpstan
DESIDERIO_RUN_ON_HOST=1 Build/Scripts/runTests.sh -s functional
vendor/bin/php-cs-fixer fix --dry-run --diff
php -l Build/Scripts/audit-collection-limits.php
```

- Unit final: **1.103 Tests, 55.084 Assertions, erfolgreich**, kein Skip (`unit-final.txt`). Darin alle 244 Content-Element-YAMLs gegen das installierte Content-Blocks-Schema geprüft.
- PHPStan: **0 Fehler** (`phpstan-final.txt`).
- CGL: **0 von 239 Dateien zu ändern**, PHP-Lint erfolgreich (`cgl-final.txt`). `git diff --check` sauber.
- Funktional final: **141 Tests, 3.639 Assertions, 0 Fehler/Failures**, **3 bestehende Skips** (`molecule.figure`, `molecule.formRenderer`, `molecule.pagination`) und eine bekannte PHPUnit-Runner-Deprecation des bestehenden XML-Attributs `cacheResult` (`functional-verified.txt`). Keine übersprungene Prüfung als bestanden gewertet.
- Gezielter neuer Funktionstest: **1 Test, 361 Assertions** erfolgreich; vorhandene Seed-Regressionsklasse **8 Tests, 111 Assertions** erfolgreich. Vorherige rote volle Läufe betrafen aktualisierte Seitenerwartungen; diese wurden korrigiert.
- Playwright: **12/12 erfolgreich** (`e2e-final.txt`), alle vier anonymen Sprachseiten auf Desktop/Mobil einschließlich 22 Collection-Block-Anzahlen, verschachtelte Tabellen, keine Editor-Custom-Elements/-Konfiguration, Navigation verborgen, Axe WCAG A/AA; EN/DE-Login mit Logo, GPL-Disclosure, Links und Axe im Credit-Bereich. Screenshots in `e2e-final/`.
- `lint-copy`: **0 Fehler, 9 weiche Warnungen** aus wiederverwendeten Fixtures (`lint-copy.txt`).
- Siteweiter, lesender Content-Audit: **0 Fehler, 562 Warnungen** über 130 Seiten (`content-audit.txt`); umfasst bestehenden Inhalt, ist kein seitengefilterter Audit.
- `ddev ... content-blocks:lint` gegen den unveränderten installierten Vendor wurde ausgeführt und schlägt dort erwartungsgemäß auf die alten camelCase-/File.required-Schlüssel an. **Nicht als erfolgreich ausgegeben.** Der Schema-Test des aktualisierten Clones ist grün; CLI-Lint nach regulärer Installation erneut ausführen.

WT hat kein `node_modules`, MAIN schon. Deshalb wurde die owned E2E-Datei unverändert nach `/tmp/ve-showcase-run/` kopiert, dort mit Symlink auf MAINs vorhandene Abhängigkeiten und äquivalenter Desktop-/Pixel-7-Konfiguration ausgeführt; keine neue Datei oder Abhängigkeit im MAIN-Lab angelegt. Befehl:

```sh
PLAYWRIGHT_BROWSERS_PATH=/Users/dirnbauer/projects/webconsulting-typo3-lab/var/playwright/browsers \
 /Users/dirnbauer/projects/webconsulting-typo3-lab/node_modules/.bin/playwright test \
 --config=/tmp/ve-showcase-run/playwright.config.mjs --output=/tmp/ve-showcase-evidence/e2e-final
```

## Integration durch den Parent

1. Desiderio-Commit prüfen/übernehmen; die sieben site_package-Dateien, E2E-Datei und Übergabe im WT in den Lab-Commit aufnehmen. Die beiden untracked Konzeptdokumente nicht versehentlich diesem Implementierungscommit zuschlagen.
2. VEE-Ergebnis separat integrieren. Desiderio setzt nur `tx_visualeditorenhancements.inlineRecords.enabled = 1`; keinerlei VEE-Interna vorausgesetzt.
3. Installierte Paketkopien auf die geprüften Commits aktualisieren/linken. Dabei die vorgeschriebene Single-Package-Composer-Prozedur lesen/beachten; bestehende Links erhalten, Mutagen synchronisieren, Autoload/PackageArtifact prüfen, OPCache und TYPO3-Systemcache erneuern. Der Worker hat das bewusst nicht vorweggenommen.
4. Content-Blocks-CLI-Lint, Schema/TCA und diese Seite im **regulären** Runtime erneut prüfen, insbesondere Tabs min 2, drei Preispläne min=max 3, Galerie-Bild min 1. Lokaler Seed ist bereits vorhanden; kein erneutes Ganzseiten- oder Ganzsite-Seeding notwendig. Ein gezieltes Reseed löscht die drei Übersetzungen; danach genau UID 8430 erneut übersetzen.
5. Tatsächliche VEE-Reise mit Admin und eingeschränktem Editor, Live und Workspace, Pending-Save-Sperre, Shared-/Nested-Isolation und erforderlichen Bildern durchführen. Diese privilegierten VEE-Integrationstests waren außerhalb dieser Worker-Ownership und sind hier **nicht** als bestanden behauptet. Workspace-Daten wurden vom Limit-Audit nicht als Live-Bestand gezählt.
6. Produktionsbackup, Zielsystem-Audit und dann ausschließlich die neue Seite säen/übersetzen. Ziel-UID aus der Seed-Ausgabe übernehmen; bei abweichenden verlinkten Seiten-UIDs die exakten gespeicherten Translation-Memory-Quellen prüfen. Kein globaler Seed/Translate als Abkürzung. Release/Push/Tags/Deployment bleiben beim Parent; keine Produktionslaufzeit zugesagt.
