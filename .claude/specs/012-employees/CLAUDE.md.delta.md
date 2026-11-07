# 012 · employees — Delta de reglas
> **Instrucciones:** este archivo contiene **solo las reglas duras nuevas** que este spec agrega al repo. Al cerrar el spec se mergean en [.agents/rules/](../../../.agents/rules/) (archivo existente o uno nuevo con frontmatter `trigger` / `description`), según el destino indicado en cada sección.
>
> **Origen:** [requirements.md](./requirements.md), [design.md](./design.md), [todo.md](./todo.md).
>
---
## (+ nuevo ".agents/rules/employees.md", trigger: always_on) Empleados vs usuarios
**description:** Separación entre la ficha de personal (employees) y el acceso al sistema (users).
- Las referencias a **personas que realizan una operación comercial** (vendedor, responsable) apuntan a `employees.id` (`seller_id`, `responsible_employee_id`). Las referencias a **quién ejecutó la acción en el sistema** (`created_by`, `opened_by`, `approved_by`) apuntan a `users.id`.
- Crear un usuario a partir de un empleado pasa siempre por `EmployeeUserService::createUser()`, que reutiliza `UserService::create()` y la regla anti-escalamiento de roles; nunca se crea `User` directamente desde el módulo `Maintenance`.
- Un usuario se vincula como máximo a un empleado (`employees.user_id` único entre no eliminados). Del empleado al usuario solo se sincroniza `name`.
- Todo spec que agregue una FK a `employees` agrega en `EmployeeService::delete()` la verificación que impide eliminar un empleado con documentos asociados.
