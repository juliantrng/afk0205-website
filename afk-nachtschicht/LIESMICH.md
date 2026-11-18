# AFK Nachtschicht – Theme 1.2.4

Dunkles Theme für die Website des Abschnittsfeuerwehrkommandos Millstatt – Radenthein
(Designrichtung „A – Nachtschicht“). Arbeitet mit dem Plugin **AFK Core** zusammen.
Kein Elementor, kein Filter-Plugin nötig. Schriften (Oswald, Source Sans 3) sind lokal
eingebunden – keine Verbindung zu Google.

## Installation (zuerst auf Staging!)

1. **AFK Core** (ab 0.4.0) und **Advanced Custom Fields** müssen aktiv sein.
2. Design → Themes → **Installieren** → **Theme hochladen** → `afk-nachtschicht.zip` → Installieren → **Aktivieren**.
3. Einstellungen → **Permalinks** einmal speichern.
4. Werkzeuge → (Plugin) **Regenerate Thumbnails** einmal laufen lassen, damit für alte Beiträge
   die neuen Bildgrößen erzeugt werden (große Titelbilder, Kacheln). Ohne das funktioniert es auch,
   die Seiten laden dann aber größere Bilder.

Das bisherige Theme (Hestia) bleibt installiert – zurückschalten geht jederzeit unter Design → Themes.

## Neu in 1.2.4

- Rote Leiste bei mittlerer Breite (Tablet, schmales Fenster): Alarmierungen in voller Breite unter der Überschrift, nichts mehr abgeschnitten.

## Neu in 1.2.3

- Behoben: Direkt nach einem Update konnte „Aktualisiere auf“ dieselbe Version erneut anzeigen.

## Neu in 1.2.2

- Update-Prüfung zuverlässiger (Ausweichweg über den Release-Feed von GitHub).

## Neu in 1.2.1

- Rote Leiste: Quellenangabe „Quelle: KLFV“ mit Link neben „Alle Alarmierungen“.

## Neu in 1.2.0

- **Automatische Updates**: neue Versionen erscheinen im Dashboard unter Aktualisierungen („Jetzt aktualisieren“). Details im LIESMICH von AFK Core.

## Neu in 1.1.4

- Handy: Kategorie-Filter als 2×2-Raster statt seitlich scrollen.

## Neu in 1.1.3

- Logo in der Kopfzeile größer (PC 68 px, Handy 44–52 px hoch).
- Rote Leiste: Uhrzeit, Ereignis und Feuerwehren je in eigener Zeile, Trennstriche zwischen den Alarmierungen – keine abgeschnittenen Wörter mehr.

## Neu in 1.1.2

- Startseite: Hat der neueste Bericht kein Foto, bekommt der neueste Bericht mit Foto die große Kachel.
- Kennzahlen: Solange keine Mitgliederzahlen eingetragen sind, zeigt die dritte Zahl die Berichte des laufenden Jahres.

## Neu in 1.1.0

- Einheitliche Abstände auf allen Seiten, überarbeitete Handy-Ansicht (Kennzahlen nebeneinander,
  Feuerwehren zweispaltig, Kategorie-Filter seitlich wischbar, Notruf-Knopf zeigt immer „122“)
- Rote Leiste zeigt die **KLFV-Alarmierungen** (wenn in AFK Core eingerichtet), sonst die letzten Einsatzberichte
- Feuerwehr-Seite: „Letzte Alarmierungen“ und Gründungsjahr
- Neue Seitenvorlagen **Feuerwehren-Übersicht** und **Alarmierungen**
- Neu gestaltet: Übersichten, Seiten, Suche (findet jetzt auch Seiten und Tipps), 404
- Teilen-Knopf (Handy: Teilen-Dialog, PC: Link kopieren) und WhatsApp-Link, ohne Fremd-Skripte
- Vorschaubild/-text beim Teilen auf Facebook & WhatsApp (entfällt automatisch, wenn Yoast,
  Rank Math, SEOPress oder AIOSEO aktiv ist)
- Druckansicht für Berichte, dunkle Farben für The Events Calendar

## Seiten anlegen

| Seite | Vorlage (rechts im Editor unter „Vorlage“) |
|---|---|
| Feuerwehren | **Feuerwehren-Übersicht** – Text im Editor erscheint oberhalb der Karten |
| Alarmierungen | **Alarmierungen** – braucht AFK Core → Einstellungen → Alarmierungen |
| Mitglied werden | Standard; Adresse im Customizer bei „Link Werde Teil davon“ eintragen |

Sobald die Seite „Feuerwehren“ existiert, zeigen Menü, Startseite und Mitmachen-Knopf automatisch dorthin.

## Einrichten

**Menüs** (Design → Menüs)
- Neues Menü anlegen, Position **Hauptmenü**. Vorschlag: Aktuelles (Link `/#aktuelles`),
  Einsätze (Kategorie), Feuerwehren (Seite „Feuerwehren“), Alarmierungen, Termine, Mitmachen (`/#mitmachen`).
  Untermenüs werden nicht angezeigt – das Menü bleibt bewusst flach.
- Zweites Menü für die Position **Fußzeile**: Impressum, Datenschutz, Kontakt, Intern, Telegram.
- Solange kein Menü zugewiesen ist, zeigt das Theme automatisch sinnvolle Einträge.

**AFK Einstellungen** (Design → Customizer → „AFK Einstellungen“)
- Mitglieder im Abschnitt (Kennzahl auf der Startseite) – leer = Summe aus den Feuerwehr-Profilen;
  ist auch die leer, wird die Kennzahl ausgeblendet.
- Link „Werde Teil davon“, Telegram-Kanal, Kontakt-E-Mail.
- Vorschaubild beim Teilen (für Seiten ohne eigenes Bild, Querformat ca. 1200 × 630).
- Kategorie-Slugs: voreingestellt `einsaetze`, `uebungen`, `allgemein`, Übersicht `news`
  (so heißen sie auf der aktuellen Website). Nur ändern, wenn sich das ändert.
- Bereich „Sicher durchs Jahr“ ein/aus.

**Logo**: Das weiße AFK-Logo ist eingebaut. Ein anderes kann unter Customizer → Website-Informationen
→ Logo hochgeladen werden (für den dunklen Hintergrund die weiße Version verwenden).

## Was wo erscheint

| Bereich | Woher |
|---|---|
| Hinweisband ganz oben | Menü „Hinweisband“ (AFK Core) |
| Großer Bericht oben (Aufmacher) | Häkchen „Oben auf der Startseite“ beim Bericht, sonst der neueste |
| Rote Leiste | die 3 letzten KLFV-Alarmierungen (AFK Core), sonst die 3 neuesten Einsatzberichte |
| Kennzahlen | Anzahl Feuerwehren/Gemeinden aus der Taxonomie, Mitglieder aus dem Customizer |
| Kacheln „Aus dem Abschnitt“ | die 9 neuesten Beiträge (ohne Aufmacher), Filter nach Kategorie, Feuerwehr, Jahr |
| 12 Feuerwehren | Taxonomie „Feuerwehr“ (AFK Core) |
| Termine | nur wenn das Plugin **The Events Calendar** aktiv ist und es kommende Termine gibt |
| Sicher durchs Jahr | Sicherheitstipps (AFK Core), aktuelle zuerst |
| Bericht: Titelbild | Beitragsbild; Anrisstext = Textauszug des Beitrags |
| Bericht: Bericht/Fotos | Felder „Bericht verfasst von“ und „Bildquelle“ (sonst WordPress-Autor) |
| Bericht: beteiligte Feuerwehren | angehakte Feuerwehren + Feld „Weitere beteiligte Kräfte“ |
| Bericht: „Mehr von der FF …“ | die Wehr aus der Bildquelle bzw. Funktion des Verfassers, sonst die erste |
| Feuerwehr-Seite | Profilfelder der Wehr (Beiträge → Feuerwehren → bearbeiten) + alle ihre Berichte |

**Bilder im Bericht:** Galerien aus dem Editor (Block „Galerie“ oder alter `[gallery]`-Shortcode)
werden automatisch als 3er-Raster dargestellt (am Handy 2er).

## Filter-Adressen

Die Filter funktionieren auch als Link, z. B. zum Teilen:
- `/?kat=einsatz&ff=ff-millstatt&jahr=2026#aktuelles` (Startseite)
- `/category/news/?ff=ff-kaning` (Übersicht)
- `/feuerwehr/ff-millstatt/?kat=uebung` (Feuerwehr-Seite)

`kat` = `einsatz`, `uebung` oder `allgemein`; `ff` = Slug der Feuerwehr; `jahr` = Jahreszahl.

## Dateien

- `style.css` – gesamtes Aussehen, Farben oben als Variablen (`--c-red`, `--c-yellow` …)
- `front-page.php` Startseite · `single.php` Bericht · `taxonomy-feuerwehr.php` Feuerwehr-Seite
- `archive.php` Übersichten · `page.php` Seiten · `search.php` Suche · `404.php`
- `page-feuerwehren.php`, `page-alarmierungen.php` Seitenvorlagen
- `inc/` Hilfsfunktionen, Customizer, Filter-Logik · `assets/` Schriften, Logo, Skript

Schriften: Oswald und Source Sans 3, SIL Open Font License (Lizenztexte in `assets/fonts/`).
