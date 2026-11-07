---
trigger: always_on
description: Documentos de identidad y registros de sistema.
---

## Registros de sistema

- Los registros "de sistema" (`is_system = true`: cliente genérico, motivos, conceptos, métodos de pago) los crean los seeders, se referencian por código o con un helper estático (`Client::system()`) y el Service rechaza su edición o eliminación con 422.
