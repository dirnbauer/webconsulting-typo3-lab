# Content-Elemente: Frontend-Prüfung vom 12. Oktober 2026

Alle **244 Desiderio-Content-Elemente** wurden anhand ihrer Konfiguration, Templates und Fixtures geprüft und auf Desktop sowie Mobil gerendert. 241 Typen wurden auf den vorhandenen TYPO3-Seiten geprüft; Hero Video, Testimonial Video und Video Embed wurden mit ihren Fixtures durch TYPO3/Fluid im Speicher gerendert, ohne Datenbankinhalte anzulegen. Die Renderprüfung umfasst 526 Elementdarstellungen; für jeden Typ gibt es Desktop- und Mobilbilder. Geprüft wurden die vorhandenen Beispielzustände, nicht jede mögliche Kombination aus Inhalt, Variante und Theme.

- [Vollständige Bildübersicht mit Filter](/Users/dirnbauer/projects/webconsulting-typo3-lab/var/qa/content-elements-20261012/review.html)
- [Nachweis je Content-Typ, URL und Screenshot](/Users/dirnbauer/projects/webconsulting-typo3-lab/var/qa/content-elements-20261012/coverage.json)
- [Strukturprüfung aller 244 Typen](/Users/dirnbauer/projects/webconsulting-typo3-lab/var/qa/content-elements-20261012/schema-audit.json)

## Umgesetzt

1. **Eyebrows:** dekorativen Punkt entfernt. Schrift, Schriftgröße, Gewicht, Laufweite und Farbe folgen den vorhandenen Theme-Tokens. Bestehende shadcn-Badges behalten ihre Gestaltung.
2. **Mobile Karten:** Feature Cards, Features, Blog Teasers, Product Grid, Pricing Usage und die Cards-Variante von Content Carousel bleiben vertikale Raster. Die zuvor erzwungenen horizontalen Streifen entfallen.
3. **Echte Slider:** Feature Carousel, Team Carousel und Gallery-Vorschaubilder erhalten vorhandene shadcn-Outline-Buttons. Die Pfeile erscheinen bei Überlauf; Tastatur, Endzustände, RTL, Größenwechsel und reduzierte Bewegung werden berücksichtigt.
4. **Listen:** Regeln aus 126 Element-Stylesheets in einer gemeinsamen CSS-Datei zusammengeführt. Punkte verwenden die Theme-Primärfarbe, Ziffern die Textfarbe. Marker beginnen an derselben Kante wie Absätze; verschachtelte Listen, mehrzeiliger Text und Rechts-nach-links-Ausrichtung bleiben unterstützt. Native `start`, `reversed` und `value` bleiben erhalten.
5. **Seitentemplate:** Tastaturfokus beim Öffnen des mobilen Menüs korrigiert; Escape schließt zuerst das Untermenü; Theme-Auswahl gibt den Fokus zurück. Aktive Footerlinks erhalten `aria-current`. Im älteren Styleguide-Template wurden ein verschachteltes `main` und fehlende Panel-Verweise korrigiert. Die shadcn-Gestaltung wurde dafür nicht verändert.

Beim **Accordion** wurde der Befund nach dem konkreten Seitenhinweis und Screenshot aufgelöst: „Questions before you install“ auf `/features/docx-editor/` zeigte eine zusätzliche graue FAQ-Hover-Fläche mit negativen Seitenabständen. Sie ragte links/rechts über die Trennlinie hinaus. Die eigene Flächengestaltung wurde durch die Hover-Unterstreichung der offiziellen shadcn-Accordion-Stile ersetzt; die negativen Seitenabstände entfallen. Theme-Fokusring und horizontale Trennlinien bleiben bestehen. Offen/geschlossen sowie Hover und Tastaturbedienung sind auf Desktop/Mobil geprüft.

## Zusätzliche Abstandsauffälligkeiten — nur dokumentiert

| Element / Ansicht | Befund | Vorschlag für eine spätere Änderung |
| --- | --- | --- |
| Feature Minimal, Mobil (390 px), UID 157863 | Etwa 96 px Abstand zwischen Einleitung und erstem Eintrag. Intro-Abstand, Rasterabstand und oberes Item-Padding addieren sich sichtbar. | Den Übergang aus einer einzigen Abstandsregel steuern. |
| Footer Mega, Desktop (1440 px), UID 158020 | Die Navigation endet ungefähr bei y=223, der Newsletter erst bei y=511. Unter den Navigationsspalten bleiben rund 288 px ungenutzt. Der lokale CAPTCHA-Testhinweis ist in der schmalen Newsletter-Spalte stark umgebrochen. | Newsletter-Anordnung und CAPTCHA-Umbruch getrennt prüfen; der Testhinweis ist umgebungsspezifisch. |

Diese Abstände wurden gemäß Auftrag **nicht verändert**.

![Feature Minimal: großer Abstand vor dem ersten Eintrag](/Users/dirnbauer/projects/webconsulting-typo3-lab/var/qa/content-elements-20261012/screenshots/mobile-featureminimal-157863.jpg)

![Footer Mega: leere Fläche unter den Navigationsspalten](/Users/dirnbauer/projects/webconsulting-typo3-lab/var/qa/content-elements-20261012/screenshots/desktop-footermega-158020.jpg)

## Weitere beobachtete Punkte

- Das Gallery-Beispiel UID 157838 hat ein defektes verarbeitetes Bild (`csm_backend-visual-editor-460d5038_4fba19042c.webp`). Das betrifft den Bildbestand; die Prüfung hat ihn nicht verändert.
- Die bislang nicht veröffentlichte Testimonial-Video-Fixture enthält keine Video-URL. Ihr Template gibt dennoch ein leeres `iframe` aus. Dieser zusätzliche Befund ist dokumentiert; das Fixture bzw. der Fallback wurde nicht verändert.
- Die neue gemeinsame ScrollControls-Komponente erhöht den technischen Bestand auf 63 Komponenten / 38 Moleküle. Vorhandene redaktionelle Angaben von 62 / 37 wurden nicht durch einen erneuten Seed überschrieben.

## Verifikation und Grenzen

- 865 PHP-Tests erfolgreich; 22 Browserprüfungen für Ausgabe, Seitenüberschriften und Library-Preview erfolgreich.
- Seitentemplate: 7 Browserprüfungen erfolgreich, 1 bewusst übersprungener Desktopfall für das mobile Menü.
- Responsive Interaktion: 15 Seitenzustände, 33 Rasterprüfungen und 12 Scrolltrack-Prüfungen erfolgreich; zusätzlich RTL und Fokus bei Größenwechsel geprüft.
- Keine JavaScript-Fehler im abschließenden Katalogdurchlauf; keine unbeabsichtigte horizontale Seitenverbreiterung. Die vier automatischen Überlaufkandidaten sind absichtlich außerhalb des Bildschirms liegende Newsletter-Spamfallen.
- CSS, Tailwind, Vite und Registry wurden gebaut; JavaScript-/PHP-Syntax und Git-Whitespace geprüft.
- [Barrierefreiheitsbericht zum Seitentemplate](/Users/dirnbauer/projects/webconsulting-typo3-lab/var/transient/page-shell-a11y-summary.md): fünf Seiten in zwei Viewports, keine automatischen axe-A/AA-Verstöße; 848 Textkontrastprüfungen ausgewertet. Das ist keine vollständige WCAG-Konformitätsbestätigung für sämtliche Inhalte/Themes. Video-Untertitelqualität und echte Screenreader-/Gerätekombinationen bleiben außerhalb dieser automatisierten Prüfung.

Die Gestaltung verwendet die vorhandenen shadcn-Tokens und -Komponenten. Als Referenz wurden die offiziellen [Accordion](https://ui.shadcn.com/docs/components/base/accordion)- und [Typography/Typeset](https://ui.shadcn.com/docs/typeset)-Dokumentationen geprüft.

## Veröffentlichung

Die Änderungen sind am 12. Oktober 2026 als **Desiderio 4.23.2** auf [typo3-lab.webconsulting.at](https://typo3-lab.webconsulting.at/) veröffentlicht. Der laufende Container verwendet Website-Commit `16780edb4cb311aeef3fc482b715bbb886d2cabe` und Paket-Commit `2e164a70c912a5d62a06b1c856d390a4dc8242ff`; der Healthcheck ist erfolgreich.

- [Paket-CI: erfolgreich](https://github.com/dirnbauer/desiderio/actions/runs/38182899497), einschließlich PHP 8.4/8.5, Unit-/Integrationstests, Theme-Kontrasten und generierten Assets. Der zuerst fehlende ScrollControls-Testdatensatz wurde in 4.23.2 ergänzt und erfolgreich geprüft.
- [Website-CI und Deployment: erfolgreich](https://github.com/dirnbauer/webconsulting-typo3-lab/actions/runs/38182955158).
- Live-Prüfung: **14/14** Desktop-/Mobil-Prüfgruppen bestanden, außerdem **15 Browser-Regressionstests** bestanden und ein nicht anwendbarer Desktop-Test für das mobile Menü übersprungen.
- Verifiziert wurden die Homepage, die DOCX-FAQ und vier Katalogseiten bei 1440 und 390 px; enthalten sind mobile Karten, drei Slider-Steuerungen, Listen in LTR/RTL, FAQ-Hover/Fokus und Seitennavigation. Die gesamte vorherige 244-Typen-Prüfung erfolgte lokal.
- [Live-Prüfnachweis](/Users/dirnbauer/projects/webconsulting-typo3-lab/var/qa/frontend-deploy-20261012/production-smoke-results.json), [mobile Architektur-Karten](/Users/dirnbauer/projects/webconsulting-typo3-lab/var/qa/frontend-deploy-20261012/architecture-390.png), [FAQ auf Desktop](/Users/dirnbauer/projects/webconsulting-typo3-lab/var/qa/frontend-deploy-20261012/faq-1440.png).

Der zusätzliche [CI-Katalogdurchlauf](https://github.com/dirnbauer/desiderio/actions/runs/38182899413) läuft separat; Strukturprüfung abgeschlossen, Abstands-/Renderprüfung beim Abschluss der Live-Prüfung noch in Arbeit.
