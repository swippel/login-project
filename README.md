# Secure Login

Ein vollständiges Anmeldesystem mit **Registrierung**, **Login**, **Bestätigungscode per E-Mail**,
**Zwei-Faktor-Authentifizierung** und **Passwort-Zurücksetzen** – geschrieben in reinem PHP 8.2
mit MariaDB und ausgeliefert über Docker Compose.

Der Fokus liegt auf Sicherheit: Passwörter werden nie im Klartext gespeichert, jedes Formular ist
gegen Cross-Site-Request-Forgery geschützt, und wiederholte Anmeldeversuche werden begrenzt.

<!-- Inhaltsverzeichnis -->

- [Funktionsumfang](#funktionsumfang)
- [Schnellstart mit Docker](#schnellstart-mit-docker)
- [Installation ohne Docker](#installation-ohne-docker)
- [Konfiguration](#konfiguration)
- [Projektstruktur](#projektstruktur)
- [Sicherheitskonzept](#sicherheitskonzept)
- [Die drei Anmeldevarianten](#die-drei-anmeldevarianten)
- [Tests](#tests)
- [Lizenz](#lizenz)

## Funktionsumfang

| Funktion | Beschreibung |
|---|---|
| Registrierung | E-Mail und Passwort ab 12 Zeichen, Dubletten werden abgefangen |
| Login | Passwortprüfung, Versuchsbegrenzung |
| Bestätigung per E-Mail | Zusätzlicher sechsstelliger Code aus dem Postfach bei der Anmeldung |
| Zwei-Faktor-Authentifizierung | Einmalcode aus einer Authenticator-App, Replay-Schutz |
| Passwort vergessen | Einmal-Link per E-Mail, 30 Minuten gültig |
| Passwort ändern | Neues Passwort setzen in einer Transaktion mit gesperrter Zeile |
| Konto-Seite | E-Mail-Bestätigung und 2FA ein- und ausschalten |
| Abmelden | Sitzung wird vollständig zerstört, Sitzungs-Cookie gelöscht |

## Schnellstart mit Docker

Voraussetzung: Docker mit Compose-Plug-in. PHP, MariaDB und Webserver bringt das Projekt selbst mit.

```bash
# 1. Konfiguration anlegen und Geheimnisse erzeugen
cp .env.example .env
php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'   # -> APP_KEY in .env eintragen

# 2. Stack starten
docker compose up -d --build

# 3. Öffnen
open http://127.0.0.1:8081
```

Beim ersten Start legt der Datenbank-Container das Schema automatisch an. Ein Konto lässt sich
danach über **„Konto erstellen"** anlegen.

**Weitere Befehle:**

```bash
docker compose logs -f app      # Protokoll der Anwendung
docker compose down             # Stack beenden
docker compose down -v          # Stack beenden und Datenbank löschen
```

> **Änderungen am Code:** Das Image enthält eine Kopie des Quellcodes. Nach Änderungen genügt
> `docker compose up -d --build`, um das Image neu zu bauen.

## Installation ohne Docker

Voraussetzungen: PHP 8.2 oder neuer mit den Erweiterungen `pdo_mysql`, `sodium`, `mbstring`
und `json` sowie eine MariaDB-Datenbank.

```bash
# 1. Abhaengigkeiten installieren
composer install

# 2. Konfiguration anlegen
cp .env.example .env
php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'   # -> APP_KEY in .env eintragen

# 3. Datenbank und Tabellen anlegen
mariadb -u root < database/schema.sql

# 4. Entwicklungsserver starten
php -S 127.0.0.1:8081 -t public
```

Danach ist das Projekt unter <http://127.0.0.1:8081> erreichbar.

## Konfiguration

Die Datei `.env` enthält alle Einstellungen. Sie steht in der `.gitignore` und gehört nie in die
Versionsverwaltung. Die Vorlage `.env.example` listet alle verfügbaren Werte mit Erklärung auf.

| Variable | Bedeutung |
|---|---|
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` | Zugang zur Datenbank |
| `APP_URL` | Adresse unter der das Projekt erreichbar ist; wird für Reset-Links verwendet |
| `SMTP_HOST`, `SMTP_PORT`, `SMTP_SECURE` | SMTP-Server (Standard `smtp.resend.com`, Port `587`, `starttls`; alternativ `ssl` für Port 465) |
| `SMTP_USER`, `SMTP_PASSWORD` | Zugangsdaten; bei Resend `resend` und der API-Key |
| `SMTP_FROM` | Absenderadresse, leer = `SMTP_USER` |
| `APP_KEY` | 32 zufällige Bytes, Base64-kodiert; schützt die 2FA-Geheimnisse |
| `WEB_PORT` | Port des Webservers im Container |
| `APP_DB_USER`, `APP_DB_PASSWORD` | Zugangsdaten der Datenbank **im Container** |

> **Wichtig:** Der `APP_KEY` darf nach dem ersten Start nicht mehr geändert werden. Sonst lassen
> sich die gespeicherten 2FA-Geheimnisse nicht mehr entschlüsseln.

### E-Mail-Versand einrichten (Resend)

1. Bei [resend.com](https://resend.com) einen API-Key erzeugen (beginnt mit `re_`) und die Absender-Domain verifizieren.
2. In der `.env` eintragen: `SMTP_PASSWORD=<API-Key>` und `SMTP_FROM=<Adresse der verifizierten Domain>`.
   `SMTP_USER` bleibt `resend`, `SMTP_HOST` ist `smtp.resend.com`. Jeder andere SMTP-Server (z. B. Gmail) geht genauso.
3. Container neu erstellen: `docker compose up -d --force-recreate app`.

Ohne `SMTP_PASSWORD` läuft der **Entwicklungsmodus**: Es wird keine Mail verschickt,
stattdessen erscheinen Code oder Reset-Link in der Seite und im Log (`docker compose logs -f app`).

## Projektstruktur

```
secure-login/
├── docker-compose.yml      Dienste: app (Apache + PHP), db (MariaDB)
├── Dockerfile              PHP-Image mit Apache
├── database/schema.sql     Datenbankschema (wird beim ersten Start eingelesen)
├── public/                 Dokument-Root, ein Controller je Seite
│   └── assets/             Stylesheet, Skript, QR-Code-Bibliothek
├── src/                    Anwendungslogik
│   ├── Application.php     baut alle Bausteine zusammen
│   ├── bootstrap.php       Start für jeden Seitenaufruf
│   ├── Web.php             Eingaben, Sitzung, CSRF, Weiterleitung, Templates
│   ├── Auth.php            Anmeldezustand und mehrstufiger Login
│   ├── EmailCodes.php      Codes per E-Mail (Anmeldung, Adresse bestätigen)
│   ├── PasswordReset.php   Passwort zurücksetzen
│   ├── TwoFactor.php       Authenticator-App einrichten und prüfen
│   ├── Totp.php            Einmalcodes nach RFC 6238
│   ├── Security.php        Passwörter, Verschlüsselung, Eingabeprüfung
│   ├── RateLimit.php       Versuchsbegrenzung
│   ├── Users.php           Tabelle users
│   ├── Mailer.php          E-Mail-Versand über SMTP
│   ├── Config.php          .env und Umgebungsvariablen
│   └── Database.php        PDO mit Prepared Statements
├── templates/              HTML-Seiten als PHP-Dateien, E-Mail-Vorlagen in emails/
└── tests/run.php           Tests ohne Datenbank
```

**Die Trennung ist strikt:**

- **PHP** enthält ausschließlich Logik. Jede Datei hat genau eine Aufgabe.
- **HTML** liegt in `templates/`. Dort steht nur HTML mit `<?= $e($wert) ?>` für Ausgaben und einfachen `if`-Blöcken.
- **CSS** liegt in einer einzigen Datei unter `public/assets/style.css`.
- **Nur `public/`** ist über den Web erreichbar. Konfiguration (`src/`, `.env`) sind geschützt.

## Sicherheitskonzept

| Angriffsart | Gegenmaßnahme |
|---|---|
| SQL-Injection | Ausschließlich Prepared Statements, `ATTR_EMULATE_PREPARES => false` |
| Cross-Site-Scripting | Jede Ausgabe in den Templates läuft durch `$e()` (`htmlspecialchars`) |
| CSRF | Verstecktes Token (32 Zufallsbytes) in jedem Formular, zeitkonstant verglichen |
| Brute-Force | Versuchsbegrenzung in der Datenbank: 20 Logins / 15 Min., 10 Authenticator-Codes / 15 Min., 5 Reset-Anfragen / Std. |
| Passwort im Klartext | bcrypt (`password_hash`) mit Salt im Hash |
| Schwache Passwörter | Mindestlänge 12 Zeichen, zusätzliche Prüfung in der Registrierung |
| Session-Hijacking | `httponly`, `samesite=Lax`, `secure` bei HTTPS, neue Sitzungs-ID nach Login |
| Gestohlene Sitzungen | `auth_state` bindet die Sitzung an Passwort und 2FA-Geheimnis |
| Wiederverwendeter Einmalcode | `last_used_step` verhindert die doppelte Verwendung |
| Geklautes 2FA-Geheimnis | Verschlüsselt mit libsodium `secretbox` |
| Geklauter Reset-Link | Token nur als SHA-256-Hash gespeichert, 30 Minuten gültig |
| Doppelte Token-Einlösung | Transaktion mit `SELECT … FOR UPDATE` |
| Auskunft über Konten | „Passwort vergessen" verrät nie, ob eine Adresse existiert |
| Angriffe über den Browser | `Content-Security-Policy`, `nosniff`, `no-referrer`, `X-Frame-Options` |

### Passwort-Hashing

Passwörter werden mit bcrypt (`password_hash`) gehasht. Salt und Kostenfaktor stehen im Hash, ein Klartext-Passwort wird nie gespeichert.

### Zwei-Faktor-Authentifizierung

Das Geheimnis wird aus 20 Zufallsbytes erzeugt und mit libsodium verschlüsselt in der Datenbank
abgelegt. Der Einmalcode wird nach RFC 6238 berechnet (HMAC-SHA1, 30-Sekunden-Schritt, sechs
Stellen). Geprüft werden der aktuelle sowie der vorherige und nächste Schritt, um abweichende
Handyuhren auszugleichen. Das Feld `last_used_step` verhindert, dass derselbe Code zweimal gilt.

## Die drei Anmeldevarianten

Die Aufgabenstellung nennt drei aufeinander aufbauende Lösungen. Alle drei sind umgesetzt und
lassen sich kombinieren:

1. **E-Mail + Passwort** – der Standardpfad.
2. **E-Mail + Passwort + Bestätigungscode aus dem Postfach** – wird verlangt, wenn die Option
   „Bestaetigung per E-Mail" auf der Konto-Seite aktiviert ist. Der Code wird per E-Mail
   versendet, gilt 10 Minuten und darf fünfmal falsch eingegeben werden.
3. **E-Mail + Passwort + Einmalcode der Authenticator-App** – wird verlangt, sobald die 2FA auf der
   Konto-Seite eingerichtet ist.

Die Varianten 2 und 3 lassen sich gleichzeitig einschalten. Wird die Variante 2 zum ersten Mal
aktiviert, wird die Adresse vorher durch einen Code bestätigt – so kann niemand eine fremde
Adresse hinterlegen.

## Tests

```bash
composer test    # Einmalcodes (RFC-6238-Testvektor), Passwörter, Verschlüsselung, Eingabeprüfung
composer lint    # Syntaxprüfung aller PHP-Dateien
```

Die Tests brauchen weder Datenbank noch Docker. Den ganzen Ablauf prüft man im laufenden Stack im Browser.

## Lizenz

MIT – siehe [LICENSE](LICENSE).

Die eingebundene Bibliothek [QRCode.js](https://github.com/davidshimjs/qrcodejs) steht unter der
MIT-Lizenz, siehe `public/assets/qrcode.LICENSE`.
