<script setup>
/* eslint-disable camelcase */
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { formatDateDMY } from '@/utils/dates'
import PageHeader from '@/components/common/PageHeader.vue'
import ServerDataTable from '@/components/common/ServerDataTable.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import { useAcl } from '@/composables/useAcl'
import { useConfirm } from '@/composables/useConfirm'
import { useNotify } from '@/composables/useNotify'
import { useServerTable } from '@/composables/useServerTable'
import { categoriesApi, warehousesApi } from '@/modules/inventory/services/inventorySettingsApi'
import { inventoryCountsApi } from '@/modules/inventory/services/inventoryCountsApi'

definePage({
  meta: {
    action: 'inventory_counts.view',
    title: 'Toma de inventario',
    icon: 'tabler-clipboard-check',
    description: 'Toma física de inventario, conteo por almacén o categoría y generación automática de ajustes',
  },
})

const router = useRouter()
const { can } = useAcl()
const { confirm, alert } = useConfirm()
const notify = useNotify()

const COUNT_STATUS_OPTIONS = [
  { title: 'En conteo', value: 'counting' },
  { title: 'Cerrada', value: 'closed' },
  { title: 'Anulada', value: 'cancelled' },
]

const headers = [
  { title: 'NÚMERO', key: 'number', sortable: false, alwaysVisible: true },
  { title: 'FECHA', key: 'count_date', sortable: false },
  { title: 'ALMACÉN', key: 'warehouse', sortable: false },
  { title: 'ALCANCE / CATEGORÍA', key: 'category', sortable: false },
  { title: 'PROGRESO', key: 'progress', sortable: false, align: 'center' },
  { title: 'ESTADO', key: 'status', sortable: false, align: 'center' },
  { title: 'INICIADO POR', key: 'creator', sortable: false },
  { title: 'ACCIONES', key: 'actions', sortable: false, align: 'center' },
]

const warehouses = ref([])
const categories = ref([])

const table = useServerTable({
  fetch: params => inventoryCountsApi.list(params),
  filters: {
    warehouse_id: null,
    category_id: null,
    status: null,
    date_from: null,
    date_to: null,
  },
})

// Modal para iniciar nueva toma
const isCreateDialogOpen = ref(false)
const isSubmitting = ref(false)
const createErrors = ref({})

const createForm = ref({
  warehouse_id: null,
  category_id: null,
  count_date: new Date().toLocaleDateString('en-CA'),
  notes: '',
})

const openCreateModal = () => {
  createErrors.value = {}
  createForm.value = {
    warehouse_id: warehouses.value[0]?.id || null,
    category_id: null,
    count_date: new Date().toLocaleDateString('en-CA'),
    notes: '',
  }
  isCreateDialogOpen.value = true
}

const handleCreateSubmit = async () => {
  createErrors.value = {}
  if (!createForm.value.warehouse_id) {
    createErrors.value = { warehouse_id: 'Debe seleccionar un almacén' }
    
    return
  }

  isSubmitting.value = true
  try {
    const payload = {
      warehouse_id: createForm.value.warehouse_id,
      category_id: createForm.value.category_id || null,
      count_date: createForm.value.count_date,
      notes: createForm.value.notes || null,
    }

    const res = await inventoryCountsApi.create(payload)

    notify.success('Toma de inventario iniciada correctamente')
    isCreateDialogOpen.value = false

    const newId = res?.data?.id
    if (newId) {
      router.push({ name: 'inventory-counts-id', params: { id: newId } })
    } else {
      table.reload()
    }
  }
  catch (err) {
    if (err?.data?.errors) {
      createErrors.value = err.data.errors
    } else {
      const msg = err?.data?.message || err?.message || 'Error al iniciar la toma de inventario'

      alert({ title: 'No se pudo iniciar', message: msg })
    }
  }
  finally {
    isSubmitting.value = false
  }
}

const handleCancelCount = async count => {
  const confirmed = await confirm({
    title: 'Anular toma de inventario',
    message: `¿Estás seguro de anular la toma de inventario ${count.number}? Esta acción no se puede deshacer.`,
    confirmText: 'Sí, anular',
    cancelText: 'Cancelar',
  })

  if (!confirmed) return

  try {
    await inventoryCountsApi.cancel(count.id)
    notify.success('Toma de inventario anulada correctamente')
    table.reload()
  }
  catch (err) {
    const message = err?.data?.message || err?.message || 'Error al anular la toma'

    alert({ title: 'No se pudo anular', message })
  }
}

const statusColor = status => {
  if (status === 'counting') return 'info'
  if (status === 'closed') return 'success'
  if (status === 'cancelled') return 'error'
  
  return 'secondary'
}

onMounted(async () => {
  try {
    const [whRes, catRes] = await Promise.all([
      warehousesApi.options(),
      categoriesApi.options(),
    ])

    warehouses.value = whRes?.data || []
    categories.value = catRes?.data || []
  }
  catch (err) {
    console.error('Error al cargar opciones de almacenes o categorías', err)
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

      <ServerDataTable
        v-model:options="table.options"
        v-model:search="table.search"
        table-id="inventory-counts"
        :headers="headers"
        :items="table.items"
        :total-items="table.total"
        :loading="table.loading"
        :has-filters="table.hasFilters"
        search-placeholder="Buscar por número, p. ej. TI-000001"
        create-label="Nueva toma"
        :creatable="can('inventory_counts.manage')"
        @refresh="table.reload"
        @create="openCreateModal"
      >
        <template #filters>
          <VSelect
            v-model="table.filters.warehouse_id"
            :items="warehouses"
            item-title="name"
            item-value="id"
            label="Almacén"
            placeholder="Almacén Central"
            density="compact"
            clearable
          />
          <VSelect
            v-model="table.filters.category_id"
            :items="categories"
            item-title="name"
            item-value="id"
            label="Categoría"
            placeholder="Abarrotes"
            density="compact"
            clearable
          />
          <VSelect
            v-model="table.filters.status"
            :items="COUNT_STATUS_OPTIONS"
            label="Estado"
            placeholder="En conteo"
            density="compact"
            clearable
          />
          <VTextField
            v-model="table.filters.date_from"
            type="date"
            label="Desde"
            density="compact"
            clearable
          />
          <VTextField
            v-model="table.filters.date_to"
            type="date"
            label="Hasta"
            density="compact"
            clearable
          />
        </template>

        <!-- Slot de Número -->
        <template #[`item.number`]="{ item }">
          <RouterLink
            :to="{ name: 'inventory-counts-id', params: { id: item.id } }"
            class="font-weight-bold text-primary text-decoration-none"
          >
            {{ item.number }}
          </RouterLink>
        </template>

        <!-- Slot de Fecha -->
        <template #[`item.count_date`]="{ item }">
          <span>{{ formatDateDMY(item.count_date) }}</span>
        </template>

        <!-- Slot de Almacén -->
        <template #[`item.warehouse`]="{ item }">
          <span>{{ item.warehouse?.name || '—' }}</span>
        </template>

        <!-- Slot de Categoría -->
        <template #[`item.category`]="{ item }">
          <VChip
            v-if="item.category"
            size="small"
            variant="tonal"
            color="secondary"
          >
            {{ item.category.name }}
          </VChip>
          <span
            v-else
            class="text-caption text-medium-emphasis"
          >
            Todo el almacén
          </span>
        </template>

        <!-- Slot de Progreso -->
        <template #[`item.progress`]="{ item }">
          <div class="d-flex align-center justify-center gap-1">
            <span class="font-weight-medium text-caption">
              {{ item.counted_items }} / {{ item.total_items }}
            </span>
            <span class="text-caption text-medium-emphasis">
              ({{ item.total_items > 0 ? Math.round((item.counted_items / item.total_items) * 100) : 0 }}%)
            </span>
          </div>
        </template>

        <!-- Slot de Estado -->
        <template #[`item.status`]="{ item }">
          <VChip
            size="small"
            variant="tonal"
            :color="statusColor(item.status)"
          >
            {{ item.status_label }}
          </VChip>
        </template>

        <!-- Slot de Creador -->
        <template #[`item.creator`]="{ item }">
          <span>{{ item.creator?.name || '—' }}</span>
        </template>

        <!-- Slot de Acciones -->
        <template #[`item.actions`]="{ item }">
          <div class="d-flex align-center justify-center gap-1">
            <!-- Ver / Gestionar conteo -->
            <RowActionButton
              action="view"
              color="info"
              tooltip="Ver / Gestionar conteo"
              @click="router.push({ name: 'inventory-counts-id', params: { id: item.id } })"
            />

            <!-- Anular toma si está en proceso -->
            <RowActionButton
              v-if="item.status === 'counting' && can('inventory_counts.close')"
              icon="tabler-ban"
              color="error"
              tooltip="Anular toma"
              @click="handleCancelCount(item)"
            />
          </div>
        </template>
      </ServerDataTable>
    </VCard>

    <!-- Modal Iniciar Nueva Toma Física -->
    <VDialog
      v-model="isCreateDialogOpen"
      max-width="540"
      persistent
    >
      <VCard>
        <VCardTitle class="pt-4 px-6 d-flex align-center gap-2">
          <VIcon
            icon="tabler-clipboard-check"
            color="primary"
          />
          <span>Iniciar toma de inventario</span>
        </VCardTitle>

        <VCardText class="px-6 pt-3 pb-2">
          <p class="text-body-2 text-medium-emphasis mb-4">
            Al iniciar el conteo se tomará una instantánea del stock del sistema de los artículos seleccionados.
          </p>

          <VRow>
            <!-- Almacén -->
            <VCol cols="12">
              <VSelect
                v-model="createForm.warehouse_id"
                :items="warehouses"
                item-title="name"
                item-value="id"
                label="Almacén *"
                placeholder="Seleccionar almacén a inventariar"
                :error-messages="createErrors.warehouse_id"
              />
            </VCol>

            <!-- Categoría (opcional) -->
            <VCol cols="12">
              <VSelect
                v-model="createForm.category_id"
                :items="categories"
                item-title="name"
                item-value="id"
                label="Categoría (Opcional)"
                placeholder="Dejar en blanco para contar todo el almacén"
                clearable
                :error-messages="createErrors.category_id"
              />
              <div class="text-caption text-medium-emphasis mt-1 ms-1">
                Puedes limitar la toma a una categoría específica o contar todos los artículos.
              </div>
            </VCol>

            <!-- Fecha del conteo -->
            <VCol cols="12">
              <VTextField
                v-model="createForm.count_date"
                type="date"
                label="Fecha de la toma *"
                placeholder="YYYY-MM-DD"
                :error-messages="createErrors.count_date"
              />
            </VCol>

            <!-- Observaciones -->
            <VCol cols="12">
              <VTextarea
                v-model="createForm.notes"
                label="Observaciones"
                placeholder="Ej. Inventario físico mensual o regularización..."
                rows="2"
                auto-grow
                :error-messages="createErrors.notes"
              />
            </VCol>
          </VRow>
        </VCardText>

        <VCardActions class="px-6 pb-4 pt-2 justify-end gap-2">
          <VBtn
            variant="tonal"
            color="secondary"
            :disabled="isSubmitting"
            @click="isCreateDialogOpen = false"
          >
            Cancelar
          </VBtn>

          <VBtn
            color="primary"
            variant="elevated"
            :loading="isSubmitting"
            @click="handleCreateSubmit"
          >
            <VIcon
              icon="tabler-check"
              class="me-1"
            />
            Iniciar conteo
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
