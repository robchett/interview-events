> docker compose build
> docker compose up -d
> docker-compose exec php bin/console doctrine:database:create
> docker-compose exec php bin/console doctrine:migrations:migrate

# [Optional] Seed the database with fixtures
> docker-compose exec php php bin/console doctrine:fixtures:load

# [Optional] Run Psalm
> docker-compose exec php vendor/bin/psalm

# [Optional] Run tests
> docker-compose exec php php bin/console --env=test doctrine:database:create
> docker-compose exec php php bin/console --env=test doctrine:schema:create
> docker-compose exec php php bin/console --env=test doctrine:fixtures:load --purge-with-truncate --no-interaction
> docker-compose exec php bin/phpunit

# [Optional] Edit code
> docker compose down
> docker compose up --watch

> docker-compose exec php vendor/bin/php-cs-fixer --config=.php-cs-fixer.dist.php fix src tests migrations config bin