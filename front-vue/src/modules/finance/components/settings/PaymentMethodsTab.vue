<script setup>
import { ref } from 'vue'
import { useAcl } from '@/composables/useAcl'
import { useConfirm } from '@/composables/useConfirm'
import { useNotify } from '@/composables/useNotify'
import { useServerTable } from '@/composables/useServerTable'
import ServerDataTable from '@/components/common/ServerDataTable.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import PaymentMethodFormDrawer from './PaymentMethodFormDrawer.vue'
import { paymentMethodsApi } from '../../services/financeSettingsApi'
import { useFinanceSettingsStore } from '../../stores/financeSettingsStore'
import { PAYMENT_METHOD_KINDS, SETTLEMENT_TYPES, SETTINGS_DRAWERS_TARGET } from '../../utils/constants'
import { STATUS_OPTIONS } from '@/utils/options'

const { can } = useAcl()
const { confirmDelete, alert } = useConfirm()
const notify = useNotify()
const financeStore = useFinanceSettingsStore()

const formDrawerRef = ref(null)
const isDrawerOpen = ref(false)
const isSubmitting = ref(false)
const selectedMethod = ref(null)
const deletingId = ref(null)

const table = useServerTable({
  fetch: params => paymentMethodsApi.list(params),
  filters: {
    kind: null,
    settlement: null,
    'is_active': null,
  },
})

const headers = [
  { title: 'CÓDIGO', key: 'code', sortable: false, alwaysVisible: true },
  { title: 'NOMBRE', key: 'name', sortable: false },
  { title: 'TIPO', key: 'kind', sortable: false, align: 'center' },
  { title: 'LIQUIDACIÓN', key: 'settlement', sortable: false, align: 'center' },
  { title: 'CUENTA BANCARIA', key: 'bank_account.alias', sortable: false },
  { title: 'ORDEN', key: 'sort_order', sortable: false, align: 'center' },
  { title: 'ESTADO', key: 'is_active', sortable: false, align: 'center' },
  { title: 'ACCIONES', key: 'actions', sortable: false, align: 'center' },
]

const getKindLabel = kind => {
  const value = kind?.value || kind
  const found = PAYMENT_METHOD_KINDS.find(k => k.value === value)

  return found ? found.title : value
}

const getSettlementLabel = settlement => {
  const value = settlement?.value || settlement
  const found = SETTLEMENT_TYPES.find(s => s.value === value)

  return found ? found.title : value
}

const openCreateDrawer = () => {
  selectedMethod.value = null
  isDrawerOpen.value = true
}

const openEditDrawer = method => {
  selectedMethod.value = { ...method }
  isDrawerOpen.value = true
}

const handleFormSubmit = async ({ id, ...payload }) => {
  isSubmitting.value = true
  formDrawerRef.value?.clearErrors()
  try {
    if (id) {
      await paymentMethodsApi.update(id, payload)
      notify.success('Método de pago actualizado correctamente')
    }
    else {
      await paymentMethodsApi.create(payload)
      notify.success('Método de pago creado correctamente')
    }
    isDrawerOpen.value = false
    financeStore.invalidatePaymentMethods()
    await table.reload()
  }
  catch (err) {
    formDrawerRef.value?.setErrors(err)
  }
  finally {
    isSubmitting.value = false
  }
}

const removeMethod = async method => {
  if (method.is_system) {
    await alert({ message: 'No se puede eliminar un método de pago del sistema.' })

    return
  }

  if (!await confirmDelete({ entity: 'método de pago', name: method.name }))
    return

  deletingId.value = method.id
  try {
    await paymentMethodsApi.delete(method.id)
    notify.success('Método de pago eliminado correctamente')
    financeStore.invalidatePaymentMethods()
    await table.reload()
  }
  catch (err) {
    await alert({ message: err?.data?.message || 'No se pudo eliminar el método de pago.' })
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
    table-id="finance-payment-methods"
    :headers="headers"
    :items="table.items"
    :total-items="table.total"
    :loading="table.loading"
    :has-filters="table.hasFilters"
    search-placeholder="CASH / Yape / Transferencia..."
    create-label="Nuevo método"
    :creatable="can('finance_settings.manage')"
    @refresh="table.reload"
    @create="openCreateDrawer"
  >
    <template #filters>
      <VSelect
        v-model="table.filters.kind"
        :items="PAYMENT_METHOD_KINDS"
        label="Tipo"
        placeholder="Efectivo"
        density="compact"
        clearable
      />

      <VSelect
        v-model="table.filters.settlement"
        :items="SETTLEMENT_TYPES"
        label="Liquidación"
        placeholder="Caja"
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
      <div class="d-flex align-center gap-1">
        <span class="font-weight-medium">{{ item.code }}</span>
        <VTooltip
          v-if="item.is_system"
          text="Método del sistema (protegido)"
        >
          <template #activator="{ props: tooltipProps }">
            <VIcon
              v-bind="tooltipProps"
              size="16"
              icon="tabler-lock"
              class="text-disabled"
            />
          </template>
        </VTooltip>
      </div>
    </template>

    <template #item.kind="{ item }">
      <VChip
        size="small"
        variant="tonal"
        color="primary"
      >
        {{ getKindLabel(item.kind) }}
      </VChip>
    </template>

    <template #item.settlement="{ item }">
      <VChip
        size="small"
        variant="tonal"
        :color="item.settlement === 'cash_box' ? 'warning' : 'info'"
      >
        {{ getSettlementLabel(item.settlement) }}
      </VChip>
    </template>

    <template #item.bank_account.alias="{ item }">
      <span>{{ item.bank_account?.alias || '—' }}</span>
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
          tooltip="Editar método de pago"
          @click="openEditDrawer(item)"
        />
        <RowActionButton
          v-if="can('finance_settings.manage') && !item.is_system"
          action="delete"
          tooltip="Eliminar método de pago"
          :loading="deletingId === item.id"
          @click="removeMethod(item)"
        />
      </div>
    </template>
  </ServerDataTable>

  <Teleport
    defer
    :to="SETTINGS_DRAWERS_TARGET"
  >
    <PaymentMethodFormDrawer
      ref="formDrawerRef"
      v-model:is-drawer-open="isDrawerOpen"
      :method-data="selectedMethod"
      :is-submitting="isSubmitting"
      @submit="handleFormSubmit"
    />
  </Teleport>
</template>
