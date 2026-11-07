# Delta CLAUDE.md — 004 · queue-and-scheduler-infra

### Comandos de Colas y Scheduler
- Monitorear worker de colas: `make queue-logs`
- Reiniciar worker tras modificar Jobs: `make queue-restart`
- Ver tareas programadas: `make artisan CMD="schedule:list"`
