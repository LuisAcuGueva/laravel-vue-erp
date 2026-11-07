<script setup>
import { computed, onMounted, ref } from 'vue'
import { useAcl } from '@/composables/useAcl'
import { useNotify } from '@/composables/useNotify'
import { useServerTable } from '@/composables/useServerTable'
import ServerDataTable from '@/components/common/ServerDataTable.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import ExchangeRateFormDrawer from './ExchangeRateFormDrawer.vue'
import { exchangeRatesApi } from '../../services/financeSettingsApi'
import { useFinanceSettingsStore } from '../../stores/financeSettingsStore'
import { formatDate } from '@/@core/utils/formatters'
import { SETTINGS_DRAWERS_TARGET } from '../../utils/constants'

const { can } = useAcl()
const notify = useNotify()
const financeStore = useFinanceSettingsStore()

const formDrawerRef = ref(null)
const isDrawerOpen = ref(false)
const isSubmitting = ref(false)
const selectedRate = ref(null)

const table = useServerTable({
  fetch: params => exchangeRatesApi.list(params),
  filters: {
    'currency_id': null,
    'date_from': null,
    'date_to': null,
  },
})

const headers = [
  { title: 'MONEDA', key: 'currency.code', sortable: false, alwaysVisible: true },
  { title: 'FECHA', key: 'date', sortable: false },
  { title: 'COMPRA', key: 'buy_rate', sortable: false, align: 'end' },
  { title: 'VENTA', key: 'sell_rate', sortable: false, align: 'end' },
  { title: 'REGISTRADO POR', key: 'creator.name', sortable: false },
  { title: 'ACCIONES', key: 'actions', sortable: false, align: 'center' },
]

const foreignCurrencies = computed(() => {
  return financeStore.currenciesOptions
    .filter(c => !c.is_base)
    .map(c => ({
      value: c.id,
      title: `${c.code} - ${c.name}`,
    }))
})

onMounted(() => {
  financeStore.fetchCurrenciesOptions()
})

const openCreateDrawer = () => {
  selectedRate.value = null
  isDrawerOpen.value = true
}

const openEditDrawer = rate => {
  selectedRate.value = { ...rate }
  isDrawerOpen.value = true
}

const handleFormSubmit = async payload => {
  isSubmitting.value = true
  formDrawerRef.value?.clearErrors()
  try {
    await exchangeRatesApi.upsert(payload)
    notify.success('Tipo de cambio registrado correctamente')
    isDrawerOpen.value = false
    await table.reload()
  }
  catch (err) {
    formDrawerRef.value?.setErrors(err)
  }
  finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <ServerDataTable
    v-model:options="table.options"
    v-model:search="table.search"
    table-id="finance-exchange-rates"
    :headers="headers"
    :items="table.items"
    :total-items="table.total"
    :loading="table.loading"
    :has-filters="table.hasFilters"
    search-placeholder="Buscar fecha o moneda..."
    create-label="Registrar Tipo de Cambio"
    :creatable="can('exchange_rates.manage')"
    @refresh="table.reload"
    @create="openCreateDrawer"
  >
    <template #filters>
      <VSelect
        v-model="table.filters.currency_id"
        :items="foreignCurrencies"
        label="Moneda"
        placeholder="USD"
        density="compact"
        clearable
      />

      <VTextField
        v-model="table.filters.date_from"
        type="date"
        label="Desde"
        placeholder="2026-10-01"
        density="compact"
        clearable
      />

      <VTextField
        v-model="table.filters.date_to"
        type="date"
        label="Hasta"
        placeholder="2026-10-31"
        density="compact"
        clearable
      />
    </template>

    <template #item.date="{ item }">
      <span>{{ formatDate(item.date) }}</span>
    </template>

    <template #item.currency.code="{ item }">
      <span class="font-weight-medium">{{ item.currency?.code }}</span>
    </template>

    <template #item.buy_rate="{ item }">
      <span class="font-weight-medium text-success">{{ Number(item.buy_rate).toFixed(4) }}</span>
    </template>

    <template #item.sell_rate="{ item }">
      <span class="font-weight-medium text-info">{{ Number(item.sell_rate).toFixed(4) }}</span>
    </template>

    <template #item.creator.name="{ item }">
      <span>{{ item.creator?.name || 'Sistema' }}</span>
    </template>

    <template #item.actions="{ item }">
      <div class="d-flex justify-center gap-1">
        <RowActionButton
          v-if="can('exchange_rates.manage')"
          action="edit"
          tooltip="Actualizar tipo de cambio"
          @click="openEditDrawer(item)"
        />
      </div>
    </template>
  </ServerDataTable>

  <Teleport
    defer
    :to="SETTINGS_DRAWERS_TARGET"
  >
    <ExchangeRateFormDrawer
      ref="formDrawerRef"
      v-model:is-drawer-open="isDrawerOpen"
      :rate-data="selectedRate"
      :is-submitting="isSubmitting"
      @submit="handleFormSubmit"
    />
  </Teleport>
</template>
