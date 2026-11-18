# AFK Core – Version 0.5.1

Plugin für die Website des Abschnittsfeuerwehrkommandos Millstatt – Radenthein.
Es legt die Datenstruktur an (Feuerwehren, Sicherheitstipps, Felder), steuert Aufmacher und
Hinweisband, holt die KLFV-Alarmierungen und kümmert sich um den Datenschutz bei Fotos und
eingebetteten Inhalten. Zusammen mit dem Theme **AFK Nachtschicht** gedacht, funktioniert über
Shortcodes aber auch mit anderen Themes.

## Neu in 0.5.1

- Betriebsfeuerwehr heißt jetzt **BtF RHI Magnesita** (bestehende Einträge werden automatisch umbenannt, sofern der Name nicht händisch geändert wurde).

## Neu in 0.5.0

- **Automatische Updates**: neue Versionen erscheinen im Dashboard unter Aktualisierungen wie bei jedem anderen Plugin („Jetzt aktualisieren“).
- Hinweisband: Link bricht nicht mehr mitten im Text um, am Handy steht er in einer eigenen Zeile.

## Neu in 0.4.0

- **Alarmierungen** vom Kärntner Landesfeuerwehrverband (Einstellungen → Alarmierungen)
- **Datenschutz**: Fotos werden beim Hochladen von GPS-/Kameradaten befreit, YouTube, Facebook,
  Google Maps & Co. laden erst nach Klick, Kommentare und Emoji-Abruf aus
- Werkzeuge → **Fotos bereinigen** für alle bisher hochgeladenen Bilder
- Neue Felder bei der Feuerwehr: **Gegründet**, **Name in den Alarmierungen (KLFV)**

## Voraussetzungen

- WordPress ab 6.3, PHP ab 7.4
- **Advanced Custom Fields** – die kostenlose Version reicht.

## Installation / Update (zuerst auf Staging!)

1. Plugins → Installieren → Plugin hochladen → `afk-core.zip` → bei „bereits installiert“
   **Aktuelle Version durch hochgeladene ersetzen** → Aktivieren.
2. Einstellungen → Permalinks einmal speichern.
3. Werkzeuge → **Fotos bereinigen** → so oft „Nächste 25 Fotos bereinigen“ klicken, bis „Noch zu prüfen: 0 Fotos“ steht.
   (Eine Sicherung vorher ist Pflicht – die Originaldateien werden überschrieben.
   Farbprofile bleiben erhalten, nur Standort, Kameramodell usw. werden entfernt.)

## Was wo gepflegt wird

| Was | Wo |
|---|---|
| Beteiligte Wehren eines Berichts | Beitrag bearbeiten → rechts „Feuerwehren“ anhaken (nur Wehren, nicht die Gemeinde) |
| Verfasser, Bildquelle, weitere Kräfte | Beitrag bearbeiten → „Bericht: Verfasser & Bildquelle“ |
| Bericht oben auf der Startseite | Häkchen „Oben auf der Startseite“ – gilt 14 Tage, sonst der neueste Bericht |
| Profil einer Wehr | Beiträge → Feuerwehren → Wehr bearbeiten |
| Hinweisband (Waldbrand, Unwetter, Sperre) | Menü „Hinweisband“ – für alle Redakteure |
| Sicherheitstipps + Monate „Jetzt aktuell“ | Menü „Sicherheitstipps“ |
| Alarmierungen | Einstellungen → Alarmierungen (nur Administratoren) |

## Alarmierungen (KLFV)

Der Server der Website holt alle 5 Minuten die Liste von alarmierungen.feuerwehr-ktn.at, behält
nur Alarmierungen mit Beteiligung einer Wehr aus dem Abschnitt und speichert davon nur
Zeit, Ereignis, Einheiten, Status und Bezirk (max. 200 Einträge, älter als die eingestellten Tage
werden nicht angezeigt). Besucher verbinden sich **nicht** mit dem KLFV.

Einrichten:
1. Einstellungen → Alarmierungen: **Quelle** = Datenadresse der KLFV-Seite eintragen, „Anzeigen“
   anhaken, Tage (Standard 7) wählen, speichern, **Jetzt abrufen** – die Tabelle zeigt, was erkannt wurde.
2. Heißt eine Wehr in der Alarmierung anders (z. B. „FF Döbriach-Seeboden“), beim Profil der Wehr
   im Feld **Name in den Alarmierungen (KLFV)** eintragen.
3. Seite „Alarmierungen“ anlegen und rechts die Vorlage **Alarmierungen** wählen.

Vor dem Livegang den KLFV um Zustimmung zur Weiterverwendung bitten.
Das genaue Datenformat ist noch nicht bestätigt; der Abruf erkennt übliche Feldnamen automatisch.
Passt etwas nicht, lässt sich die Zuordnung per Filter `afk_alarm_normalisieren` anpassen.

## Datenschutz-Funktionen

| Funktion | Abschalten (Code-Snippet) |
|---|---|
| GPS-/Kameradaten beim Upload entfernen | `add_filter( 'afk_ds_fotos_bereinigen', '__return_false' );` |
| Eingebettete Inhalte erst nach Klick | `add_filter( 'afk_ds_zwei_klick', '__return_false' );` |
| Kommentare & Pingbacks aus | `add_filter( 'afk_ds_kommentare_aus', '__return_false' );` |
| Emoji-Skript (lädt von wordpress.org) aus | `add_filter( 'afk_ds_emojis_aus', '__return_false' );` |

Der Platzhalter verlinkt die Datenschutzerklärung (Einstellungen → Datenschutz → Seite festlegen).

## Shortcodes (für andere Themes oder einzelne Seiten)

| Shortcode | Zeigt |
|---|---|
| `[afk_aufmacher]` | Aufmacher-Bericht |
| `[afk_alarmierungen anzahl="5" feuerwehr="ff-millstatt"]` | Liste der Alarmierungen |
| `[afk_hinweis]` | Hinweisband an bestimmter Stelle |
| `[afk_byline]`, `[afk_beteiligte_feuerwehren]`, `[afk_bildquelle]` | Angaben im Bericht |
| `[afk_feuerwehr_profil slug="ff-kaning"]`, `[afk_feuerwehren_liste]`, `[afk_tipps anzahl="4"]` | Feuerwehren, Tipps |

## URLs

- Feuerwehr: `/feuerwehr/ff-millstatt/` · Gemeinde: `/feuerwehr/gemeinde-millstatt/`
- Sicherheitstipps: `/sicherheit/`

## Updates

Plugin und Theme holen ihre Updates aus GitHub (öffentliches Repository `juliantrng/afk0205-website`).
WordPress prüft alle paar Stunden; sofort geht es unter Dashboard → Aktualisierungen → **Erneut prüfen**.
Einstellungen und Inhalte bleiben bei Updates erhalten – sie liegen in der Datenbank.

- Anderes Repository: in `wp-config.php` `define( 'AFK_UPDATE_REPO', 'besitzer/repository' );`
- Updates abschalten: `define( 'AFK_UPDATE_REPO', '' );`

Beim Deaktivieren gehen keine Daten verloren; der 5-Minuten-Abruf wird beendet.
