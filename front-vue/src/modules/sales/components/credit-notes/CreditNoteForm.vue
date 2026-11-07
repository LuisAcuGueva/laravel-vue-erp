<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import PaymentMethodFields from '@/components/common/PaymentMethodFields.vue'
import { useNotify } from '@/composables/useNotify'
import { warehousesApi } from '@/modules/inventory/services/inventorySettingsApi'
import CreditNoteLinesTable from './CreditNoteLinesTable.vue'
import { creditNotesApi } from '../../services/creditNotesApi'

const props = defineProps({
  prefillData: {
    type: Object,
    required: true,
  },
})

const router = useRouter()
const notify = useNotify()

const CREDIT_NOTE_REASONS = [
  { value: '01', title: '01 · Anulación de la operación' },
  { value: '04', title: '04 · Descuento global' },
  { value: '05', title: '05 · Descuento por ítem' },
  { value: '06', title: '06 · Devolución total' },
  { value: '07', title: '07 · Devolución por ítem' },
  { value: '09', title: '09 · Disminución en el valor' },
]

const SETTLEMENT_MODES = [
  { value: 'auto', title: 'Automático (rebaja saldo de la venta y reembolsa el exceso)' },
  { value: 'refund', title: 'Reembolso directo (sin modificar saldo ni cuotas de la venta)' },
]

const sale = computed(() => props.prefillData?.sale || {})
const lines = computed(() => props.prefillData?.lines || [])
const seriesList = computed(() => props.prefillData?.series || [])
const maxCreditable = computed(() => parseFloat(props.prefillData?.max_creditable) || 0)
const saleBalance = computed(() => parseFloat(props.prefillData?.balance) || 0)
const currencyDecimals = computed(() => sale.value.currency?.decimals ?? 2)

const today = new Date().toISOString().slice(0, 10)

const form = ref({
  sale_id: sale.value.id,
  document_series_id: seriesList.value[0]?.id || null,
  reason_code: '07',
  reason_description: '',
  issue_date: today,
  warehouse_id: sale.value.warehouse_id || null,
  amount: 0,
  items: [],
  settlement: {
    mode: 'auto',
    payment_method_id: null,
    cash_session_id: null,
    reference: '',
  },
})

const warehouses = ref([])
const errors = ref({})
const isSubmitting = ref(false)

const isReturnReason = computed(() => ['01', '06', '07'].includes(form.value.reason_code))

// Inicializar o ajustar items según el motivo seleccionado
const syncItemsForReason = reason => {
  if (['01', '06'].includes(reason)) {
    // Todos los ítems pendientes van incluidos
    form.value.items = lines.value
      .filter(l => parseFloat(l.pending_quantity) > 0)
      .map(l => ({
        sale_item_id: l.id,
        quantity: parseFloat(l.pending_quantity),
        amount: 0,
        return_to_stock: Boolean(l.track_stock),
      }))
  }
  else if (reason === '07') {
    // Inicializar con todos o vacío para selección manual
    if (form.value.items.length === 0) {
      form.value.items = lines.value
        .filter(l => parseFloat(l.pending_quantity) > 0)
        .map(l => ({
          sale_item_id: l.id,
          quantity: parseFloat(l.pending_quantity),
          amount: 0,
          return_to_stock: Boolean(l.track_stock),
        }))
    }
  }
  else if (['05', '09'].includes(reason)) {
    form.value.items = lines.value.map(l => ({
      sale_item_id: l.id,
      quantity: 0,
      amount: 0,
      return_to_stock: false,
    }))
  }
  else if (reason === '04') {
    form.value.items = []
  }
}

watch(() => form.value.reason_code, newReason => {
  syncItemsForReason(newReason)
}, { immediate: true })

onMounted(async () => {
  try {
    const res = await warehousesApi.options()

    warehouses.value = res?.data || []
    if (!form.value.warehouse_id && warehouses.value.length > 0) {
      form.value.warehouse_id = warehouses.value[0].id
    }
  }
  catch (err) {
    console.error('Error cargando almacenes:', err)
  }
})

// Cálculo del total estimado de la nota de crédito
const calculatedTotal = computed(() => {
  const code = form.value.reason_code
  if (code === '04') {
    return parseFloat(form.value.amount) || 0
  }

  if (['05', '09'].includes(code)) {
    return form.value.items.reduce((acc, it) => acc + (parseFloat(it.amount) || 0), 0)
  }

  // Motivos 01, 06, 07 basados en cantidad
  return form.value.items.reduce((acc, it) => {
    const line = lines.value.find(l => l.id === it.sale_item_id)
    if (!line) return acc

    const qty = parseFloat(it.quantity) || 0
    const unitPrice = parseFloat(line.unit_price) || 0
    const discountPct = parseFloat(line.discount_percent) || 0
    const netPrice = unitPrice * (1 - discountPct / 100)

    return acc + (qty * netPrice)
  }, 0)
})

// Vista previa de liquidación
const settlementPreview = computed(() => {
  const total = calculatedTotal.value
  const mode = form.value.settlement.mode
  const balance = saleBalance.value

  if (mode === 'refund') {
    return {
      debtReduction: 0,
      refundAmount: total,
    }
  }

  // modo 'auto'
  const debtReduction = Math.min(total, balance)
  const refundAmount = Math.max(0, total - balance)

  return {
    debtReduction,
    refundAmount,
  }
})

const seriesOptions = computed(() => seriesList.value.map(s => ({
  value: s.id,
  title: `${s.series} ${s.is_electronic ? '(Electrónica SUNAT)' : '(Interna)'}`,
})))

const warehouseOptions = computed(() => warehouses.value.map(w => ({
  value: w.id,
  title: `${w.code} - ${w.name}`,
})))

const handleSubmit = async () => {
  errors.value = {}
  isSubmitting.value = true

  try {
    const payload = {
      sale_id: form.value.sale_id,
      document_series_id: form.value.document_series_id,
      reason_code: form.value.reason_code,
      reason_description: form.value.reason_description,
      issue_date: form.value.issue_date,
      warehouse_id: isReturnReason.value ? form.value.warehouse_id : null,
      settlement: {
        mode: form.value.settlement.mode,
        payment_method_id: form.value.settlement.payment_method_id,
        cash_session_id: form.value.settlement.cash_session_id,
        reference: form.value.settlement.reference,
      },
    }

    if (form.value.reason_code === '04') {
      payload.amount = parseFloat(form.value.amount) || 0
    }
    else {
      payload.items = form.value.items
        .filter(it => (form.value.reason_code === '07' || ['01', '06'].includes(form.value.reason_code) ? it.quantity > 0 : it.amount > 0))
        .map(it => ({
          sale_item_id: it.sale_item_id,
          quantity: it.quantity,
          amount: it.amount,
          return_to_stock: Boolean(it.return_to_stock),
        }))
    }

    const res = await creditNotesApi.create(payload)

    notify.success('Nota de crédito emitida correctamente')

    const createdId = res?.data?.id
    if (createdId) {
      router.push(`/sales/credit-notes/${createdId}`)
    }
    else {
      router.push('/sales/credit-notes')
    }
  }
  catch (err) {
    if (err?.data?.errors) {
      errors.value = err.data.errors
    }
    else {
      notify.error(err?.data?.message || err?.message || 'Error al emitir nota de crédito')
    }
  }
  finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <form @submit.prevent="handleSubmit">
    <div class="d-flex flex-column gap-6">
      <!-- 1. Tarjeta Resumen de la Venta -->
      <VCard>
        <VCardItem>
          <VCardTitle class="d-flex align-center gap-2">
            <VIcon
              icon="tabler-file-invoice"
              color="primary"
            />
            <span>Comprobante Afectado</span>
          </VCardTitle>
        </VCardItem>

        <VCardText>
          <VRow>
            <VCol
              cols="12"
              md="3"
            >
              <div class="text-caption text-medium-emphasis">
                COMPROBANTE
              </div>
              <div class="text-body-1 font-weight-bold font-mono">
                {{ sale.full_number }}
              </div>
            </VCol>

            <VCol
              cols="12"
              md="3"
            >
              <div class="text-caption text-medium-emphasis">
                CLIENTE
              </div>
              <div class="text-body-1 font-weight-medium">
                {{ sale.client?.name }}
              </div>
              <div class="text-caption text-medium-emphasis font-mono">
                Doc: {{ sale.client?.document_number || 'S/D' }}
              </div>
            </VCol>

            <VCol
              cols="6"
              md="2"
            >
              <div class="text-caption text-medium-emphasis">
                TOTAL ORIGINAL
              </div>
              <div class="text-body-1 font-mono font-weight-bold">
                {{ sale.currency?.symbol }} {{ parseFloat(sale.total || 0).toFixed(currencyDecimals) }}
              </div>
            </VCol>

            <VCol
              cols="6"
              md="2"
            >
              <div class="text-caption text-medium-emphasis">
                SALDO PENDIENTE
              </div>
              <div class="text-body-1 font-mono font-weight-bold text-warning">
                {{ sale.currency?.symbol }} {{ saleBalance.toFixed(currencyDecimals) }}
              </div>
            </VCol>

            <VCol
              cols="12"
              md="2"
            >
              <div class="text-caption text-medium-emphasis">
                MÁX. ACREDITABLE
              </div>
              <div class="text-body-1 font-mono font-weight-bold text-success">
                {{ sale.currency?.symbol }} {{ maxCreditable.toFixed(currencyDecimals) }}
              </div>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>

      <!-- 2. Datos de Emisión de la Nota de Crédito -->
      <VCard>
        <VCardItem>
          <VCardTitle class="d-flex align-center gap-2">
            <VIcon
              icon="tabler-receipt-refund"
              color="primary"
            />
            <span>Datos de la Nota de Crédito</span>
          </VCardTitle>
        </VCardItem>

        <VCardText>
          <VRow>
            <VCol
              cols="12"
              md="4"
            >
              <VSelect
                v-model="form.document_series_id"
                :items="seriesOptions"
                label="Serie del comprobante *"
                placeholder="Seleccione la serie"
                :error-messages="errors.document_series_id"
                required
              />
            </VCol>

            <VCol
              cols="12"
              md="4"
            >
              <VSelect
                v-model="form.reason_code"
                :items="CREDIT_NOTE_REASONS"
                label="Motivo de la emisión (Catálogo 09) *"
                placeholder="Seleccione el motivo"
                :error-messages="errors.reason_code"
                required
              />
            </VCol>

            <VCol
              cols="12"
              md="4"
            >
              <VTextField
                v-model="form.issue_date"
                type="date"
                label="Fecha de emisión *"
                placeholder="YYYY-MM-DD"
                :error-messages="errors.issue_date"
                required
              />
            </VCol>

            <VCol
              cols="12"
              md="8"
            >
              <VTextField
                v-model="form.reason_description"
                label="Sustento del motivo *"
                placeholder="Ej. Devolución de mercadería defectuosa por garantía comercial"
                :error-messages="errors.reason_description"
                counter="500"
                required
              />
            </VCol>

            <VCol
              v-if="isReturnReason"
              cols="12"
              md="4"
            >
              <VSelect
                v-model="form.warehouse_id"
                :items="warehouseOptions"
                label="Almacén de reingreso de mercadería *"
                placeholder="Seleccione el almacén"
                :error-messages="errors.warehouse_id"
                clearable
              />
            </VCol>
          </VRow>
        </VCardText>
      </VCard>

      <!-- 3. Detalle de Ítems / Descuento Global -->
      <VCard>
        <VCardItem>
          <VCardTitle class="d-flex align-center justify-space-between">
            <div class="d-flex align-center gap-2">
              <VIcon
                icon="tabler-list"
                color="primary"
              />
              <span>Líneas y Valores de la Nota</span>
            </div>

            <div class="text-h6 font-weight-bold font-mono">
              Total Calculado: {{ sale.currency?.symbol }} {{ calculatedTotal.toFixed(currencyDecimals) }}
            </div>
          </VCardTitle>
        </VCardItem>

        <VCardText>
          <div
            v-if="form.reason_code === '04'"
            class="py-4"
          >
            <VRow>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model.number="form.amount"
                  type="number"
                  step="0.01"
                  min="0.01"
                  :max="maxCreditable"
                  label="Monto de descuento global *"
                  placeholder="Ej. 50.00"
                  class="font-mono"
                  :error-messages="errors.amount"
                  required
                />
                <div class="text-caption text-medium-emphasis mt-1">
                  El descuento global se prorrateará automáticamente entre las bases imponibles de la venta.
                  Máximo permitido: {{ sale.currency?.symbol }} {{ maxCreditable.toFixed(currencyDecimals) }}.
                </div>
              </VCol>
            </VRow>
          </div>

          <div v-else>
            <CreditNoteLinesTable
              v-model="form.items"
              :lines="lines"
              :reason-code="form.reason_code"
              :currency-symbol="sale.currency?.symbol || 'S/'"
              :currency-decimals="currencyDecimals"
              :tax-rate="sale.tax_rate || 18"
              :prices-include-tax="sale.prices_include_tax ?? true"
              :errors="errors"
            />
            <div
              v-if="errors.items"
              class="text-caption text-error mt-2"
            >
              {{ Array.isArray(errors.items) ? errors.items.join(', ') : errors.items }}
            </div>
          </div>
        </VCardText>
      </VCard>

      <!-- 4. Liquidación / Aplicación del Monto -->
      <VCard>
        <VCardItem>
          <VCardTitle class="d-flex align-center gap-2">
            <VIcon
              icon="tabler-cash"
              color="primary"
            />
            <span>Liquidación y Aplicación del Monto</span>
          </VCardTitle>
        </VCardItem>

        <VCardText>
          <VRow>
            <VCol
              cols="12"
              md="6"
            >
              <VSelect
                v-model="form.settlement.mode"
                :items="SETTLEMENT_MODES"
                label="Modalidad de aplicación *"
                placeholder="Seleccione la modalidad"
                :error-messages="errors['settlement.mode']"
                required
              />
            </VCol>

            <VCol
              cols="12"
              md="6"
            >
              <VAlert
                color="info"
                variant="tonal"
                icon="tabler-calculator"
                density="compact"
                class="mb-0"
              >
                <div class="font-weight-medium text-body-2">
                  Vista Previa de Liquidación
                </div>
                <div class="text-caption mt-1">
                  Rebaja de deuda pendiente:
                  <strong class="font-mono text-warning">
                    {{ sale.currency?.symbol }} {{ settlementPreview.debtReduction.toFixed(currencyDecimals) }}
                  </strong>
                  · Reembolso directo:
                  <strong class="font-mono text-success">
                    {{ sale.currency?.symbol }} {{ settlementPreview.refundAmount.toFixed(currencyDecimals) }}
                  </strong>
                </div>
              </VAlert>
            </VCol>

            <!-- Reembolso con PaymentMethodFields si hay saldo a reembolsar -->
            <VCol
              v-if="settlementPreview.refundAmount > 0"
              cols="12"
            >
              <VDivider class="my-2" />
              <div class="font-weight-medium text-body-2 mb-3 text-primary d-flex align-center gap-2">
                <VIcon
                  icon="tabler-arrow-back-up"
                  size="18"
                />
                <span>Datos para el Reembolso al Cliente ({{ sale.currency?.symbol }} {{ settlementPreview.refundAmount.toFixed(currencyDecimals) }})</span>
              </div>

              <PaymentMethodFields
                v-model="form.settlement"
                :currency-id="sale.currency?.id"
                :errors="errors"
                error-prefix="settlement."
              />
            </VCol>
          </VRow>
        </VCardText>
      </VCard>

      <!-- Botones de Acción -->
      <div class="d-flex align-center justify-end gap-3">
        <VBtn
          variant="tonal"
          color="secondary"
          @click="router.back()"
        >
          Cancelar
        </VBtn>

        <VBtn
          type="submit"
          color="primary"
          prepend-icon="tabler-check"
          :loading="isSubmitting"
          :disabled="isSubmitting || calculatedTotal <= 0"
        >
          Emitir Nota de Crédito
        </VBtn>
      </div>
    </div>
  </form>
</template>

<style scoped>
.font-mono {
  font-family: monospace;
}
</style>
