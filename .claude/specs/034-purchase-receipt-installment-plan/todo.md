# 034 · purchase-receipt-installment-plan — TODO
> **Spec:** 034 · **Alcance:** fullstack · **Módulo:** Purchases · **Prioridad:** media · **Depende de specs:** 022, 023, 024
> **Status:** Done
> **Última actualización:** 2026-10-24
>
> **Prerequisito de lectura:** [requirements.md](./requirements.md), [design.md](./design.md).
>
> **Convenciones:**
> - 🔧 = pendiente · ✅ = hecho · ⚪ = opcional
> - **Subj** (subproyecto): `back` | `front` | `infra` | `docs`
> - **Complejidad:** S = ≤ 1 h · M = ≤ ½ día · L = > ½ día (evitar L: partir en varias M)
> - Cada tarea es **autocontenida**: un agente con `requirements.md`, `design.md` y los archivos indicados puede ejecutarla sin contexto adicional.
> - **Dependencias** explícitas entre tareas → preservar el orden indicado.
>
---
## Mapa de Tareas
| ID | Estado | Subj | Compl | Archivos clave | Depende de |
|---|---|---|---|---|---|
| T-1 | ✅ | back | M | `ConfirmPurchaseReceiptRequest.php`, `ReceiveAllRequest.php` | — |
| T-2 | ✅ | back | M | `SupplierInvoiceService.php` | — |
| T-3 | ✅ | back | S | `PurchaseReceiptConfirmationService.php` | T-2 |
| T-4 | ✅ | back | S | `PurchaseReceiptController.php` | T-1, T-3 |
| T-5 | ✅ | back | M | `PurchaseReceiptsApiTest.php` | T-4 |
| T-6 | ✅ | back | S | `PurchaseReceiptItemResource.php` | — |
| T-7 | ✅ | front | M | `InstallmentPlanFields.vue` (nuevo) | — |
| T-8 | ✅ | front | S | `SupplierInvoiceForm.vue` (refactor) | T-7 |
| T-9 | ✅ | front | S | `purchaseReceiptsApi.js` | — |
| T-10 | ✅ | front | M | `receipts/new.vue` | T-7, T-9 |
| T-11 | ✅ | front | M | `orders/[id].vue` | T-7, T-9 |
| T-12 | ✅ | front | M | `receipts/[id].vue` | T-6, T-7, T-9 |
| T-13 | ✅ | docs | S | `.agents/rules/` (delta) | T-1..T-12 |

---
## Detalle de Tareas

### T-1 — Validar `installments` en `confirm` y `receive-all`
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** —
**Archivos clave:**
- `back-laravel/app/Modules/Purchases/Requests/ConfirmPurchaseReceiptRequest.php` (nuevo)
- `back-laravel/app/Modules/Purchases/Requests/ReceiveAllRequest.php`
**Descripción:**
1. Crear `ConfirmPurchaseReceiptRequest` (ver diseño §3.1) con `authorize() => true` y las reglas `installments`/`installments.*.due_date`/`installments.*.amount` (idénticas a `StoreSupplierInvoiceRequest::rules()`, máximo 60 filas).
2. Agregar las mismas tres reglas al `rules()` de `ReceiveAllRequest`.
**Tests:**
- Cubierto indirectamente por T-5 (los `FormRequest` se validan vía las rutas reales).
**Criterios de done:**
- [ ] `ConfirmPurchaseReceiptRequest` existe y compila (`php -l`).
- [ ] `ReceiveAllRequest::rules()` incluye las 3 reglas nuevas.
- [ ] Backend: `cd back-laravel && vendor/bin/pint --test` en verde.

---

### T-2 — `SupplierInvoiceService::createOrAttachFromConfirmedReceipt()` acepta cronograma
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** —
**Archivos clave:**
- `back-laravel/app/Modules/Purchases/Services/SupplierInvoiceService.php`
**Descripción:**
1. Cambiar la firma a `createOrAttachFromConfirmedReceipt(PurchaseReceipt $receipt, User $user, ?array $installments = null): array` (retorna `list<string>` de warnings; antes era `void`).
2. Implementar exactamente la lógica de diseño §2.3: si `provider_document_type !== Invoice` o sin número de documento → `return []`. Si hay factura existente (fusión) → si `$installments` no vacío, agregar warning de US-4 al array de retorno y continuar fusionando sin usarlo (sin cambiar `attachReceiptToInvoice()`). Si es creación nueva → pasar `'installments' => $installments ?? []` a `create()`.
3. El `try/catch(Throwable) { report($e); }` que hoy envuelve toda la operación **solo** debe envolver la ejecución cuando `$installments` está vacío/null; cuando el usuario definió un cronograma explícito, cualquier excepción se propaga (no se atrapa).
4. No tocar `attachReceiptToInvoice()` (firma ni lógica) — solo se le agrega el chequeo de `$installments` no vacío en el llamador, para generar el warning.
**Tests:**
- Cubierto por T-5 (tests de servicio se verifican a través de la API, siguiendo el patrón ya usado en este archivo — no hay tests unitarios aislados del servicio en el repo).
**Criterios de done:**
- [ ] `vendor/bin/phpstan analyse app/Modules/Purchases/Services/SupplierInvoiceService.php --memory-limit=1G` sin errores nuevos.
- [ ] `vendor/bin/pint --test` en verde.
- [ ] El comportamiento sin `$installments` (cuota única, fusión silenciosa, fallos ajenos atrapados) es idéntico al actual — verificado por los tests ya existentes (`test_confirming_receipt_with_invoice_document_creates_supplier_invoice_automatically`, `test_second_receipt_with_same_provider_invoice_number_merges_into_existing_invoice`, `test_receipt_with_guide_document_does_not_create_supplier_invoice`) sin modificarlos.

---

### T-3 — `PurchaseReceiptConfirmationService` propaga cronograma y warnings
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** T-2
**Archivos clave:**
- `back-laravel/app/Modules/Purchases/Services/PurchaseReceiptConfirmationService.php`
**Descripción:**
1. `confirm(PurchaseReceipt $receipt, User $user, ?array $installments = null): array` — cambia el tipo de retorno de `PurchaseReceipt` a `array{receipt: PurchaseReceipt, warnings: list<string>}` (ver diseño §2.4). El paso 7 pasa `$installments` a `createOrAttachFromConfirmedReceipt()` y captura su retorno como `warnings`.
2. `receiveAll(PurchaseOrder $order, array $header, User $user): array` — mismo cambio de tipo de retorno; extrae `$header['installments'] ?? null` y lo pasa a `confirm()`, reenvía su `array` de salida tal cual (no reconstruye el array).
**Tests:**
- Cubierto por T-5.
**Criterios de done:**
- [ ] `vendor/bin/phpstan analyse app/Modules/Purchases/Services/PurchaseReceiptConfirmationService.php --memory-limit=1G` sin errores nuevos.
- [ ] `vendor/bin/pint --test` en verde.

---

### T-4 — `PurchaseReceiptController` usa el nuevo FormRequest y expone warnings
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** T-1, T-3
**Archivos clave:**
- `back-laravel/app/Modules/Purchases/Controllers/PurchaseReceiptController.php`
**Descripción:**
1. `confirm(string $id, ConfirmPurchaseReceiptRequest $request): JsonResponse` (cambia el segundo parámetro de `Request` a `ConfirmPurchaseReceiptRequest`; cambia el tipo de retorno de `JsonResponse|PurchaseReceiptResource` a `JsonResponse`). Desestructura `['receipt' => $confirmed, 'warnings' => $warnings] = $this->confirmationService->confirm($receipt, $user, $request->validated('installments'));` y responde `(new PurchaseReceiptResource($confirmed))->additional(['meta' => ['warnings' => $warnings]])->response();`.
2. `receiveAll(string $id, ReceiveAllRequest $request): JsonResponse` — mismo patrón: desestructurar el array de `receiveAll()`, responder con `->additional(['meta' => ['warnings' => $warnings]])->response()->setStatusCode(201);`.
**Tests:**
- Cubierto por T-5.
**Criterios de done:**
- [ ] `vendor/bin/pint --test` en verde.
- [ ] `vendor/bin/phpstan analyse app/Modules/Purchases/Controllers/PurchaseReceiptController.php --memory-limit=1G` sin errores nuevos.

---

### T-5 — Tests backend del cronograma
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-4
**Archivos clave:**
- `back-laravel/tests/Feature/Modules/Purchases/PurchaseReceiptsApiTest.php`
**Descripción:**
Agregar los 6 tests descritos en diseño §9.1:
1. `test_confirming_receipt_with_custom_installments_creates_invoice_with_that_schedule`
2. `test_confirming_receipt_with_installments_not_matching_total_fails_and_blocks_confirmation`
3. `test_confirming_receipt_with_installment_due_date_before_issue_date_fails`
4. `test_second_receipt_with_custom_installments_on_merge_is_ignored_with_warning`
5. `test_receipt_with_guide_document_and_installments_payload_is_ignored_without_error`
6. `test_receive_all_with_custom_installments_creates_invoice_with_that_schedule`

Reutilizar `createApprovedOrder()` y los helpers ya existentes en el archivo (mismo patrón que los tests de la sesión anterior: `test_confirming_receipt_with_invoice_document_creates_supplier_invoice_automatically`, etc.). Usar el total conocido (826.00, con la orden por defecto de `createApprovedOrder()`) para construir cuotas que sí/no cuadren.
**Tests:**
- Son los propios tests de esta tarea.
**Criterios de done:**
- [ ] `make test-run ARGS="--filter=PurchaseReceiptsApiTest"` en verde (los 6 nuevos + los existentes, contra `postgres-test`, nunca contra `laravel_dev`).
- [ ] `make test-run ARGS="--filter=Purchases"` en verde (sin regresión en `SupplierInvoicesApiTest`, `SupplierPaymentsApiTest`, `SupplierInvoicesDemoSeederTest`, etc.).

---

### T-6 — `PurchaseReceiptItemResource` expone `tax_type`
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** —
**Archivos clave:**
- `back-laravel/app/Modules/Purchases/Resources/PurchaseReceiptItemResource.php`
**Descripción:**
1. Agregar `'tax_type' => $this->orderItem?->tax_type?->value,` al `toArray()` del resource (la relación `orderItem` ya se carga en `PurchaseReceiptService::findById()` vía `'items.orderItem:id,line,description,quantity,received_quantity'` — agregar `tax_type` a esa lista de columnas seleccionadas).
**Tests:**
- Verificación manual o un `assertJsonPath` agregado a un test existente de `PurchaseReceiptsApiTest` que ya revise `data.items`.
**Criterios de done:**
- [ ] `vendor/bin/pint --test` en verde.
- [ ] El campo aparece en la respuesta de `GET /purchases/receipts/{id}`.

---

### T-7 — Componente compartido `InstallmentPlanFields.vue`
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** —
**Archivos clave:**
- `front-vue/src/modules/purchases/components/payables/InstallmentPlanFields.vue` (nuevo)
**Descripción:**
1. Extraer el bloque "Cuotas por pagar" de `front-vue/src/modules/purchases/components/invoices/SupplierInvoiceForm.vue` (líneas ~598-699 a la fecha de este spec) a este componente nuevo, con las props/emits de diseño §8.1 (`modelValue`, `total`, `issueDate`, `defaultDueDate`, `currencySymbol`, `currencyDecimals`, `errors`).
2. Mover `splitCount`, `splitInterval`, `splitInstallments()` y el markup de filas editables (`VTextField` de `due_date`/`amount` por fila) al nuevo componente, operando sobre `modelValue` vía `v-model` (patrón estándar Vue 3 `defineModel` o `props.modelValue` + `emit('update:modelValue', ...)`, según el resto del código del módulo).
3. Mantener la validación visual "Suma de cuotas: X / total Y" con color `text-success`/`text-error`, igual que hoy.
**Tests:**
- Ninguno automatizado nuevo (el módulo no tiene tests unitarios de componentes); se verifica por el uso en T-8.
**Criterios de done:**
- [ ] `cd front-vue && npm run lint` en verde.
- [ ] El componente no depende de nada específico de factura de proveedor (no importa `supplierInvoicesApi` ni nada de `SupplierInvoiceForm`) — debe ser reutilizable tal cual en las pantallas de recepción.

---

### T-8 — `SupplierInvoiceForm.vue` usa el componente extraído
**Estado:** ✅ · **Subj:** front · **Complejidad:** S · **Depende de:** T-7
**Archivos clave:**
- `front-vue/src/modules/purchases/components/invoices/SupplierInvoiceForm.vue`
**Descripción:**
1. Reemplazar el bloque inline de cuotas por `<InstallmentPlanFields v-model="form.installments" :total="Number(totals.total)" :issue-date="form.issue_date" :default-due-date="form.due_date" :currency-symbol="currencySymbol" :currency-decimals="currencyDecimals" :errors="errors" />`.
2. Eliminar `splitCount`, `splitInterval`, `splitInstallments()` y el markup movido del `<script setup>` de este archivo.
**Tests:**
- Ninguno automatizado nuevo — esta tarea es la verificación de que T-7 no cambió el comportamiento observable del formulario de factura manual (spec 024), que sí tiene cobertura en `SupplierInvoicesApiTest` (backend) aunque no en frontend.
**Criterios de done:**
- [ ] `npm run lint` en verde.
- [ ] Verificación manual (`/run`): crear una factura de proveedor manual con "Dividir en cuotas" sigue funcionando igual que antes de la extracción.

---

### T-9 — `purchaseReceiptsApi.confirm()` acepta payload
**Estado:** ✅ · **Subj:** front · **Complejidad:** S · **Depende de:** —
**Archivos clave:**
- `front-vue/src/modules/purchases/services/purchaseReceiptsApi.js`
**Descripción:**
1. Cambiar `confirm(id) { return api.post(...) }` a `confirm(id, payload = {}) { return api.post(\`/purchases/receipts/${id}/confirm\`, payload) }`.
**Tests:**
- Ninguno — es un cambio de 1 línea, compatible con todas las llamadas existentes sin segundo argumento.
**Criterios de done:**
- [ ] `npm run lint` en verde.

---

### T-10 — `receipts/new.vue` muestra la calculadora al confirmar
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-7, T-9
**Archivos clave:**
- `front-vue/src/pages/purchases/receipts/new.vue`
**Descripción:**
1. Cargar `configurationApi.getSummary()` en `onMounted` (ya se importa `warehousesApi`; agregar este fetch en paralelo) para obtener `default_tax_rate`.
2. Agregar un `computed` `previewTotal` con `calculateDocumentTotals()` sobre `items.value` (ver diseño §8.2).
3. Agregar estado `installments` (array) y mostrar `<InstallmentPlanFields v-if="form.provider_document_type === 'invoice'" v-model="installments" :total="previewTotal" :issue-date="form.receipt_date" :default-due-date="...">` bajo `<ReceiptItemsTable>` (la fecha por defecto de cuota única requiere los `credit_days` del proveedor de la orden seleccionada — `selectedOrder.value.provider.credit_days` si el endpoint de la orden ya lo expone; si no, usar `form.receipt_date` tal cual y documentarlo como aproximación visual, ya que el backend siempre calcula el default real).
4. En `submitForm(true)`, pasar `{ installments: installments.value }` a `purchaseReceiptsApi.confirm(receipt.id, ...)`. En `submitForm(false)`, no enviar cuotas.
5. Si la respuesta de `confirm()` trae `meta.warnings` no vacío, mostrarlo con `notify.warning(...)` (o `alert()` si no existe ese método en `useNotify`) antes de navegar.
**Tests:**
- Cubierto por T-5 (backend) + verificación manual.
**Criterios de done:**
- [ ] `npm run lint` en verde.
- [ ] Verificación manual: elegir una OC, tipo de comprobante "Factura", dividir en 3 cuotas, "Guardar y confirmar", ver las 3 cuotas en la factura generada.

---

### T-11 — Diálogo "Recibir todo" muestra la calculadora
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-7, T-9
**Archivos clave:**
- `front-vue/src/pages/purchases/orders/[id].vue`
**Descripción:**
1. Al abrir `receiveAllDialog` (`openReceiveAllDialog()`), si `provider_document_type` termina en `'invoice'`, pedir `purchaseReceiptsApi.getPendingItems(orderId)` para tener ítems con `unit_cost_preview`/`tax_type` (igual que hace `receipts/new.vue`); guardarlos en un `ref` local del diálogo.
2. Ampliar `VDialog` de `max-width="500"` a `max-width="700"`.
3. Agregar `<InstallmentPlanFields v-if="receiveAllForm.provider_document_type === 'invoice'" ...>` al template del diálogo, con el mismo cálculo de `previewTotal` que T-10 (duplicar el `computed`, o extraer un composable compartido `usePurchaseInvoicePreviewTotal(items, companyTaxRate)` si se prefiere evitar duplicación — a criterio de quien implemente, no es un requisito duro).
4. `confirmReceiveAll()` agrega `installments: planRows` al payload de `purchaseReceiptsApi.receiveAll(...)` cuando el bloque está visible.
5. Mostrar `meta.warnings` de la respuesta igual que T-10.
**Tests:**
- Cubierto por T-5 (backend) + verificación manual.
**Criterios de done:**
- [ ] `npm run lint` en verde.
- [ ] Verificación manual: "Recibir todo" con tipo "Factura" y 3 cuotas genera la factura con esas 3 cuotas.

---

### T-12 — `receipts/[id].vue` permite definir cuotas al confirmar un borrador
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-6, T-7, T-9
**Archivos clave:**
- `front-vue/src/pages/purchases/receipts/[id].vue`
**Descripción:**
1. Agregar un diálogo (`VDialog`) que se abre al click en "Confirmar" en vez de llamar directo a `confirmReceipt()`, **solo si** `receipt.value.provider_document_type === 'invoice'` (si es `guide`, el botón "Confirmar" sigue llamando directo, sin diálogo, como hoy).
2. Dentro del diálogo, `<InstallmentPlanFields>` con `total` calculado desde `receipt.value.items` (ya tienen `quantity`/`unit_cost`/ahora `tax_type` gracias a T-6) y la tasa de impuesto de la empresa (`configurationApi.getSummary()`).
3. El botón "Confirmar" del diálogo llama a `purchaseReceiptsApi.confirm(receiptId, { installments: planRows })` y sigue el mismo flujo de `notify.success`/`load()` que ya tiene `confirmReceipt()`.
4. Mostrar `meta.warnings` de la respuesta igual que T-10/T-11.
**Tests:**
- Cubierto por T-5 (backend) + verificación manual.
**Criterios de done:**
- [ ] `npm run lint` en verde.
- [ ] Verificación manual: crear un borrador con tipo "Factura", confirmarlo después definiendo cuotas, ver el resultado en la factura generada.

---

### T-13 — Delta de reglas
**Estado:** ✅ · **Subj:** docs · **Complejidad:** S · **Depende de:** T-1..T-12
**Archivos clave:**
- `.claude/specs/034-purchase-receipt-installment-plan/CLAUDE.md.delta.md` (ya redactado en este spec)
- `.agents/rules/` (mergear al cerrar el spec)
**Descripción:**
1. Al terminar y validar T-1..T-12, mergear el contenido de `CLAUDE.md.delta.md` de este spec en `.agents/rules/` (crear o extender el archivo que corresponda, ver el propio delta).
2. Actualizar `Status` de los 4 archivos de este spec a `Done` y la fila en `.claude/specs/README.md`.
**Tests:**
- No aplica.
**Criterios de done:**
- [ ] Reglas mergeadas en `.agents/rules/`.
- [ ] `requirements.md`, `design.md`, `todo.md`, `CLAUDE.md.delta.md` con `Status: Done`.
- [ ] Fila de este spec en `.claude/specs/README.md` con estado `Done`.
