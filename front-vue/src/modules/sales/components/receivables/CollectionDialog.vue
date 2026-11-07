<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, ref, watch } from 'vue'
import ClientPicker from '@/components/common/ClientPicker.vue'
import PaymentMethodFields from '@/components/common/PaymentMethodFields.vue'
import { useApiFormErrors } from '@/composables/useApiFormErrors'
import { useNotify } from '@/composables/useNotify'
import { currenciesApi } from '@/modules/finance/services/financeSettingsApi'
import { receivablesApi } from '@/modules/sales/services/receivablesApi'
import { formatDateDMY } from '@/utils/dates'
import { formatAmount } from '@/utils/documentTotals'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  installments: {
    type: Array,
    default: () => [],
  },
  defaultClientId: {
    type: String,
    default: null,
  },
  defaultCashSessionId: {
    type: String,
    default: null,
  },
})

const emit = defineEmits(['update:modelValue', 'collected'])

const notify = useNotify()
const { errors, generalError, setErrors, clearErrors } = useApiFormErrors()

const today = () => new Date().toISOString().slice(0, 10)

const isOpen = computed({
  get: () => props.modelValue,
  set: val => emit('update:modelValue', val),
})

const isSubmitting = ref(false)
const isLoadingInstallments = ref(false)
const currencies = ref([])

const clientId = ref(props.defaultClientId)
const selectedClient = ref(null)
const currencyId = ref(null)
const collectionDate = ref(today())
const autoAllocate = ref(false)
const manualAmount = ref('0.00')
const notes = ref('')

const payment = ref({
  payment_method_id: null,
  cash_session_id: props.defaultCashSessionId,
  reference: '',
})

// Lista de cuotas a pagar: { installment, amount }
const allocationRows = ref([])

const loadCurrencies = async () => {
  try {
    const res = await currenciesApi.options()

    currencies.value = res?.data || []
    if (!currencyId.value && currencies.value.length) {
      const baseCurr = currencies.value.find(c => c.is_base) || currencies.value[0]

      currencyId.value = baseCurr.id
    }
  }
  catch {
    // Silencioso
  }
}

onMounted(() => {
  loadCurrencies()
})

const hasFixedInstallments = computed(() => props.installments && props.installments.length > 0)

const activeCurrency = computed(() => {
  return currencies.value.find(c => c.id === currencyId.value) || null
})

const currencySymbol = computed(() => {
  return activeCurrency.value?.symbol || 'S/'
})

const totalAllocated = computed(() => {
  if (autoAllocate.value) {
    return Number(manualAmount.value || 0)
  }

  const sum = allocationRows.value.reduce((acc, row) => {
    if (!row.selected) return acc

    return acc + Math.round(Number(row.amount || 0) * 100)
  }, 0)

  return sum / 100
})

const loadClientInstallments = async () => {
  if (!clientId.value) {
    allocationRows.value = []

    return
  }

  isLoadingInstallments.value = true
  try {
    const res = await receivablesApi.list({
      client_id: clientId.value,
      currency_id: currencyId.value,
      status: 'pending,partially_paid',
      per_page: 50,
    })

    allocationRows.value = (res?.data || [])
      .filter(inst => Number(inst.balance) > 0 && inst.status !== 'cancelled' && inst.status !== 'paid')
      .map(inst => ({
        installment: inst,
        amount: inst.balance,
        selected: true,
      }))
  }
  catch {
    notify.error('No se pudieron cargar las cuotas pendientes del cliente')
  }
  finally {
    isLoadingInstallments.value = false
  }
}

watch(() => props.modelValue, open => {
  if (!open) return

  clearErrors()
  collectionDate.value = today()
  notes.value = ''
  payment.value = {
    payment_method_id: null,
    cash_session_id: props.defaultCashSessionId,
    reference: '',
  }

  if (hasFixedInstallments.value) {
    const first = props.installments[0]

    clientId.value = first.client_id || first.sale?.client_id || null
    selectedClient.value = first.client || first.sale?.client || null
    currencyId.value = first.currency_id || first.currency?.id || null
    autoAllocate.value = false
    allocationRows.value = props.installments
      .filter(inst => Number(inst.balance) > 0 && inst.status !== 'cancelled' && inst.status !== 'paid')
      .map(inst => ({
        installment: inst,
        amount: inst.balance,
        selected: true,
      }))
  }
  else {
    clientId.value = props.defaultClientId
    autoAllocate.value = false
    manualAmount.value = '0.00'
    if (clientId.value) {
      loadClientInstallments()
    }
    else {
      allocationRows.value = []
    }
  }
})

watch(() => clientId.value, () => {
  if (!hasFixedInstallments.value && props.modelValue) {
    loadClientInstallments()
  }
})

watch(() => currencyId.value, () => {
  if (!hasFixedInstallments.value && props.modelValue && clientId.value) {
    loadClientInstallments()
  }
})

const onClientSelect = client => {
  selectedClient.value = client
  clientId.value = client?.id || null
}

const submit = async () => {
  clearErrors()

  if (!clientId.value) {
    notify.error('Debe seleccionar un cliente.')

    return
  }

  if (!currencyId.value) {
    notify.error('Debe seleccionar una moneda.')

    return
  }

  if (totalAllocated.value <= 0) {
    notify.error('El monto total a cobrar debe ser mayor a cero.')

    return
  }

  isSubmitting.value = true
  try {
    const payload = {
      client_id: clientId.value,
      currency_id: currencyId.value,
      collection_date: collectionDate.value,
      amount: totalAllocated.value,
      payment_method_id: payment.value.payment_method_id,
      cash_session_id: payment.value.cash_session_id || null,
      reference: payment.value.reference?.trim() || null,
      notes: notes.value?.trim() || null,
      auto_allocate: autoAllocate.value,
    }

    if (!autoAllocate.value) {
      payload.allocations = allocationRows.value
        .filter(r => r.selected && Number(r.amount) > 0)
        .map(r => ({
          installment_id: r.installment.id,
          amount: Number(r.amount),
        }))

      if (!payload.allocations.length) {
        notify.error('Debe seleccionar al menos una cuota con monto mayor a cero.')
        isSubmitting.value = false

        return
      }
    }

    const res = await receivablesApi.createCollection(payload)

    notify.success('Cobranza registrada correctamente')
    emit('collected', res.data)
    isOpen.value = false
  }
  catch (err) {
    setErrors(err)
  }
  finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <VDialog
    v-model="isOpen"
    max-width="820"
    persistent
  >
    <VCard>
      <VCardItem class="pb-2">
        <VCardTitle class="d-flex align-center justify-space-between">
          <span>Registrar Cobranza</span>
          <VChip
            size="small"
            color="primary"
            variant="tonal"
          >
            {{ currencySymbol }} {{ formatAmount(totalAllocated) }}
          </VChip>
        </VCardTitle>
      </VCardItem>

      <VDivider />

      <VCardText class="pt-4">
        <VAlert
          v-if="generalError"
          type="error"
          variant="tonal"
          class="mb-4"
        >
          {{ generalError }}
        </VAlert>

        <VRow>
          <!-- Cliente -->
          <VCol
            cols="12"
            md="7"
          >
            <ClientPicker
              v-if="!hasFixedInstallments"
              v-model="clientId"
              :initial-item="selectedClient"
              :readonly="hasFixedInstallments"
              :error-messages="errors.client_id"
              label="Cliente *"
              placeholder="Buscar cliente por documento o nombre"
              @selected="onClientSelect"
              @change="onClientSelect"
            />
            <VTextField
              v-else
              :model-value="selectedClient?.name || installments[0]?.client_name || 'Cliente seleccionado'"
              label="Cliente"
              placeholder="Cliente seleccionado"
              readonly
            />
          </VCol>

          <!-- Fecha de Cobro -->
          <VCol
            cols="12"
            md="5"
          >
            <VTextField
              v-model="collectionDate"
              type="date"
              label="Fecha de cobranza *"
              placeholder="YYYY-MM-DD"
              :error-messages="errors.collection_date"
            />
          </VCol>

          <!-- Moneda -->
          <VCol
            cols="12"
            md="4"
          >
            <VSelect
              v-model="currencyId"
              :items="currencies"
              item-title="name"
              item-value="id"
              label="Moneda *"
              placeholder="Seleccione moneda"
              :readonly="hasFixedInstallments"
              :error-messages="errors.currency_id"
            />
          </VCol>

          <!-- Modalidad de asignación -->
          <VCol
            v-if="!hasFixedInstallments"
            cols="12"
            md="8"
            class="d-flex align-center"
          >
            <VSwitch
              v-model="autoAllocate"
              label="Asignación automática FIFO (Aplica al saldo más antiguo)"
              color="primary"
              density="compact"
            />
          </VCol>

          <!-- Si es asignación automática, ingresa monto total -->
          <VCol
            v-if="autoAllocate"
            cols="12"
            md="6"
          >
            <VTextField
              v-model="manualAmount"
              type="number"
              min="0.01"
              step="0.01"
              :prefix="currencySymbol"
              label="Monto total a cobrar *"
              placeholder="0.00"
              :error-messages="errors.amount"
            />
          </VCol>
        </VRow>

        <!-- Cuotas a aplicar si no es auto-allocate -->
        <div
          v-if="!autoAllocate"
          class="mt-4"
        >
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-subtitle-2 font-weight-bold">
              Cuotas pendientes
            </span>
            <span class="text-caption text-medium-emphasis">
              Seleccione las cuotas y modifique el monto a amortizar si es un pago parcial
            </span>
          </div>

          <VProgressLinear
            v-if="isLoadingInstallments"
            indeterminate
            class="mb-3"
          />

          <VAlert
            v-else-if="!allocationRows.length"
            type="info"
            variant="tonal"
            density="compact"
            class="mb-3"
          >
            {{ clientId ? 'No hay cuotas pendientes para este cliente y moneda.' : 'Seleccione un cliente para ver sus cuotas pendientes.' }}
          </VAlert>

          <div
            v-else
            class="border rounded overflow-hidden mb-4"
          >
            <VTable density="compact">
              <thead>
                <tr>
                  <th style="width: 40px;" />
                  <th>DOCUMENTO</th>
                  <th class="text-center">
                    CUOTA
                  </th>
                  <th>VENCIMIENTO</th>
                  <th class="text-end">
                    SALDO
                  </th>
                  <th
                    class="text-end"
                    style="width: 170px;"
                  >
                    MONTO A COBRAR
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="row in allocationRows"
                  :key="row.installment.id"
                >
                  <td>
                    <VCheckbox
                      v-model="row.selected"
                      density="compact"
                      hide-details
                    />
                  </td>
                  <td>
                    <span class="font-weight-medium font-mono">
                      {{ row.installment.sale_full_number || '—' }}
                    </span>
                  </td>
                  <td class="text-center">
                    #{{ row.installment.installment_number || row.installment.number }}
                  </td>
                  <td>
                    <span
                      class="font-mono text-caption"
                      :class="{ 'text-error font-weight-bold': row.installment.is_overdue }"
                    >
                      {{ formatDateDMY(row.installment.due_date) }}
                    </span>
                  </td>
                  <td class="text-end font-mono">
                    {{ currencySymbol }} {{ formatAmount(row.installment.balance) }}
                  </td>
                  <td class="text-end">
                    <VTextField
                      v-model="row.amount"
                      type="number"
                      min="0.01"
                      :max="row.installment.balance"
                      step="0.01"
                      density="compact"
                      hide-details
                      :disabled="!row.selected"
                      class="text-end"
                    />
                  </td>
                </tr>
              </tbody>
            </VTable>
          </div>
        </div>

        <VDivider class="my-4" />

        <!-- Campos de Medio de Pago y Caja/Banco -->
        <span class="text-subtitle-2 font-weight-bold mb-2 d-block">
          Medio de pago y liquidación financiera
        </span>

        <PaymentMethodFields
          v-model="payment"
          :currency-id="currencyId"
          :errors="errors"
        />

        <!-- Observaciones -->
        <VRow class="mt-2">
          <VCol cols="12">
            <VTextarea
              v-model="notes"
              label="Notas u observaciones"
              placeholder="Ej. Depósito transferido con orden de compra #123"
              rows="2"
              density="comfortable"
              :error-messages="errors.notes"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4">
        <VSpacer />
        <VBtn
          variant="outlined"
          color="secondary"
          :disabled="isSubmitting"
          @click="isOpen = false"
        >
          Cancelar
        </VBtn>

        <VBtn
          color="primary"
          variant="elevated"
          :loading="isSubmitting"
          :disabled="isSubmitting || totalAllocated <= 0"
          @click="submit"
        >
          Confirmar Cobranza ({{ currencySymbol }} {{ formatAmount(totalAllocated) }})
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
