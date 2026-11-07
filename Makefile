# ==============================================================================
# Makefile para Monolito Modular: Laravel 12 + Vue 3 + PostgreSQL
# Entornos: Dev (Híbrido), Test (Docker Postgres dedicado), Prod (Nginx unificado)
# ==============================================================================

.PHONY: test-run test-db-up test-db-down help dev-up dev-down dev-restart dev-logs dev-ps front-dev dev \
        test test-filter test-down test-front phpstan \
        prod-build prod-up prod-down prod-restart prod-logs \
        queue-logs queue-restart scheduler-logs \
        artisan composer migrate migrate-fresh migrate-fresh-inventory seed tinker back-bash db-bash clean

# Colores para la salida en consola
YELLOW  := $(shell printf '\033[33m')
GREEN   := $(shell printf '\033[32m')
CYAN    := $(shell printf '\033[36m')
BOLD    := $(shell printf '\033[1m')
RESET   := $(shell printf '\033[0m')

# Archivos de Compose
COMPOSE_DEV  := docker compose -f docker-compose.yml
COMPOSE_TEST := docker compose -f docker-compose.test.yml
COMPOSE_PROD := docker compose -f docker-compose.prod.yml

# Comando por defecto: Mostrar ayuda
help:
	@echo ""
	@echo "$(BOLD)$(CYAN)==================================================================$(RESET)"
	@echo "$(BOLD)$(CYAN)  MONOLITO MODULAR - COMANDOS DOCKER & AUTOMATIZACIÓN$(RESET)"
	@echo "$(BOLD)$(CYAN)==================================================================$(RESET)"
	@echo ""
	@echo "$(BOLD)$(YELLOW)🛠️  DESARROLLO (Híbrido)$(RESET)"
	@echo "  $(GREEN)make dev$(RESET)          Inicia backend + Postgres en Docker y da instrucciones para el front"
	@echo "  $(GREEN)make dev-up$(RESET)       Levanta los contenedores de desarrollo en segundo plano"
	@echo "  $(GREEN)make dev-down$(RESET)     Detiene y remueve los contenedores de desarrollo"
	@echo "  $(GREEN)make dev-restart$(RESET)  Reinicia los contenedores de desarrollo"
	@echo "  $(GREEN)make dev-logs$(RESET)     Muestra los logs en tiempo real de los contenedores de dev"
	@echo "  $(GREEN)make dev-ps$(RESET)       Muestra el estado de los contenedores de dev"
	@echo "  $(GREEN)make front-dev$(RESET)    Inicia Vite en el host (front-vue con HMR ultrarrápido)"
	@echo "  $(GREEN)make queue-logs$(RESET)   Muestra los logs en tiempo real del worker de colas"
	@echo "  $(GREEN)make queue-restart$(RESET)Reinicia el worker de colas tras cambios en jobs"
	@echo "  $(GREEN)make scheduler-logs$(RESET)Muestra los logs del planificador de tareas"
	@echo ""
	@echo "$(BOLD)$(YELLOW)🧪 TEST & CALIDAD DE CÓDIGO$(RESET)"
	@echo "  $(GREEN)make test$(RESET)         Ejecuta todas las pruebas en contenedor de pruebas aislado"
	@echo "  $(GREEN)make test-filter$(RESET)  Ejecuta una prueba específica: make test-filter FILTER=ClientTest"
	@echo "  $(GREEN)make test-front$(RESET)   Ejecuta las pruebas unitarias del frontend con Vitest"
	@echo "  $(GREEN)make phpstan$(RESET)      Ejecuta análisis estático con Larastan / PHPStan (Nivel 8)"
	@echo "  $(GREEN)make test-down$(RESET)    Detiene y limpia el entorno de testing y sus volúmenes"
	@echo ""
	@echo "$(BOLD)$(YELLOW)🚀 PRODUCCIÓN (Nginx unificado con build de Vue 3 + PHP-FPM)$(RESET)"
	@echo "  $(GREEN)make prod-build$(RESET)   Construye las imágenes optimizadas de producción"
	@echo "  $(GREEN)make prod-up$(RESET)      Levanta el stack de producción en segundo plano"
	@echo "  $(GREEN)make prod-down$(RESET)    Detiene el stack de producción"
	@echo "  $(GREEN)make prod-restart$(RESET) Reinicia el stack de producción"
	@echo "  $(GREEN)make prod-logs$(RESET)    Muestra los logs de producción en tiempo real"
	@echo ""
	@echo "$(BOLD)$(YELLOW)📦 LARAVEL & BASE DE DATOS (Entorno Dev)$(RESET)"
	@echo "  $(GREEN)make artisan CMD=\"...\"$(RESET) Ejecuta comando artisan (ej: make artisan CMD=\"route:list\")"
	@echo "  $(GREEN)make composer CMD=\"...\"$(RESET)Ejecuta comando composer (ej: make composer CMD=\"dump-autoload\")"
	@echo "  $(GREEN)make migrate$(RESET)      Ejecuta las migraciones pendientes en PostgreSQL dev"
	@echo "  $(GREEN)make migrate-fresh$(RESET)Recrea la base de datos de dev desde cero con seeders"
	@echo "  $(GREEN)make migrate-fresh-inventory$(RESET)Recrea la BD con mantenimiento + inventario inicial (sin ventas)"
	@echo "  $(GREEN)make seed$(RESET)         Ejecuta los seeders de la base de datos"
	@echo "  $(GREEN)make tinker$(RESET)       Abre la consola interactiva de Laravel Tinker"
	@echo "  $(GREEN)make back-bash$(RESET)    Abre una terminal interactiva dentro del contenedor PHP"
	@echo "  $(GREEN)make db-bash$(RESET)      Abre consola interactiva psql dentro del contenedor Postgres"
	@echo ""
	@echo "$(BOLD)$(YELLOW)🧹 LIMPIEZA$(RESET)"
	@echo "  $(GREEN)make clean$(RESET)        Detiene todos los contenedores y elimina volúmenes huérfanos"
	@echo ""

# ==============================================================================
# DESARROLLO (Híbrido)
# ==============================================================================

dev-up:
	@echo "$(CYAN)Levantando Backend (PHP 8.3 FPM + Nginx Gateway) y PostgreSQL...$(RESET)"
	$(COMPOSE_DEV) up -d --build
	@echo "$(GREEN)✓ Backend listo en:$(RESET) http://api.localhost"
	@echo "$(GREEN)✓ PostgreSQL listo en:$(RESET) localhost:5432"

dev-down:
	@echo "$(YELLOW)Deteniendo entorno de desarrollo...$(RESET)"
	$(COMPOSE_DEV) down

dev-restart: dev-down dev-up

dev-logs:
	$(COMPOSE_DEV) logs -f

dev-ps:
	$(COMPOSE_DEV) ps

front-dev:
	@echo "$(CYAN)Iniciando servidor de desarrollo Frontend (Vite accesible en http://localhost)...$(RESET)"
	@cd front-vue && npm run dev

queue-logs:
	$(COMPOSE_DEV) logs -f back-queue

queue-restart:
	@echo "$(CYAN)Reiniciando worker de colas...$(RESET)"
	$(COMPOSE_DEV) restart back-queue
	@echo "$(GREEN)✓ Worker reiniciado.$(RESET)"

scheduler-logs:
	$(COMPOSE_DEV) logs -f back-scheduler

dev: dev-up
	@echo ""
	@echo "$(BOLD)$(GREEN)==================================================================$(RESET)"
	@echo "$(BOLD)$(GREEN)✓ El entorno de desarrollo está activo en el puerto 80 estándar.$(RESET)"
	@echo "  - Backend API: $(CYAN)http://api.localhost$(RESET)"
	@echo "  - Frontend:    $(CYAN)http://localhost$(RESET)"
	@echo "  - PostgreSQL:  $(CYAN)localhost:5432$(RESET)"
	@echo ""
	@echo "$(BOLD)$(YELLOW)Para iniciar el frontend en modo híbrido con recarga instantánea:$(RESET)"
	@echo "  Ejecuta en otra terminal: $(BOLD)$(GREEN)make front-dev$(RESET)"
	@echo "  O bien directamente:      $(BOLD)cd front-vue && npm run dev$(RESET)"
	@echo "  URL en navegador:         $(BOLD)$(CYAN)http://localhost$(RESET)"
	@echo "$(BOLD)$(GREEN)==================================================================$(RESET)"
	@echo ""

# ==============================================================================
# TEST (Docker con PostgreSQL dedicado)
# ==============================================================================

test:
	@echo "$(CYAN)Iniciando entorno de pruebas con PostgreSQL dedicado...$(RESET)"
	$(COMPOSE_TEST) up --build --abort-on-container-exit --exit-code-from back-test
	@status=$$?; $(COMPOSE_TEST) down -v; exit $$status

test-filter:
	@if [ -z "$(FILTER)" ]; then \
		echo "$(YELLOW)Debes especificar el filtro: make test-filter FILTER=NombreTest$(RESET)"; \
		exit 1; \
	fi
	@echo "$(CYAN)Ejecutando prueba '$(FILTER)' en contenedor de test...$(RESET)"
	$(COMPOSE_TEST) up -d postgres-test
	$(COMPOSE_TEST) run --rm back-test php artisan test --filter=$(FILTER)
	@status=$$?; $(COMPOSE_TEST) down -v; exit $$status

test-front:
	@echo "$(CYAN)Ejecutando pruebas unitarias de frontend (Vitest)...$(RESET)"
	pnpm --filter front-vue test

phpstan:
	@echo "$(CYAN)Ejecutando análisis estático con Larastan / PHPStan (Nivel 8)...$(RESET)"
	$(COMPOSE_DEV) exec back-app php -d memory_limit=512M ./vendor/bin/phpstan analyse

test-db-up:
	@echo "$(CYAN)Levantando BD de test persistente (localhost:5434)...$(RESET)"
	$(COMPOSE_TEST) up -d --wait postgres-test

test-run:
	@$(COMPOSE_TEST) up -d --wait postgres-test
	$(COMPOSE_TEST) run --rm back-test php artisan test $(ARGS)

test-db-down:
	$(COMPOSE_TEST) down -v

test-down:
	$(COMPOSE_TEST) down -v

# ==============================================================================
# PRODUCCIÓN (Nginx unificado + Vue build + PHP-FPM)
# ==============================================================================

prod-build:
	@echo "$(CYAN)Construyendo imágenes de producción (Front SPA + PHP-FPM)...$(RESET)"
	$(COMPOSE_PROD) build

prod-up:
	@echo "$(CYAN)Levantando stack de producción unificado...$(RESET)"
	$(COMPOSE_PROD) up -d
	@echo "$(GREEN)✓ Producción activa en:$(RESET) http://localhost"

prod-down:
	@echo "$(YELLOW)Deteniendo stack de producción...$(RESET)"
	$(COMPOSE_PROD) down

prod-restart: prod-down prod-up

prod-logs:
	$(COMPOSE_PROD) logs -f

# ==============================================================================
# COMANDOS DE LARAVEL & COMPOSER
# ==============================================================================

artisan:
	@if [ -z "$(CMD)" ]; then \
		$(COMPOSE_DEV) exec back-app php artisan; \
	else \
		$(COMPOSE_DEV) exec back-app php artisan $(CMD); \
	fi

composer:
	@if [ -z "$(CMD)" ]; then \
		$(COMPOSE_DEV) exec back-app composer; \
	else \
		$(COMPOSE_DEV) exec back-app composer $(CMD); \
	fi

migrate:
	$(COMPOSE_DEV) exec back-app php artisan migrate

migrate-fresh:
	$(COMPOSE_DEV) exec back-app php artisan migrate:fresh --seed

migrate-fresh-inventory:
	$(COMPOSE_DEV) exec back-app php artisan migrate:fresh --seed --seeder=InventoryBaselineSeeder

seed:
	$(COMPOSE_DEV) exec back-app php artisan db:seed

tinker:
	$(COMPOSE_DEV) exec back-app php artisan tinker

back-bash:
	$(COMPOSE_DEV) exec back-app bash

db-bash:
	$(COMPOSE_DEV) exec postgres psql -U $${DB_USERNAME:-laravel} -d $${DB_DATABASE:-laravel_dev}

clean:
	@echo "$(YELLOW)Limpiando contenedores huérfanos y redes...$(RESET)"
	$(COMPOSE_DEV) down -v --remove-orphans 2>/dev/null || true
	$(COMPOSE_TEST) down -v --remove-orphans 2>/dev/null || true
	$(COMPOSE_PROD) down -v --remove-orphans 2>/dev/null || true
	@echo "$(GREEN)✓ Limpieza completada.$(RESET)"
