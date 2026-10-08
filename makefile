include .env

# Запустить контейнеры:
up:
	docker compose up -d --build --remove-orphans

# Остановить контейнеры:
stop:
	docker compose stop

# Остановить и удалить контейнеры:
down:
	docker compose down -v

# Очистить кэш Laravel:
cache-clear:
	docker compose exec -it application php artisan optimize:clear

# Сгенерировать APP_KEY — ключ шифрования сессий, cookie и прочих данных:
key-generate:
	docker compose exec -it application php artisan key:generate

# КОМАНДЫ ДОСТУПНЫ ПРИ УСЛОВИИ ЧТО ПРОЕКТ LARAVEL УЖЕ СОЗДАН:

## Создать файл миграции:
migrations-create: ## Пример: make migrations-create NAME=create_posts_table
	docker compose exec -it application php artisan make:migration $(NAME)

## Накатить все миграции:
migrations-up:
	docker compose exec -it application php artisan migrate --force

## Откатить последнюю пачку миграций:
migrations-down:
	docker compose exec -it application php artisan migrate:rollback

## Произвольная команда artisan:
artisan: ## Пример: make artisan CMD="route:list"
	docker compose exec -it application php artisan $(CMD)

# КОМАНДА ДОСТУПНА ПРИ УСЛОВИИ ЧТО УСТАНОВЛЕН ПАКЕТ PHPSTAN:

## Проверить код на ошибки:
phpstan:
	docker compose exec -it application php vendor/bin/phpstan analyse

# КОМАНДЫ ДОСТУПНЫ ПРИ УСЛОВИИ ЧТО УСТАНОВЛЕН LARAVEL PINT (есть в Laravel по умолчанию):

## Исправить стиль кода:
pint:
	docker compose exec -it application php vendor/bin/pint

## Проверить стиль кода без исправления:
pint-dry:
	docker compose exec -it application php vendor/bin/pint --test || true

# КОМАНДЫ ДОСТУПНЫ ПРИ УСЛОВИИ ЧТО УСТАНОВЛЕН ПАКЕТ PHPCS:

## Исправить ошибки:
phpcs:
	docker compose exec -it application php vendor/bin/php-cs-fixer fix --allow-risky=yes

## Проверить код на ошибки без исправления:
phpcs-dry:
	docker compose exec -it application php vendor/bin/php-cs-fixer fix --allow-risky=yes --dry-run || true

# КОМАНДЫ ДОСТУПНЫ ПРИ УСЛОВИИ ЧТО УСТАНОВЛЕН ПАКЕТ RECTOR:

## Исправить ошибки:
rector:
	docker compose exec -it application php vendor/bin/rector process

## Проверить код на ошибки без исправления:
rector-dry:
	docker compose exec -it application php vendor/bin/rector process --dry-run || true

# КОМАНДА ДОСТУПНА ПРИ УСЛОВИИ ЧТО ЕСТЬ ТЕСТЫ LARAVEL:

## Запустить тесты:
test:
	docker compose exec -it application php artisan test
