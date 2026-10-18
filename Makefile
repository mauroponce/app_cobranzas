.PHONY: up down logs migrate seed test shell composer artisan assets database-volume

up: database-volume
	docker compose up -d --build

database-volume:
	docker volume create app-cobranzas_postgres_data >/dev/null

down:
	docker compose down

logs:
	docker compose logs -f

migrate:
	docker compose exec app php artisan migrate

seed:
	@test "$(CONFIRM_DATABASE_WRITE)" = "yes" || (echo "Comando bloqueado: usá 'make seed CONFIRM_DATABASE_WRITE=yes' solo con autorización explícita." && exit 1)
	docker compose exec -e ALLOW_DESTRUCTIVE_DATABASE_COMMANDS=true app php artisan db:seed

test:
	docker compose exec -e APP_ENV=testing -e DB_DATABASE=app_cobranzas_test -e SESSION_DRIVER=array app php artisan test $(TEST_ARGS)

shell:
	docker compose exec app sh

composer:
	docker compose exec app composer $(ARGS)

artisan:
	docker compose exec app php artisan $(ARGS)

assets:
	docker compose run --rm assets
