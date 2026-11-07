<script setup>
import DataTableColumnMenu from '@/components/common/data-table/DataTableColumnMenu.vue'
import DataTableFooter from '@/components/common/data-table/DataTableFooter.vue'
import DataTableToolbar from '@/components/common/data-table/DataTableToolbar.vue'

const props = defineProps({
  tableId: {
    type: String,
    required: true,
  },

  // Extensión opcional por header: { alwaysVisible, hidden (oculta por defecto), maxWidth }.
  headers: {
    type: Array,
    required: true,
  },
  items: {
    type: Array,
    default: () => [],
  },
  totalItems: {
    type: Number,
    default: 0,
  },
  loading: {
    type: Boolean,
    default: false,
  },

  // { page, itemsPerPage, sortBy }
  options: {
    type: Object,
    default: () => ({ page: 1, itemsPerPage: 10, sortBy: [] }),
  },
  search: {
    type: String,
    default: '',
  },

  // Hay filtros aplicados además de la búsqueda (cambia el texto del estado vacío).
  hasFilters: {
    type: Boolean,
    default: false,
  },
  searchable: {
    type: Boolean,
    default: true,
  },
  searchPlaceholder: {
    type: String,
    default: '',
  },
  createLabel: {
    type: String,
    default: 'Nuevo registro',
  },
  creatable: {
    type: Boolean,
    default: false,
  },
  exportable: {
    type: Boolean,
    default: false,
  },
  exporting: {
    type: Boolean,
    default: false,
  },
  importable: {
    type: Boolean,
    default: false,
  },
  refreshable: {
    type: Boolean,
    default: true,
  },
  columnToggle: {
    type: Boolean,
    default: true,
  },
  itemsPerPageOptions: {
    type: Array,
    default: () => [10, 25, 50, 100],
  },
  showExpand: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits([
  'update:options',
  'update:search',
  'refresh',
  'create',
  'export',
  'import',
])

defineOptions({
  inheritAttrs: false,
})

const slots = useSlots()

// Slots propios de la barra; el resto se reenvía a VDataTableServer.
const OWN_SLOTS = ['filters', 'toolbar-append', 'no-data', 'loading']

const forwardedSlots = computed(() => Object.keys(slots).filter(name => !OWN_SLOTS.includes(name)))

const hiddenKeys = ref(props.headers.filter(h => h.hidden).map(h => h.key))

const visibleHeaders = computed(() => props.headers
  .filter(h => h.key === 'actions' || h.alwaysVisible || !hiddenKeys.value.includes(h.key))
  .map(({ alwaysVisible, hidden, ...header }) => header))

// Columnas con `maxWidth` y sin slot propio: elipsis + tooltip con el texto completo.
const ellipsisHeaders = computed(() => visibleHeaders.value.filter(h => h.maxWidth && !slots[`item.${h.key}`]))

const currentOptions = computed(() => ({
  page: props.options?.page ?? 1,
  itemsPerPage: props.options?.itemsPerPage ?? 10,
  sortBy: props.options?.sortBy ?? [],
}))

const updateOptions = patch => {
  const next = { ...currentOptions.value, ...patch }

  if (JSON.stringify(next) !== JSON.stringify(currentOptions.value))
    emit('update:options', next)
}

const onSortBy = sortBy => updateOptions({ sortBy, page: 1 })

const onItemsPerPage = itemsPerPage => updateOptions({ itemsPerPage, page: 1 })

const isFiltered = computed(() => Boolean(props.search) || props.hasFilters)

const emptyText = computed(() => {
  if (props.search)
    return `No hay resultados para "${props.search}"`
  if (props.hasFilters)
    return 'No hay resultados para los filtros aplicados'

  return 'No se encontraron registros'
})

const cellText = (item, key) => {
  const value = item?.[key]

  return value === null || value === undefined || value === '' ? '—' : String(value)
}
</script>

<template>
  <div class="server-data-table">
    <DataTableToolbar
      :search="props.search"
      :searchable="props.searchable"
      :search-placeholder="props.searchPlaceholder"
      :create-label="props.createLabel"
      :creatable="props.creatable"
      :exportable="props.exportable"
      :exporting="props.exporting"
      :importable="props.importable"
      :refreshable="props.refreshable"
      :loading="props.loading"
      @update:search="emit('update:search', $event)"
      @refresh="emit('refresh')"
      @create="emit('create')"
      @export="emit('export')"
      @import="emit('import', $event)"
    >
      <template
        v-if="$slots.filters"
        #filters
      >
        <slot name="filters" />
      </template>

      <template #append>
        <slot name="toolbar-append" />
        <DataTableColumnMenu
          v-if="props.columnToggle"
          v-model="hiddenKeys"
          :table-id="props.tableId"
          :headers="props.headers"
        />
      </template>
    </DataTableToolbar>

    <VDivider />

    <VDataTableServer
      v-bind="$attrs"
      :headers="visibleHeaders"
      :items="props.items"
      :items-length="props.totalItems"
      :loading="props.loading"
      :page="currentOptions.page"
      :items-per-page="currentOptions.itemsPerPage"
      :sort-by="currentOptions.sortBy"
      :show-expand="props.showExpand"
      hide-default-footer
      class="text-no-wrap"
      @update:sort-by="onSortBy"
    >
      <template
        v-for="header in ellipsisHeaders"
        :key="header.key"
        #[`item.${header.key}`]="{ item }"
      >
        <VTooltip
          :text="cellText(item, header.key)"
          :disabled="cellText(item, header.key).length < 30"
        >
          <template #activator="{ props: tooltipProps }">
            <span
              v-bind="tooltipProps"
              class="d-inline-block text-truncate align-middle"
              :style="{ maxWidth: `${header.maxWidth}px` }"
            >
              {{ cellText(item, header.key) }}
            </span>
          </template>
        </VTooltip>
      </template>

      <template
        v-for="slotName in forwardedSlots"
        #[slotName]="slotScope"
      >
        <slot
          :name="slotName"
          v-bind="slotScope || {}"
        />
      </template>

      <template #no-data>
        <slot name="no-data">
          <div
            class="text-center py-10 text-medium-emphasis"
            data-testid="dt-empty"
          >
            <VIcon
              size="40"
              icon="tabler-database-off"
              class="mb-3 d-block mx-auto"
            />
            <p class="text-body-1 mb-0">
              {{ emptyText }}
            </p>
            <VBtn
              v-if="props.creatable && !isFiltered"
              class="mt-4"
              prepend-icon="tabler-plus"
              data-testid="dt-empty-create"
              @click="emit('create')"
            >
              {{ props.createLabel }}
            </VBtn>
          </div>
        </slot>
      </template>

      <template #loading>
        <slot name="loading">
          <div class="py-4 px-4">
            <VSkeletonLoader
              type="table-row@5"
              class="w-100"
            />
          </div>
        </slot>
      </template>
    </VDataTableServer>

    <VDivider />

    <DataTableFooter
      :page="currentOptions.page"
      :items-per-page="currentOptions.itemsPerPage"
      :total-items="props.totalItems"
      :items-per-page-options="props.itemsPerPageOptions"
      @update:page="updateOptions({ page: $event })"
      @update:items-per-page="onItemsPerPage"
    />
  </div>
</template>
