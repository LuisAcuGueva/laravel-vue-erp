<script setup>
import { computed, onMounted, ref } from 'vue'
import { useAcl } from '@/composables/useAcl'
import { useConfirm } from '@/composables/useConfirm'
import { useNotify } from '@/composables/useNotify'
import { useServerTable } from '@/composables/useServerTable'
import ServerDataTable from '@/components/common/ServerDataTable.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import BankAccountFormDrawer from './BankAccountFormDrawer.vue'
import { bankAccountsApi } from '../../services/financeSettingsApi'
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
const selectedAccount = ref(null)
const deletingId = ref(null)

const table = useServerTable({
  fetch: params => bankAccountsApi.list(params),
  filters: {
    'bank_id': null,
    'currency_id': null,
    'is_active': null,
  },
})

const headers = [
  { title: 'BANCO', key: 'bank.short_name', sortable: false, alwaysVisible: true },
  { title: 'ALIAS', key: 'alias', sortable: false },
  { title: 'NÚMERO DE CUENTA', key: 'account_number', sortable: false },
  { title: 'MONEDA', key: 'currency.code', sortable: false, align: 'center' },
  { title: 'SALDO', key: 'balance', sortable: false, align: 'end' },
  { title: 'ESTADO', key: 'is_active', sortable: false, align: 'center' },
  { title: 'ACCIONES', key: 'actions', sortable: false, align: 'center' },
]

const bankOptions = computed(() => {
  return financeStore.banksOptions.map(b => ({
    value: b.id,
    title: b.short_name,
  }))
})

const currencyOptions = computed(() => {
  return financeStore.currenciesOptions.map(c => ({
    value: c.id,
    title: c.code,
  }))
})

onMounted(() => {
  financeStore.fetchBanksOptions()
  financeStore.fetchCurrenciesOptions()
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
      await bankAccountsApi.update(id, payload)
      notify.success('Cuenta bancaria actualizada correctamente')
    }
    else {
      await bankAccountsApi.create(payload)
      notify.success('Cuenta bancaria creada correctamente')
    }
    isDrawerOpen.value = false
    financeStore.invalidateBankAccounts()
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
  if (!await confirmDelete({ entity: 'cuenta bancaria', name: account.alias }))
    return

  deletingId.value = account.id
  try {
    await bankAccountsApi.delete(account.id)
    notify.success('Cuenta bancaria eliminada correctamente')
    financeStore.invalidateBankAccounts()
    await table.reload()
  }
  catch (err) {
    await alert({ message: err?.data?.message || 'No se pudo eliminar la cuenta bancaria.' })
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
    table-id="finance-bank-accounts"
    :headers="headers"
    :items="table.items"
    :total-items="table.total"
    :loading="table.loading"
    :has-filters="table.hasFilters"
    search-placeholder="Alias o número de cuenta..."
    create-label="Nueva cuenta"
    :creatable="can('finance_settings.manage')"
    @refresh="table.reload"
    @create="openCreateDrawer"
  >
    <template #filters>
      <VSelect
        v-model="table.filters.bank_id"
        :items="bankOptions"
        label="Banco"
        placeholder="BCP"
        density="compact"
        clearable
      />

      <VSelect
        v-model="table.filters.currency_id"
        :items="currencyOptions"
        label="Moneda"
        placeholder="PEN"
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

    <template #item.bank.short_name="{ item }">
      <span class="font-weight-medium">{{ item.bank?.short_name }}</span>
    </template>

    <template #item.alias="{ item }">
      <div class="d-flex flex-column">
        <span class="font-weight-medium">{{ item.alias }}</span>
        <span
          v-if="item.holder_name"
          class="text-caption text-medium-emphasis"
        >
          {{ item.holder_name }}
        </span>
      </div>
    </template>

    <template #item.account_number="{ item }">
      <code>{{ item.account_number }}</code>
    </template>

    <template #item.currency.code="{ item }">
      <VChip
        size="small"
        variant="tonal"
        color="primary"
      >
        {{ item.currency?.code }}
      </VChip>
    </template>

    <template #item.balance="{ item }">
      <span class="font-weight-medium font-monospace">
        {{ item.currency?.symbol }} {{ Number(item.balance || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
      </span>
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
          tooltip="Editar cuenta bancaria"
          @click="openEditDrawer(item)"
        />
        <RowActionButton
          v-if="can('finance_settings.manage')"
          action="delete"
          tooltip="Eliminar cuenta bancaria"
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
    <BankAccountFormDrawer
      ref="formDrawerRef"
      v-model:is-drawer-open="isDrawerOpen"
      :account-data="selectedAccount"
      :is-submitting="isSubmitting"
      @submit="handleFormSubmit"
    />
  </Teleport>
</template>
