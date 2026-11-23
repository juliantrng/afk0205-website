# AFK Core – Version 0.11.0

Plugin für die Website des Abschnittsfeuerwehrkommandos Millstatt – Radenthein.
Es legt die Datenstruktur an (Feuerwehren, Sicherheitstipps, Felder), steuert Aufmacher und
Hinweisband, holt die KLFV-Alarmierungen und kümmert sich um den Datenschutz bei Fotos und
eingebetteten Inhalten. Zusammen mit dem Theme **AFK Nachtschicht** gedacht, funktioniert über
Shortcodes aber auch mit anderen Themes.

## Neu in 0.11.0

- **Eigene Statistik** statt Matomo: Dashboard → Statistik mit Seitenaufrufen pro Tag (7/30/90 Tage, 12 Monate), meistgelesenen Berichten, Herkunft (Suchmaschinen, Facebook, Instagram, Telegram, WhatsApp, andere Websites, direkt), Geräten (Handy/Tablet/Computer) und Berichten nach Feuerwehr. Dazu ein Kasten auf dem Dashboard und die Spalte „Aufrufe (30 T.)“ in der Beitragsliste.
- Datensparsam: keine Cookies, keine IP-Adressen, kein externer Server – gespeichert werden nur Tagessummen. Angemeldete Redakteure und Bots zählen nicht. Funktioniert auch hinter Cloudflare/Seiten-Cache.

## Neu in 0.10.0

- **Unwetterwarnung im Hinweisband**: Warnungen der GeoSphere Austria (früher ZAMG) für Millstatt am See, Radenthein und Bad Kleinkirchheim erscheinen automatisch – Art, Zeitraum, betroffene Gemeinden und ein kurzer Rat, Farbe nach Warnstufe (Gelb/Orange/Rot). Endet die Warnung, verschwindet das Band.
- Hinweisband → Unwetterwarnung: ab welcher Stufe (Standard: Orange), wie viele Stunden vor Beginn, „Jetzt abrufen“, Übersicht der aktuellen Warnungen.
- Abruf alle 30 Minuten serverseitig, Besucher verbinden sich nicht mit der GeoSphere. Ein eigener Hinweis „Allgemeine Information“ wird während einer Warnung ersetzt, eigene Hinweise zu Waldbrand, Unwetter oder Sperre bleiben stehen.

## Neu in 0.9.0

- **Social Media** statt FS Poster: Beiträge einheitlich auf Telegram, Facebook und Instagram posten – mit eigener Bildvorlage (1080 × 1350, Foto + Titelkasten + Feuerwehren + Adressleiste) und Textvorlage (Art, Datum, Titel, Auszug, beteiligte Feuerwehren, Link, Hashtags).
- Im Beitragseditor rechts der Kasten „Social Media“: Kanäle wählen, eigenen Text schreiben, Vorschau, „Jetzt posten“ für bereits veröffentlichte Beiträge. Protokoll mit Links je Kanal; jeder Kanal wird je Beitrag nur einmal bedient.
- Gepostet wird nur unter der eingetragenen Live-Adresse – Staging-Kopien posten nie.
- Einstellungen → Social Media, Anleitung unten unter „Social Media einrichten“.

## Neu in 0.8.4

- Updates erscheinen auch dann, wenn ein anderes Plugin die Update-Liste beim Speichern verwirft.

## Neu in 0.8.3

- Updates erscheinen auch, wenn ein anderes Plugin oder der Hoster die Update-Liste vorab liefert.

## Neu in 0.8.2

- Behoben: Updates wurden nicht angezeigt, wenn ein anderes Plugin die Update-Liste von WordPress ohne Versionsangaben liefert. Die installierte Version wird jetzt direkt aus der Datei gelesen.

## Neu in 0.8.1

- Update-Suche mit drittem Weg: Sind GitHub-API und Release-Feed vom Server aus nicht erreichbar, wird die Versionsangabe direkt aus dem Repository gelesen.
- In der Plugin-Liste steht unter AFK Core, wann zuletzt nach Updates gesucht wurde und was dabei schiefging.

## Neu in 0.8.0

- Werkzeuge → **Beiträge abgleichen**: holt auf Knopfdruck neue und geänderte Beiträge der bisherigen Website auf die neue (z. B. Staging unter /neu/). Mit Liste zum Auswählen, Bilder werden in die eigene Mediathek übernommen (GPS wird dabei entfernt), Feuerwehr wird aus den Schlagwörtern „FF …“ erkannt.
- Optional mit Anwendungspasswort der bisherigen Website – dann kommen die Beiträge als bearbeitbare Blöcke samt Auszug.
- Gesperrt, solange FS Poster oder MailPoet aktiv sind (sonst doppelte Social-Media-Posts bzw. Newsletter). Pingbacks werden beim Übernehmen nicht ausgelöst.
- Ohne eingetragene Quelle tut das Werkzeug nichts.

## Neu in 0.7.1

- Update-Anzeige robuster: Das Update erscheint auch dann, wenn ein anderes Plugin die Update-Liste von WordPress ersetzt.

## Neu in 0.7.0

- **Termine** als eigenes Werkzeug statt Kalender-Plugin: Menü „Termine“ mit Beginn, Ende, ganztägig, Ort, Art, Link und veranstaltender Feuerwehr.
- Jeder Termin als Kalenderdatei (.ics), alle Termine als Kalender-Abo (`/?afk_ics=alle`).
- Werkzeuge → **Termine übernehmen**: übernimmt die Termine aus Modern Events Calendar (Titel, Text, Bild, Zeiten, Ort, Link).
- Shortcode `[afk_termine anzahl="5" feuerwehr="ff-millstatt"]`.

## Neu in 0.6.4

- Behoben: Direkt nach einem Update konnte „Aktualisiere auf“ dieselbe Version erneut anzeigen.

## Neu in 0.6.3

- Update-Prüfung zuverlässiger: Ist die GitHub-Schnittstelle ausgelastet (bei Webhosting mit geteilter Server-Adresse häufig), werden neue Versionen über den Release-Feed erkannt.

## Neu in 0.6.2

- Betriebsfeuerwehr: In den KLFV-Alarmierungen heißt sie „BtF Veitsch-Radex“ – das ist jetzt als „Name in den Alarmierungen“ hinterlegt (nur wenn das Feld noch leer ist). Auf der Website bleibt der Name „BtF RHI Magnesita“.
- Erkennung genauer: abweichende Schreibweisen zählen nur im Bezirk Spittal, genau gleiche Namen überall.

## Neu in 0.6.1

- Alarmierungen werden standardmäßig **stündlich** abgerufen; unter Einstellungen → Alarmierungen wählbar (1 Stunde, 30 oder 10 Minuten).

## Neu in 0.6.0

- **Alarmierungen** lesen jetzt direkt die öffentliche Seite des KLFV (Quelle ist voreingestellt), nur Alarmierungen mit Beteiligung einer Feuerwehr des Abschnitts.
- Abruf alle 10 statt 5 Minuten, Koordinaten werden nicht gespeichert.
- Quellenangabe auch unter dem Shortcode `[afk_alarmierungen]`.

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
| Termine | Menü „Termine“ – Übersicht auf der Seite mit der Vorlage **Termine** |

## Alarmierungen (KLFV)

Der Server der Website liest jede Stunde (einstellbar: 30 oder 10 Minuten) die öffentliche Seite
[alarmierungen.feuerwehr-ktn.at](https://alarmierungen.feuerwehr-ktn.at/), behält **nur Alarmierungen,
bei denen eine Feuerwehr des Abschnitts dabei war**, und speichert davon nur Zeit, Ereignis,
alarmierte Einheiten, Status und Bezirk – keine Koordinaten (max. 200 Einträge, älter als die
eingestellten Tage werden verworfen). Besucher verbinden sich **nicht** mit dem KLFV.
Überall, wo Alarmierungen erscheinen, steht die Quellenangabe (KLFV, mit Link).

Einrichten:
1. Einstellungen → Alarmierungen: „Anzeigen“ anhaken, Tage (Standard 7) wählen, speichern,
   **Jetzt abrufen** – die Tabelle zeigt, was erkannt wurde. Die Quelle ist bereits eingetragen.
2. Heißt eine Wehr in den Alarmierungen anders (z. B. die Betriebsfeuerwehr), beim Profil der Wehr
   im Feld **Name in den Alarmierungen (KLFV)** die Schreibweise der KLFV-Seite eintragen.
   „FF St.Peter ob Radenthein“ wird erkannt, „FF St.Peter/Spittal“ zählt nicht dazu. Für die
   Betriebsfeuerwehr ist „BtF Veitsch-Radex“ bereits eingetragen.
3. Seite „Alarmierungen“ anlegen, Vorlage **Alarmierungen** wählen und ins Menü aufnehmen.

Der KLFV erlaubt die Anzeige mit Quellenangabe. Ändert der KLFV seine Seite, steht unter
Einstellungen → Alarmierungen „Letzter Fehler: Datenblock nicht gefunden“ – dann melden.

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

Beim Deaktivieren gehen keine Daten verloren; der regelmäßige Abruf wird beendet.

## Social Media einrichten

Erst nach der Umstellung auf der echten Website einrichten und dann **FS Poster deaktivieren**, sonst wird doppelt gepostet.

**Allgemein:** Einstellungen → Social Media → Live-Adresse `https://www.afk-millstatt-radenthein.at` eintragen.

**Telegram**
1. In Telegram @BotFather öffnen, `/newbot`, Namen vergeben (z. B. „AFK Millstatt-Radenthein“). Der BotFather schickt den Bot-Token.
2. Im Kanal: Administratoren → Bot hinzufügen, Recht „Nachrichten senden“.
3. Token und Kanal (@name) eintragen, „Verbindung prüfen“.

**Facebook und Instagram**
1. Instagram muss ein professionelles Konto sein (Business oder Creator) und mit der Facebook-Seite verknüpft (Facebook-Seite → Einstellungen → Verknüpfte Konten).
2. Auf developers.facebook.com mit deinem Facebook-Konto eine App anlegen (Typ „Business“). Die App bleibt im Entwicklungsmodus – für eigene Seiten reicht das, solange du Admin der App und der Seite bist.
3. Im **Graph API Explorer** die App wählen, „User Token“ mit diesen Berechtigungen erzeugen: `pages_show_list`, `pages_read_engagement`, `pages_manage_posts`, `instagram_basic`, `instagram_content_publish`, `business_management`.
4. Im **Access Token Debugger** den Token prüfen und „Extend Access Token“ (langlebiger Token).
5. Mit dem langlebigen Token im Explorer `me/accounts` abfragen: Dort stehen ID und `access_token` der Seite. Dieser Seiten-Token läuft nicht ab.
6. Seiten-ID und Seiten-Token eintragen, „Verbindung prüfen“ – die Instagram-Konto-ID wird dabei automatisch eingetragen.

Tokens werden in der Datenbank gespeichert und nirgends angezeigt. Widerrufen: Facebook → Einstellungen → Business-Integrationen bzw. im BotFather `/revoke`.

