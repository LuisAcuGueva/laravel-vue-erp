<script setup>
/* eslint-disable camelcase */
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import CatalogItemPicker from '@/components/common/CatalogItemPicker.vue'
import ClientPicker from '@/components/common/ClientPicker.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import PosCartTable from '@/modules/sales/components/pos/PosCartTable.vue'
import { useNotify } from '@/composables/useNotify'
import { exchangeRatesApi } from '@/modules/finance/services/financeSettingsApi'
import PosPaymentDialog from '@/modules/sales/components/pos/PosPaymentDialog.vue'
import VoucherPreviewDialog from '@/modules/sales/components/pos/VoucherPreviewDialog.vue'
import { salesApi } from '@/modules/sales/services/salesApi'
import { usePosStore } from '@/modules/sales/stores/posStore'
import { formatAmount } from '@/utils/documentTotals'

definePage({
  meta: {
    action: 'sales.create',
    title: 'Punto de Venta',
    icon: 'tabler-scan',
    description: 'Emisión rápida de boletas y facturas con lector de código de barras y cobro integrado.',
  },
})

const route = useRoute()
const posStore = usePosStore()
const notify = useNotify()

const isPaymentDialogOpen = ref(false)
const isPreviewDialogOpen = ref(false)
const isSubmitting = ref(false)
const completedSale = ref(null)
const isLoading = ref(true)

const itemPickerKey = ref(0)
const pickerRef = ref(null)

const totals = computed(() => posStore.totals)
const currencySymbol = computed(() => posStore.currency?.symbol || 'S/')
const baseCurrencySymbol = computed(() => posStore.currencies.find(c => c.is_base)?.symbol || 'S/')

const currencyId = computed({
  get: () => posStore.currency?.id || null,
  set: val => posStore.setCurrency(val),
})

const isFetchingRate = ref(false)

const fetchExchangeRate = async () => {
  if (!posStore.isForeignCurrency || !posStore.currency?.id)
    return

  isFetchingRate.value = true
  try {
    const res = await exchangeRatesApi.current(posStore.currency.id)

    if (res?.data?.sale_rate)
      posStore.setExchangeRate(res.data.sale_rate)
  }
  catch {
    // Si no hay TC registrado hoy, el usuario lo ingresa manualmente
  }
  finally {
    isFetchingRate.value = false
  }
}

watch(() => posStore.currency?.id, () => {
  if (posStore.isForeignCurrency)
    fetchExchangeRate()
})

const loadPos = async () => {
  isLoading.value = true
  try {
    const qId = route.query.quotation_id ? String(route.query.quotation_id) : null

    await posStore.initPos(qId)
  }
  catch (e) {
    notify.error(e?.data?.message || 'Error al inicializar el punto de venta.')
  }
  finally {
    isLoading.value = false
    nextTick(() => {
      focusProductSearch()
    })
  }
}

const onItemSelected = found => {
  if (!found) return

  const result = posStore.addLookupItem(found, 1)

  if (result?.ok === false)
    notify.error(result.message)

  // Limpiar el selector para el siguiente escaneo
  itemPickerKey.value++
  nextTick(() => {
    focusProductSearch()
  })
}

const focusProductSearch = () => {
  const el = document.querySelector('#pos-item-search input')
  if (el) el.focus()
}

const focusClientSearch = () => {
  const el = document.querySelector('#pos-client-search input')
  if (el) el.focus()
}

const handleOpenPayment = () => {
  if (!posStore.canCheckout) {
    if (!posStore.client) {
      notify.warning('Debe seleccionar un cliente antes de cobrar.')
      focusClientSearch()

      return
    }
    if (!posStore.cart.length) {
      notify.warning('El carrito está vacío.')
      focusProductSearch()

      return
    }
    if (!posStore.documentSeriesId) {
      notify.warning('No hay una serie de comprobante seleccionada para esta sede.')

      return
    }

    return
  }

  if (posStore.isForeignCurrency && !(Number(posStore.exchangeRate) > 0)) {
    notify.warning('Debe ingresar el tipo de cambio para la moneda seleccionada.')

    return
  }

  isPaymentDialogOpen.value = true
}

const handlePaymentSubmit = async paymentData => {
  isSubmitting.value = true
  try {
    const payload = {
      document_series_id: posStore.documentSeriesId,
      client_id: posStore.client.id,
      warehouse_id: posStore.selectedWarehouseId,
      currency_id: posStore.currency?.id,
      exchange_rate: posStore.isForeignCurrency ? Number(posStore.exchangeRate) : null,
      cash_session_id: posStore.activeCashSession?.id || null,
      seller_id: paymentData.seller_id || posStore.sellerId || null,
      quotation_id: posStore.quotationId || null,
      price_type: posStore.priceType,
      prices_include_tax: true,
      payment_condition: paymentData.payment_condition,
      credit_days: paymentData.credit_days,
      installments_count: paymentData.installments_count,
      installment_interval_days: paymentData.installment_interval_days,
      installments: paymentData.installments,
      notes: paymentData.notes,
      items: posStore.cart.map(line => ({
        item_presentation_id: line.item_presentation_id,
        catalog_item_id: line.catalog_item_id,
        quantity: line.quantity,
        unit_price: line.unit_price,
        discount_percent: line.discount_percent,
        tax_type: line.tax_type,
        description: line.item_name + (line.presentation_name ? ` - ${line.presentation_name}` : ''),
      })),
      payments: paymentData.payments || [],
    }

    const res = await salesApi.create(payload)

    completedSale.value = res?.data || null

    notify.success(`Venta ${completedSale.value?.full_number || ''} emitida correctamente`)

    isPaymentDialogOpen.value = false
    posStore.resetForNewSale()

    // Abrir modal de ticket / previsualización
    isPreviewDialogOpen.value = true
  }
  catch (e) {
    notify.error(e?.data?.message || 'Error al emitir el comprobante de venta.')
  }
  finally {
    isSubmitting.value = false
  }
}

const handleNewSale = () => {
  completedSale.value = null
  isPreviewDialogOpen.value = false
  nextTick(() => {
    focusProductSearch()
  })
}

// Atajos de teclado globales (F2, F4, F9)
const handleKeydown = e => {
  if (isLoading.value) return

  if (e.key === 'F2') {
    e.preventDefault()
    focusProductSearch()
  }
  else if (e.key === 'F4') {
    e.preventDefault()
    focusClientSearch()
  }
  else if (e.key === 'F9') {
    e.preventDefault()
    if (!isPaymentDialogOpen.value && !isPreviewDialogOpen.value)
      handleOpenPayment()
  }
  else if (e.key === 'Escape') {
    if (isPaymentDialogOpen.value)
      isPaymentDialogOpen.value = false
    else if (isPreviewDialogOpen.value)
      isPreviewDialogOpen.value = false
  }
  else if (e.key === 'Enter' && isPreviewDialogOpen.value) {
    e.preventDefault()
    handleNewSale()
  }
}

onMounted(() => {
  loadPos()
  window.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <div>
    <VCard class="mb-4">
      <VCardText>
        <PageHeader />
      </VCardText>
    </VCard>

    <!-- Indicador de carga inicial -->
    <div
      v-if="isLoading || posStore.isLoading"
      class="text-center py-12"
    >
      <VProgressCircular
        indeterminate
        color="primary"
        size="48"
      />
    </div>

    <!-- Error al inicializar -->
    <VCard
      v-else-if="!posStore.isInitialized"
      class="pa-8 text-center"
    >
      <VIcon
        icon="tabler-alert-circle"
        size="48"
        color="error"
        class="mb-3"
      />
      <div class="text-h6 font-weight-medium mb-2">
        Error al cargar los datos del Punto de Venta
      </div>
      <p class="text-caption text-medium-emphasis mb-4">
        No se pudieron cargar los almacenes, series o el estado de caja. Por favor, reintente la operación.
      </p>
      <VBtn
        color="primary"
        prepend-icon="tabler-reload"
        @click="loadPos"
      >
        Reintentar
      </VBtn>
    </VCard>

    <template v-else>
      <!-- Barra Superior: Estado de Caja y Configuración Rápida -->
      <VCard class="mb-4 pa-3">
        <div class="d-flex flex-wrap align-center justify-space-between gap-4">
          <!-- Caja / Sesión activa -->
          <div class="d-flex align-center gap-2">
            <VChip
              v-if="posStore.activeCashSession"
              color="success"
              variant="tonal"
              size="small"
              prepend-icon="tabler-cash"
            >
              {{ posStore.activeBox?.name || 'Caja' }} · Sesión #{{ posStore.activeCashSession.number }}
            </VChip>
            <VChip
              v-else
              color="warning"
              variant="tonal"
              size="small"
              prepend-icon="tabler-alert-triangle"
            >
              Sin sesión de caja abierta (Solo transferencias/crédito)
            </VChip>
          </div>

          <div class="d-flex flex-wrap align-center gap-3">
            <!-- Almacén -->
            <VSelect
              v-model="posStore.selectedWarehouseId"
              :items="posStore.warehouses"
              item-title="name"
              item-value="id"
              label="Almacén *"
              placeholder="Seleccione el almacén de despacho"
              prepend-inner-icon="tabler-building-warehouse"
              density="compact"
              hide-details
              class="flex-shrink-0 flex-grow-0"
              style="min-width: 190px; max-width: 220px"
            />

            <!-- Tipo de Comprobante: Boleta / Factura -->
            <div class="segmented-toggle flex-shrink-0">
              <VBtn
                size="small"
                rounded="lg"
                class="px-4"
                :variant="posStore.documentType === 'receipt' ? 'flat' : 'text'"
                :color="posStore.documentType === 'receipt' ? 'primary' : 'default'"
                @click="posStore.setDocumentType('receipt')"
              >
                Boleta
              </VBtn>
              <VBtn
                size="small"
                rounded="lg"
                class="px-4"
                :variant="posStore.documentType === 'invoice' ? 'flat' : 'text'"
                :color="posStore.documentType === 'invoice' ? 'primary' : 'default'"
                @click="posStore.setDocumentType('invoice')"
              >
                Factura
              </VBtn>
            </div>

            <!-- Serie de Comprobante -->
            <VSelect
              v-model="posStore.documentSeriesId"
              :items="posStore.filteredSeries"
              item-title="series"
              item-value="id"
              label="Serie *"
              placeholder="Serie"
              prepend-inner-icon="tabler-hash"
              density="compact"
              hide-details
              class="flex-shrink-0 flex-grow-0"
              style="min-width: 120px; max-width: 140px"
            />

            <!-- Tipo de Precio -->
            <div class="segmented-toggle flex-shrink-0">
              <VBtn
                size="small"
                rounded="lg"
                class="px-4"
                :variant="posStore.priceType === 'retail' ? 'flat' : 'text'"
                :color="posStore.priceType === 'retail' ? 'primary' : 'default'"
                @click="posStore.setPriceType('retail')"
              >
                Minorista
              </VBtn>
              <VBtn
                size="small"
                rounded="lg"
                class="px-4"
                :variant="posStore.priceType === 'wholesale' ? 'flat' : 'text'"
                :color="posStore.priceType === 'wholesale' ? 'primary' : 'default'"
                @click="posStore.setPriceType('wholesale')"
              >
                Mayorista
              </VBtn>
            </div>

            <!-- Moneda -->
            <VSelect
              v-model="currencyId"
              :items="posStore.currencies"
              item-title="code"
              item-value="id"
              label="Moneda *"
              prepend-inner-icon="tabler-currency-dollar"
              density="compact"
              hide-details
              class="flex-shrink-0 flex-grow-0"
              style="min-width: 110px; max-width: 130px"
            />

            <!-- Tipo de Cambio (solo moneda extranjera) -->
            <VTextField
              v-if="posStore.isForeignCurrency"
              :model-value="posStore.exchangeRate"
              type="number"
              step="0.0001"
              min="0.0001"
              label="T.C. *"
              placeholder="3.8000"
              density="compact"
              hide-details
              :loading="isFetchingRate"
              class="flex-shrink-0 flex-grow-0"
              style="width: 110px"
              @update:model-value="val => posStore.setExchangeRate(val)"
            />
          </div>
        </div>
      </VCard>

      <VRow>
        <!-- Columna Principal: Búsqueda y Carrito -->
        <VCol
          cols="12"
          md="8"
        >
          <!-- Búsqueda de Artículos y Lector de Códigos -->
          <VCard class="mb-4">
            <VCardText class="pa-4">
              <div
                id="pos-item-search"
                class="d-flex align-center gap-2"
              >
                <div class="flex-grow-1">
                  <CatalogItemPicker
                    :key="itemPickerKey"
                    ref="pickerRef"
                    context="sale"
                    :warehouse-id="posStore.selectedWarehouseId"
                    label="Buscar producto o escanear código de barras (F2) *"
                    placeholder="Escribe nombre, código o escanea código de barras..."
                    @selected="onItemSelected"
                  />
                </div>
              </div>
            </VCardText>
          </VCard>

          <!-- Selector de Cliente -->
          <VCard class="mb-4">
            <VCardText class="pa-4">
              <div
                id="pos-client-search"
                class="d-flex flex-wrap align-center justify-space-between gap-3"
              >
                <div
                  class="flex-grow-1"
                  style="max-width: 480px"
                >
                  <ClientPicker
                    :model-value="posStore.client?.id"
                    :initial-item="posStore.client"
                    label="Cliente (F4) *"
                    placeholder="Buscar cliente por nombre o documento..."
                    @update:model-value="() => {}"
                    @selected="posStore.setClient"
                  />
                </div>

                <div
                  v-if="posStore.client"
                  class="d-flex align-center gap-2"
                >
                  <VChip
                    size="small"
                    color="primary"
                    variant="tonal"
                  >
                    {{ posStore.client.document_type || 'DOC' }}: {{ posStore.client.document_number || 'S/D' }}
                  </VChip>

                  <VChip
                    v-if="Number(posStore.client.credit_limit || 0) > 0"
                    size="small"
                    color="info"
                    variant="tonal"
                  >
                    Crédito: {{ formatAmount(posStore.client.credit_limit, baseCurrencySymbol) }}
                  </VChip>
                </div>
              </div>
            </VCardText>
          </VCard>

          <!-- Tabla del Carrito -->
          <VCard>
            <div class="pa-4 pb-2 d-flex justify-space-between align-center border-b">
              <div class="font-weight-bold text-subtitle-1">
                Líneas de Venta ({{ posStore.linesCount }} ítems)
              </div>

              <VBtn
                v-if="posStore.cart.length"
                size="small"
                variant="text"
                color="error"
                prepend-icon="tabler-trash"
                @click="posStore.clearCart"
              >
                Vaciar carrito
              </VBtn>
            </div>

            <PosCartTable v-if="posStore.cart.length" />

            <!-- Estado Vacío -->
            <div
              v-else
              class="pa-10 text-center text-medium-emphasis"
            >
              <VAvatar
                size="56"
                color="secondary"
                variant="tonal"
                class="mb-3"
              >
                <VIcon
                  size="32"
                  icon="tabler-scan"
                />
              </VAvatar>
              <div class="text-h6 font-weight-medium">
                Carrito Vacío
              </div>
              <p class="text-caption mb-4">
                Escanee un código de barras con el lector o busque productos arriba con <strong>F2</strong>.
              </p>
              <div class="d-flex justify-center gap-3 text-caption">
                <span><kbd class="pa-1 bg-surface-variant rounded">F2</kbd> Buscar producto</span>
                <span><kbd class="pa-1 bg-surface-variant rounded">F4</kbd> Seleccionar cliente</span>
                <span><kbd class="pa-1 bg-surface-variant rounded">F9</kbd> Cobrar venta</span>
              </div>
            </div>
          </VCard>
        </VCol>

        <!-- Columna Derecha: Totales y Cobro -->
        <VCol
          cols="12"
          md="4"
        >
          <VCard
            class="sticky-top"
            style="top: 80px"
          >
            <VCardTitle class="pa-4 border-b">
              <span class="text-subtitle-1 font-weight-bold">Resumen del Comprobante</span>
            </VCardTitle>

            <VCardText class="pa-4 space-y-3">
              <div class="d-flex justify-space-between text-body-2">
                <span class="text-medium-emphasis">Subtotal Op. Gravadas:</span>
                <span class="font-mono">{{ formatAmount(totals.taxableBase, currencySymbol) }}</span>
              </div>

              <div
                v-if="Number(totals.exemptBase) > 0"
                class="d-flex justify-space-between text-body-2"
              >
                <span class="text-medium-emphasis">Op. Exoneradas:</span>
                <span class="font-mono">{{ formatAmount(totals.exemptBase, currencySymbol) }}</span>
              </div>

              <div
                v-if="Number(totals.unaffectedBase) > 0"
                class="d-flex justify-space-between text-body-2"
              >
                <span class="text-medium-emphasis">Op. Inafectas:</span>
                <span class="font-mono">{{ formatAmount(totals.unaffectedBase, currencySymbol) }}</span>
              </div>

              <div
                v-if="Number(totals.discountTotal) > 0"
                class="d-flex justify-space-between text-body-2 text-warning"
              >
                <span>Descuento Total:</span>
                <span class="font-mono">-{{ formatAmount(totals.discountTotal, currencySymbol) }}</span>
              </div>

              <div class="d-flex justify-space-between text-body-2">
                <span class="text-medium-emphasis">I.G.V. (18%):</span>
                <span class="font-mono">{{ formatAmount(totals.taxTotal, currencySymbol) }}</span>
              </div>

              <VDivider class="my-2" />

              <div class="d-flex justify-space-between align-center">
                <span class="text-h6 font-weight-bold">TOTAL:</span>
                <span class="text-h4 font-weight-bold text-primary font-mono">
                  {{ formatAmount(totals.total, currencySymbol) }}
                </span>
              </div>

              <VAlert
                v-if="posStore.documentType === 'receipt' && Number(totals.total) >= 700 && (posStore.client?.is_system || !posStore.client?.document_number)"
                type="warning"
                variant="tonal"
                density="compact"
                class="mt-3 text-caption"
              >
                Boletas por S/ 700.00 o más exigen identificar al cliente con DNI o RUC.
              </VAlert>

              <VAlert
                v-if="posStore.documentType === 'invoice' && (!posStore.client || posStore.client.document_type !== 'RUC' && String(posStore.client.document_number).length !== 11)"
                type="error"
                variant="tonal"
                density="compact"
                class="mt-3 text-caption"
              >
                Para emitir factura el cliente debe tener un RUC válido de 11 dígitos.
              </VAlert>
            </VCardText>

            <VCardActions class="pa-4 pt-0">
              <VBtn
                block
                color="primary"
                size="x-large"
                prepend-icon="tabler-cash"
                class="font-weight-bold text-h6 py-6"
                :disabled="!posStore.canCheckout"
                @click="handleOpenPayment"
              >
                Cobrar (F9)
              </VBtn>
            </VCardActions>
          </VCard>
        </VCol>
      </VRow>

      <!-- Diálogo de Cobro -->
      <PosPaymentDialog
        v-model="isPaymentDialogOpen"
        :total-amount="totals.total"
        :currency-symbol="currencySymbol"
        :client="posStore.client"
        :active-cash-session="posStore.activeCashSession"
        :default-seller-id="posStore.sellerId"
        :is-submitting="isSubmitting"
        @submit="handlePaymentSubmit"
      />

      <!-- Diálogo de Ticket / Éxito -->
      <VoucherPreviewDialog
        v-model="isPreviewDialogOpen"
        :sale="completedSale"
        @new-sale="handleNewSale"
      />
    </template>
  </div>
</template>

<style scoped>
.sticky-top {
  position: sticky;
}

.segmented-toggle {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  padding: 3px;
  border-radius: 10px;
  background: rgba(var(--v-theme-on-surface), 0.06);
}
</style>
