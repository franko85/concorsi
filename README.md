# concorsi

## Avvio
docker compose up --build -d

## Log app
docker compose logs -f app

## Shell nella app
docker compose exec app bash

## PHP info / estensioni
docker compose exec app php -v
docker compose exec app php -m

## Reset DB (attenzione: perde dati!)
docker compose down -v
docker compose up --build -d