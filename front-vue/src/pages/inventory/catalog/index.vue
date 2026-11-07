<script setup>
/* eslint-disable camelcase */
import PageHeader from '@/components/common/PageHeader.vue'
import ServerDataTable from '@/components/common/ServerDataTable.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import { useAcl } from '@/composables/useAcl'
import { useConfirm } from '@/composables/useConfirm'
import { useNotify } from '@/composables/useNotify'
import { useServerTable } from '@/composables/useServerTable'
import CatalogItemFormDrawer from '@/modules/inventory/components/catalog/CatalogItemFormDrawer.vue'
import { catalogApi } from '@/modules/inventory/services/catalogApi'
import { useInventorySettingsStore } from '@/modules/inventory/stores/inventorySettingsStore'
import { STATUS_OPTIONS } from '@/utils/options'
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

definePage({
  meta: {
    action: 'catalog.view',
    title: 'Catálogo de Artículos',
    icon: 'tabler-box',
    description: 'Gestión maestra de bienes y servicios, presentaciones y listas de precios.',
  },
})

const router = useRouter()
const { can } = useAcl()
const { confirmDelete, alert } = useConfirm()
const notify = useNotify()
const settingsStore = useInventorySettingsStore()

const formDrawerRef = ref(null)
const isDrawerOpen = ref(false)
const selectedItem = ref(null)
const isSubmitting = ref(false)
const deletingId = ref(null)

const categories = ref([])
const brands = ref([])
const units = ref([])

onMounted(async () => {
  try {
    const [cats, brs, uns] = await Promise.all([
      settingsStore.fetchCategoryOptions(),
      settingsStore.fetchBrandOptions(),
      settingsStore.fetchUnitOptions(),
    ])

    categories.value = cats || []
    brands.value = brs || []
    units.value = uns || []
  }
  catch {
    notify.error('No se pudieron cargar las categorías, marcas y unidades.')
  }
})

const table = useServerTable({
  fetch: params => catalogApi.list(params),
  filters: { type: null, category_id: null, brand_id: null, is_active: null },
})

const headers = [
  { title: 'CÓDIGO', key: 'code', sortable: false, width: 130, alwaysVisible: true },
  { title: 'ARTÍCULO', key: 'name', sortable: false, alwaysVisible: true },
  { title: 'TIPO', key: 'type', sortable: false, width: 110 },
  { title: 'CATEGORÍA', key: 'category', sortable: false },
  { title: 'MARCA', key: 'brand', sortable: false },
  { title: 'UNIDAD BASE', key: 'base_unit', sortable: false },
  { title: 'PRECIO REF.', key: 'price', sortable: false, align: 'end' },
  { title: 'ESTADO', key: 'is_active', sortable: false, width: 100 },
  { title: 'ACCIONES', key: 'actions', sortable: false, align: 'center', width: 120 },
]

const typeFilterOptions = [
  { value: 'product', title: 'Producto' },
  { value: 'service', title: 'Servicio' },
]

const openCreateDrawer = () => {
  selectedItem.value = null
  isDrawerOpen.value = true
}

const openEditDrawer = item => {
  selectedItem.value = { ...item }
  isDrawerOpen.value = true
}

const goToDetail = item => {
  router.push({
    name: 'inventory-catalog-id',
    params: { id: item.id },
  })
}

const handleFormSubmit = async formData => {
  isSubmitting.value = true
  formDrawerRef.value?.clearErrors()
  try {
    if (formData.id) {
      await catalogApi.update(formData.id, formData)
      notify.success('Artículo actualizado correctamente')
    }
    else {
      await catalogApi.create(formData)
      notify.success('Artículo creado correctamente')
    }
    isDrawerOpen.value = false
    await table.reload()
  }
  catch (err) {
    formDrawerRef.value?.setErrors(err)
  }
  finally {
    isSubmitting.value = false
  }
}

const handleDelete = async item => {
  const confirmed = await confirmDelete({
    entity: 'el artículo',
    name: item.name,
  })

  if (!confirmed) return

  deletingId.value = item.id
  try {
    await catalogApi.delete(item.id)
    notify.success('Artículo eliminado correctamente')
    await table.reload()
  }
  catch (err) {
    const message = err?.data?.message || err?.message || 'No se pudo eliminar el artículo'

    await alert({ message })
  }
  finally {
    deletingId.value = null
  }
}
</script>

<template>
  <div class="catalog-page">
    <VCard>
      <VCardText>
        <PageHeader />
      </VCardText>

      <VDivider />

      <ServerDataTable
        v-model:options="table.options"
        v-model:search="table.search"
        table-id="catalog-items"
        :headers="headers"
        :items="table.items"
        :total-items="table.total"
        :loading="table.loading"
        :has-filters="table.hasFilters"
        search-placeholder="Laptop o ITM-000001"
        create-label="Nuevo artículo"
        :creatable="can('catalog.create')"
        :exportable="false"
        @refresh="table.reload"
        @create="openCreateDrawer"
      >
        <template #filters>
          <VSelect
            v-model="table.filters.type"
            :items="typeFilterOptions"
            label="Tipo"
            placeholder="Producto"
            density="compact"
            clearable
          />
          <VAutocomplete
            v-model="table.filters.category_id"
            :items="categories"
            item-title="name"
            item-value="id"
            label="Categoría"
            placeholder="Electrónica"
            density="compact"
            clearable
          />
          <VAutocomplete
            v-model="table.filters.brand_id"
            :items="brands"
            item-title="name"
            item-value="id"
            label="Marca"
            placeholder="Samsung"
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

        <!-- Columna Código -->
        <template #[`item.code`]="{ item }">
          <span
            class="font-weight-medium text-primary cursor-pointer"
            @click="goToDetail(item)"
          >
            {{ item.code }}
          </span>
        </template>

        <!-- Columna Nombre -->
        <template #[`item.name`]="{ item }">
          <div class="d-flex flex-column">
            <span
              class="font-weight-medium cursor-pointer text-high-emphasis"
              @click="goToDetail(item)"
            >
              {{ item.name }}
            </span>
            <span
              v-if="item.default_presentation?.barcode"
              class="text-caption text-disabled"
            >
              EAN: {{ item.default_presentation.barcode }}
            </span>
          </div>
        </template>

        <!-- Columna Tipo -->
        <template #[`item.type`]="{ item }">
          <VChip
            size="small"
            :color="item.type === 'product' ? 'primary' : 'info'"
            variant="tonal"
          >
            {{ item.type === 'product' ? 'Producto' : 'Servicio' }}
          </VChip>
        </template>

        <!-- Columna Categoría -->
        <template #[`item.category`]="{ item }">
          <span>{{ item.category?.name || '—' }}</span>
        </template>

        <!-- Columna Marca -->
        <template #[`item.brand`]="{ item }">
          <span>{{ item.brand?.name || '—' }}</span>
        </template>

        <!-- Columna Unidad Base -->
        <template #[`item.base_unit`]="{ item }">
          <span>{{ item.base_unit?.name }} ({{ item.base_unit?.symbol }})</span>
        </template>

        <!-- Columna Precio de Referencia -->
        <template #[`item.price`]="{ item }">
          <span v-if="item.default_presentation?.prices?.length">
            {{ Number(item.default_presentation.prices[0].price).toLocaleString('es-PE', { style: 'currency', currency: 'PEN' }) }}
          </span>
          <span
            v-else
            class="text-disabled"
          >
            —
          </span>
        </template>

        <!-- Columna Estado -->
        <template #[`item.is_active`]="{ item }">
          <VChip
            size="small"
            :color="item.is_active ? 'success' : 'secondary'"
            variant="tonal"
          >
            {{ item.is_active ? 'Activo' : 'Inactivo' }}
          </VChip>
        </template>

        <!-- Acciones -->
        <template #[`item.actions`]="{ item }">
          <div class="d-flex justify-center gap-2">
            <RowActionButton
              action="view"
              tooltip="Ver presentaciones y precios"
              @click="goToDetail(item)"
            />
            <RowActionButton
              v-if="can('catalog.update')"
              action="edit"
              tooltip="Editar artículo"
              @click="openEditDrawer(item)"
            />
            <RowActionButton
              v-if="can('catalog.delete')"
              action="delete"
              tooltip="Eliminar artículo"
              :loading="deletingId === item.id"
              @click="handleDelete(item)"
            />
          </div>
        </template>
      </ServerDataTable>
    </VCard>

    <Teleport to="body">
      <CatalogItemFormDrawer
        ref="formDrawerRef"
        v-model:is-drawer-open="isDrawerOpen"
        :item-data="selectedItem"
        :categories="categories"
        :brands="brands"
        :units="units"
        :is-submitting="isSubmitting"
        @submit="handleFormSubmit"
      />
    </Teleport>
  </div>
</template>
