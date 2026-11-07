<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useAcl } from '@/composables/useAcl'
import { useNotify } from '@/composables/useNotify'
import { useServerTable } from '@/composables/useServerTable'
import PageHeader from '@/components/common/PageHeader.vue'
import ServerDataTable from '@/components/common/ServerDataTable.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import CashSummaryCards from '@/modules/finance/components/cash/CashSummaryCards.vue'
import CashMovementDialog from '@/modules/finance/components/cash/CashMovementDialog.vue'
import CashCountDialog from '@/modules/finance/components/cash/CashCountDialog.vue'
import CancelMovementDialog from '@/modules/finance/components/cash/CancelMovementDialog.vue'
import CashSessionMethodSummary from '@/modules/finance/components/cash/CashSessionMethodSummary.vue'
import CashTransferDialog from '@/modules/finance/components/cash/CashTransferDialog.vue'
import BankOperationDialog from '@/modules/finance/components/banks/BankOperationDialog.vue'
import ExpenseDialog from '@/modules/purchases/components/invoices/ExpenseDialog.vue'
import CollectionDialog from '@/modules/sales/components/receivables/CollectionDialog.vue'
import { useCashSession } from '@/modules/finance/composables/useCashSession'
import { boxesApi, cashSessionsApi } from '@/modules/finance/services/cashApi'
import { cashTransfersApi } from '@/modules/finance/services/cashTransfersApi'
import { banksApi } from '@/modules/finance/services/banksApi'
import { useApiFormErrors } from '@/composables/useApiFormErrors'

definePage({
  meta: {
    action: 'cash_sessions.operate',
    title: 'Caja',
    icon: 'tabler-cash-register',
    description: 'Operación diaria de caja: apertura, cobros, egresos y cierre de turno.',
  },
})

const { can } = useAcl()
const notify = useNotify()

const {
  availableBoxes,
  selectedBoxId,
  selectedBox,
  currentSession,
  sessionSummary,
  hasOpenSession,
  isLoadingBoxes,
  isLoadingSession,
  fetchAvailableBoxes,
  selectBox,
  fetchCurrentSession,
  openSession,
  closeSession,
  addMovement,
  cancelMovement,
} = useCashSession()

const { errors: openErrors, generalError: openGeneralError, setErrors: setOpenErrors, clearErrors: clearOpenErrors } = useApiFormErrors()

// Estados locales
const activeTab = ref('movements')
const suggestedAmount = ref(0)
const openingAmount = ref('0.00')
const openingNotes = ref('')
const isOpeningSession = ref(false)

// Modales
const isMovementDialogOpen = ref(false)
const movementDirection = ref('in')
const isSubmittingMovement = ref(false)

const isCloseDialogOpen = ref(false)
const isSubmittingClose = ref(false)

const isCancelDialogOpen = ref(false)
const selectedMovementToCancel = ref(null)
const isSubmittingCancel = ref(false)

const isTransferDialogOpen = ref(false)
const isSubmittingTransfer = ref(false)

const isBankDepositDialogOpen = ref(false)
const isExpenseDialogOpen = ref(false)
const isCollectionDialogOpen = ref(false)
const bankDepositDialogRef = ref(null)
const isSubmittingBankDeposit = ref(false)

// Movimientos de la sesión actual
const movements = ref([])
const totalMovements = ref(0)
const isLoadingMovements = ref(false)

const loadSuggestedOpening = async boxId => {
  if (!boxId) {
    suggestedAmount.value = 0
    openingAmount.value = '0.00'

    return
  }
  try {
    const res = await boxesApi.suggestedOpening(boxId)
    const amount = Number(res?.data?.suggested_amount || res?.data?.suggested_opening || 0)

    suggestedAmount.value = amount
    openingAmount.value = amount.toFixed(2)
  }
  catch {
    suggestedAmount.value = 0
    openingAmount.value = '0.00'
  }
}

const fetchMovements = async params => {
  if (!currentSession.value?.id) {
    movements.value = []
    totalMovements.value = 0

    return { data: [], meta: { total: 0 } }
  }

  isLoadingMovements.value = true
  try {
    const res = await cashSessionsApi.movements(currentSession.value.id, params)

    movements.value = res?.data || []
    totalMovements.value = res?.meta?.total ?? movements.value.length

    return res
  }
  catch {
    movements.value = []
    totalMovements.value = 0

    return { data: [], meta: { total: 0 } }
  }
  finally {
    isLoadingMovements.value = false
  }
}

const table = useServerTable({
  fetch: fetchMovements,
})

watch(() => selectedBoxId.value, newBoxId => {
  if (newBoxId && !hasOpenSession.value) {
    loadSuggestedOpening(newBoxId)
  }
}, { immediate: true })

watch(() => hasOpenSession.value, isOpen => {
  if (isOpen) {
    table.reload()
  }
  else if (selectedBoxId.value) {
    loadSuggestedOpening(selectedBoxId.value)
  }
})

onMounted(async () => {
  await fetchAvailableBoxes()
})

const handleBoxChange = async boxId => {
  await selectBox(boxId)
}

const handleOpenSession = async () => {
  clearOpenErrors()
  isOpeningSession.value = true
  try {
    await openSession({
      'opening_amount': Number(openingAmount.value),
      'opening_notes': openingNotes.value ? openingNotes.value.trim() : null,
    })
    notify.success('Caja abierta correctamente')
    await table.reload()
  }
  catch (err) {
    setOpenErrors(err)
  }
  finally {
    isOpeningSession.value = false
  }
}

const openMovementDialog = dir => {
  movementDirection.value = dir
  isMovementDialogOpen.value = true
}

const handleMovementSubmit = async payload => {
  isSubmittingMovement.value = true
  try {
    await addMovement(payload)
    notify.success(payload.direction === 'in' ? 'Ingreso registrado correctamente' : 'Egreso registrado correctamente')
    isMovementDialogOpen.value = false
    await table.reload()
  }
  catch (err) {
    notify.error(err?.data?.message || 'Error al registrar movimiento')
  }
  finally {
    isSubmittingMovement.value = false
  }
}

const openCloseDialog = () => {
  isCloseDialogOpen.value = true
}

const handleCloseSubmit = async payload => {
  isSubmittingClose.value = true
  try {
    await closeSession(payload)
    notify.success('Sesión de caja cerrada correctamente')
    isCloseDialogOpen.value = false
  }
  catch (err) {
    notify.error(err?.data?.message || 'Error al cerrar caja')
  }
  finally {
    isSubmittingClose.value = false
  }
}

const openCancelMovement = movement => {
  selectedMovementToCancel.value = movement
  isCancelDialogOpen.value = true
}

const handleCancelSubmit = async reason => {
  if (!selectedMovementToCancel.value)
    return
  isSubmittingCancel.value = true
  try {
    await cancelMovement(selectedMovementToCancel.value.id, reason)
    notify.success('Movimiento anulado correctamente')
    isCancelDialogOpen.value = false
    await table.reload()
  }
  catch (err) {
    notify.error(err?.data?.message || 'Error al anular movimiento')
  }
  finally {
    isSubmittingCancel.value = false
  }
}

const openTransferDialog = () => {
  isTransferDialogOpen.value = true
}

const handleTransferSubmit = async (payload, setErrors) => {
  isSubmittingTransfer.value = true
  try {
    await cashTransfersApi.create(payload)
    notify.success('Transferencia solicitada correctamente')
    isTransferDialogOpen.value = false
    await fetchCurrentSession()
    await table.reload()
  }
  catch (err) {
    if (setErrors && err?.data?.errors) {
      setErrors(err.data.errors)
    }
    else {
      notify.error(err?.data?.message || 'Error al solicitar transferencia')
    }
  }
  finally {
    isSubmittingTransfer.value = false
  }
}

const openBankDepositDialog = () => {
  isBankDepositDialogOpen.value = true
}

const handleBankDepositSubmit = async formData => {
  isSubmittingBankDeposit.value = true
  try {
    await banksApi.createOperation(formData)
    notify.success('Depósito en banco registrado correctamente')
    isBankDepositDialogOpen.value = false
    await fetchCurrentSession()
    await table.reload()
  }
  catch (err) {
    bankDepositDialogRef.value?.setErrors(err)
  }
  finally {
    isSubmittingBankDeposit.value = false
  }
}

// El gasto pagado en el acto agrega un egreso a la sesión: se refrescan el resumen y los movimientos.
const handleExpenseSaved = async () => {
  await fetchCurrentSession()
  await table.reload()
}

// La cobranza en efectivo agrega un ingreso a la sesión: se refrescan el resumen y los movimientos.
const handleCollectionSaved = async () => {
  await fetchCurrentSession()
  await table.reload()
}

const formatCurrency = val => {
  const num = Number(val || 0)

  return `S/ ${num.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

const formatTime = dateStr => {
  if (!dateStr)
    return '—'
  const date = new Date(dateStr)

  return date.toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
}

const headers = [
  { title: 'HORA', key: 'moved_at', sortable: false, alwaysVisible: true },
  { title: 'TIPO', key: 'direction', sortable: false, align: 'center' },
  { title: 'CONCEPTO', key: 'concept', sortable: false },
  { title: 'MÉTODO', key: 'payment_method', sortable: false },
  { title: 'MONTO', key: 'amount', sortable: false, align: 'end' },
  { title: 'REFERENCIA', key: 'reference', sortable: false },
  { title: 'DESCRIPCIÓN', key: 'description', sortable: false },
  { title: 'REGISTRADO POR', key: 'creator', sortable: false },
  { title: 'ACCIONES', key: 'actions', sortable: false, align: 'center' },
]
</script>

<template>
  <div>
    <!-- Encabezado de Página -->
    <VCard class="mb-4">
      <VCardText>
        <PageHeader />
      </VCardText>

      <VDivider />

      <!-- Barra de Selección de Caja -->
      <VCardText class="py-3">
        <VRow align="center">
          <VCol
            cols="12"
            sm="6"
            md="4"
          >
            <VSelect
              :model-value="selectedBoxId"
              :items="availableBoxes"
              item-title="name"
              item-value="id"
              label="Caja Registradora *"
              placeholder="Seleccionar caja"
              density="compact"
              :loading="isLoadingBoxes"
              @update:model-value="handleBoxChange"
            >
              <template #item="{ props: itemProps, item }">
                <VListItem
                  v-bind="itemProps"
                  :subtitle="item.raw.branch ? item.raw.branch.name : ''"
                >
                  <template #append>
                    <VChip
                      v-if="item.raw.open_session"
                      size="x-small"
                      color="success"
                      variant="tonal"
                    >
                      Abierta
                    </VChip>
                    <VChip
                      v-else
                      size="x-small"
                      color="secondary"
                      variant="tonal"
                    >
                      Cerrada
                    </VChip>
                  </template>
                </VListItem>
              </template>
            </VSelect>
          </VCol>

          <VCol
            v-if="selectedBox"
            cols="12"
            sm="6"
            md="8"
            class="d-flex align-center flex-wrap gap-2 justify-sm-end"
          >
            <VChip
              variant="outlined"
              color="primary"
            >
              <VIcon
                start
                icon="tabler-device-desktop"
              />
              {{ selectedBox.code }}
            </VChip>

            <VChip
              v-if="hasOpenSession && currentSession"
              color="success"
              variant="elevated"
            >
              <VIcon
                start
                icon="tabler-circle-check"
              />
              Sesión N° {{ currentSession.number }}
            </VChip>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Estado: No hay cajas asignadas -->
    <VAlert
      v-if="!isLoadingBoxes && availableBoxes.length === 0"
      type="warning"
      variant="tonal"
      class="mb-4"
    >
      <div class="font-weight-medium">
        No tienes cajas asignadas o activas para operar en esta sede.
      </div>
      <div class="text-caption">
        Contacte al administrador para que asigne su rol a una caja registradora de la sede activa.
      </div>
    </VAlert>

    <!-- Estado 0: Cargando la sesión de la caja seleccionada -->
    <VCard
      v-else-if="selectedBox && isLoadingSession"
      border
      class="mb-6"
    >
      <VCardText class="d-flex justify-center align-center py-10">
        <VProgressCircular
          indeterminate
          color="primary"
        />
      </VCardText>
    </VCard>

    <!-- Estado 1: Caja seleccionada SIN sesión abierta (Formulario de Apertura) -->
    <VCard
      v-else-if="selectedBox && !hasOpenSession"
      border
      class="mb-6"
    >
      <VCardItem class="border-b">
        <template #prepend>
          <VAvatar
            color="primary"
            variant="tonal"
            rounded
            size="38"
            class="me-2"
          >
            <VIcon icon="tabler-door-enter" />
          </VAvatar>
        </template>
        <VCardTitle>Apertura de Turno de Caja</VCardTitle>
        <VCardSubtitle>
          La caja <strong>{{ selectedBox.name }}</strong> ({{ selectedBox.code }}) se encuentra cerrada actualmente.
        </VCardSubtitle>
      </VCardItem>

      <VCardText class="pt-6">
        <VAlert
          v-if="openGeneralError"
          type="error"
          variant="tonal"
          class="mb-4"
        >
          {{ openGeneralError }}
        </VAlert>

        <VRow justify="center">
          <VCol
            cols="12"
            md="8"
          >
            <VForm @submit.prevent="handleOpenSession">
              <VRow>
                <VCol
                  cols="12"
                  sm="6"
                >
                  <VTextField
                    v-model="openingAmount"
                    type="number"
                    step="0.01"
                    min="0"
                    label="Fondo Inicial de Apertura (S/) *"
                    placeholder="150.00"
                    prefix="S/"
                    :error-messages="openErrors.opening_amount"
                  />
                  <div class="text-caption text-medium-emphasis mt-1">
                    Monto sugerido del último cierre: <strong>{{ formatCurrency(suggestedAmount) }}</strong>
                  </div>
                </VCol>

                <VCol
                  cols="12"
                  sm="6"
                >
                  <VTextarea
                    v-model="openingNotes"
                    label="Observaciones de Apertura"
                    placeholder="Fondo entregado en billetes y monedas..."
                    rows="2"
                    maxlength="500"
                    :error-messages="openErrors.opening_notes"
                  />
                </VCol>

                <VCol
                  cols="12"
                  class="d-flex justify-end mt-2"
                >
                  <VBtn
                    color="primary"
                    size="large"
                    type="submit"
                    :loading="isOpeningSession"
                    :disabled="isOpeningSession"
                  >
                    <VIcon
                      start
                      icon="tabler-door-enter"
                    />
                    Abrir Caja e Iniciar Turno
                  </VBtn>
                </VCol>
              </VRow>
            </VForm>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Estado 2: Caja CON sesión abierta (Operación Diaria) -->
    <div v-else-if="selectedBox && hasOpenSession && currentSession">
      <!-- Tarjetas de Resumen Financiero -->
      <CashSummaryCards
        :summary="sessionSummary"
        :session="currentSession"
      />

      <!-- Barra de Operaciones y Acciones de Turno -->
      <VCard class="mb-4">
        <VCardText class="d-flex flex-wrap align-center justify-space-between gap-4 py-3">
          <div class="d-flex align-center gap-3">
            <VAvatar
              color="success"
              variant="tonal"
              rounded
              size="38"
            >
              <VIcon icon="tabler-cash-register" />
            </VAvatar>
            <div>
              <div class="text-subtitle-1 font-weight-bold">
                Turno Activo: {{ currentSession.number }}
              </div>
              <div class="text-caption text-disabled">
                Abierto por {{ currentSession.opener?.name || 'Usuario' }} a las {{ formatTime(currentSession.opened_at) }}
              </div>
            </div>
          </div>

          <div class="d-flex align-center flex-wrap gap-2">
            <VBtn
              color="success"
              variant="elevated"
              @click="openMovementDialog('in')"
            >
              <VIcon
                start
                icon="tabler-plus"
              />
              Nuevo Ingreso
            </VBtn>

            <VBtn
              color="error"
              variant="elevated"
              @click="openMovementDialog('out')"
            >
              <VIcon
                start
                icon="tabler-minus"
              />
              Nuevo Egreso
            </VBtn>

            <VBtn
              v-if="can('cash_transfers.create')"
              color="primary"
              variant="tonal"
              @click="openTransferDialog"
            >
              <VIcon
                start
                icon="tabler-arrows-left-right"
              />
              Transferir
            </VBtn>

            <VBtn
              v-if="can('bank_operations.create')"
              color="info"
              variant="tonal"
              @click="openBankDepositDialog"
            >
              <VIcon
                start
                icon="tabler-building-bank"
              />
              Depositar en banco
            </VBtn>

            <VBtn
              v-if="can('supplier_invoices.create') && can('supplier_payments.create')"
              color="secondary"
              variant="tonal"
              @click="isExpenseDialogOpen = true"
            >
              <VIcon
                start
                icon="tabler-receipt-tax"
              />
              Registrar gasto
            </VBtn>

            <VBtn
              v-if="can('collections.create')"
              color="primary"
              variant="tonal"
              @click="isCollectionDialogOpen = true"
            >
              <VIcon
                start
                icon="tabler-coins"
              />
              Cobrar crédito
            </VBtn>

            <VBtn
              color="warning"
              variant="elevated"
              @click="openCloseDialog"
            >
              <VIcon
                start
                icon="tabler-lock"
              />
              Cerrar Caja
            </VBtn>
          </div>
        </VCardText>
      </VCard>

      <!-- Pestañas de Vista: Movimientos y Resumen -->
      <VTabs
        v-model="activeTab"
        class="v-tabs-pill mb-4"
      >
        <VTab value="movements">
          <VIcon
            start
            icon="tabler-list"
          />
          Movimientos del Turno
        </VTab>
        <VTab value="summary">
          <VIcon
            start
            icon="tabler-chart-pie"
          />
          Resumen por Método y Concepto
        </VTab>
      </VTabs>

      <!-- Contenido de Pestaña Movimientos -->
      <div v-show="activeTab === 'movements'">
        <VCard>
          <ServerDataTable
            v-model:options="table.options"
            v-model:search="table.search"
            table-id="cash-movements"
            :headers="headers"
            :items="movements"
            :total-items="totalMovements"
            :loading="isLoadingMovements"
            :has-filters="false"
            search-placeholder="Buscar movimientos..."
            :creatable="false"
            @refresh="table.reload"
          >
            <!-- Hora -->
            <template #item.moved_at="{ item }">
              <span class="font-monospace text-caption">{{ formatTime(item.moved_at) }}</span>
            </template>

            <!-- Tipo (Dirección) -->
            <template #item.direction="{ item }">
              <VChip
                size="small"
                variant="tonal"
                :color="item.direction === 'in' ? 'success' : 'error'"
              >
                {{ item.direction === 'in' ? 'Ingreso' : 'Egreso' }}
              </VChip>
            </template>

            <!-- Concepto -->
            <template #item.concept="{ item }">
              <span class="font-weight-medium">{{ item.concept?.name || '—' }}</span>
            </template>

            <!-- Método -->
            <template #item.payment_method="{ item }">
              <span>{{ item.payment_method?.name || '—' }}</span>
            </template>

            <!-- Monto -->
            <template #item.amount="{ item }">
              <span
                class="font-monospace font-weight-bold"
                :class="item.direction === 'in' ? 'text-success' : 'text-error'"
              >
                {{ item.direction === 'in' ? '+' : '-' }}{{ formatCurrency(item.amount) }}
              </span>
            </template>

            <!-- Referencia -->
            <template #item.reference="{ item }">
              <span>{{ item.reference || '—' }}</span>
            </template>

            <!-- Descripción -->
            <template #item.description="{ item }">
              <div>
                <div>{{ item.description || '—' }}</div>
                <div
                  v-if="item.counterparty_name"
                  class="text-caption text-disabled"
                >
                  {{ item.counterparty_name }}
                </div>
              </div>
            </template>

            <!-- Registrado por -->
            <template #item.creator="{ item }">
              <span class="text-caption">{{ item.creator?.name || '—' }}</span>
            </template>

            <!-- Acciones -->
            <template #item.actions="{ item }">
              <div class="d-flex justify-center">
                <RowActionButton
                  v-if="item.is_manual && !item.reversed_by && can('cash_movements.cancel')"
                  action="delete"
                  tooltip="Anular movimiento"
                  @click="openCancelMovement(item)"
                />
                <VChip
                  v-else-if="item.reversed_by"
                  size="x-small"
                  color="secondary"
                  variant="tonal"
                >
                  Anulado
                </VChip>
                <span
                  v-else
                  class="text-disabled"
                >—</span>
              </div>
            </template>
          </ServerDataTable>
        </VCard>
      </div>

      <!-- Contenido de Pestaña Resumen -->
      <div v-show="activeTab === 'summary'">
        <CashSessionMethodSummary :summary="sessionSummary" />
      </div>
    </div>

    <!-- Modales -->
    <CashMovementDialog
      v-model:is-dialog-open="isMovementDialogOpen"
      :direction="movementDirection"
      :is-submitting="isSubmittingMovement"
      @submit="handleMovementSubmit"
    />

    <CashCountDialog
      v-model:is-dialog-open="isCloseDialogOpen"
      :expected-cash="sessionSummary?.expected_cash ?? currentSession?.expected_cash ?? 0"
      :is-submitting="isSubmittingClose"
      @submit="handleCloseSubmit"
    />

    <CancelMovementDialog
      v-model:is-dialog-open="isCancelDialogOpen"
      :movement="selectedMovementToCancel"
      :is-submitting="isSubmittingCancel"
      @submit="handleCancelSubmit"
    />

    <CashTransferDialog
      v-model:is-dialog-open="isTransferDialogOpen"
      :source-session="currentSession"
      :source-box="selectedBox"
      :available-cash="sessionSummary?.available_cash ?? sessionSummary?.expected_cash ?? 0"
      :is-submitting="isSubmittingTransfer"
      @submit="handleTransferSubmit"
    />

    <BankOperationDialog
      ref="bankDepositDialogRef"
      v-model:is-dialog-open="isBankDepositDialogOpen"
      initial-type="deposit"
      :preselected-cash-session-id="currentSession?.id"
      :is-submitting="isSubmittingBankDeposit"
      @submit="handleBankDepositSubmit"
    />

    <ExpenseDialog
      v-model="isExpenseDialogOpen"
      @saved="handleExpenseSaved"
    />

    <CollectionDialog
      v-model="isCollectionDialogOpen"
      :default-cash-session-id="currentSession?.id"
      @collected="handleCollectionSaved"
    />
  </div>
</template>
