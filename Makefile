# =====================================================================
# Secure Login — Kurzbefehle
# =====================================================================

.DEFAULT_GOAL := help
.PHONY: help setup up down rebuild logs shell test lint schema user clean

help: ## Zeigt diese Uebersicht
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

setup: ## Erstellt die .env aus der Vorlage
	@test -f .env && echo ".env existiert bereits" || (cp .env.example .env && echo ".env angelegt")
	@echo "APP_KEY erzeugen: php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'"

up: ## Startet den Container-Stack
	docker compose up -d --build
	@echo "Bereit unter http://127.0.0.1:$${WEB_PORT:-8081}"

down: ## Stoppt den Container-Stack
	docker compose down

rebuild: ## Baut das Image neu und startet neu
	docker compose up -d --build

logs: ## Zeigt das Protokoll der Anwendung
	docker compose logs -f app

shell: ## Oeffnet eine Shell im Anwendungscontainer
	docker compose exec app sh

schema: ## Legt die Datenbank und alle Tabellen an
	docker compose exec -T db mariadb -u root -p$${DB_ROOT_PASSWORD:-root} < database/schema.sql

user: ## Legt ein Konto an: make user EMAIL=name@example.com
	docker compose exec app php bin/console user:create $(EMAIL)

test: ## Prueft die Syntax aller PHP-Dateien
	composer lint

lint: ## Prueft die Syntax aller PHP-Dateien
	composer lint

clean: ## Entfernt Cache und Abhaengigkeiten
	rm -rf vendor var/cache
