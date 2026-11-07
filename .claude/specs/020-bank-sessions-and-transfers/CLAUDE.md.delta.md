# 020 · bank-sessions-and-transfers — Delta de reglas
> **Instrucciones:** este archivo contiene **solo las reglas duras nuevas** que este spec agrega al repo. Al cerrar el spec se mergean en [.agents/rules/](../../../.agents/rules/) (archivo existente o uno nuevo con frontmatter `trigger` / `description`), según el destino indicado en cada sección.
>
> **Origen:** [requirements.md](./requirements.md), [design.md](./design.md), [todo.md](./todo.md).
>
---
## (+ en ".agents/rules/finance.md") Ledger bancario
- Todo abono o cargo en una cuenta bancaria (cobros con tarjeta o transferencia, pagos, depósitos, retiros, transferencias, cargos del banco) se registra con `BankLedgerService::register()` dentro de la transacción del documento de origen. Prohibido insertar en `bank_movements` o escribir `bank_accounts.balance` desde otro lugar.
- `bank_movements` es inmutable; la anulación se hace con `BankLedgerService::reverse()` y solo dentro del periodo abierto.
- Los cobros y pagos con un método cuyo `settlement = bank_account` registran el movimiento bancario en la cuenta del método, no en una cuenta elegida por el usuario.

## (+ nuevo ".agents/rules/file-uploads.md", trigger: always_on) Archivos adjuntos
**description:** Almacenamiento y descarga de archivos subidos por usuarios.
- Los archivos con información sensible (comprobantes bancarios, documentos de proveedores y clientes) se guardan en el disco `local` (privado) y se descargan solo por un endpoint autenticado con permiso que usa `Storage::download()`; las respuestas JSON exponen la URL de ese endpoint, nunca la ruta física.
- Solo los archivos de marca (logo de empresa) van al disco `public`.
- Toda subida valida `mimes` y `max` (KB) en el FormRequest.
