<script setup>
import { ref } from 'vue'
import { useAcl } from '@/composables/useAcl'
import { useConfirm } from '@/composables/useConfirm'
import { useNotify } from '@/composables/useNotify'
import { useServerTable } from '@/composables/useServerTable'
import ServerDataTable from '@/components/common/ServerDataTable.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import BankFormDrawer from './BankFormDrawer.vue'
import { banksApi } from '../../services/financeSettingsApi'
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
const selectedBank = ref(null)
const deletingId = ref(null)

const table = useServerTable({
  fetch: params => banksApi.list(params),
  filters: { 'is_active': null },
})

const headers = [
  { title: 'NOMBRE', key: 'name', sortable: false, alwaysVisible: true },
  { title: 'ABREVIATURA', key: 'short_name', sortable: false },
  { title: 'ESTADO', key: 'is_active', sortable: false, align: 'center' },
  { title: 'ACCIONES', key: 'actions', sortable: false, align: 'center' },
]

const openCreateDrawer = () => {
  selectedBank.value = null
  isDrawerOpen.value = true
}

const openEditDrawer = bank => {
  selectedBank.value = { ...bank }
  isDrawerOpen.value = true
}

const handleFormSubmit = async ({ id, ...payload }) => {
  isSubmitting.value = true
  formDrawerRef.value?.clearErrors()
  try {
    if (id) {
      await banksApi.update(id, payload)
      notify.success('Banco actualizado correctamente')
    }
    else {
      await banksApi.create(payload)
      notify.success('Banco creado correctamente')
    }
    isDrawerOpen.value = false
    financeStore.invalidateBanks()
    await table.reload()
  }
  catch (err) {
    formDrawerRef.value?.setErrors(err)
  }
  finally {
    isSubmitting.value = false
  }
}

const removeBank = async bank => {
  if (!await confirmDelete({ entity: 'banco', name: bank.name }))
    return

  deletingId.value = bank.id
  try {
    await banksApi.delete(bank.id)
    notify.success('Banco eliminado correctamente')
    financeStore.invalidateBanks()
    await table.reload()
  }
  catch (err) {
    await alert({ message: err?.data?.message || 'No se pudo eliminar el banco.' })
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
    table-id="finance-banks"
    :headers="headers"
    :items="table.items"
    :total-items="table.total"
    :loading="table.loading"
    :has-filters="table.hasFilters"
    search-placeholder="BCP / Interbank"
    create-label="Nuevo banco"
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

    <template #item.name="{ item }">
      <span class="font-weight-medium">{{ item.name }}</span>
    </template>

    <template #item.short_name="{ item }">
      <VChip
        size="small"
        variant="tonal"
        color="info"
      >
        {{ item.short_name }}
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
          tooltip="Editar banco"
          @click="openEditDrawer(item)"
        />
        <RowActionButton
          v-if="can('finance_settings.manage')"
          action="delete"
          tooltip="Eliminar banco"
          :loading="deletingId === item.id"
          @click="removeBank(item)"
        />
      </div>
    </template>
  </ServerDataTable>

  <Teleport
    defer
    :to="SETTINGS_DRAWERS_TARGET"
  >
    <BankFormDrawer
      ref="formDrawerRef"
      v-model:is-drawer-open="isDrawerOpen"
      :bank-data="selectedBank"
      :is-submitting="isSubmitting"
      @submit="handleFormSubmit"
    />
  </Teleport>
</template>
