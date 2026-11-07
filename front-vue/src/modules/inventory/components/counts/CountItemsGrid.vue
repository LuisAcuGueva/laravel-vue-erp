<script setup>
/* eslint-disable camelcase */
import { computed, nextTick, ref, watch } from 'vue'

const props = defineProps({
  items: {
    type: Array,
    default: () => [],
  },
  readonly: {
    type: Boolean,
    default: false,
  },
  isSaving: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['save'])

const searchQuery = ref('')
const barcodeInput = ref('')
const filterStatus = ref('all') // 'all', 'counted', 'uncounted', 'diff_positive', 'diff_negative', 'exact'
const modifiedIds = ref(new Set())

// Copia local para editar cantidades
const localItems = ref([])

watch(
  () => props.items,
  newItems => {
    localItems.value = (newItems || []).map(item => ({
      ...item,
      local_counted: item.counted_quantity !== null ? Number(item.counted_quantity) : null,
    }))
    modifiedIds.value.clear()
  },
  { immediate: true },
)

const filterOptions = [
  { value: 'all', title: 'Todos los artículos' },
  { value: 'counted', title: 'Contados' },
  { value: 'uncounted', title: 'Sin contar' },
  { value: 'diff_positive', title: 'Con sobrante (+)' },
  { value: 'diff_negative', title: 'Con faltante (-)' },
  { value: 'exact', title: 'Conteo exacto (=)' },
]

// Cálculo de métricas
const totalItems = computed(() => localItems.value.length)

const countedItemsCount = computed(() => {
  return localItems.value.filter(it => it.local_counted !== null && it.local_counted !== '').length
})

const progressPercent = computed(() => {
  if (totalItems.value === 0) return 0
  
  return Math.round((countedItemsCount.value / totalItems.value) * 100)
})

const uncountedCount = computed(() => totalItems.value - countedItemsCount.value)

// Filtrado de la tabla
const filteredItems = computed(() => {
  let list = localItems.value

  // Filtro de texto (código o nombre)
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.trim().toLowerCase()

    list = list.filter(it => {
      const code = (it.catalog_item?.code || '').toLowerCase()
      const name = (it.catalog_item?.name || '').toLowerCase()
      
      return code.includes(q) || name.includes(q)
    })
  }

  // Filtro de estado
  if (filterStatus.value === 'counted') {
    list = list.filter(it => it.local_counted !== null && it.local_counted !== '')
  } else if (filterStatus.value === 'uncounted') {
    list = list.filter(it => it.local_counted === null || it.local_counted === '')
  } else if (filterStatus.value === 'diff_positive') {
    list = list.filter(it => {
      if (it.local_counted === null || it.local_counted === '') return false
      
      return Number(it.local_counted) > Number(it.system_quantity)
    })
  } else if (filterStatus.value === 'diff_negative') {
    list = list.filter(it => {
      if (it.local_counted === null || it.local_counted === '') return false
      
      return Number(it.local_counted) < Number(it.system_quantity)
    })
  } else if (filterStatus.value === 'exact') {
    list = list.filter(it => {
      if (it.local_counted === null || it.local_counted === '') return false
      
      return Number(it.local_counted) === Number(it.system_quantity)
    })
  }

  return list
})

const onItemCountChange = item => {
  if (item.local_counted === '' || item.local_counted === null) {
    item.local_counted = null
  } else {
    item.local_counted = Number(item.local_counted)
  }
  modifiedIds.value.add(item.catalog_item_id)

  // Auto-guardado en lote si supera 20 cambios
  if (modifiedIds.value.size >= 20) {
    handleSave()
  }
}

const getDifference = item => {
  if (item.local_counted === null || item.local_counted === undefined || item.local_counted === '') {
    return null
  }
  
  return Number(item.local_counted) - Number(item.system_quantity)
}

const getDifferenceValue = item => {
  const diff = getDifference(item)
  if (diff === null) return null
  
  return diff * Number(item.unit_cost || 0)
}

const formatCurrency = val => {
  if (val === null || val === undefined) return '—'
  const num = Number(val)
  
  return `S/ ${num.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

const formatNumber = val => {
  if (val === null || val === undefined) return '—'
  
  return Number(val).toLocaleString('es-PE', { minimumFractionDigits: 0, maximumFractionDigits: 4 })
}

// Búsqueda y selección rápida por código de barras
const handleBarcodeScan = () => {
  const code = barcodeInput.value.trim().toLowerCase()
  if (!code) return

  const foundItem = localItems.value.find(it => {
    const itCode = (it.catalog_item?.code || '').toLowerCase()
    
    return itCode === code
  })

  if (foundItem) {
    searchQuery.value = foundItem.catalog_item?.code || ''
    nextTick(() => {
      const el = document.getElementById(`count-input-${foundItem.catalog_item_id}`)
      if (el) {
        el.focus()
        el.select()
      }
    })
  }

  barcodeInput.value = ''
}

const handleSave = () => {
  if (modifiedIds.value.size === 0 || props.isSaving) return

  const itemsToSave = localItems.value
    .filter(it => modifiedIds.value.has(it.catalog_item_id) && it.local_counted !== null)
    .map(it => ({
      catalog_item_id: it.catalog_item_id,
      counted_quantity: Number(it.local_counted),
    }))

  if (itemsToSave.length > 0) {
    emit('save', itemsToSave)
  }
}

defineExpose({
  handleSave,
  hasPendingChanges: computed(() => modifiedIds.value.size > 0),
})
</script>

<template>
  <div class="count-items-grid">
    <!-- Progreso general -->
    <VCardText>
      <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-3">
        <div class="d-flex align-center gap-2">
          <h6 class="text-h6">
            Progreso de toma física
          </h6>
          <VChip
            size="small"
            :color="progressPercent === 100 ? 'success' : 'primary'"
            variant="tonal"
          >
            {{ progressPercent }}% completado
          </VChip>
        </div>

        <div class="text-body-2 text-medium-emphasis">
          Contados: <strong class="text-success">{{ countedItemsCount }}</strong> de <strong>{{ totalItems }}</strong> artículos
          <span
            v-if="uncountedCount > 0"
            class="ms-2 text-warning"
          >
            ({{ uncountedCount }} pendientes)
          </span>
        </div>
      </div>

      <VProgressLinear
        :model-value="progressPercent"
        :color="progressPercent === 100 ? 'success' : 'primary'"
        height="8"
        rounded
      />
    </VCardText>

    <VDivider />

    <!-- Escaneo y filtros -->
    <div class="d-flex flex-wrap align-center gap-4 px-6 py-4 count-items-grid__filters">
      <VTextField
        v-model="barcodeInput"
        label="Escanear código de barras"
        placeholder="ITM-000001"
        prepend-inner-icon="tabler-barcode"
        density="compact"
        clearable
        @keydown.enter.prevent="handleBarcodeScan"
      />
      <VTextField
        v-model="searchQuery"
        label="Buscar artículo"
        placeholder="Laptop o ITM-000001"
        prepend-inner-icon="tabler-search"
        density="compact"
        clearable
      />
      <VSelect
        v-model="filterStatus"
        :items="filterOptions"
        label="Mostrar"
        placeholder="Todos los artículos"
        density="compact"
      />
    </div>

    <VDivider />

    <!-- Barra de aviso de cambios sin guardar -->
    <div
      v-if="modifiedIds.size > 0 && !readonly"
      class="count-items-grid__pending d-flex align-center justify-space-between px-6 py-3"
    >
      <div class="d-flex align-center gap-2 text-warning">
        <VIcon icon="tabler-alert-circle" />
        <span class="text-body-2 font-weight-medium">
          Tienes {{ modifiedIds.size }} artículos con cambios no guardados en el servidor.
        </span>
      </div>

      <VBtn
        size="small"
        color="primary"
        variant="elevated"
        :loading="isSaving"
        @click="handleSave"
      >
        <VIcon
          icon="tabler-device-floppy"
          class="me-1"
        />
        Guardar conteo
      </VBtn>
    </div>

    <!-- Grilla de artículos -->
    <div>
      <div>
        <VTable
          density="compact"
          hover
        >
          <thead>
            <tr>
              <th
                style="width: 50px;"
                class="text-center"
              >
                #
              </th>
              <th style="width: 140px;">
                Código
              </th>
              <th>Artículo / Descripción</th>
              <th style="width: 80px;">
                U.M.
              </th>
              <th
                style="width: 110px;"
                class="text-end"
              >
                Stock Sist.
              </th>
              <th
                style="width: 150px;"
                class="text-end"
              >
                Conteo Físico
              </th>
              <th
                style="width: 120px;"
                class="text-end"
              >
                Diferencia
              </th>
              <th
                style="width: 120px;"
                class="text-end"
              >
                Valor Dif.
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(item, idx) in filteredItems"
              :key="item.id"
              :class="{ 'count-row--modified': modifiedIds.has(item.catalog_item_id) }"
            >
              <td class="text-center text-caption text-medium-emphasis">
                {{ idx + 1 }}
              </td>
              <td class="font-weight-medium">
                <RouterLink
                  v-if="item.catalog_item_id"
                  :to="`/inventory/catalog/${item.catalog_item_id}`"
                  class="text-primary text-decoration-none"
                >
                  {{ item.catalog_item?.code || '—' }}
                </RouterLink>
                <span v-else>{{ item.catalog_item?.code || '—' }}</span>
              </td>
              <td>
                <div class="font-weight-medium">
                  {{ item.catalog_item?.name || '—' }}
                </div>
                <div
                  v-if="item.counter"
                  class="text-caption text-medium-emphasis"
                >
                  Contado por: {{ item.counter.name }}
                </div>
              </td>
              <td>
                <VChip
                  size="x-small"
                  variant="outlined"
                >
                  {{ item.catalog_item?.base_unit?.symbol || 'UND' }}
                </VChip>
              </td>
              <td class="text-end font-weight-medium">
                {{ formatNumber(item.system_quantity) }}
              </td>
              <td class="text-end">
                <div
                  v-if="readonly"
                  class="font-weight-bold"
                >
                  {{ item.local_counted !== null ? formatNumber(item.local_counted) : '—' }}
                </div>
                <VTextField
                  v-else
                  :id="`count-input-${item.catalog_item_id}`"
                  v-model="item.local_counted"
                  type="number"
                  min="0"
                  step="any"
                  density="compact"
                  placeholder="0.00"
                  hide-details
                  class="ms-auto"
                  style="max-width: 130px;"
                  @update:model-value="() => onItemCountChange(item)"
                  @keydown.enter.prevent="handleSave"
                />
              </td>
              <td class="text-end">
                <span
                  v-if="getDifference(item) === null"
                  class="text-medium-emphasis"
                >
                  —
                </span>
                <VChip
                  v-else
                  size="x-small"
                  variant="tonal"
                  :color="getDifference(item) > 0 ? 'success' : getDifference(item) < 0 ? 'error' : 'secondary'"
                >
                  {{ getDifference(item) > 0 ? '+' : '' }}{{ formatNumber(getDifference(item)) }}
                </VChip>
              </td>
              <td
                class="text-end font-weight-medium"
                :class="getDifferenceValue(item) > 0 ? 'text-success' : getDifferenceValue(item) < 0 ? 'text-error' : 'text-medium-emphasis'"
              >
                {{ formatCurrency(getDifferenceValue(item)) }}
              </td>
            </tr>

            <tr v-if="filteredItems.length === 0">
              <td
                colspan="8"
                class="text-center py-6 text-medium-emphasis"
              >
                No se encontraron artículos con los filtros aplicados.
              </td>
            </tr>
          </tbody>
        </VTable>
      </div>
    </div>
  </div>
</template>

<style scoped>
.count-items-grid__filters > :deep(.v-input) {
  flex: 1 1 12rem;
  max-inline-size: 18rem;
}

.count-items-grid__pending {
  background-color: rgba(var(--v-theme-warning), 0.12);
}

/* Fila actual (hover o campo de conteo enfocado) y fila con cambios sin guardar */
.count-items-grid :deep(tbody tr:hover > td),
.count-items-grid :deep(tbody tr:focus-within > td) {
  background-color: rgba(var(--v-theme-primary), 0.16) !important;
}

.count-items-grid :deep(tbody tr:hover > td:first-child),
.count-items-grid :deep(tbody tr:focus-within > td:first-child) {
  box-shadow: inset 3px 0 0 rgb(var(--v-theme-primary));
}

.count-items-grid :deep(tbody tr.count-row--modified > td) {
  background-color: rgba(var(--v-theme-warning), 0.10) !important;
}

.count-items-grid :deep(tbody tr.count-row--modified > td:first-child) {
  box-shadow: inset 3px 0 0 rgb(var(--v-theme-warning));
}

.count-items-grid :deep(tbody tr.count-row--modified:hover > td),
.count-items-grid :deep(tbody tr.count-row--modified:focus-within > td) {
  background-color: rgba(var(--v-theme-primary), 0.16) !important;
}
</style>
