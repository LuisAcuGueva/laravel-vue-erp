# 021 · providers — Delta de reglas
> **Instrucciones:** este archivo contiene **solo las reglas duras nuevas** que este spec agrega al repo. Al cerrar el spec se mergean en [.agents/rules/](../../../.agents/rules/) (archivo existente o uno nuevo con frontmatter `trigger` / `description`), según el destino indicado en cada sección.
>
> **Origen:** [requirements.md](./requirements.md), [design.md](./design.md), [todo.md](./todo.md).
>
---
## (+ nuevo ".agents/rules/identity-documents.md", trigger: always_on) Documentos de identidad y entidades externas
**description:** Validación de documentos de identidad y sincronización de sub-colecciones de entidades (proveedores, clientes, empleados).
- Todo tipo y número de documento de identidad (empleados, proveedores, clientes) se valida con el enum `app/Support/Identity/DocumentType` y su `rulesFor()`; no se escriben regex de RUC/DNI en otros lugares.
- La unicidad de un tercero es por `(document_type, document_number)` entre registros no eliminados (`SoftDeleteUniqueIndex`).
- Las sub-colecciones editables de una entidad (contactos, cuentas bancarias) se actualizan con un endpoint `PUT /<recurso>/{id}/<coleccion>` que sincroniza la lista completa en una transacción y rechaza (422) ids que no pertenezcan a la entidad.
