<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import CancelDocumentDialog from '@/components/common/CancelDocumentDialog.vue'
import ClientPicker from '@/components/common/ClientPicker.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import ServerDataTable from '@/components/common/ServerDataTable.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import { useAcl } from '@/composables/useAcl'
import { useNotify } from '@/composables/useNotify'
import { useServerTable } from '@/composables/useServerTable'
import { currenciesApi, paymentMethodsApi } from '@/modules/finance/services/financeSettingsApi'
import CollectionDialog from '@/modules/sales/components/receivables/CollectionDialog.vue'
import ReceivablesAgingTable from '@/modules/sales/components/receivables/ReceivablesAgingTable.vue'
import { receivablesApi } from '@/modules/sales/services/receivablesApi'
import { formatDateDMY } from '@/utils/dates'
import { formatAmount } from '@/utils/documentTotals'

definePage({
  meta: {
    action: ['receivables.view', 'collections.create'],
    title: 'Cuentas por cobrar',
    icon: 'tabler-calendar-dollar',
    description: 'Control de cuotas pendientes, cobranzas a clientes, antigüedad de deuda y estados de cuenta.',
  },
})

const route = useRoute()
const { can } = useAcl()
const notify = useNotify()

const tabs = computed(() => [
  { value: 'installments', title: 'Cuotas por cobrar', icon: 'tabler-calendar-dollar', visible: can('receivables.view') },
  { value: 'aging', title: 'Antigüedad de deuda', icon: 'tabler-chart-bar', visible: can('receivables.view') },
  { value: 'collections', title: 'Historial de cobranzas', icon: 'tabler-history', visible: can('receivables.view') || can('collections.create') },
].filter(tab => tab.visible))

const activeTab = ref(
  tabs.value.some(tab => tab.value === route.query.tab)
    ? route.query.tab
    : tabs.value[0]?.value || 'installments',
)

const currencies = ref([])
const paymentMethods = ref([])
const summaryTotals = ref([])

const loadDependencies = async () => {
  try {
    const [cRes, pmRes] = await Promise.all([
      currenciesApi.options(),
      paymentMethodsApi.options(),
    ])

    currencies.value = cRes?.data || []
    paymentMethods.value = pmRes?.data || []
  }
  catch {
    // Silencioso
  }
}

onMounted(() => {
  loadDependencies()
})

// --- TAB 1: Cuotas por cobrar ---
const selectedInstallments = ref([])
const isCollectionDialogOpen = ref(false)
const collectionDialogInstallments = ref([])

const installmentsTable = useServerTable({
  immediate: can('receivables.view'),
  fetch: async params => {
    const listRes = await receivablesApi.list(params)

    summaryTotals.value = listRes?.summary || []

    return listRes
  },
  filters: {
    client_id: null,
    currency_id: null,
    status: null,
    overdue: null,
    due_from: null,
    due_to: null,
  },
  mapParams: params => {
    const mapped = { ...params }
    if (mapped.overdue === '0') {
      mapped.overdue = undefined
    }

    return mapped
  },
})

const installmentHeaders = [
  { title: 'VENCIMIENTO', key: 'due_date', sortable: false, alwaysVisible: true },
  { title: 'CLIENTE', key: 'client', sortable: false },
  { title: 'DOCUMENTO', key: 'sale', sortable: false },
  { title: 'CUOTA', key: 'number', sortable: false, align: 'center' },
  { title: 'MONTO', key: 'amount', sortable: false, align: 'end' },
  { title: 'PAGADO', key: 'paid_amount', sortable: false, align: 'end' },
  { title: 'SALDO', key: 'balance', sortable: false, align: 'end' },
  { title: 'ESTADO', key: 'status', sortable: false, align: 'center' },
  { title: 'ACCIONES', key: 'actions', sortable: false, align: 'center' },
]

const installmentStatusBadge = status => {
  switch (status) {
  case 'paid':
    return { title: 'Pagada', color: 'success' }
  case 'partially_paid':
    return { title: 'Pago Parcial', color: 'info' }
  case 'pending':
    return { title: 'Pendiente', color: 'warning' }
  case 'cancelled':
    return { title: 'Anulada', color: 'secondary' }
  default:
    return { title: status, color: 'default' }
  }
}

const canCollectSelected = computed(() => {
  if (!selectedInstallments.value.length) return false
  const first = selectedInstallments.value[0]
  const clientId = first.client_id || first.sale?.client_id
  const currencyId = first.currency_id || first.currency?.id

  return selectedInstallments.value.every(inst => {
    const cId = inst.client_id || inst.sale?.client_id
    const currId = inst.currency_id || inst.currency?.id

    return cId === clientId && currId === currencyId && Number(inst.balance) > 0
  })
})

const openCollectionForSelected = () => {
  if (!canCollectSelected.value) {
    notify.error('Solo se pueden cobrar juntas cuotas del mismo cliente y la misma moneda.')

    return
  }
  collectionDialogInstallments.value = [...selectedInstallments.value]
  isCollectionDialogOpen.value = true
}

const openCollectionForSingle = inst => {
  collectionDialogInstallments.value = [inst]
  isCollectionDialogOpen.value = true
}

const openNewCollection = () => {
  collectionDialogInstallments.value = []
  isCollectionDialogOpen.value = true
}

const exportInstallments = async () => {
  try {
    await receivablesApi.export(installmentsTable.params)
    notify.success('Archivo CSV descargado correctamente')
  }
  catch {
    notify.error('No se pudo exportar el listado de cuotas')
  }
}

// --- TAB 3: Historial de cobranzas ---
const isCancelDialogOpen = ref(false)
const collectionToCancel = ref(null)
const isCancelling = ref(false)

const collectionsTable = useServerTable({
  immediate: can('receivables.view') || can('collections.create'),
  fetch: receivablesApi.listCollections,
  filters: {
    client_id: null,
    payment_method_id: null,
    currency_id: null,
    from: null,
    to: null,
  },
})

const collectionHeaders = [
  { title: 'N° RECIBO', key: 'number', sortable: false, alwaysVisible: true },
  { title: 'FECHA', key: 'collection_date', sortable: false },
  { title: 'CLIENTE', key: 'client', sortable: false },
  { title: 'MÉTODO DE PAGO', key: 'payment_method', sortable: false },
  { title: 'REFERENCIA', key: 'reference', sortable: false },
  { title: 'MONTO', key: 'amount', sortable: false, align: 'end' },
  { title: 'ESTADO', key: 'status', sortable: false, align: 'center' },
  { title: 'ACCIONES', key: 'actions', sortable: false, align: 'center' },
]

const collectionStatusBadge = status => {
  switch (status) {
  case 'completed':
  case 'active':
    return { title: 'Registrada', color: 'success' }
  case 'cancelled':
    return { title: 'Anulada', color: 'error' }
  default:
    return { title: status, color: 'default' }
  }
}

const downloadReceipt = async collectionId => {
  try {
    await receivablesApi.downloadReceiptPdf(collectionId)
    notify.success('Recibo de cobranza descargado correctamente')
  }
  catch {
    notify.error('No se pudo descargar el recibo de cobranza')
  }
}

const openCancelCollection = collection => {
  collectionToCancel.value = collection
  isCancelDialogOpen.value = true
}

const handleCancelConfirm = async reason => {
  if (!collectionToCancel.value) return

  isCancelling.value = true
  try {
    await receivablesApi.cancelCollection(collectionToCancel.value.id, {
      cancellation_reason: reason,
    })
    notify.success('Cobranza anulada correctamente')
    isCancelDialogOpen.value = false
    collectionToCancel.value = null
    collectionsTable.reload()
    installmentsTable.reload()
  }
  catch (err) {
    notify.error(err?.data?.message || err?.message || 'Error al anular la cobranza')
  }
  finally {
    isCancelling.value = false
  }
}

const exportCollections = async () => {
  try {
    await receivablesApi.exportCollections(collectionsTable.params)
    notify.success('Historial de cobranzas exportado correctamente')
  }
  catch {
    notify.error('No se pudo exportar el historial de cobranzas')
  }
}

const onCollectionSuccess = () => {
  selectedInstallments.value = []
  installmentsTable.reload()
  collectionsTable.reload()
}

onMounted(() => {
  if (route.query.search && activeTab.value === 'collections') {
    collectionsTable.search = String(route.query.search)
  }
})
</script>

<template>
  <div>
    <VCard>
      <VCardText>
        <PageHeader />
      </VCardText>

      <!-- Tarjetas de resumen de saldos por moneda -->
      <VCardText
        v-if="installmentsTable.loading && !summaryTotals.length"
        class="pt-0 d-flex justify-center"
      >
        <VProgressCircular
          indeterminate
          color="primary"
        />
      </VCardText>

      <VCardText
        v-else-if="summaryTotals.length"
        class="pt-0"
      >
        <VRow>
          <VCol
            v-for="tot in summaryTotals"
            :key="tot.currency_id"
            cols="12"
            sm="6"
            md="4"
          >
            <VCard
              variant="outlined"
              class="pa-4"
            >
              <div class="d-flex align-center justify-space-between mb-2">
                <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis">
                  Saldo Pendiente ({{ tot.currency_code }})
                </span>
                <VChip
                  size="x-small"
                  color="primary"
                >
                  {{ tot.currency_code }}
                </VChip>
              </div>
              <div class="d-flex align-baseline justify-space-between">
                <div class="text-h5 font-weight-bold font-mono">
                  {{ tot.currency_symbol }} {{ formatAmount(tot.total_balance) }}
                </div>
                <div
                  v-if="Number(tot.overdue_balance) > 0"
                  class="text-caption text-error font-weight-medium font-mono"
                >
                  Vencido: {{ tot.currency_symbol }} {{ formatAmount(tot.overdue_balance) }}
                </div>
              </div>
            </VCard>
          </VCol>
        </VRow>
      </VCardText>

      <!-- Pestañas Principales -->
      <VTabs
        v-model="activeTab"
        class="v-tabs-pill mx-6 mb-4"
      >
        <VTab
          v-for="tab in tabs"
          :key="tab.value"
          :value="tab.value"
        >
          <VIcon
            start
            :icon="tab.icon"
          />
          {{ tab.title }}
        </VTab>
      </VTabs>

      <VDivider />

      <VWindow
        v-model="activeTab"
        class="disable-tab-transition"
      >
        <!-- PESTAÑA 1: Cuotas por cobrar -->
        <VWindowItem value="installments">
          <ServerDataTable
            v-model:search="installmentsTable.search"
            v-model:options="installmentsTable.options"
            table-id="receivables-installments"
            :headers="installmentHeaders"
            :items="installmentsTable.items"
            :total-items="installmentsTable.total"
            :loading="installmentsTable.loading"
            :has-filters="installmentsTable.hasFilters"
            :exportable="can('receivables.export')"
            :creatable="can('collections.create')"
            create-label="Nueva Cobranza"
            @create="openNewCollection"
            @export="exportInstallments"
            @refresh="installmentsTable.reload"
            @reset:filters="installmentsTable.resetFilters"
          >
            <template #extra-actions>
              <VBtn
                v-if="selectedInstallments.length > 0 && can('collections.create')"
                color="success"
                variant="elevated"
                size="small"
                prepend-icon="tabler-coins"
                :disabled="!canCollectSelected"
                @click="openCollectionForSelected"
              >
                Cobrar Seleccionadas ({{ selectedInstallments.length }})
              </VBtn>
            </template>

            <template #filters>
              <ClientPicker
                v-model="installmentsTable.filters.client_id"
                label="Cliente"
                placeholder="Filtrar por cliente"
                density="compact"
                :creatable="false"
                clearable
                class="receivables-filter-client"
              />

              <div class="receivables-filters-row-2 d-flex flex-wrap align-center gap-4 w-100">
                <VSelect
                  v-model="installmentsTable.filters.currency_id"
                  :items="currencies"
                  item-title="name"
                  item-value="id"
                  label="Moneda"
                  placeholder="Todas"
                  density="compact"
                  clearable
                />

                <VSelect
                  v-model="installmentsTable.filters.overdue"
                  :items="[
                    { title: 'Solo vencidas', value: '1' },
                    { title: 'Por vencer', value: '0' },
                  ]"
                  label="Vencimiento"
                  placeholder="Todas"
                  density="compact"
                  clearable
                />

                <VTextField
                  v-model="installmentsTable.filters.due_from"
                  type="date"
                  label="Vence desde"
                  placeholder="YYYY-MM-DD"
                  density="compact"
                  clearable
                />

                <VTextField
                  v-model="installmentsTable.filters.due_to"
                  type="date"
                  label="Vence hasta"
                  placeholder="YYYY-MM-DD"
                  density="compact"
                  clearable
                />
              </div>
            </template>

            <!-- Slot Vencimiento -->
            <template #item.due_date="{ item }">
              <div class="d-flex align-center gap-1 font-mono">
                <VIcon
                  v-if="item.is_overdue"
                  icon="tabler-alert-circle"
                  color="error"
                  size="16"
                />
                <span :class="{ 'text-error font-weight-bold': item.is_overdue }">
                  {{ formatDateDMY(item.due_date) }}
                </span>
              </div>
              <div
                v-if="item.is_overdue"
                class="text-caption text-error"
              >
                Vencida hace {{ item.days_overdue }} d
              </div>
            </template>

            <!-- Slot Cliente -->
            <template #item.client="{ item }">
              <div class="d-flex flex-column py-1">
                <RouterLink
                  v-if="item.client_id"
                  :to="`/clients/${item.client_id}`"
                  class="font-weight-medium text-primary text-decoration-none"
                >
                  {{ item.client_name || 'Cliente Genérico' }}
                </RouterLink>
                <span
                  v-else
                  class="font-weight-medium text-high-emphasis"
                >{{ item.client_name || 'Cliente Genérico' }}</span>
                <span
                  v-if="item.client_document_number"
                  class="text-caption text-medium-emphasis"
                >
                  {{ item.client_document_type?.toUpperCase?.() }} {{ item.client_document_number }}
                </span>
              </div>
            </template>

            <!-- Slot Documento -->
            <template #item.sale="{ item }">
              <RouterLink
                v-if="item.sale_id"
                :to="`/sales/history/${item.sale_id}`"
                class="font-mono text-primary text-decoration-none"
              >
                {{ item.sale_full_number || '—' }}
              </RouterLink>
              <span
                v-else
                class="font-mono"
              >{{ item.sale_full_number || '—' }}</span>
            </template>

            <!-- Slot Cuota -->
            <template #item.number="{ item }">
              <span class="font-weight-bold font-mono">#{{ item.installment_number || item.number }}</span>
            </template>

            <!-- Slot Monto -->
            <template #item.amount="{ item }">
              <span class="font-mono">{{ item.currency_symbol || item.currency?.symbol }} {{ formatAmount(item.amount) }}</span>
            </template>

            <!-- Slot Pagado -->
            <template #item.paid_amount="{ item }">
              <span class="font-mono text-medium-emphasis">{{ item.currency_symbol || item.currency?.symbol }} {{ formatAmount(item.paid_amount) }}</span>
            </template>

            <!-- Slot Saldo -->
            <template #item.balance="{ item }">
              <span class="font-mono font-weight-bold">{{ item.currency_symbol || item.currency?.symbol }} {{ formatAmount(item.balance) }}</span>
            </template>

            <!-- Slot Estado -->
            <template #item.status="{ item }">
              <VChip
                size="small"
                variant="tonal"
                :color="installmentStatusBadge(item.status).color"
              >
                {{ installmentStatusBadge(item.status).title }}
              </VChip>
            </template>

            <!-- Slot Acciones -->
            <template #item.actions="{ item }">
              <div class="d-flex align-center justify-center gap-1">
                <RowActionButton
                  v-if="can('collections.create') && Number(item.balance) > 0"
                  action="custom"
                  icon="tabler-coins"
                  color="success"
                  tooltip="Cobrar cuota"
                  @click="openCollectionForSingle(item)"
                />
              </div>
            </template>
          </ServerDataTable>
        </VWindowItem>

        <!-- PESTAÑA 2: Antigüedad de deuda -->
        <VWindowItem value="aging">
          <VCardText>
            <ReceivablesAgingTable />
          </VCardText>
        </VWindowItem>

        <!-- PESTAÑA 3: Historial de cobranzas -->
        <VWindowItem value="collections">
          <ServerDataTable
            v-model:search="collectionsTable.search"
            v-model:options="collectionsTable.options"
            table-id="receivables-collections"
            :headers="collectionHeaders"
            :items="collectionsTable.items"
            :total-items="collectionsTable.total"
            :loading="collectionsTable.loading"
            :has-filters="collectionsTable.hasFilters"
            :exportable="can('receivables.export')"
            @export="exportCollections"
            @refresh="collectionsTable.reload"
            @reset:filters="collectionsTable.resetFilters"
          >
            <template #filters>
              <ClientPicker
                v-model="collectionsTable.filters.client_id"
                label="Cliente"
                placeholder="Filtrar por cliente"
                density="compact"
                :creatable="false"
                clearable
                class="receivables-filter-client"
              />

              <div class="receivables-filters-row-2 d-flex flex-wrap align-center gap-4 w-100">
                <VSelect
                  v-model="collectionsTable.filters.payment_method_id"
                  :items="paymentMethods"
                  item-title="name"
                  item-value="id"
                  label="Método de pago"
                  placeholder="Todos"
                  density="compact"
                  clearable
                />

                <VTextField
                  v-model="collectionsTable.filters.from"
                  type="date"
                  label="Desde"
                  placeholder="YYYY-MM-DD"
                  density="compact"
                  clearable
                />

                <VTextField
                  v-model="collectionsTable.filters.to"
                  type="date"
                  label="Hasta"
                  placeholder="YYYY-MM-DD"
                  density="compact"
                  clearable
                />
              </div>
            </template>

            <!-- Slot Número de Recibo -->
            <template #item.number="{ item }">
              <span class="font-mono font-weight-bold">{{ item.number }}</span>
            </template>

            <!-- Slot Fecha -->
            <template #item.collection_date="{ item }">
              <span class="font-mono">{{ formatDateDMY(item.collection_date) }}</span>
            </template>

            <!-- Slot Cliente -->
            <template #item.client="{ item }">
              <div class="d-flex flex-column py-1">
                <RouterLink
                  v-if="item.client_id"
                  :to="`/clients/${item.client_id}`"
                  class="font-weight-medium text-primary text-decoration-none"
                >
                  {{ item.client_name || 'Cliente Genérico' }}
                </RouterLink>
                <span
                  v-else
                  class="font-weight-medium text-high-emphasis"
                >{{ item.client_name || 'Cliente Genérico' }}</span>
                <span
                  v-if="item.client_document_number"
                  class="text-caption text-medium-emphasis"
                >
                  {{ item.client_document_type?.toUpperCase?.() }} {{ item.client_document_number }}
                </span>
              </div>
            </template>

            <!-- Slot Método de pago -->
            <template #item.payment_method="{ item }">
              <span>{{ item.payment_method?.name || item.payment_method_name || '—' }}</span>
            </template>

            <!-- Slot Referencia -->
            <template #item.reference="{ item }">
              <span class="font-mono text-caption text-medium-emphasis">{{ item.reference || '—' }}</span>
            </template>

            <!-- Slot Monto -->
            <template #item.amount="{ item }">
              <span class="font-mono font-weight-bold">{{ item.currency?.symbol || item.currency_symbol }} {{ formatAmount(item.amount) }}</span>
            </template>

            <!-- Slot Estado -->
            <template #item.status="{ item }">
              <VChip
                size="small"
                variant="tonal"
                :color="collectionStatusBadge(item.status).color"
              >
                {{ collectionStatusBadge(item.status).title }}
              </VChip>
            </template>

            <!-- Slot Acciones -->
            <template #item.actions="{ item }">
              <div class="d-flex align-center justify-center gap-1">
                <RowActionButton
                  action="custom"
                  icon="tabler-file-type-pdf"
                  color="info"
                  tooltip="Descargar recibo PDF"
                  @click="downloadReceipt(item.id)"
                />

                <RowActionButton
                  v-if="can('collections.cancel') && item.status !== 'cancelled'"
                  action="delete"
                  tooltip="Anular cobranza"
                  @click="openCancelCollection(item)"
                />
              </div>
            </template>
          </ServerDataTable>
        </VWindowItem>
      </VWindow>
    </VCard>

    <!-- Dialog de Cobranza -->
    <CollectionDialog
      v-model="isCollectionDialogOpen"
      :installments="collectionDialogInstallments"
      @collected="onCollectionSuccess"
    />

    <!-- Dialog de Anulación de Cobranza -->
    <CancelDocumentDialog
      v-model="isCancelDialogOpen"
      title="Anular Cobranza"
      :document-number="collectionToCancel?.number || ''"
      description="Esta acción revertirá los saldos de las cuotas afectadas y anulará el movimiento financiero asociado en caja o banco."
      :loading="isCancelling"
      @confirm="handleCancelConfirm"
    />
  </div>
</template>

<style scoped>
.receivables-filter-client {
  flex: 2 1 20rem;
  min-inline-size: 16rem;
  max-inline-size: 34rem;

  :deep(.v-input) {
    inline-size: 100%;
  }
}

.receivables-filters-row-2 {
  flex-basis: 100%;

  :deep(.v-input) {
    flex: 1 1 9rem;
    min-inline-size: 8rem;
    max-inline-size: 14rem;
  }
}

@media (max-width: 599.98px) {
  .receivables-filter-client {
    max-inline-size: none;
    inline-size: 100%;
  }

  .receivables-filters-row-2 {
    flex-direction: column;
    align-items: stretch !important;

    :deep(.v-input) {
      max-inline-size: none;
      inline-size: 100%;
    }
  }
}
</style>
