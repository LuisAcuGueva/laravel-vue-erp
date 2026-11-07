# 004 · queue-and-scheduler-infra — Requirements
> **Spec:** 004 · **Alcance:** infra · **Módulo:** Transversal · **Prioridad:** alta · **Depende de specs:** —
> **Status:** Done
> **Última actualización:** 2026-10-14
> **Autores:** Luis Acuña Guevara
> **Origen:** Auditoría arquitectónica para grado profesional - ejecución asíncrona y mantenimiento programado
>
> **Referencias del proyecto:**
> - [README.md](../../../README.md) — arquitectura de entornos y orquestación Docker.
> - [docker-compose.yml](../../../docker-compose.yml) — servicios de desarrollo.
> - [docker-compose.prod.yml](../../../docker-compose.prod.yml) — servicios de producción.
> - [Makefile](../../../Makefile) — comandos de automatización.
>
---
## 1. Contexto de Negocio
En software a medida empresarial (ERP, CRM, plataformas administrativas), procesos pesados como el envío de correos transaccionales, generación de reportes masivos, exportaciones y sincronizaciones no pueden bloquear el hilo HTTP de la API de Laravel (PHP-FPM).

Actualmente, el proyecto configura `QUEUE_CONNECTION=database` y `SESSION_DRIVER=database`, pero **no cuenta con ningún contenedor worker ni scheduler** en Docker Compose ni en desarrollo ni en producción. Los jobs encolados permanecen indefinidamente en la tabla `jobs` de PostgreSQL a menos que un desarrollador ejecute manualmente un comando en terminal. Adicionalmente, no existe un proceso programado que limpie periódicamente tokens de sesión revocados o expirados en la tabla `refresh_tokens`.

Este spec añade los servicios Docker necesarios y la automatización para garantizar procesamiento asíncrono y tareas programadas fiables tanto en desarrollo como en producción sobre el driver PostgreSQL existente.

## 2. Actores
| Actor | Quién | Cómo se autentica / Permisos base |
|---|---|---|
| Sistema / Demonio | Worker de colas de Laravel | Proceso CLI interno en red Docker aislada |
| Sistema / Cron | Scheduler de Laravel | Proceso CLI interno en red Docker aislada |
| Desarrollador / DevOps | Operador de infraestructura | Comandos Make (`make queue-work`, `make dev-logs`) |

## 3. User Stories (formato EARS)
### US-1 — Procesamiento continuo de trabajos en segundo plano
**Como** desarrollador de módulos de negocio,
**quiero** despachar eventos y trabajos a la cola de base de datos (`dispatch(new SendReportJob())`),
**para** que la API responda inmediatamente al cliente (< 100ms) sin bloquear la petición HTTP.
**Cuando** un trabajo es insertado en la tabla `jobs`, **el sistema debe** consumirlo y procesarlo en menos de 5 segundos a través del contenedor `queue-worker`.
**Módulo:** Transversal
**Criterios de aceptación:**
- [ ] En desarrollo (`docker-compose.yml`), existe un servicio `back-queue` que ejecuta `php artisan queue:work --tries=3 --timeout=90`.
- [ ] En producción (`docker-compose.prod.yml`), existe un servicio `back-queue` optimizado con reinicio automático (`restart: always`).
- [ ] Si un trabajo falla tras 3 intentos, se traslada automáticamente a la tabla `failed_jobs`.

### US-2 — Ejecución periódica de tareas de mantenimiento (Scheduler)
**Como** administrador del sistema,
**quiero** que las tareas recurrentes se ejecuten automáticamente,
**para** mantener la base de datos limpia de registros obsoletos y realizar tareas programadas.
**Cuando** transcurre cada hora o día según la regla en `routes/console.php`, **el sistema debe** ejecutar las rutinas sin intervención humana.
**Módulo:** Transversal
**Criterios de aceptación:**
- [ ] Existe un servicio `back-scheduler` en Docker que ejecuta `php artisan schedule:work` (dev) o un loop regulado.
- [ ] Se implementa una tarea programada diaria que purga `RefreshToken::where('expires_at', '<', now())->delete()`.
- [ ] Se implementa el comando de purga de trabajos fallidos antiguos (`queue:prune-failed --hours=168`).

## 4. Reglas de negocio e invariantes
- **Driver de base de datos:** Se mantiene `QUEUE_CONNECTION=database` utilizando la base de datos PostgreSQL existente (`postgres:16-alpine`), sin añadir Redis para evitar sobrecarga operativa innecesaria.
- **Aislamiento de procesos:** El worker y el scheduler corren en contenedores separados del servidor web FastCGI (`back-app`) para que picos de trabajo en background no degraden la latencia de la API.
- **Gestión de memoria:** El worker debe reiniciarse al alcanzar un umbral de memoria (`--max-time=3600` o `--max-jobs=1000`) para evitar *memory leaks* en PHP.

## 5. Requisitos no funcionales
- **Resiliencia:** `restart: unless-stopped` en desarrollo y `restart: always` en producción.
- **Observabilidad:** Salida de logs capturable vía `docker compose logs -f back-queue` y comandos en `Makefile`.
- **Salud del contenedor:** Healthcheck básico o verificación de proceso vivo.

## 6. Out of Scope
- Configuración de colas distribuidas complejas con Amazon SQS o Redis (se mantiene Postgres `database`).
- Panel de control Horizon (Horizon requiere Redis obligatoriamente; con driver `database` no aplica).

## 7. Open Questions
- [x] ¿Usar Redis o Postgres? *Decisión: Postgres (`database`) para mantener el stack ligero y autocontenido.*

## 8. Métricas de éxito
- Jobs despachados se procesan en background sin intervención manual.
- Zero acumulación indefinida de registros en la tabla `jobs`.
- Tokens vencidos de refresh eliminados diariamente de la tabla `refresh_tokens`.
