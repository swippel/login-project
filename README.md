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
- [Kommandozeile](#kommandozeile)
- [Tests](#tests)
- [Lizenz](#lizenz)

## Funktionsumfang

| Funktion | Beschreibung |
|---|---|
| Registrierung | E-Mail und Passwort ab 12 Zeichen, Dubletten werden abgefangen |
| Login | Passwortprüfung, Versuchsbegrenzung, automatische Umstellung alter Hash-Verfahren |
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
danach über **„Konto erstellen"** anlegen – oder per Kommandozeile:

```bash
docker compose exec app php bin/console user:create name@example.com
```

**Weitere Befehle:**

```bash
docker compose logs -f app      # Protokoll der Anwendung
docker compose down             # Stack beenden
docker compose down -v          # Stack beenden und Datenbank löschen
```

> **Änderungen am Code:** Das Image enthält eine Kopie des Quellcodes. Nach Änderungen genügt
> `docker compose up -d --build`, um das Image neu zu bauen.

## Installation ohne Docker

Voraussetzungen: PHP 8.2 oder neuer mit den Erweiterungen `pdo_mysql`, `sodium`, `curl`, `mbstring`
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

**Bestehende Installationen aktualisieren:** Die Migrationen liegen in `database/migrations/`.

```bash
mariadb -u root auth_system < database/migrations/001_email_verification.sql
```

## Konfiguration

Die Datei `.env` enthält alle Einstellungen. Sie steht in der `.gitignore` und gehört nie in die
Versionsverwaltung. Die Vorlage `.env.example` listet alle verfügbaren Werte mit Erklärung auf.

| Variable | Bedeutung |
|---|---|
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` | Zugang zur Datenbank |
| `APP_URL` | Adresse unter der das Projekt erreichbar ist; wird für Reset-Links verwendet |
| `EMAIL_CODE_ENABLED` | `1` = Bestätigung per E-Mail ist verfügbar |
| `RESEND_API_KEY` | API-Schlüssel des E-Mail-Dienstes |
| `RESEND_FROM` | Absenderadresse einer verifizierten Domain |
| `APP_KEY` | 32 zufällige Bytes, Base64-kodiert; schützt die 2FA-Geheimnisse |
| `WEB_PORT` | Port des Webservers im Container |
| `APP_DB_USER`, `APP_DB_PASSWORD` | Zugangsdaten der Datenbank **im Container** |

> **Wichtig:** Der `APP_KEY` darf nach dem ersten Start nicht mehr geändert werden. Sonst lassen
> sich die gespeicherten 2FA-Geheimnisse nicht mehr entschlüsseln.

## Projektstruktur

```
secure-login/
├── composer.json           Abhaengigkeiten und PSR-4-Autoloading
├── docker-compose.yml      Dienste: web, app, db
├── Dockerfile              PHP-FPM-Image
├── database/
│   ├── schema.sql          Datenbankschema für Neuinstallationen
│   └── migrations/         Migrationen für bestehende Installationen
├── docker/
│   ├── nginx/              Reverse-Proxy-Konfiguration
│   └── php/php.ini         PHP-Einstellungen für den Container
├── bin/console             Kommandozeile für Verwaltungsaufgaben
├── public/                 Dokument-Root: der einzige öffentliche Bereich
│   ├── *.php               Ein Controller je Seite
│   └── assets/             Stylesheet, Skript, QR-Code-Bibliothek
├── src/                    Anwendungslogik
│   ├── Application.php     Verdrahtung aller Bausteine
│   ├── AuthService.php     Sitzungszustand und Anmeldeablauf
│   ├── Config.php          Konfiguration
│   ├── Database.php        Datenbankzugriff
│   ├── Http.php            Eingaben, Weiterleitungen, CSRF
│   ├── Mailer.php          E-Mail-Versand
│   ├── PasswordService.php Passwort-Reset und Bestätigungscodes
│   ├── Security.php        Passwörter, Einmalcodes, Verschlüsselung
│   ├── SecurityRepository.php  Datenbankzugriff der Sicherheitstabellen
│   ├── Session.php         Sitzungsverwaltung
│   ├── TwoFactorService.php    Zwei-Faktor-Authentifizierung
│   ├── UserRepository.php  Datenbankzugriff der Kontotabelle
│   ├── View.php            Rendern der Templates
│   └── bootstrap.php       Start der Anwendung
├── templates/              HTML-Templates (Twig)
│   ├── *.twig              Eine Vorlage je Seite
│   └── emails/             Vorlagen für E-Mails
└── tests/                  Testhelfer
```

**Die Trennung ist strikt:**

- **PHP** enthält ausschließlich Logik. Jede Datei hat genau eine Aufgabe.
- **HTML** liegt in `templates/` als Twig-Templates ohne PHP-Code.
- **CSS** liegt in einer einzigen Datei unter `public/assets/style.css`.
- **Nur `public/`** ist über den Web erreichbar. Konfiguration (`src/`, `.env`) sind geschützt.

## Sicherheitskonzept

| Angriffsart | Gegenmaßnahme |
|---|---|
| SQL-Injection | Ausschließlich Prepared Statements, `ATTR_EMULATE_PREPARES => false` |
| Cross-Site-Scripting | Twig escapt automatisch, Ausgabe nur über Templates |
| CSRF | Verstecktes Token (32 Zufallsbytes) in jedem Formular, zeitkonstant verglichen |
| Brute-Force | Versuchsbegrenzung in der Datenbank: 20 Logins / 15 Min., 5 Reset-Anfragen / Std. |
| Passwort im Klartext | bcrypt mit automatischer Prüfung auf veraltete Parameter |
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

Passwörter werden mit bcrypt (`password_hash`) gehasht. Gespeicherte Hashes tragen das Präfix `v2:`.
Konten aus einer älteren Version, bei denen der Browser den SHA-256-Digest gesendet hat, werden
beim nächsten erfolgreichen Login automatisch auf das aktuelle Verfahren umgestellt.

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

## Kommandozeile

```bash
php bin/console help                             # Übersicht aller Befehle
php bin/console user:list                        # Konten auflisten
php bin/console user:create name@example.com     # Konto anlegen
php bin/console user:email-code name@x.de on     # E-Mail-Bestätigung einschalten
php bin/console user:totp-off name@example.com   # 2FA entfernen
php bin/console schema:import                    # Schema einlesen
php bin/console migrate                          # Migrationen ausführen
```

Im Container gilt jeweils `docker compose exec app php bin/console …`.

## Tests

```bash
composer lint                                    # Syntaxprüfung aller PHP-Dateien

# Testhilfen für die manuelle Prüfung
php tests/generate-totp-code.php name@example.com      # gültigen 2FA-Code erzeugen
php tests/seed-verification-code.php name@x.de 123456  # E-Mail-Code hinterlegen
```

## Lizenz

MIT – siehe [LICENSE](LICENSE).

Die eingebundene Bibliothek [QRCode.js](https://github.com/davidshimjs/qrcodejs) steht unter der
MIT-Lizenz, siehe `public/assets/qrcode.LICENSE`.
