# 006 · code-quality-and-ci — Design
> **Spec:** 006 · **Alcance:** fullstack · **Módulo:** Transversal · **Prioridad:** alta · **Depende de specs:** —
> **Status:** In Progress
> **Última actualización:** 2026-10-14
> **Autores:** Luis Acuña Guevara

---
## 1. Configuración de Larastan en Backend

### 1.1 Dependencia en `back-laravel/composer.json`
```json
"require-dev": {
    "larastan/larastan": "^3.0",
    "laravel/pint": "^1.24",
    "phpunit/phpunit": "^11.5"
}
```

### 1.2 Archivo de configuración: `back-laravel/phpstan.neon`
```neon
includes:
    - vendor/larastan/larastan/extension.neon

parameters:
    paths:
        - app/
    level: 8
    checkGenericClassInNonGenericObjectType: false
    ignoreErrors:
        # Errores específicos conocidos o de macros de Laravel si aplica
```

### 1.3 Script en Composer
```json
"scripts": {
    "phpstan": "vendor/bin/phpstan analyse"
}
```

## 2. Configuración de Vitest en Frontend

### 2.1 Dependencias en `front-vue/package.json`
```json
"devDependencies": {
    "vitest": "^2.1.0",
    "@vue/test-utils": "^2.4.6",
    "jsdom": "^25.0.0"
}
```

### 2.2 Ajuste en `front-vue/vite.config.js`
```javascript
export default defineConfig({
  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: ['./src/tests/setup.js'],
  },
  // plugins y resolve existentes...
})
```

### 2.3 Scripts en `front-vue/package.json`
```json
"scripts": {
    "test": "vitest run",
    "test:watch": "vitest",
    "test:coverage": "vitest run --coverage"
}
```

### 2.4 Tests Unitarios Iniciales
- `src/tests/unit/useAcl.spec.js`: Verifica que la función `can()` evalúa correctamente los permisos presentes en `authStore` y rechaza acciones no autorizadas.
- `src/tests/unit/authStore.spec.js`: Valida el almacenamiento seguro de sesión, la decodificación del token JWT y la mutación de estado al llamar `updateTokens`.
- `src/tests/unit/api.spec.js`: Valida la inyección del encabezado `Authorization: Bearer <token>` y `X-CSRF-TOKEN`.

## 3. Configuración de GitLab CI (`.gitlab-ci.yml`)

Ubicación en la raíz del repositorio: `.gitlab-ci.yml`

```yaml
stages:
  - lint
  - analyze
  - test
  - build

default:
  interruptible: true

variables:
  POSTGRES_DB: laravel_test
  POSTGRES_USER: laravel
  POSTGRES_PASSWORD: secret
  POSTGRES_HOST_AUTH_METHOD: trust

# Cache global de pnpm y composer
cache:
  key: "${CI_COMMIT_REF_SLUG}"
  paths:
    - back-laravel/vendor/
    - front-vue/node_modules/
    - .pnpm-store/

# --------------------------------------------------------------------------
# 1. LINT
# --------------------------------------------------------------------------
lint:backend:
  stage: lint
  image: php:8.3-cli-alpine
  before_script:
    - cd back-laravel
    - composer install --prefer-dist --no-progress --no-interaction
  script:
    - vendor/bin/pint --test

lint:frontend:
  stage: lint
  image: node:20-alpine
  before_script:
    - corepack enable
    - corepack prepare pnpm@latest --activate
    - pnpm install --frozen-lockfile
  script:
    - pnpm --filter front-vue run lint

# --------------------------------------------------------------------------
# 2. ANALYZE
# --------------------------------------------------------------------------
analyze:backend:
  stage: analyze
  image: php:8.3-cli-alpine
  before_script:
    - cd back-laravel
    - composer install --prefer-dist --no-progress --no-interaction
  script:
    - vendor/bin/phpstan analyse

# --------------------------------------------------------------------------
# 3. TEST
# --------------------------------------------------------------------------
test:backend:
  stage: test
  image: php:8.3-cli-alpine
  services:
    - postgres:16-alpine
  variables:
    DB_CONNECTION: pgsql
    DB_HOST: postgres
    DB_PORT: 5432
    DB_DATABASE: laravel_test
    DB_USERNAME: laravel
    DB_PASSWORD: secret
  before_script:
    - apk add --no-cache postgresql-client postgresql-dev
    - docker-php-ext-install pdo pdo_pgsql
    - cd back-laravel
    - composer install --prefer-dist --no-progress --no-interaction
    - cp .env.example .env
    - php artisan key:generate
    - php artisan migrate:fresh --seed
  script:
    - php artisan test

test:frontend:
  stage: test
  image: node:20-alpine
  before_script:
    - corepack enable
    - corepack prepare pnpm@latest --activate
    - pnpm install --frozen-lockfile
  script:
    - pnpm --filter front-vue run test

# --------------------------------------------------------------------------
# 4. BUILD
# --------------------------------------------------------------------------
build:frontend:
  stage: build
  image: node:20-alpine
  before_script:
    - corepack enable
    - corepack prepare pnpm@latest --activate
    - pnpm install --frozen-lockfile
  script:
    - pnpm --filter front-vue run build
  artifacts:
    paths:
      - front-vue/dist/
    expire_in: 1 week
```
