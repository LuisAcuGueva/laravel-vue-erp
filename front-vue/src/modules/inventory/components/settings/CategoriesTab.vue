<script setup>
import { computed, onMounted, ref } from 'vue'
import { useAcl } from '@/composables/useAcl'
import { useConfirm } from '@/composables/useConfirm'
import { useNotify } from '@/composables/useNotify'
import { useServerTable } from '@/composables/useServerTable'
import ServerDataTable from '@/components/common/ServerDataTable.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import CategoryFormDrawer from './CategoryFormDrawer.vue'
import { categoriesApi } from '../../services/inventorySettingsApi'
import { useInventorySettingsStore } from '../../stores/inventorySettingsStore'
import { categoryPath, MAX_DEPTH } from '../../utils/categoryTree'
import { CATEGORY_LEVEL_OPTIONS, SETTINGS_DRAWERS_TARGET } from '../../utils/options'
import { STATUS_OPTIONS } from '@/utils/options'

const ROOT_FILTER = 'root'

const { can } = useAcl()
const { confirmDelete, alert } = useConfirm()
const notify = useNotify()
const inventoryStore = useInventorySettingsStore()

const formDrawerRef = ref(null)
const isDrawerOpen = ref(false)
const isSubmitting = ref(false)
const selectedCategory = ref(null)
const deletingId = ref(null)
const categoryOptions = ref([])

const table = useServerTable({
  fetch: params => categoriesApi.list(params),
  filters: { 'parent_id': null, depth: null, 'is_active': null },
})

const headers = [
  { title: 'NOMBRE', key: 'name', sortable: false, alwaysVisible: true },
  { title: 'CÓDIGO', key: 'code', sortable: false },
  { title: 'NIVEL', key: 'depth', sortable: false, align: 'center' },
  { title: 'RUTA', key: 'path', sortable: false },
  { title: 'ESTADO', key: 'is_active', sortable: false, align: 'center' },
  { title: 'ACCIONES', key: 'actions', sortable: false, align: 'center' },
]

const LEVEL_COLORS = ['primary', 'info', 'secondary']

// Solo pueden tener hijos las categorías de los dos primeros niveles.
const parentFilterOptions = computed(() => {
  const byId = new Map(categoryOptions.value.map(c => [c.id, c]))

  return [
    { value: ROOT_FILTER, title: 'Solo categorías de primer nivel' },
    ...categoryOptions.value
      .filter(c => c.depth < MAX_DEPTH)
      .map(c => ({ value: c.id, title: categoryPath(c, byId) }))
      .sort((a, b) => a.title.localeCompare(b.title, 'es')),
  ]
})

const loadCategoryOptions = async () => {
  try {
    categoryOptions.value = await inventoryStore.fetchCategoryOptions(true)
  }
  catch {
    categoryOptions.value = []
  }
}

const openCreateDrawer = async () => {
  selectedCategory.value = null
  await loadCategoryOptions()
  isDrawerOpen.value = true
}

const openEditDrawer = async category => {
  selectedCategory.value = { ...category }
  await loadCategoryOptions()
  isDrawerOpen.value = true
}

const handleFormSubmit = async ({ id, ...payload }) => {
  isSubmitting.value = true
  formDrawerRef.value?.clearErrors()
  try {
    if (id) {
      await categoriesApi.update(id, payload)
      notify.success('Categoría actualizada correctamente')
    }
    else {
      await categoriesApi.create(payload)
      notify.success('Categoría creada correctamente')
    }
    isDrawerOpen.value = false
    inventoryStore.invalidateCategories()
    await Promise.all([table.reload(), loadCategoryOptions()])
  }
  catch (err) {
    // El drawer muestra los errores de campo y el mensaje general de la API.
    formDrawerRef.value?.setErrors(err)
  }
  finally {
    isSubmitting.value = false
  }
}

const removeCategory = async category => {
  if (!await confirmDelete({ entity: 'categoría', name: category.path || category.name }))
    return

  deletingId.value = category.id
  try {
    await categoriesApi.delete(category.id)
    notify.success('Categoría eliminada correctamente')
    inventoryStore.invalidateCategories()
    await Promise.all([table.reload(), loadCategoryOptions()])
  }
  catch (err) {
    // Reglas de negocio de la API (ej. categoría con subcategorías).
    await alert({ message: err?.data?.message || 'No se pudo eliminar la categoría.' })
  }
  finally {
    deletingId.value = null
  }
}

onMounted(loadCategoryOptions)
</script>

<template>
  <ServerDataTable
    v-model:options="table.options"
    v-model:search="table.search"
    table-id="inventory-categories"
    :headers="headers"
    :items="table.items"
    :total-items="table.total"
    :loading="table.loading"
    :has-filters="table.hasFilters"
    search-placeholder="Laptops o TEC-COM"
    create-label="Nueva categoría"
    :creatable="can('categories.create')"
    :exportable="can('categories.export')"
    :exporting="table.exporting"
    @refresh="table.reload"
    @create="openCreateDrawer"
    @export="table.export('/v1/inventory/categories/export', 'categorias')"
  >
    <template #filters>
      <VAutocomplete
        v-model="table.filters.parent_id"
        :items="parentFilterOptions"
        label="Categoría padre"
        placeholder="Tecnología"
        density="compact"
        clearable
      />

      <VSelect
        v-model="table.filters.depth"
        :items="CATEGORY_LEVEL_OPTIONS"
        label="Nivel"
        placeholder="Nivel 1"
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

    <!-- El listado viene ordenado por jerarquía: la sangría muestra el nivel. -->
    <template #item.name="{ item }">
      <div
        class="d-flex align-center"
        :style="{ paddingInlineStart: `${item.depth * 20}px` }"
      >
        <VIcon
          :icon="item.depth === 0 ? 'tabler-folder' : 'tabler-corner-down-right'"
          :class="item.depth === 0 ? 'text-primary' : 'text-disabled'"
          size="18"
          class="me-2"
        />
        <span class="font-weight-medium">{{ item.name }}</span>
      </div>
    </template>

    <template #item.code="{ item }">
      <span class="font-monospace text-caption">{{ item.code || '—' }}</span>
    </template>

    <template #item.depth="{ item }">
      <VChip
        size="small"
        :color="LEVEL_COLORS[item.depth] || 'secondary'"
      >
        Nivel {{ item.depth + 1 }}
      </VChip>
    </template>

    <template #item.path="{ item }">
      <span class="text-medium-emphasis">{{ item.path || item.name }}</span>
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
          v-if="can('categories.update')"
          action="edit"
          tooltip="Editar categoría"
          @click="openEditDrawer(item)"
        />
        <RowActionButton
          v-if="can('categories.delete')"
          action="delete"
          tooltip="Eliminar categoría"
          :loading="deletingId === item.id"
          @click="removeCategory(item)"
        />
      </div>
    </template>
  </ServerDataTable>

  <Teleport
    defer
    :to="SETTINGS_DRAWERS_TARGET"
  >
    <CategoryFormDrawer
      ref="formDrawerRef"
      v-model:is-drawer-open="isDrawerOpen"
      :category-data="selectedCategory"
      :category-options="categoryOptions"
      :is-submitting="isSubmitting"
      @submit="handleFormSubmit"
    />
  </Teleport>
</template>
