<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import PageHeader from '@/components/common/PageHeader.vue'
import CatalogItemPicker from '@/components/common/CatalogItemPicker.vue'
import KardexTable from '@/modules/inventory/components/kardex/KardexTable.vue'
import { useNotify } from '@/composables/useNotify'
import { catalogApi } from '@/modules/inventory/services/catalogApi'
import { stockApi } from '@/modules/inventory/services/stockApi'
import { warehousesApi } from '@/modules/inventory/services/inventorySettingsApi'

definePage({
  meta: {
    title: 'Kardex Valorizado',
    icon: 'tabler-report-analytics',
    description: 'Movimientos valorizados de entradas, salidas y saldos por artículo y almacén',
    action: 'kardex.view',
  },
})

const route = useRoute()
const notify = useNotify()

const now = new Date()
const year = now.getFullYear()
const month = String(now.getMonth() + 1).padStart(2, '0')
const day = String(now.getDate()).padStart(2, '0')
const defaultDateFrom = `${year}-${month}-01`
const defaultDateTo = `${year}-${month}-${day}`

const warehouseOptions = ref([])
const selectedWarehouseId = ref(null)
const selectedPresentationId = ref(null)
const selectedCatalogItemId = ref(null)
const initialPickerItem = ref(null)

const dateFrom = ref(defaultDateFrom)
const dateTo = ref(defaultDateTo)

const isLoading = ref(false)
const isExporting = ref(false)
const hasSearched = ref(false)

const kardexData = ref({
  opening_balance: { quantity: 0, unit_cost: 0, total: 0 },
  movements: [],
  closing_balance: { quantity: 0, unit_cost: 0, total: 0 },
})

const isQueryValid = computed(() => {
  return Boolean(
    selectedWarehouseId.value
    && selectedCatalogItemId.value
    && dateFrom.value
    && dateTo.value,
  )
})

const handleItemSelected = item => {
  if (item) {
    selectedCatalogItemId.value = item.catalog_item_id
    selectedPresentationId.value = item.presentation_id
  }
  else {
    selectedCatalogItemId.value = null
    selectedPresentationId.value = null
  }
}

const executeQuery = async () => {
  if (!isQueryValid.value) {
    notify.error('Seleccione almacén, artículo y rango de fechas')
    
    return
  }

  isLoading.value = true
  try {
    const params = {
      warehouse_id: selectedWarehouseId.value,
      catalog_item_id: selectedCatalogItemId.value,
      date_from: dateFrom.value,
      date_to: dateTo.value,
    }

    const response = await stockApi.kardex(params)

    kardexData.value = response?.data || {
      opening_balance: { quantity: 0, unit_cost: 0, total: 0 },
      movements: [],
      closing_balance: { quantity: 0, unit_cost: 0, total: 0 },
    }
    hasSearched.value = true
  }
  catch (err) {
    notify.error(err?.data?.message || 'Error al consultar el kardex')
  }
  finally {
    isLoading.value = false
  }
}

const handleExport = async () => {
  if (!isQueryValid.value) return

  isExporting.value = true
  try {
    const params = {
      warehouse_id: selectedWarehouseId.value,
      catalog_item_id: selectedCatalogItemId.value,
      date_from: dateFrom.value,
      date_to: dateTo.value,
      format: 'csv',
    }

    await stockApi.exportKardex(params)
    notify.success('Kardex exportado correctamente')
  }
  catch (err) {
    notify.error(err?.data?.message || 'Error al exportar el kardex')
  }
  finally {
    isExporting.value = false
  }
}

onMounted(async () => {
  try {
    const res = await warehousesApi.options()

    warehouseOptions.value = res?.data || []

    const qWarehouse = route.query.warehouse_id
    const qCatalogItem = route.query.catalog_item_id

    if (qWarehouse) {
      selectedWarehouseId.value = String(qWarehouse)
    }

    if (qCatalogItem) {
      try {
        const itemRes = await catalogApi.get(String(qCatalogItem))
        const item = itemRes?.data
        if (item) {
          const pres = item.default_presentation || item.presentations?.[0]

          selectedCatalogItemId.value = item.id
          if (pres) {
            selectedPresentationId.value = pres.id
            initialPickerItem.value = {
              presentation_id: pres.id,
              catalog_item_id: item.id,
              code: item.code,
              name: item.name,
              presentation_name: pres.name,
              factor: pres.factor,
              unit: pres.unit,
            }
          }
        }
      }
      catch {
        // Silently ignore if initial item load fails
      }
    }

    if (selectedWarehouseId.value && selectedCatalogItemId.value) {
      await executeQuery()
    }
  }
  catch {
    // Silently continue
  }
})
</script>

<template>
  <div>
    <VCard>
      <VCardText>
        <PageHeader />
      </VCardText>

      <VDivider />

      <VCardText>
        <VRow>
          <VCol
            cols="12"
            md="3"
          >
            <VSelect
              v-model="selectedWarehouseId"
              :items="warehouseOptions"
              item-title="name"
              item-value="id"
              label="Almacén *"
              placeholder="Seleccione almacén"
              clearable
            />
          </VCol>

          <VCol
            cols="12"
            md="5"
          >
            <CatalogItemPicker
              v-model="selectedPresentationId"
              :initial-item="initialPickerItem"
              :warehouse-id="selectedWarehouseId"
              context="stock"
              label="Artículo / Presentación"
              placeholder="Escriba código o nombre del artículo, p. ej. ITM-000001"
              required
              @selected="handleItemSelected"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
            md="2"
          >
            <VTextField
              v-model="dateFrom"
              type="date"
              label="Fecha desde *"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
            md="2"
          >
            <VTextField
              v-model="dateTo"
              type="date"
              label="Fecha hasta *"
            />
          </VCol>
        </VRow>

        <div class="d-flex justify-end gap-3 mt-4 flex-wrap">
          <VBtn
            v-if="hasSearched"
            color="secondary"
            variant="tonal"
            prepend-icon="tabler-download"
            :loading="isExporting"
            @click="handleExport"
          >
            Exportar CSV
          </VBtn>

          <VBtn
            color="primary"
            prepend-icon="tabler-search"
            :loading="isLoading"
            :disabled="!isQueryValid"
            @click="executeQuery"
          >
            Consultar Kardex
          </VBtn>
        </div>
      </VCardText>

      <!-- Resultados del Kardex -->
      <template v-if="hasSearched">
        <VDivider />

        <VCardText class="d-flex justify-space-between align-center flex-wrap gap-2">
          <div>
            <h6 class="text-h6">
              Movimientos registrados
            </h6>
            <span class="text-body-2 text-medium-emphasis">Método de valuación: Promedio Ponderado Móvil</span>
          </div>
          <VChip
            color="info"
            variant="tonal"
          >
            {{ kardexData.movements.length }} movimientos en el período
          </VChip>
        </VCardText>

        <KardexTable
          :kardex-data="kardexData"
          :date-from="dateFrom"
          :date-to="dateTo"
        />
      </template>
    </VCard>
  </div>
</template>
