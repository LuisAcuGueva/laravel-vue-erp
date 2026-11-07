<script setup>
/* eslint-disable camelcase */
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAcl } from '@/composables/useAcl'
import { useNotify } from '@/composables/useNotify'
import { useServerTable } from '@/composables/useServerTable'
import PageHeader from '@/components/common/PageHeader.vue'
import ServerDataTable from '@/components/common/ServerDataTable.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import BankOperationDialog from '@/modules/finance/components/banks/BankOperationDialog.vue'
import { banksApi } from '@/modules/finance/services/banksApi'
import { STATUS_OPTIONS } from '@/utils/options'

definePage({
  meta: {
    action: 'bank_movements.view',
    title: 'Bancos',
    icon: 'tabler-building-bank',
    description: 'Gestión de saldos bancarios, conciliación por periodos y transferencias entre cuentas.',
  },
})

const router = useRouter()
const { can } = useAcl()
const notify = useNotify()

const summaryList = ref([])
const isLoadingSummary = ref(false)

// Diálogo de operaciones
const isOperationDialogOpen = ref(false)
const operationDialogRef = ref(null)
const selectedAccountIdForOp = ref(null)
const isSubmittingOperation = ref(false)

const headers = [
  { title: 'Banco', key: 'bank.name', sortable: false },
  { title: 'Alias / Nombre', key: 'alias' },
  { title: 'N° Cuenta', key: 'account_number' },
  { title: 'CCI', key: 'cci', sortable: false },
  { title: 'Moneda', key: 'currency.code', sortable: false },
  { title: 'Saldo actual', key: 'balance', align: 'end' },
  { title: 'Periodo', key: 'open_period', sortable: false },
  { title: 'Estado', key: 'is_active', align: 'center', sortable: false },
  { title: 'Acciones', key: 'actions', align: 'center', sortable: false },
]

const formatMoney = (val, symbol = '') => {
  if (val === null || val === undefined || val === '')
    return `${symbol} 0.00`.trim()
  const num = Number(val)
  const formatted = Number.isNaN(num) ? '0.00' : num.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

  return symbol ? `${symbol} ${formatted}` : formatted
}

const loadSummary = async () => {
  isLoadingSummary.value = true
  try {
    const res = await banksApi.summary()

    summaryList.value = res?.data?.by_currency || []
  }
  catch {
    summaryList.value = []
  }
  finally {
    isLoadingSummary.value = false
  }
}

const table = useServerTable({
  fetch: params => banksApi.listAccounts(params),
  filters: {
    is_active: null,
  },
})

onMounted(() => {
  loadSummary()
})

const openOperationDialog = (accountId = null) => {
  selectedAccountIdForOp.value = accountId
  isOperationDialogOpen.value = true
}

const handleOperationSubmit = async formData => {
  isSubmittingOperation.value = true
  try {
    await banksApi.createOperation(formData)
    notify.success('Operación procesada correctamente')
    isOperationDialogOpen.value = false
    await Promise.all([
      loadSummary(),
      table.reload(),
    ])
  }
  catch (err) {
    operationDialogRef.value?.setErrors(err)
  }
  finally {
    isSubmittingOperation.value = false
  }
}

const navigateToDetail = account => {
  router.push({ name: 'finance-banks-id', params: { id: account.id } })
}
</script>

<template>
  <div>
    <!-- Encabezado de página -->
    <VCard class="mb-6">
      <VCardText>
        <PageHeader />
      </VCardText>
    </VCard>

    <!-- Resumen por Moneda -->
    <VRow
      v-if="summaryList.length"
      class="mb-4"
    >
      <VCol
        v-for="sum in summaryList"
        :key="sum.currency?.code"
        cols="12"
        sm="6"
        md="4"
      >
        <VCard>
          <VCardText class="d-flex align-center justify-space-between">
            <div>
              <div class="text-caption text-medium-emphasis">
                Saldo total {{ sum.currency?.name }}
              </div>
              <div class="text-h5 font-weight-bold my-1">
                {{ formatMoney(sum.total, sum.currency?.symbol) }}
              </div>
              <div class="text-caption text-disabled">
                {{ sum.accounts?.length || 0 }} {{ (sum.accounts?.length || 0) === 1 ? 'cuenta' : 'cuentas' }}
              </div>
            </div>
            <VAvatar
              size="48"
              color="primary"
              variant="tonal"
            >
              <VIcon
                icon="tabler-wallet"
                size="28"
              />
            </VAvatar>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Tarjeta de Acciones Rápidas -->
      <VCol
        cols="12"
        sm="6"
        md="4"
      >
        <VCard
          class="h-100 d-flex flex-column justify-center"
          variant="tonal"
          color="primary"
        >
          <VCardText class="d-flex align-center justify-space-between">
            <div>
              <div class="text-subtitle-1 font-weight-bold">
                Operaciones Bancarias
              </div>
              <div class="text-caption text-medium-emphasis">
                Depósitos, retiros y transferencias
              </div>
            </div>
            <VBtn
              v-if="can('bank_operations.create')"
              color="primary"
              variant="elevated"
              @click="openOperationDialog"
            >
              <VIcon
                start
                icon="tabler-plus"
              />
              Nueva operación
            </VBtn>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Listado de Cuentas Bancarias -->
    <VCard>
      <ServerDataTable
        v-model:options="table.options"
        v-model:search="table.search"
        table-id="bank-accounts"
        :headers="headers"
        :items="table.items"
        :total-items="table.total"
        :loading="table.loading"
        :has-filters="table.hasFilters"
        search-placeholder="Buscar por alias, cuenta o banco..."
        @refresh="table.reload"
      >
        <template #filters>
          <VSelect
            v-model="table.filters.is_active"
            :items="STATUS_OPTIONS"
            label="Estado"
            placeholder="Todos los estados"
            density="compact"
            clearable
            style="min-width: 180px;"
          />
        </template>

        <!-- Columna Banco -->
        <template #item.bank.name="{ item }">
          <div class="d-flex align-center">
            <VAvatar
              size="32"
              color="secondary"
              variant="tonal"
              class="me-2"
            >
              <VIcon
                icon="tabler-building-bank"
                size="18"
              />
            </VAvatar>
            <div>
              <div class="font-weight-medium text-high-emphasis">
                {{ item.bank?.name || '—' }}
              </div>
              <div
                v-if="item.bank?.short_name"
                class="text-caption text-disabled"
              >
                {{ item.bank.short_name }}
              </div>
            </div>
          </div>
        </template>

        <!-- Columna Saldo -->
        <template #item.balance="{ item }">
          <div class="text-end font-weight-bold">
            {{ formatMoney(item.balance, item.currency?.symbol) }}
          </div>
        </template>

        <!-- Columna Periodo Actual -->
        <template #item.open_period="{ item }">
          <VChip
            v-if="item.open_period"
            size="small"
            color="success"
            variant="tonal"
          >
            Periodo N° {{ item.open_period.number }}
          </VChip>
          <VChip
            v-else
            size="small"
            color="warning"
            variant="tonal"
          >
            Sin apertura
          </VChip>
        </template>

        <!-- Columna Estado -->
        <template #item.is_active="{ item }">
          <VChip
            size="small"
            :color="item.is_active ? 'success' : 'secondary'"
            variant="tonal"
          >
            {{ item.is_active ? 'Activa' : 'Inactiva' }}
          </VChip>
        </template>

        <!-- Columna Acciones -->
        <template #item.actions="{ item }">
          <div class="d-flex justify-center gap-1">
            <RowActionButton
              action="view"
              tooltip="Ver detalle y movimientos"
              @click="navigateToDetail(item)"
            />
            <RowActionButton
              v-if="can('bank_operations.create')"
              action="custom"
              icon="tabler-arrows-left-right"
              tooltip="Operar con esta cuenta"
              @click="openOperationDialog(item.id)"
            />
          </div>
        </template>
      </ServerDataTable>
    </VCard>

    <!-- Diálogo de Operación -->
    <BankOperationDialog
      ref="operationDialogRef"
      v-model:is-dialog-open="isOperationDialogOpen"
      :preselected-bank-account-id="selectedAccountIdForOp"
      :is-submitting="isSubmittingOperation"
      @submit="handleOperationSubmit"
    />
  </div>
</template>
