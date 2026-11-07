# Delta CLAUDE.md — 006 · code-quality-and-ci

### Comandos de Calidad y Pruebas
- Análisis estático PHP: `make phpstan` (o `cd back-laravel && vendor/bin/phpstan`)
- Pruebas unitarias frontend: `make test-front` (o `pnpm --filter front-vue run test`)
- CI Pipeline: el repositorio utiliza `.gitlab-ci.yml` con validación estricta de PHPStan nivel 8 y Vitest.
