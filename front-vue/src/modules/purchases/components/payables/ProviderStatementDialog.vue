<script setup>
/* eslint-disable camelcase */
import { computed, ref, watch } from 'vue'
import { useNotify } from '@/composables/useNotify'
import { accountsPayableApi } from '@/modules/purchases/services/accountsPayableApi'
import { formatDateDMY } from '@/utils/dates'
import { formatAmount } from '@/utils/documentTotals'

/**
 * Estado de cuenta de un proveedor en una moneda: facturas como cargo, pagos como abono y saldo acumulado.
 */
const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  provider: {
    type: Object,
    default: null,
  },
  currency: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['update:modelValue'])

const notify = useNotify()

const firstOfYear = () => `${new Date().getFullYear()}-01-01`
const today = () => new Date().toISOString().slice(0, 10)

const dateFrom = ref(firstOfYear())
const dateTo = ref(today())
const statement = ref(null)
const isLoading = ref(false)

const isOpen = computed({
  get: () => props.modelValue,
  set: value => emit('update:modelValue', value),
})

const symbol = computed(() => statement.value?.currency?.symbol || props.currency?.symbol || 'S/')

const load = async () => {
  if (!props.provider?.id || !dateFrom.value || !dateTo.value)
    return

  isLoading.value = true
  try {
    const res = await accountsPayableApi.statement(props.provider.id, {
      date_from: dateFrom.value,
      date_to: dateTo.value,
      currency_id: props.currency?.id || undefined,
    })

    statement.value = res?.data || null
  }
  catch (err) {
    notify.error(err?.data?.message || 'No se pudo cargar el estado de cuenta.')
  }
  finally {
    isLoading.value = false
  }
}

watch(() => props.modelValue, open => {
  if (open) {
    statement.value = null
    load()
  }
})

watch([dateFrom, dateTo], () => {
  if (props.modelValue)
    load()
})
</script>

<template>
  <VDialog
    v-model="isOpen"
    max-width="860"
  >
    <VCard>
      <VCardItem>
        <VCardTitle>Estado de cuenta</VCardTitle>
        <VCardSubtitle>{{ props.provider?.business_name }} · {{ props.currency?.code }}</VCardSubtitle>
      </VCardItem>

      <VCardText>
        <VRow>
          <VCol
            cols="6"
            md="3"
          >
            <VTextField
              v-model="dateFrom"
              label="Desde *"
              placeholder="2026-01-01"
              type="date"
              density="compact"
            />
          </VCol>
          <VCol
            cols="6"
            md="3"
          >
            <VTextField
              v-model="dateTo"
              label="Hasta *"
              placeholder="2026-12-31"
              type="date"
              density="compact"
            />
          </VCol>
        </VRow>

        <VProgressLinear
          v-if="isLoading"
          indeterminate
        />

        <VTable
          v-else-if="statement"
          density="compact"
        >
          <thead>
            <tr>
              <th>FECHA</th>
              <th>DOCUMENTO</th>
              <th>DETALLE</th>
              <th class="text-end">
                CARGO
              </th>
              <th class="text-end">
                ABONO
              </th>
              <th class="text-end">
                SALDO
              </th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>{{ formatDateDMY(statement.date_from) }}</td>
              <td colspan="4">
                Saldo inicial
              </td>
              <td class="text-end font-weight-medium">
                {{ formatAmount(statement.opening, symbol) }}
              </td>
            </tr>
            <tr
              v-for="entry in statement.entries"
              :key="`${entry.type}-${entry.id}`"
            >
              <td>{{ formatDateDMY(entry.date) }}</td>
              <td class="font-monospace">
                <RouterLink
                  :to="entry.type === 'invoice'
                    ? { name: 'purchases-supplier-invoices-id', params: { id: entry.id } }
                    : { name: 'purchases-supplier-payments-id', params: { id: entry.id } }"
                  class="text-primary text-decoration-none"
                >
                  {{ entry.number }}
                </RouterLink>
              </td>
              <td>{{ entry.description }}</td>
              <td class="text-end">
                {{ Number(entry.charge) > 0 ? formatAmount(entry.charge, symbol) : '' }}
              </td>
              <td class="text-end text-success">
                {{ Number(entry.credit) > 0 ? formatAmount(entry.credit, symbol) : '' }}
              </td>
              <td class="text-end">
                {{ formatAmount(entry.balance, symbol) }}
              </td>
            </tr>
            <tr>
              <td>{{ formatDateDMY(statement.date_to) }}</td>
              <td colspan="4">
                Saldo final
              </td>
              <td class="text-end font-weight-bold">
                {{ formatAmount(statement.closing, symbol) }}
              </td>
            </tr>
          </tbody>
        </VTable>
      </VCardText>

      <VCardActions class="justify-end pa-4">
        <VBtn
          variant="tonal"
          color="secondary"
          @click="isOpen = false"
        >
          Cerrar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
