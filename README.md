# AFK Millstatt – Radenthein · Website

WordPress-Theme **AFK Nachtschicht** und Plugin **AFK Core** für
[afk-millstatt-radenthein.at](https://www.afk-millstatt-radenthein.at/).

| Ordner | Inhalt |
|---|---|
| `afk-core/` | Plugin: Feuerwehren, Felder, Aufmacher, Hinweisband, Sicherheitstipps, Alarmierungen, Datenschutz |
| `afk-nachtschicht/` | Theme (dunkel, Oswald + Source Sans 3 lokal) |

Installation und Bedienung: `afk-core/LIESMICH.md` und `afk-nachtschicht/LIESMICH.md`.

## Neue Version veröffentlichen

1. Version erhöhen – Plugin: Kopf von `afk-core/afk-core.php` **und** `AFK_CORE_VERSION`;
   Theme: Kopf von `afk-nachtschicht/style.css` **und** `AFK_NS_VERSION` in `functions.php`.
2. In der jeweiligen `LIESMICH.md` einen Abschnitt `## Neu in <Version>` ergänzen.
3. Auf `main` pushen. Die Action **Release** baut die ZIP und legt das Release
   `<slug>-<version>` an. Die Websites zeigen das Update danach unter Dashboard → Aktualisierungen.

Das Repository muss **öffentlich** sein, damit die Websites die Releases ohne Zugangsdaten abrufen können.
Es enthält keine Zugangsdaten oder personenbezogenen Daten – und soll das auch nie.
