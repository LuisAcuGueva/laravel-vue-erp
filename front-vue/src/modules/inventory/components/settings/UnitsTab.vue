<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useAcl } from '@/composables/useAcl'
import { useConfirm } from '@/composables/useConfirm'
import { useNotify } from '@/composables/useNotify'
import { useServerTable } from '@/composables/useServerTable'
import ServerDataTable from '@/components/common/ServerDataTable.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import UnitFormDrawer from './UnitFormDrawer.vue'
import { unitsApi } from '../../services/inventorySettingsApi'
import { useInventorySettingsStore } from '../../stores/inventorySettingsStore'
import { SETTINGS_DRAWERS_TARGET } from '../../utils/options'
import { STATUS_OPTIONS } from '@/utils/options'

const { can } = useAcl()
const { confirmDelete, alert } = useConfirm()
const notify = useNotify()
const inventoryStore = useInventorySettingsStore()

const formDrawerRef = ref(null)
const isDrawerOpen = ref(false)
const isSubmitting = ref(false)
const selectedUnit = ref(null)
const deletingId = ref(null)
const categoryOptions = ref([])

const isConvertDialogOpen = ref(false)
const isConverting = ref(false)
const convertUnits = ref([])
const convertResult = ref(null)

const convertForm = ref({
  quantity: 1,
  'from_unit_id': null,
  'to_unit_id': null,
})

const table = useServerTable({
  fetch: params => unitsApi.list(params),
  filters: { 'unit_category_id': null, 'is_active': null },
})

const headers = [
  { title: 'NOMBRE', key: 'name', sortable: false, alwaysVisible: true },
  { title: 'SÍMBOLO', key: 'symbol', sortable: false },
  { title: 'CATEGORÍA', key: 'category', sortable: false },
  { title: 'BASE', key: 'is_base', sortable: false, align: 'center' },
  { title: 'FACTOR', key: 'conversion_factor', sortable: false, align: 'end' },
  { title: 'DECIMALES', key: 'allow_decimals', sortable: false, align: 'center' },
  { title: 'CÓD. SUNAT', key: 'sunat_code', sortable: false },
  { title: 'ESTADO', key: 'is_active', sortable: false, align: 'center' },
  { title: 'ACCIONES', key: 'actions', sortable: false, align: 'center' },
]

const loadCategories = async (force = false) => {
  try {
    const list = await inventoryStore.fetchUnitCategoryOptions(force)

    categoryOptions.value = list.map(c => ({
      value: c.id,
      title: c.name,
      'is_packaging': Boolean(c.is_packaging),
    }))
  }
  catch {
    categoryOptions.value = []
  }
}

const packagingCategoryIds = computed(() => new Set(
  categoryOptions.value.filter(c => c.is_packaging).map(c => c.value),
))

const openCreateDrawer = async () => {
  selectedUnit.value = null
  await loadCategories()
  isDrawerOpen.value = true
}

const openEditDrawer = async unit => {
  selectedUnit.value = { ...unit }
  await loadCategories()
  isDrawerOpen.value = true
}

const handleFormSubmit = async ({ id, ...payload }) => {
  isSubmitting.value = true
  formDrawerRef.value?.clearErrors()
  try {
    if (id) {
      await unitsApi.update(id, payload)
      notify.success('Unidad actualizada correctamente')
    }
    else {
      await unitsApi.create(payload)
      notify.success('Unidad creada correctamente')
    }
    isDrawerOpen.value = false
    inventoryStore.invalidateUnits()
    await table.reload()
  }
  catch (err) {
    // El drawer muestra los errores de campo y el mensaje general de la API.
    formDrawerRef.value?.setErrors(err)
  }
  finally {
    isSubmitting.value = false
  }
}

const removeUnit = async unit => {
  if (!await confirmDelete({ entity: 'unidad', name: `${unit.name} (${unit.symbol})` }))
    return

  deletingId.value = unit.id
  try {
    await unitsApi.delete(unit.id)
    notify.success('Unidad eliminada correctamente')
    inventoryStore.invalidateUnits()
    await table.reload()
  }
  catch (err) {
    // Reglas de negocio de la API (ej. unidad base con unidades derivadas).
    await alert({ message: err?.data?.message || 'No se pudo eliminar la unidad.' })
  }
  finally {
    deletingId.value = null
  }
}

// --- Calculadora de conversión: solo unidades convertibles y de la misma categoría ---

const fromUnitOptions = computed(() => convertUnits.value.map(u => ({
  value: u.id,
  title: `${u.name} (${u.symbol})`,
})))

const toUnitOptions = computed(() => {
  const from = convertUnits.value.find(u => u.id === convertForm.value.from_unit_id)

  return convertUnits.value
    .filter(u => from && u.unit_category_id === from.unit_category_id && u.id !== from.id)
    .map(u => ({ value: u.id, title: `${u.name} (${u.symbol})` }))
})

watch(() => convertForm.value.from_unit_id, () => {
  convertResult.value = null
  if (!toUnitOptions.value.some(o => o.value === convertForm.value.to_unit_id))
    convertForm.value['to_unit_id'] = toUnitOptions.value[0]?.value ?? null
})

const openConvertDialog = async () => {
  try {
    await loadCategories()

    const list = await inventoryStore.fetchUnitOptions({}, true)

    convertUnits.value = list.filter(u => !packagingCategoryIds.value.has(u.unit_category_id))
    convertForm.value = { quantity: 1, 'from_unit_id': convertUnits.value[0]?.id ?? null, 'to_unit_id': null }
    convertForm.value['to_unit_id'] = toUnitOptions.value[0]?.value ?? null
    convertResult.value = null
    isConvertDialogOpen.value = true
  }
  catch {
    await alert({ title: 'Conversión no disponible', message: 'No se pudieron cargar las unidades para la conversión.' })
  }
}

const handleConvert = async () => {
  if (!convertForm.value.from_unit_id || !convertForm.value.to_unit_id)
    return

  isConverting.value = true
  convertResult.value = null
  try {
    const res = await unitsApi.convert({
      quantity: Number(convertForm.value.quantity),
      'from_unit_id': convertForm.value.from_unit_id,
      'to_unit_id': convertForm.value.to_unit_id,
    })

    convertResult.value = res?.data?.quantity ?? null
  }
  catch (err) {
    await alert({ title: 'No se pudo convertir', message: err?.data?.message || 'Error en la conversión.' })
  }
  finally {
    isConverting.value = false
  }
}

const unitSymbol = id => convertUnits.value.find(u => u.id === id)?.symbol ?? ''

onMounted(() => loadCategories())
</script>

<template>
  <ServerDataTable
    v-model:options="table.options"
    v-model:search="table.search"
    table-id="inventory-units"
    :headers="headers"
    :items="table.items"
    :total-items="table.total"
    :loading="table.loading"
    :has-filters="table.hasFilters"
    search-placeholder="Kilogramo o KG"
    create-label="Nueva unidad"
    :creatable="can('units.create')"
    :exportable="can('units.export')"
    :exporting="table.exporting"
    @refresh="table.reload"
    @create="openCreateDrawer"
    @export="table.export('/v1/inventory/units/export', 'unidades')"
  >
    <template #filters>
      <VAutocomplete
        v-model="table.filters.unit_category_id"
        :items="categoryOptions"
        label="Categoría"
        placeholder="Masa"
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

      <VBtn
        variant="tonal"
        color="secondary"
        prepend-icon="tabler-calculator"
        class="align-self-center"
        @click="openConvertDialog"
      >
        Probar conversión
      </VBtn>
    </template>

    <template #item.name="{ item }">
      <span class="font-weight-medium">{{ item.name }}</span>
    </template>

    <template #item.symbol="{ item }">
      <span class="font-monospace font-weight-bold">{{ item.symbol }}</span>
    </template>

    <template #item.category="{ item }">
      <span>{{ item.category?.name || '—' }}</span>
    </template>

    <template #item.is_base="{ item }">
      <VChip
        v-if="item.is_base"
        size="small"
        color="primary"
        variant="elevated"
      >
        Base
      </VChip>
      <VChip
        v-else-if="item.category?.is_packaging"
        size="small"
        color="info"
        variant="tonal"
      >
        Empaque
      </VChip>
      <span
        v-else
        class="text-disabled"
      >—</span>
    </template>

    <template #item.conversion_factor="{ item }">
      <span
        v-if="item.category?.is_packaging"
        class="text-disabled"
      >—</span>
      <span
        v-else
        class="font-monospace"
      >{{ item.conversion_factor }}</span>
    </template>

    <template #item.allow_decimals="{ item }">
      <VIcon
        :icon="item.allow_decimals ? 'tabler-check' : 'tabler-x'"
        :color="item.allow_decimals ? 'success' : 'secondary'"
        size="20"
      />
    </template>

    <template #item.sunat_code="{ item }">
      <span class="font-monospace text-caption">{{ item.sunat_code || '—' }}</span>
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
          v-if="can('units.update')"
          action="edit"
          tooltip="Editar unidad"
          @click="openEditDrawer(item)"
        />
        <RowActionButton
          v-if="can('units.delete')"
          action="delete"
          tooltip="Eliminar unidad"
          :loading="deletingId === item.id"
          @click="removeUnit(item)"
        />
      </div>
    </template>
  </ServerDataTable>

  <Teleport
    defer
    :to="SETTINGS_DRAWERS_TARGET"
  >
    <UnitFormDrawer
      ref="formDrawerRef"
      v-model:is-drawer-open="isDrawerOpen"
      :unit-data="selectedUnit"
      :category-options="categoryOptions"
      :is-submitting="isSubmitting"
      @submit="handleFormSubmit"
    />
  </Teleport>

  <VDialog
    v-model="isConvertDialogOpen"
    max-width="500"
  >
    <VCard>
      <VCardItem>
        <VCardTitle>Calculadora de conversión</VCardTitle>
        <VCardSubtitle>Solo entre unidades de la misma categoría</VCardSubtitle>
      </VCardItem>

      <VCardText>
        <VRow>
          <VCol cols="12">
            <VTextField
              v-model="convertForm.quantity"
              label="Cantidad *"
              placeholder="10"
              type="number"
              step="any"
            />
          </VCol>

          <VCol cols="12">
            <VAutocomplete
              v-model="convertForm.from_unit_id"
              label="De unidad *"
              placeholder="Kilogramo (KG)"
              :items="fromUnitOptions"
            />
          </VCol>

          <VCol cols="12">
            <VAutocomplete
              v-model="convertForm.to_unit_id"
              label="A unidad *"
              placeholder="Gramo (G)"
              :items="toUnitOptions"
              :disabled="!toUnitOptions.length"
              :hint="convertForm.from_unit_id && !toUnitOptions.length ? 'Esta categoría no tiene otras unidades.' : ''"
              persistent-hint
            />
          </VCol>

          <VCol
            v-if="convertResult !== null"
            cols="12"
          >
            <VAlert
              type="info"
              variant="tonal"
            >
              {{ convertForm.quantity }} {{ unitSymbol(convertForm.from_unit_id) }} =
              <strong>{{ convertResult }} {{ unitSymbol(convertForm.to_unit_id) }}</strong>
            </VAlert>
          </VCol>
        </VRow>
      </VCardText>

      <VCardActions>
        <VSpacer />
        <VBtn
          variant="tonal"
          color="secondary"
          @click="isConvertDialogOpen = false"
        >
          Cerrar
        </VBtn>
        <VBtn
          color="primary"
          :loading="isConverting"
          :disabled="!convertForm.to_unit_id"
          @click="handleConvert"
        >
          Calcular
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
