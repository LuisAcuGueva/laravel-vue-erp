<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, ref, watch } from 'vue'
import { useNotify } from '@/composables/useNotify'
import { currenciesApi } from '@/modules/finance/services/financeSettingsApi'
import { receivablesApi } from '@/modules/sales/services/receivablesApi'
import { formatDateTime12h } from '@/utils/dates'
import { formatAmount } from '@/utils/documentTotals'

const props = defineProps({
  client: {
    type: Object,
    required: true,
  },
})

const notify = useNotify()

const today = () => new Date().toISOString().slice(0, 10)

const firstDayOfMonth = () => {
  const d = new Date()

  return new Date(d.getFullYear(), d.getMonth(), 1).toISOString().slice(0, 10)
}

const fromDate = ref(firstDayOfMonth())
const toDate = ref(today())
const currencyId = ref(null)
const currencies = ref([])

const isLoading = ref(false)
const isDownloadingPdf = ref(false)
const statementData = ref(null)

const loadCurrencies = async () => {
  try {
    const res = await currenciesApi.options()

    currencies.value = res?.data || []
    if (currencies.value.length && !currencyId.value) {
      const baseCurr = currencies.value.find(c => c.is_base) || currencies.value[0]

      currencyId.value = baseCurr.id
    }
  }
  catch {
    // Silencioso
  }
}

const loadStatement = async () => {
  if (!props.client?.id) return

  isLoading.value = true
  try {
    const res = await receivablesApi.clientStatement(props.client.id, {
      from: fromDate.value,
      to: toDate.value,
      currency_id: currencyId.value || undefined,
    })

    statementData.value = res?.data || null
  }
  catch {
    notify.error('No se pudo cargar el estado de cuenta del cliente.')
  }
  finally {
    isLoading.value = false
  }
}

const downloadPdf = async () => {
  if (!props.client?.id) return

  isDownloadingPdf.value = true
  try {
    await receivablesApi.downloadClientStatementPdf(props.client.id, {
      from: fromDate.value,
      to: toDate.value,
      currency_id: currencyId.value || undefined,
    })
    notify.success('Estado de cuenta descargado correctamente.')
  }
  catch {
    notify.error('No se pudo descargar el PDF del estado de cuenta.')
  }
  finally {
    isDownloadingPdf.value = false
  }
}

onMounted(async () => {
  await loadCurrencies()
  await loadStatement()
})

watch(() => currencyId.value, () => {
  loadStatement()
})

const currencySymbol = computed(() => {
  return statementData.value?.currency?.symbol || 'S/'
})

const documentLink = mov => {
  if (!mov.id) return null

  switch (mov.type) {
  case 'sale':
    return `/sales/history/${mov.id}`
  case 'credit_note':
    return `/sales/credit-notes/${mov.id}`
  case 'collection':
    return `/sales/receivables?tab=collections&search=${encodeURIComponent(mov.number || '')}`
  default:
    return null
  }
}
</script>

<template>
  <div>
    <!-- Filtros y botón de PDF -->
    <VCard
      variant="outlined"
      class="pa-4 mb-4"
    >
      <VRow
        density="compact"
        class="align-center"
      >
        <VCol
          cols="12"
          sm="3"
        >
          <VTextField
            v-model="fromDate"
            type="date"
            density="compact"
            label="Desde"
            placeholder="YYYY-MM-DD"
          />
        </VCol>
        <VCol
          cols="12"
          sm="3"
        >
          <VTextField
            v-model="toDate"
            type="date"
            density="compact"
            label="Hasta"
            placeholder="YYYY-MM-DD"
          />
        </VCol>
        <VCol
          cols="12"
          sm="3"
        >
          <VSelect
            v-model="currencyId"
            :items="currencies"
            item-title="name"
            item-value="id"
            density="compact"
            label="Moneda"
            placeholder="Seleccione moneda"
          />
        </VCol>
        <VCol
          cols="12"
          sm="3"
          class="d-flex gap-2"
        >
          <VBtn
            size="small"
            variant="tonal"
            color="primary"
            prepend-icon="tabler-filter"
            :loading="isLoading"
            @click="loadStatement"
          >
            Consultar
          </VBtn>
          <VBtn
            size="small"
            variant="elevated"
            color="secondary"
            prepend-icon="tabler-file-type-pdf"
            :loading="isDownloadingPdf"
            @click="downloadPdf"
          >
            PDF
          </VBtn>
        </VCol>
      </VRow>
    </VCard>

    <VProgressLinear
      v-if="isLoading"
      indeterminate
      class="mb-4"
    />

    <template v-if="statementData">
      <!-- Resumen KPI de saldos -->
      <VRow class="mb-4">
        <VCol
          cols="12"
          sm="3"
        >
          <VCard
            variant="tonal"
            color="secondary"
            class="pa-3 text-center"
          >
            <div class="text-caption text-medium-emphasis">
              Saldo Anterior
            </div>
            <div class="text-h6 font-weight-bold font-mono">
              {{ currencySymbol }} {{ formatAmount(statementData.opening_balance) }}
            </div>
          </VCard>
        </VCol>

        <VCol
          cols="12"
          sm="3"
        >
          <VCard
            variant="tonal"
            color="error"
            class="pa-3 text-center"
          >
            <div class="text-caption text-medium-emphasis">
              Cargos (+)
            </div>
            <div class="text-h6 font-weight-bold font-mono">
              {{ currencySymbol }} {{ formatAmount(statementData.total_debits) }}
            </div>
          </VCard>
        </VCol>

        <VCol
          cols="12"
          sm="3"
        >
          <VCard
            variant="tonal"
            color="success"
            class="pa-3 text-center"
          >
            <div class="text-caption text-medium-emphasis">
              Abonos (-)
            </div>
            <div class="text-h6 font-weight-bold font-mono">
              {{ currencySymbol }} {{ formatAmount(statementData.total_credits) }}
            </div>
          </VCard>
        </VCol>

        <VCol
          cols="12"
          sm="3"
        >
          <VCard
            variant="tonal"
            color="primary"
            class="pa-3 text-center"
          >
            <div class="text-caption text-medium-emphasis">
              Saldo Final
            </div>
            <div class="text-h6 font-weight-bold font-mono">
              {{ currencySymbol }} {{ formatAmount(statementData.closing_balance) }}
            </div>
          </VCard>
        </VCol>
      </VRow>

      <!-- Tabla de Movimientos -->
      <div class="border rounded overflow-hidden">
        <VTable
          density="comfortable"
          hover
        >
          <thead>
            <tr>
              <th class="font-weight-bold">
                FECHA
              </th>
              <th class="font-weight-bold">
                TIPO
              </th>
              <th class="font-weight-bold">
                DOCUMENTO
              </th>
              <th class="font-weight-bold">
                CONCEPTO / REF
              </th>
              <th class="font-weight-bold text-end">
                CARGO (+)
              </th>
              <th class="font-weight-bold text-end">
                ABONO (-)
              </th>
              <th class="font-weight-bold text-end">
                SALDO
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!statementData.movements?.length">
              <td
                colspan="7"
                class="text-center py-4 text-medium-emphasis"
              >
                No se registraron movimientos en el período seleccionado.
              </td>
            </tr>
            <tr
              v-for="(mov, idx) in statementData.movements"
              :key="idx"
            >
              <td class="font-mono text-caption">
                {{ formatDateTime12h(mov.datetime) }}
              </td>
              <td>
                <VChip
                  size="x-small"
                  :color="Number(mov.debit) > 0 ? 'error' : 'success'"
                  variant="tonal"
                >
                  {{ Number(mov.debit) > 0 ? 'Cargo' : 'Abono' }}
                </VChip>
              </td>
              <td class="font-mono font-weight-medium">
                <RouterLink
                  v-if="documentLink(mov)"
                  :to="documentLink(mov)"
                  class="text-primary text-decoration-none"
                >
                  {{ mov.number || '—' }}
                </RouterLink>
                <span v-else>{{ mov.number || '—' }}</span>
              </td>
              <td>
                {{ mov.concept || '—' }}
              </td>
              <td class="text-end font-mono">
                {{ Number(mov.debit) > 0 ? `${currencySymbol} ${formatAmount(mov.debit)}` : '—' }}
              </td>
              <td class="text-end font-mono">
                {{ Number(mov.credit) > 0 ? `${currencySymbol} ${formatAmount(mov.credit)}` : '—' }}
              </td>
              <td class="text-end font-mono font-weight-bold">
                {{ currencySymbol }} {{ formatAmount(mov.balance) }}
              </td>
            </tr>
          </tbody>
        </VTable>
      </div>
    </template>
  </div>
</template>
