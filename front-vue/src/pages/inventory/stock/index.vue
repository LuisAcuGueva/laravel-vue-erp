<script setup>
/* eslint-disable camelcase */
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { formatDateDMY } from '@/utils/dates'
import PageHeader from '@/components/common/PageHeader.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import ServerDataTable from '@/components/common/ServerDataTable.vue'
import { useAcl } from '@/composables/useAcl'
import { useServerTable } from '@/composables/useServerTable'
import StockLimitsFormDrawer from '@/modules/inventory/components/stock/StockLimitsFormDrawer.vue'
import { stockApi } from '@/modules/inventory/services/stockApi'
import { categoriesApi, warehousesApi } from '@/modules/inventory/services/inventorySettingsApi'

definePage({
  meta: {
    title: 'Stock de Inventario',
    icon: 'tabler-stack-2',
    description: 'Consulta de existencias y valorización por almacén y artículo',
    action: 'stock.view',
  },
})

const router = useRouter()
const { can } = useAcl()

const headers = [
  { title: 'Código', key: 'code', sortable: false },
  { title: 'Artículo', key: 'name', sortable: false },
  { title: 'Almacén', key: 'warehouse', sortable: false },
  { title: 'Stock', key: 'quantity', align: 'end', sortable: false },
  { title: 'Costo Promedio', key: 'avg_cost', align: 'end', sortable: false },
  { title: 'Valor Total', key: 'total_value', align: 'end', sortable: false },
  { title: 'Mín / Máx', key: 'limits', align: 'center', sortable: false },
  { title: 'Último Movimiento', key: 'last_movement_at', sortable: false },
  { title: 'Acciones', key: 'actions', align: 'center', sortable: false },
]

const warehouseOptions = ref([])
const categoryOptions = ref([])
const totalValue = ref(0)

const isLimitsDrawerOpen = ref(false)
const selectedStock = ref(null)

const fetchStock = async params => {
  const response = await stockApi.list(params)
  if (response?.meta?.total_value !== undefined) {
    totalValue.value = response.meta.total_value
  }
  
  return response
}

const table = useServerTable({
  fetch: fetchStock,
  filters: {
    warehouse_id: null,
    category_id: null,
    below_min: false,
    include_zero: false,
  },
})

onMounted(async () => {
  try {
    const [warehousesRes, categoriesRes] = await Promise.all([
      warehousesApi.options(),
      categoriesApi.options(),
    ])

    warehouseOptions.value = warehousesRes?.data || []
    categoryOptions.value = categoriesRes?.data || []
  }
  catch {
    // Silently continue if options fail to load
  }
})

const goToKardex = item => {
  router.push({
    path: '/inventory/kardex',
    query: {
      warehouse_id: item.warehouse?.id,
      catalog_item_id: item.catalog_item?.id,
    },
  })
}

const openLimitsDialog = item => {
  selectedStock.value = item
  isLimitsDrawerOpen.value = true
}
</script>

<template>
  <div>
    <VCard>
      <VCardText>
        <PageHeader />
      </VCardText>

      <VDivider />

      <ServerDataTable
        v-model:options="table.options"
        v-model:search="table.search"
        table-id="stock-list"
        :headers="headers"
        :items="table.items"
        :total-items="table.total"
        :loading="table.loading"
        :has-filters="table.hasFilters"
        search-placeholder="Buscar por nombre o código de artículo, p. ej. ITM-000001"
        @refresh="table.reload"
      >
        <template #filters>
          <VSelect
            v-model="table.filters.warehouse_id"
            :items="warehouseOptions"
            item-title="name"
            item-value="id"
            label="Almacén"
            placeholder="Almacén Central"
            clearable
            density="compact"
          />

          <VSelect
            v-model="table.filters.category_id"
            :items="categoryOptions"
            item-title="name"
            item-value="id"
            label="Categoría"
            placeholder="Abarrotes"
            clearable
            density="compact"
          />

          <VSwitch
            v-model="table.filters.below_min"
            label="Bajo mínimo"
            density="compact"
            hide-details
          />

          <VSwitch
            v-model="table.filters.include_zero"
            label="Incluir sin stock"
            density="compact"
            hide-details
          />
        </template>

        <!-- Código -->
        <template #[`item.code`]="{ item }">
          <RouterLink
            v-if="item.catalog_item?.id"
            :to="`/inventory/catalog/${item.catalog_item.id}`"
            class="font-weight-medium text-primary text-decoration-none"
          >
            {{ item.catalog_item?.code }}
          </RouterLink>
          <span
            v-else
            class="font-weight-medium text-high-emphasis"
          >
            {{ item.catalog_item?.code }}
          </span>
        </template>

        <!-- Artículo -->
        <template #[`item.name`]="{ item }">
          <div class="d-flex flex-column">
            <span class="font-weight-medium">
              {{ item.catalog_item?.name }}
            </span>
            <span
              v-if="item.catalog_item?.category"
              class="text-caption text-medium-emphasis"
            >
              {{ item.catalog_item.category.name }}
            </span>
          </div>
        </template>

        <!-- Almacén -->
        <template #[`item.warehouse`]="{ item }">
          <span>{{ item.warehouse?.name }}</span>
        </template>

        <!-- Cantidad -->
        <template #[`item.quantity`]="{ item }">
          <div class="d-flex align-center justify-end gap-2">
            <VChip
              v-if="item.is_below_min"
              color="error"
              size="x-small"
              variant="tonal"
            >
              Bajo mínimo
            </VChip>
            <span class="font-weight-bold">
              {{ Number(item.quantity).toFixed(2) }} {{ item.catalog_item?.base_unit?.symbol || '' }}
            </span>
          </div>
        </template>

        <!-- Costo Promedio -->
        <template #[`item.avg_cost`]="{ item }">
          <span>S/ {{ Number(item.avg_cost).toFixed(4) }}</span>
        </template>

        <!-- Valor Total -->
        <template #[`item.total_value`]="{ item }">
          <span class="font-weight-medium text-high-emphasis">
            S/ {{ Number(item.total_value).toFixed(2) }}
          </span>
        </template>

        <!-- Límites -->
        <template #[`item.limits`]="{ item }">
          <span class="text-caption">
            {{ item.min_stock }} / {{ item.max_stock !== null ? item.max_stock : '—' }}
          </span>
        </template>

        <!-- Último Movimiento -->
        <template #[`item.last_movement_at`]="{ item }">
          <span class="text-caption">
            {{ formatDateDMY(item.last_movement_at) }}
          </span>
        </template>

        <!-- Acciones -->
        <template #[`item.actions`]="{ item }">
          <div class="d-flex justify-center gap-2">
            <RowActionButton
              action="custom"
              icon="tabler-report-analytics"
              color="info"
              tooltip="Ver kardex"
              @click="goToKardex(item)"
            />
            <RowActionButton
              v-if="can('stock.update_limits')"
              action="edit"
              tooltip="Ajustar límites de stock"
              @click="openLimitsDialog(item)"
            />
          </div>
        </template>
      </ServerDataTable>

      <!-- Pie con Valor Total -->
      <VDivider />
      <div class="d-flex justify-space-between align-center px-6 py-4 flex-wrap gap-2">
        <span class="text-body-2 text-medium-emphasis">
          Total de artículos con existencias mostrados en este corte
        </span>
        <div class="d-flex align-center gap-2">
          <span class="text-body-1 font-weight-medium">Valor total valorizado:</span>
          <VChip
            color="primary"
            variant="elevated"
            class="text-h6 font-weight-bold px-4"
          >
            S/ {{ Number(totalValue).toFixed(2) }}
          </VChip>
        </div>
      </div>
    </VCard>

    <StockLimitsFormDrawer
      v-model:is-drawer-open="isLimitsDrawerOpen"
      :stock-item="selectedStock"
      @saved="table.reload"
    />
  </div>
</template>
