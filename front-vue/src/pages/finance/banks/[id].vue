<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAcl } from '@/composables/useAcl'
import { useNotify } from '@/composables/useNotify'
import { useServerTable } from '@/composables/useServerTable'
import PageHeader from '@/components/common/PageHeader.vue'
import ServerDataTable from '@/components/common/ServerDataTable.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import CloseBankPeriodDialog from '@/modules/finance/components/banks/CloseBankPeriodDialog.vue'
import BankMovementDialog from '@/modules/finance/components/banks/BankMovementDialog.vue'
import CancelMovementDialog from '@/modules/finance/components/cash/CancelMovementDialog.vue'
import { banksApi } from '@/modules/finance/services/banksApi'
import { formatDateDMY } from '@/utils/dates'

definePage({
  meta: {
    action: 'bank_movements.view',
    title: 'Detalle de Cuenta Bancaria',
    icon: 'tabler-building-bank',
    description: 'Movimientos, conciliación de periodos y operaciones de la cuenta bancaria.',
  },
})

const route = useRoute()
const router = useRouter()
const { can } = useAcl()
const notify = useNotify()

const accountId = computed(() => route.params.id)

const account = ref(null)
const isLoadingAccount = ref(false)
const activeTab = ref('movements')

// Diálogos
const isMovementDialogOpen = ref(false)
const movementDialogRef = ref(null)
const isSubmittingMovement = ref(false)

const isClosePeriodDialogOpen = ref(false)
const closePeriodDialogRef = ref(null)
const isSubmittingClosePeriod = ref(false)

const isCancelDialogOpen = ref(false)
const selectedMovementToCancel = ref(null)
const isSubmittingCancel = ref(false)

// Diálogo Apertura Inicial
const isOpenInitialDialogOpen = ref(false)
const isSubmittingOpenInitial = ref(false)

const initialOpeningForm = ref({
  'opening_balance': '0.00',
  'opened_at': new Date().toISOString().substring(0, 10),
})

// Movimientos
const movements = ref([])
const totalMovements = ref(0)
const isLoadingMovements = ref(false)
const movementsSummary = ref({ opening: 0, closing: 0 })


// Periodos
const periods = ref([])
const totalPeriods = ref(0)
const isLoadingPeriods = ref(false)

// Operaciones
const operations = ref([])
const totalOperations = ref(0)
const isLoadingOperations = ref(false)

const openPeriod = computed(() => {
  return periods.value.find(p => p.status === 'open') || null
})

const hasPeriods = computed(() => periods.value.length > 0)

const formatMoney = (val, symbol = '') => {
  if (val === null || val === undefined || val === '')
    return `${symbol} 0.00`.trim()
  const num = Number(val)
  const formatted = Number.isNaN(num) ? '0.00' : num.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

  return symbol ? `${symbol} ${formatted}` : formatted
}

const formatRowDateTime = (dateStr, createdAtStr) => {
  if (!dateStr && !createdAtStr)
    return '—'

  let timeStr = ''
  if (createdAtStr) {
    const timeDate = new Date(createdAtStr)
    if (!Number.isNaN(timeDate.getTime())) {
      timeStr = timeDate.toLocaleTimeString('es-PE', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true,
      })
    }
  }

  const dateFormatted = dateStr ? formatDateDMY(dateStr) : (createdAtStr ? formatDateDMY(createdAtStr) : '—')

  return timeStr ? `${dateFormatted} ${timeStr}` : dateFormatted
}

const loadAccount = async () => {
  isLoadingAccount.value = true
  try {
    const res = await banksApi.getAccount(accountId.value)

    account.value = res?.data || null
  }
  catch {
    account.value = null
  }
  finally {
    isLoadingAccount.value = false
  }
}

const loadPeriods = async () => {
  isLoadingPeriods.value = true
  try {
    const res = await banksApi.listPeriods(accountId.value)

    periods.value = res?.data || []
    totalPeriods.value = periods.value.length
  }
  catch {
    periods.value = []
    totalPeriods.value = 0
  }
  finally {
    isLoadingPeriods.value = false
  }
}

const fetchMovements = async params => {
  isLoadingMovements.value = true
  try {
    const res = await banksApi.listMovements(accountId.value, params)
    const data = res?.data || {}

    movementsSummary.value = {
      opening: data.opening ?? 0,
      closing: data.closing ?? 0,
    }

    movements.value = data.movements || []
    totalMovements.value = res?.meta?.total ?? movements.value.length
  }
  catch {
    movements.value = []
    totalMovements.value = 0
  }
  finally {
    isLoadingMovements.value = false
  }
}

const fetchOperations = async params => {
  isLoadingOperations.value = true
  try {
    const res = await banksApi.listOperations({
      ...params,
      'bank_account_id': accountId.value,
    })

    operations.value = res?.data || []
    totalOperations.value = res?.meta?.total ?? operations.value.length
  }
  catch {
    operations.value = []
    totalOperations.value = 0
  }
  finally {
    isLoadingOperations.value = false
  }
}

const movementsTable = useServerTable({
  fetch: fetchMovements,
  filters: {
    'date_from': null,
    'date_to': null,
    direction: null,
  },
})

const periodsTable = useServerTable({
  fetch: loadPeriods,
})

const operationsTable = useServerTable({
  fetch: fetchOperations,
})

onMounted(async () => {
  await Promise.all([
    loadAccount(),
    loadPeriods(),
  ])
})

watch(activeTab, newTab => {
  if (newTab === 'movements')
    movementsTable.reload()
  else if (newTab === 'periods')
    periodsTable.reload()
  else if (newTab === 'operations')
    operationsTable.reload()
})

// Acciones
const openInitialPeriod = () => {
  initialOpeningForm.value = {
    'opening_balance': account.value?.balance !== undefined ? Number(account.value.balance).toFixed(2) : '0.00',
    'opened_at': new Date().toISOString().substring(0, 10),
  }
  isOpenInitialDialogOpen.value = true
}

const handleOpenInitialSubmit = async () => {
  isSubmittingOpenInitial.value = true
  try {
    await banksApi.openInitialPeriod(accountId.value, {
      'opening_balance': Number(initialOpeningForm.value['opening_balance']),
      'opened_at': initialOpeningForm.value['opened_at'],
    })
    notify.success('Periodo inicial abierto correctamente')
    isOpenInitialDialogOpen.value = false
    await Promise.all([
      loadAccount(),
      loadPeriods(),
      movementsTable.reload(),
    ])
  }
  catch (err) {
    notify.error(err?.data?.message || 'Error al aperturar periodo inicial')
  }
  finally {
    isSubmittingOpenInitial.value = false
  }
}

const handleMovementSubmit = async formData => {
  isSubmittingMovement.value = true
  try {
    await banksApi.createMovement(accountId.value, formData)
    notify.success('Movimiento registrado correctamente')
    isMovementDialogOpen.value = false
    await Promise.all([
      loadAccount(),
      movementsTable.reload(),
    ])
  }
  catch (err) {
    movementDialogRef.value?.setErrors(err)
  }
  finally {
    isSubmittingMovement.value = false
  }
}

const handleClosePeriodSubmit = async payload => {
  if (!openPeriod.value)
    return
  isSubmittingClosePeriod.value = true
  try {
    await banksApi.closePeriod(openPeriod.value.id, payload)
    notify.success('Periodo cerrado y nuevo periodo abierto correctamente')
    isClosePeriodDialogOpen.value = false
    await Promise.all([
      loadAccount(),
      loadPeriods(),
      movementsTable.reload(),
    ])
  }
  catch (err) {
    closePeriodDialogRef.value?.setErrors(err)
  }
  finally {
    isSubmittingClosePeriod.value = false
  }
}

const openCancelMovement = movement => {
  selectedMovementToCancel.value = movement
  isCancelDialogOpen.value = true
}

const handleCancelMovementSubmit = async reason => {
  if (!selectedMovementToCancel.value)
    return
  isSubmittingCancel.value = true
  try {
    await banksApi.cancelMovement(selectedMovementToCancel.value.id, reason)
    notify.success('Movimiento anulado correctamente')
    isCancelDialogOpen.value = false
    selectedMovementToCancel.value = null
    await Promise.all([
      loadAccount(),
      movementsTable.reload(),
    ])
  }
  catch (err) {
    notify.error(err?.data?.message || 'Error al anular movimiento')
  }
  finally {
    isSubmittingCancel.value = false
  }
}

const openMovementVoucher = async movement => {
  try {
    await banksApi.openVoucher(movement.id)
  }
  catch (err) {
    notify.error(err?.data?.message || 'No se pudo abrir el comprobante')
  }
}

const openOperationVoucher = async operation => {
  try {
    await banksApi.openOperationVoucher(operation.id)
  }
  catch (err) {
    notify.error(err?.data?.message || 'No se pudo abrir el comprobante')
  }
}

// Headers tablas
const movementHeaders = [
  { title: 'Fecha', key: 'movement_date' },
  { title: 'Tipo', key: 'direction', align: 'center', sortable: false },
  { title: 'Concepto', key: 'concept.name', sortable: false },
  { title: 'N° Operación', key: 'operation_number' },
  { title: 'Beneficiario / Depositante', key: 'counterparty_name', sortable: false },
  { title: 'Descripción', key: 'description' },
  { title: 'Monto', key: 'amount', align: 'end' },
  { title: 'Saldo posterior', key: 'balance_after', align: 'end' },
  { title: 'Voucher', key: 'voucher', align: 'center', sortable: false },
  { title: 'Acciones', key: 'actions', align: 'center', sortable: false },
]

const periodHeaders = [
  { title: 'N°', key: 'number', align: 'center' },
  { title: 'Estado', key: 'status', align: 'center', sortable: false },
  { title: 'Apertura', key: 'opened_at' },
  { title: 'Saldo inicial', key: 'opening_balance', align: 'end' },
  { title: 'Cierre', key: 'closed_at' },
  { title: 'Saldo extracto', key: 'statement_balance', align: 'end' },
  { title: 'Diferencia', key: 'difference', align: 'end' },
  { title: 'Cerrado por', key: 'closed_by_name', sortable: false },
  { title: 'Notas', key: 'notes', sortable: false },
]

const operationHeaders = [
  { title: 'N° Operación', key: 'number' },
  { title: 'Tipo', key: 'type_label', sortable: false },
  { title: 'Fecha', key: 'operation_date' },
  { title: 'Monto', key: 'amount', align: 'end' },
  { title: 'N° Ref / Banco', key: 'operation_number', sortable: false },
  { title: 'Contraparte', key: 'counterparty', sortable: false },
  { title: 'Estado', key: 'status', align: 'center', sortable: false },
  { title: 'Voucher', key: 'voucher', align: 'center', sortable: false },
]
</script>

<template>
  <div>
    <VCard>
      <!-- Encabezado de página -->
      <VCardText>
        <div class="d-flex align-center justify-space-between flex-wrap gap-4">
          <PageHeader />
          <VBtn
            variant="tonal"
            color="secondary"
            @click="router.push({ name: 'finance-banks' })"
          >
            <VIcon
              start
              icon="tabler-arrow-left"
            />
            Volver a bancos
          </VBtn>
        </div>
      </VCardText>

      <VDivider v-if="account" />

      <!-- Ficha Resumen de Cuenta Bancaria -->
      <VCardText v-if="account">
        <div class="d-flex align-center justify-space-between flex-wrap gap-4">
          <div class="d-flex align-center">
            <VAvatar
              size="48"
              color="primary"
              variant="tonal"
              class="me-3"
            >
              <VIcon
                icon="tabler-wallet"
                size="28"
              />
            </VAvatar>
            <div>
              <div class="text-h6 font-weight-bold">
                {{ account.alias }}
                <VChip
                  size="small"
                  :color="account.is_active ? 'success' : 'secondary'"
                  class="ms-2"
                >
                  {{ account.is_active ? 'Activa' : 'Inactiva' }}
                </VChip>
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ account.bank?.name }} &bull; Cuenta: <strong class="text-high-emphasis">{{ account.account_number }}</strong>
                <span v-if="account.cci"> &bull; CCI: {{ account.cci }}</span>
              </div>
            </div>
          </div>

          <!-- Saldo actual y estado del periodo -->
          <div class="d-flex align-center gap-6">
            <div class="text-end">
              <div class="text-caption text-disabled">
                Saldo Actual
              </div>
              <div class="text-h5 font-weight-bold text-primary">
                {{ formatMoney(account.balance, account.currency?.symbol) }}
              </div>
              <div class="text-caption text-disabled">
                {{ account.currency?.name }}
              </div>
            </div>

            <!-- Botones de Acción de Periodo -->
            <div class="d-flex flex-column gap-2">
              <div v-if="openPeriod">
                <VBtn
                  v-if="can('bank_movements.create')"
                  color="primary"
                  variant="elevated"
                  size="small"
                  class="me-2"
                  @click="isMovementDialogOpen = true"
                >
                  <VIcon
                    start
                    icon="tabler-plus"
                  />
                  Nuevo movimiento
                </VBtn>

                <VBtn
                  v-if="can('bank_periods.close')"
                  color="warning"
                  variant="tonal"
                  size="small"
                  @click="isClosePeriodDialogOpen = true"
                >
                  <VIcon
                    start
                    icon="tabler-lock"
                  />
                  Cerrar periodo N° {{ openPeriod.number }}
                </VBtn>
              </div>

              <div v-else-if="!hasPeriods">
                <VBtn
                  color="success"
                  variant="elevated"
                  size="small"
                  @click="openInitialPeriod"
                >
                  <VIcon
                    start
                    icon="tabler-key"
                  />
                  Aperturar cuenta
                </VBtn>
              </div>
            </div>
          </div>
        </div>

        <VAlert
          v-if="!openPeriod && hasPeriods"
          type="warning"
          variant="tonal"
          density="compact"
          class="mt-4"
        >
          No hay un periodo abierto para esta cuenta bancaria.
        </VAlert>

        <VAlert
          v-else-if="!hasPeriods"
          type="info"
          variant="tonal"
          density="compact"
          class="mt-4"
        >
          Esta cuenta bancaria es nueva. Debe realizar la apertura de su primer periodo para comenzar a operar.
        </VAlert>
      </VCardText>

      <!-- Pestañas de Detalle -->
      <VTabs
        v-model="activeTab"
        class="v-tabs-pill mx-6 mb-4"
      >
        <VTab value="movements">
          <VIcon
            start
            icon="tabler-list"
          />
          Movimientos
        </VTab>
        <VTab value="periods">
          <VIcon
            start
            icon="tabler-calendar"
          />
          Periodos
        </VTab>
        <VTab value="operations">
          <VIcon
            start
            icon="tabler-arrows-left-right"
          />
          Operaciones
        </VTab>
      </VTabs>

      <VDivider />

      <VWindow
        v-model="activeTab"
        class="disable-tab-transition"
      >
        <!-- Pestaña 1: Movimientos -->
        <VWindowItem value="movements">
          <!-- Resumen de Saldos Inicial y Final -->
          <VCardText class="pb-2">
            <div class="d-flex align-center justify-space-between flex-wrap gap-4 py-2 px-4 rounded border bg-background">
              <div>
                <span class="text-caption text-medium-emphasis">Saldo Inicial del rango: </span>
                <span class="font-weight-bold">{{ formatMoney(movementsSummary.opening, account?.currency?.symbol) }}</span>
              </div>
              <div>
                <span class="text-caption text-medium-emphasis">Saldo Final del rango: </span>
                <span class="font-weight-bold text-primary">{{ formatMoney(movementsSummary.closing, account?.currency?.symbol) }}</span>
              </div>
            </div>
          </VCardText>

          <ServerDataTable
            v-model:options="movementsTable.options"
            v-model:search="movementsTable.search"
            table-id="bank-movements"
            :headers="movementHeaders"
            :items="movements"
            :total-items="totalMovements"
            :loading="isLoadingMovements"
            :has-filters="movementsTable.hasFilters"
            search-placeholder="Buscar en descripción o contraparte..."
            @refresh="movementsTable.reload"
          >
            <template #filters>
              <VTextField
                v-model="movementsTable.filters['date_from']"
                type="date"
                label="Desde"
                placeholder="Fecha desde"
                density="compact"
                clearable
                style="min-width: 150px;"
              />
              <VTextField
                v-model="movementsTable.filters['date_to']"
                type="date"
                label="Hasta"
                placeholder="Fecha hasta"
                density="compact"
                clearable
                style="min-width: 150px;"
              />
              <VSelect
                v-model="movementsTable.filters.direction"
                :items="[
                  { title: 'Ingresos (Abonos)', value: 'in' },
                  { title: 'Egresos (Cargos)', value: 'out' },
                ]"
                label="Dirección"
                placeholder="Todas las direcciones"
                density="compact"
                clearable
                style="min-width: 160px;"
              />
            </template>

            <!-- Columna Fecha -->
            <template #item.movement_date="{ item }">
              <span class="text-no-wrap">
                {{ formatRowDateTime(item.movement_date, item.created_at) }}
              </span>
            </template>

            <!-- Columna Tipo / Dirección -->
            <template #item.direction="{ item }">
              <VChip
                size="small"
                :color="item.direction === 'in' ? 'success' : 'error'"
                variant="tonal"
              >
                {{ item.direction === 'in' ? 'Ingreso' : 'Egreso' }}
              </VChip>
            </template>

            <!-- Columna Monto -->
            <template #item.amount="{ item }">
              <div
                class="font-weight-bold"
                :class="item.direction === 'in' ? 'text-success' : 'text-error'"
              >
                {{ item.direction === 'in' ? '+' : '-' }} {{ formatMoney(item.amount, account?.currency?.symbol) }}
              </div>
            </template>

            <!-- Columna Saldo Posterior -->
            <template #item.balance_after="{ item }">
              <div class="font-weight-medium">
                {{ formatMoney(item.balance_after, account?.currency?.symbol) }}
              </div>
            </template>

            <!-- Columna Voucher -->
            <template #item.voucher="{ item }">
              <VBtn
                v-if="item.voucher_url"
                icon
                size="small"
                variant="text"
                color="primary"
                title="Ver voucher"
                @click="openMovementVoucher(item)"
              >
                <VIcon icon="tabler-paperclip" />
              </VBtn>
              <span
                v-else
                class="text-disabled"
              >—</span>
            </template>

            <!-- Columna Acciones -->
            <template #item.actions="{ item }">
              <div class="d-flex justify-center gap-1">
                <RowActionButton
                  v-if="item.is_manual && !item.is_reversal && !item.reversed_by"
                  action="delete"
                  tooltip="Anular movimiento"
                  @click="openCancelMovement(item)"
                />
                <span
                  v-else-if="item.reversed_by"
                  class="text-caption text-error font-italic"
                >
                  Anulado
                </span>
                <span
                  v-else
                  class="text-disabled"
                >—</span>
              </div>
            </template>
          </ServerDataTable>
        </VWindowItem>

        <!-- Pestaña 2: Periodos -->
        <VWindowItem value="periods">
          <ServerDataTable
            v-model:options="periodsTable.options"
            v-model:search="periodsTable.search"
            table-id="bank-periods"
            :headers="periodHeaders"
            :items="periods"
            :total-items="totalPeriods"
            :loading="isLoadingPeriods"
            :has-filters="periodsTable.hasFilters"
            search-placeholder="Buscar periodo..."
            @refresh="periodsTable.reload"
          >
            <!-- Columna Estado -->
            <template #item.status="{ item }">
              <VChip
                size="small"
                :color="item.status === 'open' ? 'success' : 'secondary'"
                variant="tonal"
              >
                {{ item.status === 'open' ? 'Abierto' : 'Cerrado' }}
              </VChip>
            </template>

            <!-- Columna Apertura -->
            <template #item.opened_at="{ item }">
              <span class="text-no-wrap">
                {{ formatRowDateTime(item.opened_at, item.created_at) }}
              </span>
            </template>

            <!-- Columna Saldo Inicial -->
            <template #item.opening_balance="{ item }">
              {{ formatMoney(item.opening_balance, account?.currency?.symbol) }}
            </template>

            <!-- Columna Cierre -->
            <template #item.closed_at="{ item }">
              <span
                v-if="item.closed_at"
                class="text-no-wrap"
              >
                {{ formatRowDateTime(item.closed_at, item.updated_at) }}
              </span>
              <span
                v-else
                class="text-disabled"
              >—</span>
            </template>

            <!-- Columna Saldo Extracto -->
            <template #item.statement_balance="{ item }">
              <span v-if="item.statement_balance !== null">
                {{ formatMoney(item.statement_balance, account?.currency?.symbol) }}
              </span>
              <span
                v-else
                class="text-disabled"
              >—</span>
            </template>

            <!-- Columna Diferencia -->
            <template #item.difference="{ item }">
              <span
                v-if="item.difference !== null"
                :class="Math.abs(item.difference) > 0.001 ? 'text-warning font-weight-bold' : 'text-success'"
              >
                {{ formatMoney(item.difference, account?.currency?.symbol) }}
              </span>
              <span
                v-else
                class="text-disabled"
              >—</span>
            </template>
          </ServerDataTable>
        </VWindowItem>

        <!-- Pestaña 3: Operaciones -->
        <VWindowItem value="operations">
          <ServerDataTable
            v-model:options="operationsTable.options"
            v-model:search="operationsTable.search"
            table-id="bank-operations"
            :headers="operationHeaders"
            :items="operations"
            :total-items="totalOperations"
            :loading="isLoadingOperations"
            :has-filters="operationsTable.hasFilters"
            search-placeholder="Buscar operación..."
            @refresh="operationsTable.reload"
          >
            <!-- Columna Fecha -->
            <template #item.operation_date="{ item }">
              <span class="text-no-wrap">
                {{ formatRowDateTime(item.operation_date, item.created_at) }}
              </span>
            </template>

            <!-- Columna Monto -->
            <template #item.amount="{ item }">
              <div class="font-weight-bold">
                {{ formatMoney(item.amount) }} {{ item.currency_code }}
              </div>
            </template>

            <!-- Columna Contraparte -->
            <template #item.counterparty="{ item }">
              <div v-if="item.type === 'deposit' || item.type === 'withdrawal'">
                Caja: {{ item.cash_session?.box?.name || 'Sesión N° ' + (item.cash_session?.number || '') }}
              </div>
              <div v-else-if="item.type === 'transfer'">
                Destino: {{ item.target_bank_account?.alias || '—' }}
              </div>
            </template>

            <!-- Columna Estado -->
            <template #item.status="{ item }">
              <VChip
                size="small"
                :color="item.status === 'completed' ? 'success' : 'error'"
                variant="tonal"
              >
                {{ item.status === 'completed' ? 'Completada' : 'Anulada' }}
              </VChip>
            </template>

            <!-- Columna Voucher -->
            <template #item.voucher="{ item }">
              <VBtn
                v-if="item.voucher_url"
                icon
                size="small"
                variant="text"
                color="primary"
                title="Ver voucher"
                @click="openOperationVoucher(item)"
              >
                <VIcon icon="tabler-paperclip" />
              </VBtn>
              <span
                v-else
                class="text-disabled"
              >—</span>
            </template>
          </ServerDataTable>
        </VWindowItem>
      </VWindow>
    </VCard>

    <!-- Diálogo Apertura Inicial -->
    <VDialog
      v-model="isOpenInitialDialogOpen"
      max-width="480"
      persistent
    >
      <VCard>
        <VCardItem class="pb-3">
          <template #prepend>
            <VAvatar
              color="success"
              variant="tonal"
              class="me-2"
            >
              <VIcon
                icon="tabler-key"
                size="24"
              />
            </VAvatar>
          </template>
          <VCardTitle>Aperturar Primer Periodo</VCardTitle>
          <VCardSubtitle>{{ account?.alias }} ({{ account?.account_number }})</VCardSubtitle>
        </VCardItem>

        <VCardText>
          <VForm @submit.prevent="handleOpenInitialSubmit">
            <VRow>
              <VCol cols="12">
                <VTextField
                  v-model="initialOpeningForm['opening_balance']"
                  type="number"
                  step="0.01"
                  label="Saldo de apertura *"
                  placeholder="ej. 1000.00"
                  :prefix="account?.currency?.symbol || 'S/'"
                />
              </VCol>
              <VCol cols="12">
                <VTextField
                  v-model="initialOpeningForm['opened_at']"
                  type="date"
                  label="Fecha de apertura *"
                  placeholder="ej. 2026-10-01"
                />
              </VCol>
            </VRow>
          </VForm>
        </VCardText>

        <VCardActions class="px-6 pb-4">
          <VSpacer />
          <VBtn
            variant="tonal"
            color="secondary"
            :disabled="isSubmittingOpenInitial"
            @click="isOpenInitialDialogOpen = false"
          >
            Cancelar
          </VBtn>
          <VBtn
            color="success"
            variant="elevated"
            :loading="isSubmittingOpenInitial"
            @click="handleOpenInitialSubmit"
          >
            Aperturar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Diálogos de Gestión -->
    <BankMovementDialog
      ref="movementDialogRef"
      v-model:is-dialog-open="isMovementDialogOpen"
      :account="account"
      :is-submitting="isSubmittingMovement"
      @submit="handleMovementSubmit"
    />

    <CloseBankPeriodDialog
      ref="closePeriodDialogRef"
      v-model:is-dialog-open="isClosePeriodDialogOpen"
      :period="openPeriod"
      :account="account"
      :is-submitting="isSubmittingClosePeriod"
      @submit="handleClosePeriodSubmit"
    />

    <CancelMovementDialog
      v-model:is-dialog-open="isCancelDialogOpen"
      :movement="selectedMovementToCancel"
      :is-submitting="isSubmittingCancel"
      @submit="handleCancelMovementSubmit"
    />
  </div>
</template>
