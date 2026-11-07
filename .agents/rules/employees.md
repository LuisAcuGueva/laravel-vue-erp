---
trigger: always_on
description: Separación entre la ficha de personal (employees) y el acceso al sistema (users).
---

## Empleados vs usuarios

- Las referencias a **personas que realizan una operación comercial** (vendedor, responsable) apuntan a `employees.id` (`seller_id`, `responsible_employee_id`). Las referencias a **quién ejecutó la acción en el sistema** (`created_by`, `opened_by`, `approved_by`) apuntan a `users.id`.
- Crear un usuario a partir de un empleado pasa siempre por `EmployeeUserService::createUser()`, que reutiliza `UserService::create()` y la regla anti-escalamiento de roles; nunca se crea `User` directamente desde el módulo `Maintenance`.
- Un usuario se vincula como máximo a un empleado (`employees.user_id` único entre no eliminados). Del empleado al usuario solo se sincroniza `name`.
- Todo spec que agregue una FK a `employees` agrega en `EmployeeService::delete()` la verificación que impide eliminar un empleado con documentos asociados.
