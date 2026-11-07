## Tests y bases de datos

- Los tests de backend corren **siempre** contra el contenedor `postgres-test` (`docker-compose.test.yml`, publicado en `localhost:5434`), nunca contra `laravel_dev`. Se ejecutan con `make test-run ARGS="--filter=X"` (BD persistente), `make test-filter FILTER=X` o `make test` (aislado, con `down -v`).
- Prohibido ejecutar `php artisan test` en el host o en `template_app_dev`: `RefreshDatabase` hace `migrate:fresh` y borraría los datos de desarrollo.
- `Tests\TestCase::setUpTraits()` aborta si la BD activa no termina en `_test` o es el servidor dev; no se elimina ni se le agregan excepciones.
- `phpunit.xml` fuerza `pgsql` y `laravel_test`; host, puerto y credenciales vienen del entorno de `back-test`, nunca de `.env`.
- El puerto 5433 queda libre para el Postgres de WSL; por eso la BD de test usa el 5434.
