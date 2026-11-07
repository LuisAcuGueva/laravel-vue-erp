# 034 · purchase-receipt-installment-plan — Design
> **Spec:** 034 · **Alcance:** fullstack · **Módulo:** Purchases · **Prioridad:** media · **Depende de specs:** 022, 023, 024
> **Status:** Done
> **Última actualización:** 2026-10-24
> **Autores:** Luis Acuña Guevara
>
> **Prerequisito de lectura:** [requirements.md](./requirements.md) — define el qué/por qué; este documento define el cómo.
> **Reglas:** [.agents/rules/](../../../.agents/rules/) · **Makefile:** [Makefile](../../../Makefile)
>
---
## 0. Ubicación y alcance
No se crea ningún módulo nuevo. Todo el trabajo es **extensión** de código existente del módulo `Purchases`:

- Backend: `app/Modules/Purchases/{Requests,Services,Controllers}/` (archivos ya existentes de los specs 022/023/024).
- Frontend: `front-vue/src/{pages/purchases,modules/purchases}/` (archivos ya existentes).
- No hay migraciones nuevas (`payable_installments` y `supplier_invoices` ya soportan múltiples cuotas desde el spec 024).
- No hay endpoints nuevos: se extienden `POST /purchases/receipts/{id}/confirm` y `POST /purchases/orders/{id}/receive-all`.

## 1. Backend — Modelo y Persistencia
No aplica — no hay cambios de esquema. Se reutilizan `SupplierInvoice`, `PayableInstallment`, `SupplierInvoiceItem`, `PurchaseReceipt` tal como están.

## 2. Backend — Lógica de Negocio y Servicios

### 2.1 `SupplierInvoiceService`
Cambiar la firma de:
```php
public function createOrAttachFromConfirmedReceipt(PurchaseReceipt $receipt, User $user): void
```
a:
```php
public function createOrAttachFromConfirmedReceipt(PurchaseReceipt $receipt, User $user, ?array $installments = null): void
```

Dentro del método, en la rama de **creación** (cuando `$existing === null`), pasar `'installments' => $installments ?? []` al array que ya se construye para `$this->create(...)`. `create()` ya delega en `buildInstallments()`, que:
- Si el array viene vacío → cuota única al `due_date` por defecto (comportamiento actual, sin cambios).
- Si viene con filas → valida `Σ amount === total` y `due_date >= issue_date`, lanzando `PurchasesRuleException` si no cumple.

En la rama de **fusión** (cuando `$existing !== null`, vía `attachReceiptToInvoice()`), **no se usa `$installments`** — se ignora explícitamente (US-4). Si `$installments` no es nulo/vacío en ese caso, se agrega una advertencia a un array de warnings que el método ahora debe exponer (ver 2.2).

### 2.2 Exponer warnings hacia arriba
Hoy `createOrAttachFromConfirmedReceipt()` es `void` y atrapa cualquier `Throwable` con `report($e)`, sin feedback al llamador (ver `PurchaseReceiptConfirmationService::confirm()` paso 7). Esto debe cambiar para dos casos nuevos, pero **sin romper la regla existente** de que los fallos ajenos al usuario no bloquean la recepción:

```php
/**
 * @param  list<array{due_date: string, amount: string|float}>|null  $installments
 * @return list<string> advertencias para mostrar al usuario (nunca lanza para fallos ajenos al usuario)
 *
 * @throws PurchasesRuleException cuando $installments fue indicado explícitamente por el usuario y es inválido
 */
public function createOrAttachFromConfirmedReceipt(PurchaseReceipt $receipt, User $user, ?array $installments = null): array
```

Reglas dentro del método:
1. Si `provider_document_type !== Invoice` o no hay `document_number` → `return []` (sin tocar `$installments`, igual que hoy).
2. Si hay factura existente (fusión) → si `$installments` no está vacío, agregar el warning de US-4 al array de retorno y continuar fusionando sin usarlo. Nunca lanza por esto.
3. Si es creación nueva:
   - Si `$installments` es `null` o `[]` → comportamiento actual (cuota única), dentro del mismo `try/catch(Throwable)` silencioso que ya existe (un fallo aquí — p. ej. sin tipo de cambio — no debe bloquear la recepción).
   - Si `$installments` **no** está vacío (el usuario lo definió explícitamente) → la llamada a `create()` **no** se envuelve en el `try/catch` silencioso para los errores de tipo `PurchasesRuleException` que vengan específicamente de `buildInstallments()`/`assertDocumentIsUnique` sobre esos datos: deben propagarse para que el endpoint responda 422 (Open Question de requirements.md, resuelta así en este diseño). El resto de fallos ajenos (tipo de cambio faltante, etc.) siguen sin bloquear — ver 2.3 para cómo se distingue.

### 2.3 Cómo distinguir "error del usuario" de "error ajeno" sin duplicar try/catch
Para no complicar el método con dos rutas de manejo de errores, la solución más simple y menos propensa a bugs es: **envolver en `try/catch(Throwable)` únicamente cuando `$installments` está vacío**. Cuando el usuario definió un cronograma explícito, se deja que cualquier excepción se propague tal cual (incluida una falta de tipo de cambio, que en ese caso también es razonable mostrar como error, porque el usuario está en medio de un flujo que explícitamente quiere completar con factura y cuotas). Esto simplifica el diseño y evita tener que inspeccionar el tipo de excepción.

```php
public function createOrAttachFromConfirmedReceipt(PurchaseReceipt $receipt, User $user, ?array $installments = null): array
{
    if ($receipt->provider_document_type !== ProviderDocumentType::Invoice) {
        return [];
    }

    $documentNumber = trim((string) $receipt->provider_document_number);
    if ($documentNumber === '' || self::isReceiptInvoiced($receipt->id)) {
        return [];
    }

    $hasCustomPlan = ! empty($installments);
    $warnings = [];

    $run = function () use ($receipt, $user, $documentNumber, $installments, &$warnings): void {
        DB::transaction(function () use ($receipt, $user, $documentNumber, $installments, &$warnings): void {
            $prefill = $this->prefill([$receipt->id], $receipt->branch_id);

            $existing = SupplierInvoice::query()
                ->where('provider_id', $receipt->provider_id)
                ->where('document_type', SupplierDocumentType::Invoice->value)
                ->whereRaw("COALESCE(series, '') = ''")
                ->where('document_number', $documentNumber)
                ->where('status', '<>', SupplierInvoiceStatus::Cancelled->value)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if (! empty($installments)) {
                    $warnings[] = "La factura {$existing->number} ya existe; el cronograma indicado no se aplicó, la recepción se agregó a su cuota vigente.";
                }
                $this->attachReceiptToInvoice($existing, $receipt, $prefill['items']);

                return;
            }

            $this->create([
                // ...igual que hoy...
                'installments' => $installments ?? [],
            ], $user, $receipt->branch);
        });
    };

    if ($hasCustomPlan) {
        $run(); // deja propagar cualquier excepción (422 visible)
    } else {
        try {
            $run();
        } catch (Throwable $e) {
            report($e);
        }
    }

    return $warnings;
}
```

### 2.4 `PurchaseReceiptConfirmationService`
```php
public function confirm(PurchaseReceipt $receipt, User $user, ?array $installments = null): array
```
Cambia de devolver `PurchaseReceipt` a devolver `array{receipt: PurchaseReceipt, warnings: list<string>}` (mismo patrón que `SupplierInvoiceService::create()` ya usa). Paso 7 pasa a:
```php
$warnings = $this->supplierInvoiceService->createOrAttachFromConfirmedReceipt($lockedReceipt, $user, $installments);

return [
    'receipt' => $this->receiptService->findById($lockedReceipt->id) ?? $lockedReceipt,
    'warnings' => $warnings,
];
```
`receiveAll()` reenvía `$header['installments'] ?? null` a `confirm()` y propaga su resultado (hoy retorna `PurchaseReceipt`; pasa a retornar el mismo array).

### 2.5 `PurchaseReceiptController`
```php
public function confirm(string $id, ConfirmPurchaseReceiptRequest $request): JsonResponse
{
    $receipt = $this->resolve($id, $request);
    /** @var User $user */
    $user = $request->user();

    ['receipt' => $confirmed, 'warnings' => $warnings] = $this->confirmationService->confirm(
        $receipt,
        $user,
        $request->validated('installments'),
    );

    return (new PurchaseReceiptResource($confirmed))
        ->additional(['meta' => ['warnings' => $warnings]])
        ->response();
}
```
`receiveAll()` sigue el mismo patrón, agregando `'meta' => ['warnings' => $warnings]` a su respuesta `201`.

## 3. Backend — Presentation (Controllers, Requests, Resources)

### 3.1 `ConfirmPurchaseReceiptRequest` (nuevo)
`back-laravel/app/Modules/Purchases/Requests/ConfirmPurchaseReceiptRequest.php`:
```php
<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmPurchaseReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'installments' => ['nullable', 'array', 'max:60'],
            'installments.*.due_date' => ['required', 'date'],
            'installments.*.amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
        ];
    }
}
```
(mismas reglas que `StoreSupplierInvoiceRequest::rules()` para `installments.*`, sin reinventar validación).

Reemplaza el `Request $request` plano que usa hoy `PurchaseReceiptController::confirm()`.

### 3.2 `ReceiveAllRequest` (extender)
Agregar al `rules()` existente:
```php
'installments' => ['nullable', 'array', 'max:60'],
'installments.*.due_date' => ['required', 'date'],
'installments.*.amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
```

### 3.3 `PurchaseReceiptResource`
Sin cambios de estructura — el `meta.warnings` se agrega en la respuesta del controller (vía `->additional()`), no en el resource.

## 4. Contrato API

| Método | Ruta | Middleware / Permiso | Request Body (nuevo campo) | Respuesta exitosa | Errores posibles |
|---|---|---|---|---|---|
| POST | `/api/v1/purchases/receipts/{id}/confirm` | `permission:purchase_receipts.confirm` | `{ "installments"?: [{ "due_date": "2026-11-20", "amount": "300.00" }, ...] }` | `200 { "data": {...recepción...}, "meta": { "warnings": ["..."] } }` | `422` si `installments` no cuadra con el total o tiene fecha inválida y fue indicado explícitamente; `404` si no existe o no es accesible; `422` "Solo se pueden confirmar recepciones en estado borrador" (sin cambio) |
| POST | `/api/v1/purchases/orders/{id}/receive-all` | `permission:purchase_receipts.create` + `permission:purchase_receipts.confirm` | agrega `"installments"?: [...]` al body ya existente | `201 { "data": {...recepción...}, "meta": { "warnings": ["..."] } }` | igual que `confirm`, más los 422 ya existentes de `receiveAll()` |

Ejemplo de request a `confirm`:
```json
{
  "installments": [
    { "due_date": "2026-11-20", "amount": "275.33" },
    { "due_date": "2026-12-20", "amount": "275.33" },
    { "due_date": "2027-01-20", "amount": "275.34" }
  ]
}
```

Ejemplo de respuesta con aviso de fusión (US-4):
```json
{
  "data": { "id": "...", "status": "confirmed", "supplier_invoices": [{ "id": "...", "number": "FP-000003", "status": "pending", "document_number": "F001-900", "total": "826.00" }] },
  "meta": { "warnings": ["La factura FP-000003 ya existe; el cronograma indicado no se aplicó, la recepción se agregó a su cuota vigente."] }
}
```

## 5. Permisos y seguridad
Sin cambios. El cronograma viaja dentro de los mismos endpoints protegidos por `purchase_receipts.confirm`/`purchase_receipts.create` (`.agents/rules/api-authorization.md`). No se expone ningún dato financiero adicional que no estuviera ya accesible desde `/purchases/supplier-invoices`.

## 6. Migraciones
No aplica — sin cambios de esquema.

## 7. Configuración e infraestructura
No aplica — sin variables de entorno ni cambios de infraestructura nuevos.

## 8. Frontend

### 8.1 Nuevo componente compartido
`front-vue/src/modules/purchases/components/payables/InstallmentPlanFields.vue`

Extraído del bloque "Cuotas por pagar" que hoy vive inline en `SupplierInvoiceForm.vue` (líneas ~598-699: `splitCount`, `splitInterval`, botón "Dividir en cuotas", filas editables de `due_date`/`amount`, resumen "Suma de cuotas: X / total Y" con color de validación).

Props:
```js
defineProps({
  modelValue: { type: Array, default: () => [] },  // [{ due_date, amount }]
  total: { type: [Number, String], required: true },
  issueDate: { type: String, required: true },
  defaultDueDate: { type: String, required: true },  // issue_date + provider.credit_days, para "cuota única"
  currencySymbol: { type: String, default: 'S/' },
  currencyDecimals: { type: Number, default: 2 },
  errors: { type: Object, default: () => ({}) },
})
defineEmits(['update:modelValue'])
```
Misma UX que hoy: `splitInstallments()` reparte en N cuotas cada M días (la última absorbe el redondeo); cada fila queda editable; se muestra el estado "Cuota única al {defaultDueDate}" cuando `modelValue` está vacío, igual que en `SupplierInvoiceForm.vue`.

`SupplierInvoiceForm.vue` se refactoriza para usar este componente en vez de su bloque inline — sin cambiar su comportamiento observable (es la primera verificación de que la extracción no rompió nada).

### 8.2 Cálculo del total de previsualización
Las tres pantallas de recepción no tienen un `DocumentLinesTable` con totales reactivos (solo cantidades sobre ítems pendientes). El total que alimenta a `InstallmentPlanFields` se calcula con el mismo util que ya usa `SupplierInvoiceForm`:

```js
import { calculateDocumentTotals } from '@/utils/documentTotals'

const previewTotal = computed(() => calculateDocumentTotals(
  items.value.filter(i => Number(i.quantity) > 0).map(i => ({
    quantity: i.quantity,
    unitPrice: i.unit_cost_preview,
    taxType: i.tax_type,
  })),
  { taxRate: company.value.default_tax_rate, pricesIncludeTax: false },
).total)
```
`company.value` se obtiene de `configurationApi.getSummary()` (igual que en `SupplierInvoiceForm.vue`). Es solo previsualización — el backend recalcula el total real al confirmar y es quien valida que `installments` cuadre (US-1).

### 8.3 `orders/[id].vue` — diálogo "Recibir todo"
- `receiveAllDialog` pasa de `max-width="500"` a `max-width="700"`.
- Al abrir el diálogo, si aún no se cargaron, pedir `purchaseReceiptsApi.getPendingItems(orderId)` (mismo endpoint que ya usa `receipts/new.vue`) para tener `unit_cost_preview`/`tax_type` por línea.
- Mostrar `<InstallmentPlanFields>` solo si `receiveAllForm.provider_document_type === 'invoice'`, debajo de los campos existentes.
- `confirmReceiveAll()` agrega `installments: planRows` (solo si el bloque está visible y tiene filas) al payload de `purchaseReceiptsApi.receiveAll()`.
- Si la respuesta trae `meta.warnings`, mostrarlos con `useNotify().warning(...)` o `useConfirm().alert(...)` antes del mensaje de éxito.

### 8.4 `receipts/new.vue`
- Ya tiene `items` con `unit_cost_preview`/`tax_type` (de `pendingItems()`) — no requiere pedir nada nuevo.
- Mostrar `<InstallmentPlanFields>` bajo `<ReceiptItemsTable>`, solo si `form.provider_document_type === 'invoice'`.
- El `total` se recalcula reactivamente cuando cambian las cantidades de `items`.
- `purchaseReceiptsApi.confirm(id, payload)` necesita aceptar un segundo argumento (ver 8.6).
- `submitForm(true)` (Guardar y confirmar) envía `{ installments: planRows }` en la llamada a `confirm()`. `submitForm(false)` (Guardar borrador) no envía cuotas — el cronograma solo aplica al confirmar.

### 8.5 `receipts/[id].vue` — confirmar un borrador
- Caso: un borrador se guardó antes con `provider_document_type = invoice` y se confirma después, en otro momento.
- Nuevo diálogo (`ConfirmReceiptDialog.vue` o inline en la misma página, a decidir con el Open Question de requirements.md) que muestra `<InstallmentPlanFields>` antes de llamar a `confirmReceipt()`, solo si `receipt.provider_document_type === 'invoice'`.
- Fuente de ítems para el total: `receipt.items` ya cargados. Requiere que `PurchaseReceiptItemResource` expida `tax_type` (hoy no lo expone) — agregar ese campo al resource, derivado de `item.orderItem.tax_type` (ya es una relación cargada en `PurchaseReceiptService::findById()`).
- `confirmReceipt()` pasa a `purchaseReceiptsApi.confirm(receiptId, { installments: planRows })`.
- Si la respuesta trae `meta.warnings`, mostrarlos con `useConfirm().alert(...)` tras el `notify.success(...)` existente.

### 8.6 `purchaseReceiptsApi.js`
```js
confirm(id, payload = {}) {
  return api.post(`/purchases/receipts/${id}/confirm`, payload)
}
```
Cambio compatible: las llamadas existentes sin segundo argumento siguen funcionando (`payload` default `{}`).

### 8.7 Estados de carga, vacío y error
- Mientras se cargan `pendingItems`/`getSummary` para el total previo, `InstallmentPlanFields` no se muestra (o se muestra deshabilitado) — mismo patrón `isLoadingOrder`/`VProgressLinear` que ya usan estas pantallas.
- Si `items` queda vacío (cantidad 0 en todo), el bloque de cuotas se oculta (no hay total que calendarizar).
- Un 422 de `buildInstallments()` se muestra con `setErrors(err)`/`generalError` (patrón `useApiFormErrors` ya usado en `receipts/new.vue` y `SupplierInvoiceForm.vue`), no con un `alert()` genérico, para que el usuario vea el mensaje exacto del backend junto al bloque de cuotas.

## 9. Testing

### 9.1 Backend (`back-laravel/tests/Feature/Modules/Purchases/`)
Extender `PurchaseReceiptsApiTest.php` (ya tiene los tests de auto-creación/fusión del cambio anterior) con:
- `test_confirming_receipt_with_custom_installments_creates_invoice_with_that_schedule`: 3 cuotas que suman el total exacto → `payable_installments` tiene 3 filas con esos montos/fechas.
- `test_confirming_receipt_with_installments_not_matching_total_fails_and_blocks_confirmation`: cuotas que no suman el total → `422`, y la recepción queda en `draft` (no se confirma; verificar con `assertDatabaseHas('purchase_receipts', ['id' => $id, 'status' => 'draft'])` o equivalente).
- `test_confirming_receipt_with_installment_due_date_before_issue_date_fails`: una cuota con `due_date` anterior a `receipt_date` → `422`.
- `test_second_receipt_with_custom_installments_on_merge_is_ignored_with_warning`: recepción que se fusiona en una factura existente (igual que el test ya existente de fusión) + `installments` en el payload → la factura sigue con 1 cuota (sin cambios), y la respuesta trae `meta.warnings` con el aviso de US-4.
- `test_receipt_with_guide_document_and_installments_payload_is_ignored_without_error`: `provider_document_type = guide` + `installments` en el payload → recepción confirmada sin error, sin facturas creadas.
- `receiveAll` equivalente: `test_receive_all_with_custom_installments_creates_invoice_with_that_schedule`.

### 9.2 Frontend
- `npm run lint` en verde tras la extracción de `InstallmentPlanFields.vue` y los cambios en las 3 pantallas.
- Smoke manual (`/run`): recibir una OC completa desde "Recibir todo" con 3 cuotas, verificar en `/purchases/supplier-invoices/{id}` que las 3 cuotas quedaron correctas.

## 10. Decisiones Rechazadas
- **Calcular el total de previsualización con un endpoint backend dedicado** (en vez de `calculateDocumentTotals()` en el cliente): se descarta porque el proyecto ya tiene un util JS vetado para este propósito exacto (usado por `DocumentLinesTable`/`SupplierInvoiceForm`/órdenes de compra), y el backend de todas formas es quien valida el total real al confirmar — un endpoint adicional solo agregaría una ida y vuelta de red sin mejorar la seguridad de los datos.
- **Permitir cronograma personalizado también en la recepción que se fusiona (US-4)**, repartiendo o re-calendarizando la factura existente: se descarta por riesgo — alterar una factura que ya puede estar siendo gestionada (aunque sin pagos, nada impide que otro usuario la esté viendo/exportando) solo porque llegó una segunda recepción es una sorpresa innecesaria; se prefiere mantener fijo el cronograma de la primera recepción y limitarse a sumar el monto a su cuota vigente, como ya funciona.
- **Reusar el componente de ventas `InstallmentPlanEditor.vue` tal cual** para esta UI: se descarta porque su UX es de solo-previsualización (no permite editar filas individuales) y no tiene el límite de 60 cuotas ni el patrón de validación visual que ya usa `SupplierInvoiceForm.vue`; se prefiere extraer el bloque de `SupplierInvoiceForm.vue`, que es el que mejor encaja con `buildInstallments()`.
