---
name: atomic-commits-laravel-vue
description: (Laravel/Vue/pnpm) Analiza el árbol de cambios de Git de un proyecto Laravel 11+/12 (API, Sanctum/JWT, stancl/tenancy, spatie/permission) y/o Vue 3 + Vite + Vuetify + Pinia, en repos separados o en monorepo pnpm + Turborepo. Detecta features, fixes y refactorizaciones independientes, separa hunks mezclados y genera commits atómicos y bisectables con Conventional Commits en español. Antes audita secretos y artefactos, verifica con Pint/Larastan/Deptrac/PHPUnit y ESLint/Vitest, y pide confirmación interactiva.
license: MIT
metadata:
  version: "2.0.0"
  domain: git-workflow
  stack: php8.2+, laravel-11/12, stancl-tenancy, spatie-permission, vue3, vite, vuetify, pinia, vitest, msw, pnpm-workspace, turborepo, docker
  triggers: atomic commits, git commit, commit atomico, separar commits, commitear, commit changes, conventional commits, split diff, git staging, laravel commit, vue commit
  role: lead-engineer
  scope: version-control
  output-format: interactive-plan-and-execution
---

# Atomic Commits — Senior Git Specialist (Laravel + Vue)

Transforma un árbol de cambios de Git (dirty working tree) en una secuencia ordenada de **commits atómicos, autocontenidos y bisectables**. Sirve para un backend Laravel, un frontend Vue o un **monorepo pnpm + Turborepo** que contiene ambos (p. ej. `back-*/` + `front-*/`).

---

## 🎯 Filosofía y Principios de Oro

1. **Un solo propósito por commit**: una unidad lógica de cambio, es decir, una feature, un fix, una refactorización o una migración con su modelo.
2. **Cada commit deja el proyecto sano (bisectable)**: en cada commit intermedio la app arranca, las migraciones corren y los tests pasan, no solo en el último. Nunca commitees código que use clases, rutas, stores o endpoints que llegan en un commit posterior.
3. **Los tests viajan con el código que prueban**: un `feat`/`fix` incluye su Feature/Unit test (PHPUnit) o su `*.spec.js` (Vitest). El tipo `test` queda solo para tests que cubren código **ya existente**.
4. **Nunca mezclar formateo con lógica**: Pint, `eslint --fix`, reordenar imports o renombres masivos van en `style`/`refactor`, separados del cambio funcional.
5. **El contrato de la API va primero**: en un cambio fullstack, el commit del backend (endpoint, Resource, FormRequest) precede al del frontend que lo consume. La excepción es que el front se apoye primero en un handler MSW.
6. **Respeta la convención del repo**: antes de redactar mensajes, lee `git log -n 30 --format=%s` y calibra los scopes ya usados, si se escriben tildes o no, y el idioma del cuerpo. El historial existente manda sobre esta skill.
7. **Cero fugas de información sensible y cero pérdida de trabajo**: ni secretos ni artefactos, y ningún comando que descarte cambios del working tree.

### Orden de dependencias (de abajo hacia arriba)

1. `build` / `chore(deps)`: `composer.json` + `composer.lock`, `package.json` + `pnpm-lock.yaml`, configuración de Vite/ESLint/Pint/PHPStan/Deptrac/Turbo.
2. **Backend**: `migración + modelo + factory` → `dominio/servicios/actions` → `FormRequest + Resource + Controller + ruta` → `policies/permisos/seeders`.
3. **Frontend**: `servicio API / handler MSW` → `store Pinia / composable` → `componentes` → `página + navegación + abilities CASL + i18n`.
4. `chore(docker)` / `ci`: Dockerfiles, compose, Makefile, pipelines.
5. `docs`: README, specs SDD (`.agents/specs/`, `.claude/specs/`).

---

## 🛡️ Reglas de seguridad operativa (no negociables)

- **Prohibido**, salvo petición explícita del usuario: `git checkout -p`, `git checkout -- <archivo>`, `git restore <archivo>` (sin `--staged`), `git reset --hard`, `git clean`, `git stash`, `git commit --amend` sobre commits ya publicados, `git push` y `--no-verify`.
- Para sacar algo del índice usa solo `git restore --staged <archivo>` (no toca el working tree).
- Haz `git add` **nombrando los archivos explícitamente**. Nunca uses `git add -A`, `git add .` ni `git commit -a`.
- Si al empezar **ya hay cambios en staging**, avisa y pregunta si se respetan como primer commit o se desindexan.
- Si la rama actual es `main`/`master`/`develop` y el repo trabaja con PRs, adviértelo antes de commitear.
- **No agregues pies de atribución (`Co-Authored-By`)** salvo que el usuario lo pida.

---

## 🧭 Flujo de Trabajo en 6 Fases

### Fase 0: Reconocimiento del repo

```bash
git rev-parse --show-toplevel
git branch --show-current
git status --short --untracked-files=all
git diff --cached --stat
git log -n 30 --format='%s'
ls composer.json package.json pnpm-workspace.yaml turbo.json Makefile 2>/dev/null
```

Determina:
- **Topología**: repo Laravel, repo Vue o monorepo, y en este último cuáles son los subdirectorios back y front (los `packages` de `pnpm-workspace.yaml`).
- **Gestor de paquetes JS**: el que fije `packageManager` en `package.json` (raíz o subproyecto). Si coexisten `pnpm-lock.yaml` y `package-lock.json`, adviértelo: uno de los dos quedará desactualizado. Commitea solo el lockfile del gestor oficial y propone eliminar el otro en un `chore` aparte.
- **Herramientas disponibles**: `vendor/bin/{pint,phpstan,deptrac,phpunit}`, scripts `lint`/`test`/`build` del `package.json`, objetivos del `Makefile` (`make test`, `make lint`).

### Fase 1: Auditoría de Seguridad e Higiene

**Archivos prohibidos** (modificados, staged o untracked):
- **Secretos**: `.env`, `.env.*` (excepto `.env.example`, `.env.*.example`), `auth.json` (credenciales de Composer), `storage/*.key` (OAuth/Passport), `*.pem`, `*.key`, `*.p12`, `id_rsa*`, `*.tfvars`, `*.tfstate*`.
- **Artefactos Laravel**: `vendor/`, `storage/logs/*`, `storage/framework/{cache,sessions,views}/*`, `bootstrap/cache/*.php`, `public/storage` (symlink), `public/build/`, `public/hot`, `.phpunit.result.cache`, `.phpunit.cache/`, `.deptrac.cache`, `database/*.sqlite`, dumps `*.sql`/`*.dump` fuera de migraciones o seeders.
- **Artefactos front/monorepo**: `node_modules/`, `dist/`, `.vite/`, `.turbo/`, `coverage/`, `public/mockServiceWorker.js` (lo genera `msw init`), íconos compilados de Iconify si están en `.gitignore`.
- **IDE/SO**: `.idea/`, `.vscode/` (salvo `extensions.json`/`settings.json` compartidos), `.DS_Store`, `Thumbs.db`.

*Si aparece alguno, avisa y propone agregarlo a `.gitignore`. Si ya está trackeado pese a estar ignorado, propone un `chore` aparte con `git rm --cached <archivo>`.*

**Secretos en el contenido**, incluidos los archivos untracked:

```bash
PAT='APP_KEY=base64:[A-Za-z0-9+/=]{20,}|JWT_SECRET=[^[:space:]]{16,}|(DB|REDIS|MAIL|AWS)_[A-Z_]*(PASSWORD|SECRET|KEY)=[^[:space:]$]+|AKIA[0-9A-Z]{16}|-----BEGIN [A-Z ]*PRIVATE KEY|eyJ[A-Za-z0-9_-]{10,}\.eyJ[A-Za-z0-9_-]{10,}\.|(password|secret|api[_-]?key|token)["'"'"']?[[:space:]]*(=|=>|:)[[:space:]]*["'"'"'][^"'"'"'$]{8,}["'"'"']'
git diff HEAD | grep -niE -- "$PAT"
git ls-files --others --exclude-standard -z | xargs -0 -r grep -nHiE -- "$PAT" 2>/dev/null
```
- `.env.example` solo debe contener valores vacíos o ficticios. Un `APP_KEY` o un `JWT_SECRET` real ahí es una fuga.
- En `config/*.php` las credenciales se leen siempre con `env('X')`, nunca como literales.
- Un JWT (`eyJ...`) en fixtures, tests o handlers MSW es aceptable solo si es claramente de prueba. Si dudas, pregunta.

**Código de depuración residual** en el diff:
- **PHP**: `dd(`, `dump(`, `ddd(`, `var_dump(`, `print_r(`, `ray(`, `->dd()`, `->dump()`, `DB::enableQueryLog`, `Log::debug(` temporales, `sleep(`.
- **JS/Vue**: `console.log(`, `console.debug(`, `debugger`, `alert(` de prueba.
- **Tests enfocados o saltados**: `it.only(` / `describe.only(` / `test.only(` / `.skip(` (Vitest), `markTestSkipped(` y `@group` de depuración (PHPUnit). Un `.only` hace que CI ejecute un solo test y pase en falso.
- **Configuración**: `APP_DEBUG=true` o `LOG_LEVEL=debug` en archivos de entorno de producción, y `Model::preventLazyLoading(false)` añadido para silenciar errores.

*Si se detecta alguno, consulta al usuario antes de commitear.*

**Permisos de archivos**: los scripts (`docker/**/*.sh`, `artisan`) deben quedar `100755` en el índice:
```bash
git ls-files -s -- '*.sh' artisan | grep -v '^100755'
```
Si alguno no lo está: `git update-index --chmod=+x <archivo>`.

### Fase 2: Análisis Topológico y Separación de Concerns

```bash
git diff
git ls-files --others --exclude-standard
```

1. **Agrupa por stack, módulo y capa**:
   - **Back**: `app/Modules/<Módulo>/...`, `app/Core/<Contexto>/{Domain,Application,Infrastructure,Presentation}`, `routes/{api,tenant,web}.php`, `database/migrations/{central,tenant}/`, `tests/{Feature,Unit}/<Módulo>/`.
   - **Front**: `src/modules/<módulo>/`, `src/pages/` (router basado en archivos), `src/stores/`, `src/composables/`, `src/navigation/`, `src/plugins/casl/`, `src/plugins/i18n/locales/`, `src/plugins/fake-api/handlers/` (MSW).
2. **Acoplamientos que obligan a commitear junto**:
   - **Migración + Modelo (`$fillable`, `$casts`, relaciones) + Factory**: un modelo sin su tabla rompe los tests que lo usan.
   - **Migración en el contexto correcto (stancl/tenancy)**: las tablas de inquilino van en `database/migrations/tenant/` y las de plataforma en `database/migrations/central/`. Una migración en la carpeta equivocada es un bug y no un detalle de orden, así que detente y avisa.
   - **FormRequest + Controller + Resource + ruta + test Feature** del mismo endpoint.
   - **Nueva clave de `config/*.php` + su variable en `.env.example`**, y en el monorepo también en el `.env.example` raíz o el `docker-compose` que la inyecta.
   - **Permiso nuevo**: seeder/constante de `spatie/permission` + policy/middleware que lo exige + ability CASL del front que lo consulta, si van en el mismo commit lógico.
   - **Servicio API del front + handler MSW** que lo simula.
   - **Página nueva**: `src/pages/...vue` + entrada en `src/navigation/` + claves i18n que usa.
   - **Archivos generados y versionados** (`auto-imports.d.ts`, `components.d.ts`, `typed-router.d.ts`, `.eslintrc-auto-import.json`): van **en el mismo commit que el cambio que los regeneró**, nunca solos ni mezclados con otro feature. Si cambiaron sin una causa en el diff, son ruido de otra rama: pregunta.
   - **`composer.json` + `composer.lock`** juntos, igual que **`package.json` + lockfile**. Un manifiesto sin su lock rompe `composer install`/`pnpm install --frozen-lockfile` en CI.
3. **Migraciones ya commiteadas son inmutables**: si el diff edita una migración que ya existe en `HEAD` y pudo haberse ejecutado en otros entornos, detente y propone una migración nueva (`add_x_to_y_table`). La excepción es una rama local sin publicar, y siempre con confirmación del usuario.
4. **Cambios de contrato API**: renombrar o eliminar campos de un `JsonResource`, cambiar códigos de estado o rutas es un **BREAKING CHANGE**. Marca el commit con `!` y el pie `BREAKING CHANGE:`, y asegúrate de que el front se adapte en el commit inmediatamente posterior.
5. **Archivos con cambios mixtos (hunk splitting)**: son típicos en `routes/api.php`, `routes/tenant.php`, `composer.json`, `package.json`, `src/navigation/**`, `locales/*.json`, `DatabaseSeeder.php`, `AppServiceProvider.php`, `deptrac.yaml`, `README.md`. Nunca los commitees en bloque:
   - En una terminal interactiva: `git add -p <archivo>`.
   - Como agente, sin modo interactivo:
     ```bash
     P=$(mktemp --suffix=.patch)
     git diff -U3 -- <archivo> > "$P"
     # Edita $P y deja solo los hunks del commit actual. Al borrar líneas '+',
     # elimínalas; al borrar líneas '-', conviértelas en contexto (' ').
     # Ajusta los contadores @@ si cambian.
     git apply --cached --check "$P" && git apply --cached "$P"
     git diff --cached -- <archivo>   # verifica lo indexado
     ```
   - Para un archivo **untracked** que pertenece a dos commits: `git add -N <archivo>` y luego el mismo flujo de parches.
   - **Lockfiles no se parten por hunks.** Si `composer.lock` o `pnpm-lock.yaml` mezclan dos dependencias de features distintas, commitea el lock completo con la primera feature que lo necesita y explícalo en el cuerpo del mensaje.

### Fase 3: Plan de Commits Atómicos

Convención **Conventional Commits en español**, en imperativo y minúsculas, sin punto final y con la primera línea de **72 caracteres o menos**:

```
<tipo>(<scope>)[!]: <descripción>

[cuerpo opcional: qué problema resuelve, por qué así y no de otro modo]

[pie opcional: Refs: #123 | Refs: specs/<feature>#T-<n> | BREAKING CHANGE: ...]
```

#### Scopes
- **Dominio o módulo de negocio**, el mismo nombre en back y front: `auth`, `identity`, `tenancy`, `users`, `roles`, `permissions`, `audit`, `clients`, `products`, etc. Reutiliza los del `git log`.
- **Transversales**: `deps`, `build`, `db` (migraciones sin modelo nuevo), `arch` (Deptrac/Larastan), `i18n`, `ui` (design system/Vuetify global), `mocks` (MSW), `docker`, `ci`, `specs`, `skills`.
- En un monorepo **no uses `back`/`front` como scope** si el dominio es claro. Úsalos solo para cambios de plataforma de un lado (`build(front): migrar a vite 7`).

#### Tipos permitidos

| Tipo | Cuándo usar | Ejemplo |
| :--- | :--- | :--- |
| `feat` | Nueva funcionalidad (incluye sus tests) | `feat(clients): exponer listado paginado con filtro por documento` |
| `fix` | Corrección de bug (incluye el test que lo reproduce) | `fix(auth): renovar token en refresh sin leer el body` |
| `refactor` | Cambio interno sin alterar comportamiento | `refactor(tenancy): extraer resolución de subdominio a composable useTenant` |
| `perf` | Rendimiento: N+1, índices, cache, lazy loading, virtual scroll | `perf(products): evitar n+1 con eager loading de categorías` |
| `style` | Formato con Pint/ESLint/Stylelint sin cambio de lógica | `style(users): aplicar pint psr-12` |
| `test` | Tests para código ya existente | `test(permissions): cubrir aislamiento de permisos entre tenants` |
| `docs` | Solo documentación, OpenAPI o specs | `docs(specs): definir requerimientos del módulo de clientes` |
| `build` | Sistema de build: Vite, Turbo, Composer o pnpm, plugins | `build: configurar workspace pnpm con turborepo` |
| `chore` | Mantenimiento: dependencias, `.gitignore`, dotfiles, tooling | `chore(deps): actualizar laravel/framework a 12.x` |
| `ci` | Pipelines de CI/CD | `ci: ejecutar pint, larastan y vitest en pull requests` |
| `revert` | Revertir un commit previo | `revert: feat(audit): auditar login fallido` |

### Fase 4: Confirmación Interactiva

**NUNCA ejecutes `git commit` sin presentar antes la propuesta y recibir confirmación.**

```markdown
### 📋 Propuesta de Commits Atómicos

Rama: `feature/clients` · Topología: monorepo (back-multi-tenancy + front-multi-tenancy)

| # | Tipo y scope | Mensaje | Archivos | Hunks mixtos |
|---|--------------|---------|----------|--------------|
| 1 | `chore(deps)` | chore(deps): agregar spatie/laravel-query-builder | `back/composer.json`, `back/composer.lock` | No |
| 2 | `feat(clients)` | feat(clients): crear tabla de clientes por tenant | `migrations/tenant/..._create_clients_table.php`, `Client.php`, `ClientFactory.php` | No |
| 3 | `feat(clients)` | feat(clients): exponer crud de clientes en api tenant | `ClientController.php`, `StoreClientRequest.php`, `ClientResource.php`, `routes/tenant.php` (hunk), `ClientsApiTest.php` | Sí |
| 4 | `feat(clients)` | feat(clients): agregar vista de listado y formulario | `src/modules/clients/**`, `src/pages/clients/index.vue`, `navigation/vertical/index.js` (hunk), `typed-router.d.ts` | Sí |

**Validaciones previas:**
- [x] Sin `.env`, `auth.json`, `storage/*.key` ni artefactos (`vendor/`, `node_modules/`, `dist/`, `public/build/`).
- [x] Sin secretos literales en `config/`, `.env.example` ni fixtures.
- [x] Sin `dd()`/`dump()`/`ray()`/`console.log`/`debugger`/`.only(` residuales.
- [x] Migraciones existentes sin modificar y en el contexto correcto (central/tenant).
- [x] Manifiestos y lockfiles en el mismo commit.
- [?] ¿Ejecuto Pint, Larastan, Deptrac, PHPUnit, ESLint y Vitest? ¿Verifico la bisectabilidad commit a commit?
```

> *"¿Confirmas este plan de commits o deseas reordenar, fusionar o modificar algún mensaje?"*

### Fase 5: Verificación y Ejecución

**1. Verificación de calidad sobre el working tree completo** (solo lo que exista en el proyecto; en un monorepo, ejecuta cada comando desde el subdirectorio correspondiente):

```bash
# Backend (Laravel)
vendor/bin/pint --test                         # sin modificar archivos
vendor/bin/phpstan analyse --no-progress --memory-limit=1G
vendor/bin/deptrac analyse --no-progress       # si existe deptrac.yaml
php artisan test --parallel                    # o: make test (Docker + Postgres)

# Frontend (Vue)
pnpm exec eslint <archivos cambiados>          # SIN --fix
pnpm exec vitest run
pnpm run build                                 # si el cambio toca router, config de Vite o imports globales
```

- **Ojo con los scripts que modifican archivos**: si `pnpm lint` ejecuta `eslint --fix` (o hay un `composer lint` con Pint sin `--test`), **no lo uses para verificar**. Alteraría el working tree en mitad del plan. Invoca la herramienta directamente en modo verificación.
- **Nunca ocultes fallos** con `|| true` ni `2>/dev/null`. Si algo falla, detente, muestra la salida y no commitees con el build roto.
- Si un formateador detecta problemas en código **fuera** del diff, no lo arregles dentro de los commits funcionales. Propón un `style` aparte al final.

**2. Commit por commit, en orden:**

```bash
git add -- back-multi-tenancy/database/migrations/tenant/2026_10_13_000100_create_clients_table.php \
           back-multi-tenancy/app/Modules/Clients/Models/Client.php \
           back-multi-tenancy/database/factories/ClientFactory.php
git diff --cached --stat        # el índice contiene SOLO lo previsto
git commit -F - <<'EOF'
feat(clients): crear tabla de clientes por tenant

La migración vive en el contexto tenant porque cada inquilino
mantiene su propio padrón de clientes.
EOF
```

- Si un hook (husky, lint-staged, pre-commit) falla, el commit **no** se creó. Corrige la causa, vuelve a indexar y crea el commit de nuevo, sin `--amend` ni `--no-verify`.
- Si un hook **modifica archivos** (lint-staged con `--fix`), revisa `git status` antes de seguir. Esos cambios pertenecen al commit en curso, no al siguiente.

**3. Verificación de bisectabilidad** (opcional, si el usuario la acepta). Ejecuta los tests en cada commit nuevo dentro de un worktree aislado, sin tocar el working tree:

```bash
BASE=<sha previo al primer commit nuevo>
ROOT=$(git rev-parse --show-toplevel)
for c in $(git rev-list --reverse "$BASE"..HEAD); do
  d=$(mktemp -d)
  git worktree add -q --detach "$d" "$c"
  # Reutiliza dependencias instaladas. Solo es válido si los lockfiles no cambian en el rango.
  for dep in vendor node_modules; do
    find "$ROOT" -maxdepth 2 -name "$dep" -type d -not -path '*/node_modules/*' | while read -r p; do
      ln -s "$p" "$d/${p#"$ROOT"/}"
    done
  done
  ( cd "$d/<subdir-back>" && cp -n .env.example .env && php artisan key:generate -q && php artisan test ) \
    && echo "OK    $c" || echo "FALLA $c"
  git worktree remove --force "$d"
done
```

Si `composer.lock` o el lockfile JS cambian dentro del rango, avisa de que el chequeo exige reinstalar dependencias en cada worktree, y pregunta antes de hacerlo. Si algún commit falla, propone reordenar o fusionar antes de cualquier push.

### Fase 6: Reporte Final

```bash
git status --short
git log --oneline --graph --decorate "$BASE"..HEAD
```

Muestra el historial generado e indica si el working tree quedó limpio o qué archivos quedaron intencionalmente sin commitear y por qué. No hagas `push` salvo que el usuario lo pida.

---

## 🛠️ Casos Especiales

### `routes/api.php` o `routes/tenant.php` con rutas de dos módulos
Separa los hunks: cada grupo de rutas viaja con su controller y su test Feature. Si las rutas comparten un mismo `Route::middleware(...)->group(...)` recién creado, el grupo vacío va con el primer módulo y el segundo solo agrega sus líneas.

### Feature fullstack (endpoint + pantalla)
Haz dos commits como mínimo: primero `feat(<dominio>)` back con su test, después `feat(<dominio>)` front. Si el front se desarrolló contra MSW, el handler va con el front. Al conectar con la API real, un `refactor(<dominio>)` reemplaza el mock.

### Permiso o rol nuevo (spatie/permission + CASL)
Seeder o constante de permiso, policy/middleware y test que verifica el 403 en el commit back. La ability CASL, el `v-if="$can(...)"` y la entrada de navegación protegida en el commit front.

### Nueva regla Deptrac o subida de nivel de Larastan que el código actual viola
Primero el `refactor` que corrige las violaciones y después `build(arch)` con la regla. Al revés, el commit intermedio queda en rojo.

### Dependencia nueva usada por dos features
Commitea `composer.json`/`package.json` + lockfile completos con la **primera** feature que la usa, y la segunda solo añade código. No separes la dependencia en un commit `chore(deps)` aislado si nadie la usa todavía, salvo que el usuario lo prefiera.

### Archivos `*.d.ts` generados que cambian por ruido
`components.d.ts` y `auto-imports.d.ts` suelen reordenarse al levantar el dev server. Si su diff no corresponde a ningún cambio real (solo reordena líneas), propone descartarlo en lugar de commitearlo, **pidiendo confirmación** antes de restaurarlo.

### Seeders y datos demo
Los datos que la app necesita para funcionar (roles base, permisos) van con la feature. Los datos demo (`tenant:seed-demo`, factories de ejemplo) van en un `chore(seed)` aparte.

### Docker / entorno de desarrollo mezclado con código
`docker/**`, `docker-compose*.yml`, `Dockerfile`, `Makefile` y `turbo.json` van en `chore(docker)`/`build` aparte, salvo que la feature necesite una variable o servicio nuevo para funcionar (p. ej. Redis para colas).

---

## 💡 Ejemplos de Invocación

- *"Analiza mis cambios y propón commits atómicos."*
- *"Tengo `routes/tenant.php` y la navegación del front mezclando clientes y productos, sepáralos."*
- *"Revisa el diff, valida que no haya secretos ni `dd()` y commitea con conventional commits en español."*
- *"Commitea el back y el front de la feature de auditoría por separado y verifica que cada commit pase los tests."*
