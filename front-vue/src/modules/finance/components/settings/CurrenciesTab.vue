<script setup>
import { ref } from 'vue'
import { useAcl } from '@/composables/useAcl'
import { useConfirm } from '@/composables/useConfirm'
import { useNotify } from '@/composables/useNotify'
import { useServerTable } from '@/composables/useServerTable'
import ServerDataTable from '@/components/common/ServerDataTable.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import CurrencyFormDrawer from './CurrencyFormDrawer.vue'
import { currenciesApi } from '../../services/financeSettingsApi'
import { useFinanceSettingsStore } from '../../stores/financeSettingsStore'
import { SETTINGS_DRAWERS_TARGET } from '../../utils/constants'
import { STATUS_OPTIONS } from '@/utils/options'

const { can } = useAcl()
const { confirmDelete, alert } = useConfirm()
const notify = useNotify()
const financeStore = useFinanceSettingsStore()

const formDrawerRef = ref(null)
const isDrawerOpen = ref(false)
const isSubmitting = ref(false)
const selectedCurrency = ref(null)
const deletingId = ref(null)

const table = useServerTable({
  fetch: params => currenciesApi.list(params),
  filters: { 'is_active': null },
})

const headers = [
  { title: 'CÓDIGO', key: 'code', sortable: false, alwaysVisible: true },
  { title: 'NOMBRE', key: 'name', sortable: false },
  { title: 'SÍMBOLO', key: 'symbol', sortable: false, align: 'center' },
  { title: 'SUNAT', key: 'sunat_code', sortable: false, align: 'center' },
  { title: 'DECIMALES', key: 'decimals', sortable: false, align: 'center' },
  { title: 'MONEDA BASE', key: 'is_base', sortable: false, align: 'center' },
  { title: 'ESTADO', key: 'is_active', sortable: false, align: 'center' },
  { title: 'ACCIONES', key: 'actions', sortable: false, align: 'center' },
]

const openCreateDrawer = () => {
  selectedCurrency.value = null
  isDrawerOpen.value = true
}

const openEditDrawer = currency => {
  selectedCurrency.value = { ...currency }
  isDrawerOpen.value = true
}

const handleFormSubmit = async ({ id, ...payload }) => {
  isSubmitting.value = true
  formDrawerRef.value?.clearErrors()
  try {
    if (id) {
      await currenciesApi.update(id, payload)
      notify.success('Moneda actualizada correctamente')
    }
    else {
      await currenciesApi.create(payload)
      notify.success('Moneda creada correctamente')
    }
    isDrawerOpen.value = false
    financeStore.invalidateCurrencies()
    await table.reload()
  }
  catch (err) {
    formDrawerRef.value?.setErrors(err)
  }
  finally {
    isSubmitting.value = false
  }
}

const removeCurrency = async currency => {
  if (currency.is_base) {
    await alert({ message: 'No se puede eliminar la moneda base del sistema.' })

    return
  }

  if (!await confirmDelete({ entity: 'moneda', name: currency.name }))
    return

  deletingId.value = currency.id
  try {
    await currenciesApi.delete(currency.id)
    notify.success('Moneda eliminada correctamente')
    financeStore.invalidateCurrencies()
    await table.reload()
  }
  catch (err) {
    await alert({ message: err?.data?.message || 'No se pudo eliminar la moneda.' })
  }
  finally {
    deletingId.value = null
  }
}
</script>

<template>
  <ServerDataTable
    v-model:options="table.options"
    v-model:search="table.search"
    table-id="finance-currencies"
    :headers="headers"
    :items="table.items"
    :total-items="table.total"
    :loading="table.loading"
    :has-filters="table.hasFilters"
    search-placeholder="PEN / Dólar"
    create-label="Nueva moneda"
    :creatable="can('finance_settings.manage')"
    @refresh="table.reload"
    @create="openCreateDrawer"
  >
    <template #filters>
      <VSelect
        v-model="table.filters.is_active"
        :items="STATUS_OPTIONS"
        label="Estado"
        placeholder="Activo"
        density="compact"
        clearable
      />
    </template>

    <template #item.code="{ item }">
      <span class="font-weight-medium">{{ item.code }}</span>
    </template>

    <template #item.name="{ item }">
      <span>{{ item.name }}</span>
    </template>

    <template #item.symbol="{ item }">
      <VChip
        size="small"
        variant="tonal"
        color="primary"
      >
        {{ item.symbol }}
      </VChip>
    </template>

    <template #item.sunat_code="{ item }">
      <span>{{ item.sunat_code || '—' }}</span>
    </template>

    <template #item.decimals="{ item }">
      <span>{{ item.decimals }}</span>
    </template>

    <template #item.is_base="{ item }">
      <VChip
        v-if="item.is_base"
        size="small"
        color="primary"
      >
        Moneda Base
      </VChip>
      <span
        v-else
        class="text-disabled"
      >—</span>
    </template>

    <template #item.is_active="{ item }">
      <VChip
        size="small"
        :color="item.is_active ? 'success' : 'secondary'"
      >
        {{ item.is_active ? 'Activo' : 'Inactivo' }}
      </VChip>
    </template>

    <template #item.actions="{ item }">
      <div class="d-flex justify-center gap-1">
        <RowActionButton
          v-if="can('finance_settings.manage')"
          action="edit"
          tooltip="Editar moneda"
          @click="openEditDrawer(item)"
        />
        <RowActionButton
          v-if="can('finance_settings.manage') && !item.is_base"
          action="delete"
          tooltip="Eliminar moneda"
          :loading="deletingId === item.id"
          @click="removeCurrency(item)"
        />
      </div>
    </template>
  </ServerDataTable>

  <Teleport
    defer
    :to="SETTINGS_DRAWERS_TARGET"
  >
    <CurrencyFormDrawer
      ref="formDrawerRef"
      v-model:is-drawer-open="isDrawerOpen"
      :currency-data="selectedCurrency"
      :is-submitting="isSubmitting"
      @submit="handleFormSubmit"
    />
  </Teleport>
</template>
