.PHONY: up down logs migrate seed test shell composer artisan assets

up:
	docker compose up -d --build

down:
	docker compose down

logs:
	docker compose logs -f

migrate:
	docker compose exec app php artisan migrate

seed:
	docker compose exec app php artisan db:seed

test:
	docker compose exec -e APP_ENV=testing -e DB_DATABASE=app_cobranzas_test -e SESSION_DRIVER=array app php artisan test

shell:
	docker compose exec app sh

composer:
	docker compose exec app composer $(ARGS)

artisan:
	docker compose exec app php artisan $(ARGS)

assets:
	docker compose run --rm assets
