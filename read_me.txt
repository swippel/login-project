Login-Projekt (PHP 8.2+, MariaDB, Resend)

Einrichtung
1. MariaDB starten: brew services start mariadb
2. Tabellen anlegen: mariadb -u root < schema.sql
   Bei einer bestehenden auth_system.users-Tabelle bleiben die Konten erhalten.
   schema.sql legt die neuen Tabellen password_resets, user_totp und auth_rate_limits an.

3. .env.example nach .env kopieren und DB-Zugang, APP_URL, Resend-Werte und APP_KEY setzen.
   APP_KEY erzeugen: php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'
   Für echte Empfänger muss RESEND_FROM eine Absenderadresse einer bei Resend
   verifizierten Domain sein. Der API-Key gehört nur in .env, nie in Browser-Code.

4. Im Projektordner starten: php -S 127.0.0.1:8081 -t public

5. Öffnen: http://127.0.0.1:8081

Funktionen
- Registrierung und Login mit serverseitigem Passwort-Hash.
- Vorhandene Konten mit dem alten SHA-256-im-Browser-Verfahren werden beim
  nächsten erfolgreichen Login automatisch auf den neuen Hash umgestellt.
- Passwort vergessen: Einmal-Link per Resend, 30 Minuten gültig.
- Optionale 2FA mit Authenticator-App (TOTP). QR-Code wird im Browser lokal
  erzeugt. APP_KEY muss dauerhaft gleich bleiben, sonst sind bestehende
  2FA-Schlüssel nicht mehr entschlüsselbar.

Wichtig für den Betrieb
- Webserver-Document-Root muss auf public/ zeigen, damit .env und src/
  nicht öffentlich erreichbar sind. Für HTTPS APP_URL auf die HTTPS-Adresse setzen.
- Reset-E-Mails funktionieren erst nach Eintragen eines gültigen Resend-Keys
  und Absenders. Ohne diese Konfiguration wird kein Reset-Link verschickt.
- QRCode.js in public/assets/ stammt von davidshimjs/qrcodejs (MIT-Lizenz).
