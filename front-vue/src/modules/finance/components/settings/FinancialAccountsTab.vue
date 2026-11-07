<script setup>
import { computed, onMounted, ref } from 'vue'
import { useAcl } from '@/composables/useAcl'
import { useConfirm } from '@/composables/useConfirm'
import { useNotify } from '@/composables/useNotify'
import { useServerTable } from '@/composables/useServerTable'
import ServerDataTable from '@/components/common/ServerDataTable.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import FinancialAccountFormDrawer from './FinancialAccountFormDrawer.vue'
import { financialAccountsApi } from '../../services/financeSettingsApi'
import { useFinanceSettingsStore } from '../../stores/financeSettingsStore'
import { FINANCIAL_ACCOUNT_TYPES, SETTINGS_DRAWERS_TARGET } from '../../utils/constants'
import { STATUS_OPTIONS } from '@/utils/options'

const { can } = useAcl()
const { confirmDelete, alert } = useConfirm()
const notify = useNotify()
const financeStore = useFinanceSettingsStore()

const formDrawerRef = ref(null)
const isDrawerOpen = ref(false)
const isSubmitting = ref(false)
const selectedAccount = ref(null)
const deletingId = ref(null)

const table = useServerTable({
  fetch: params => financialAccountsApi.list(params),
  filters: {
    type: null,
    'is_active': null,
  },
})

const headers = [
  { title: 'CÓDIGO', key: 'code', sortable: false, alwaysVisible: true },
  { title: 'NOMBRE', key: 'name', sortable: false },
  { title: 'TIPO', key: 'type', sortable: false, align: 'center' },
  { title: 'NIVEL', key: 'level', sortable: false, align: 'center' },
  { title: 'IMPUTABLE', key: 'is_postable', sortable: false, align: 'center' },
  { title: 'ESTADO', key: 'is_active', sortable: false, align: 'center' },
  { title: 'ACCIONES', key: 'actions', sortable: false, align: 'center' },
]

const accountOptions = computed(() => financeStore.financialAccountsOptions)

const getTypeLabel = type => {
  const value = type?.value || type
  const found = FINANCIAL_ACCOUNT_TYPES.find(t => t.value === value)

  return found ? found.title : value
}

const getTypeColor = type => {
  const value = type?.value || type
  switch (value) {
  case 'asset':
    return 'info'
  case 'liability':
    return 'warning'
  case 'equity':
    return 'secondary'
  case 'income':
    return 'success'
  case 'expense':
    return 'error'
  default:
    return 'primary'
  }
}

onMounted(() => {
  financeStore.fetchFinancialAccountsOptions()
})

const openCreateDrawer = () => {
  selectedAccount.value = null
  isDrawerOpen.value = true
}

const openEditDrawer = account => {
  selectedAccount.value = { ...account }
  isDrawerOpen.value = true
}

const handleFormSubmit = async ({ id, ...payload }) => {
  isSubmitting.value = true
  formDrawerRef.value?.clearErrors()
  try {
    if (id) {
      await financialAccountsApi.update(id, payload)
      notify.success('Cuenta contable actualizada correctamente')
    }
    else {
      await financialAccountsApi.create(payload)
      notify.success('Cuenta contable creada correctamente')
    }
    isDrawerOpen.value = false
    financeStore.invalidateFinancialAccounts()
    await financeStore.fetchFinancialAccountsOptions(true)
    await table.reload()
  }
  catch (err) {
    formDrawerRef.value?.setErrors(err)
  }
  finally {
    isSubmitting.value = false
  }
}

const removeAccount = async account => {
  if (!await confirmDelete({ entity: 'cuenta contable', name: `${account.code} - ${account.name}` }))
    return

  deletingId.value = account.id
  try {
    await financialAccountsApi.delete(account.id)
    notify.success('Cuenta contable eliminada correctamente')
    financeStore.invalidateFinancialAccounts()
    await financeStore.fetchFinancialAccountsOptions(true)
    await table.reload()
  }
  catch (err) {
    await alert({ message: err?.data?.message || 'No se pudo eliminar la cuenta contable.' })
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
    table-id="finance-financial-accounts"
    :headers="headers"
    :items="table.items"
    :total-items="table.total"
    :loading="table.loading"
    :has-filters="table.hasFilters"
    search-placeholder="10 / Caja / Ventas..."
    create-label="Nueva cuenta"
    :creatable="can('finance_settings.manage')"
    @refresh="table.reload"
    @create="openCreateDrawer"
  >
    <template #filters>
      <VSelect
        v-model="table.filters.type"
        :items="FINANCIAL_ACCOUNT_TYPES"
        label="Tipo de Cuenta"
        placeholder="Activo"
        density="compact"
        clearable
      />

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
      <span
        class="font-weight-medium"
        :style="{ paddingLeft: `${((item.level || 1) - 1) * 16}px` }"
      >
        {{ item.code }}
      </span>
    </template>

    <template #item.name="{ item }">
      <span :class="{ 'font-weight-bold': !item.is_postable }">
        {{ item.name }}
      </span>
    </template>

    <template #item.type="{ item }">
      <VChip
        size="small"
        variant="tonal"
        :color="getTypeColor(item.type)"
      >
        {{ getTypeLabel(item.type) }}
      </VChip>
    </template>

    <template #item.level="{ item }">
      <span>Nivel {{ item.level }}</span>
    </template>

    <template #item.is_postable="{ item }">
      <VChip
        size="small"
        :color="item.is_postable ? 'success' : 'secondary'"
      >
        {{ item.is_postable ? 'Imputable' : 'Agrupador' }}
      </VChip>
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
          tooltip="Editar cuenta contable"
          @click="openEditDrawer(item)"
        />
        <RowActionButton
          v-if="can('finance_settings.manage')"
          action="delete"
          tooltip="Eliminar cuenta contable"
          :loading="deletingId === item.id"
          @click="removeAccount(item)"
        />
      </div>
    </template>
  </ServerDataTable>

  <Teleport
    defer
    :to="SETTINGS_DRAWERS_TARGET"
  >
    <FinancialAccountFormDrawer
      ref="formDrawerRef"
      v-model:is-drawer-open="isDrawerOpen"
      :account-data="selectedAccount"
      :account-options="accountOptions"
      :is-submitting="isSubmitting"
      @submit="handleFormSubmit"
    />
  </Teleport>
</template>
