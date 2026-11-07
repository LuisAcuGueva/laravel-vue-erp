<script setup>
/* eslint-disable camelcase */
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import { useAcl } from '@/composables/useAcl'
import { useConfirm } from '@/composables/useConfirm'
import { useNotify } from '@/composables/useNotify'
import PresentationFormDrawer from '@/modules/inventory/components/catalog/PresentationFormDrawer.vue'
import { catalogApi } from '@/modules/inventory/services/catalogApi'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  item: {
    type: Object,
    required: true,
  },
  units: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['refresh'])

const { can } = useAcl()
const { confirmDelete, alert } = useConfirm()
const notify = useNotify()

const presentations = computed(() => props.item?.presentations || [])

// Estado de la matriz de precios
const priceMatrix = ref([])
const isSavingPrices = ref(false)

const initPriceMatrix = () => {
  priceMatrix.value = presentations.value.map(pres => {
    const retail = pres.prices?.find(p => p.price_type === 'retail')
    const wholesale = pres.prices?.find(p => p.price_type === 'wholesale')

    return {
      presentation_id: pres.id,
      name: pres.name,
      factor: pres.factor,
      barcode: pres.barcode,
      unit_symbol: pres.unit?.symbol || '',
      retail_price: retail?.price ?? '',
      wholesale_price: wholesale?.price ?? '',
      wholesale_min_quantity: wholesale?.min_quantity ?? 3,
    }
  })
}

watch(() => props.item, initPriceMatrix, { immediate: true })

// Guardar matriz de precios
const savePrices = async () => {
  isSavingPrices.value = true
  try {
    const payloadPrices = []

    for (const row of priceMatrix.value) {
      if (row.retail_price !== '' && row.retail_price !== null && !isNaN(Number(row.retail_price))) {
        payloadPrices.push({
          presentation_id: row.presentation_id,
          price_type: 'retail',
          price: Number(row.retail_price),
          min_quantity: 1,
        })
      }

      if (row.wholesale_price !== '' && row.wholesale_price !== null && !isNaN(Number(row.wholesale_price))) {
        payloadPrices.push({
          presentation_id: row.presentation_id,
          price_type: 'wholesale',
          price: Number(row.wholesale_price),
          min_quantity: Number(row.wholesale_min_quantity || 1),
        })
      }
    }

    await catalogApi.syncPrices(props.item.id, payloadPrices)
    notify.success('Matriz de precios actualizada correctamente')
    emit('refresh')
  }
  catch (err) {
    const message = err?.data?.message || err?.message || 'Error al guardar precios'

    await alert({ message })
  }
  finally {
    isSavingPrices.value = false
  }
}

// Drawer de presentación
const presentationDrawerRef = ref(null)
const isPresentationDrawerOpen = ref(false)
const isSubmittingPresentation = ref(false)
const selectedPresentation = ref(null)

const openCreatePresentationDrawer = () => {
  selectedPresentation.value = null
  isPresentationDrawerOpen.value = true
}

const openEditPresentationDrawer = pres => {
  selectedPresentation.value = { ...pres }
  isPresentationDrawerOpen.value = true
}

const submitPresentationForm = async ({ id, ...payload }) => {
  isSubmittingPresentation.value = true
  presentationDrawerRef.value?.clearErrors()
  try {
    if (id) {
      await catalogApi.updatePresentation(props.item.id, id, payload)
      notify.success('Presentación actualizada correctamente')
    }
    else {
      await catalogApi.createPresentation(props.item.id, payload)
      notify.success('Presentación creada correctamente')
    }
    isPresentationDrawerOpen.value = false
    emit('refresh')
  }
  catch (err) {
    presentationDrawerRef.value?.setErrors(err)
  }
  finally {
    isSubmittingPresentation.value = false
  }
}

const deletePresentation = async pres => {
  const confirmed = await confirmDelete({
    entity: 'la presentación',
    name: pres.name,
  })

  if (!confirmed) return

  try {
    await catalogApi.deletePresentation(props.item.id, pres.id)
    notify.success('Presentación eliminada correctamente')
    emit('refresh')
  }
  catch (err) {
    const message = err?.data?.message || err?.message || 'No se pudo eliminar la presentación'

    await alert({ message })
  }
}
</script>

<template>
  <div class="presentations-prices-tab">
    <!-- SECCIÓN 1: PRESENTACIONES -->
    <VCard
      variant="outlined"
      class="mb-6"
    >
      <VCardItem>
        <template #title>
          <div class="d-flex align-center justify-space-between w-100 flex-wrap gap-2">
            <div class="d-flex align-center gap-2">
              <VIcon
                icon="tabler-box-seam"
                size="24"
                color="primary"
              />
              <span class="text-h6 font-weight-bold">Presentaciones del Artículo</span>
            </div>
            <VBtn
              v-if="can('catalog.update')"
              color="primary"
              size="small"
              prepend-icon="tabler-plus"
              @click="openCreatePresentationDrawer"
            >
              Nueva Presentación
            </VBtn>
          </div>
        </template>
        <template #subtitle>
          Empaques, cajas, bolsas o formatos en los que se compra o comercializa este bien o servicio.
        </template>
      </VCardItem>

      <VCardText class="pa-0">
        <VTable
          hover
          class="text-no-wrap"
        >
          <thead>
            <tr>
              <th class="text-left font-weight-bold">
                NOMBRE
              </th>
              <th class="text-left font-weight-bold">
                UNIDAD
              </th>
              <th class="text-left font-weight-bold">
                FACTOR BASE
              </th>
              <th class="text-left font-weight-bold">
                CÓDIGO DE BARRAS
              </th>
              <th class="text-center font-weight-bold">
                PREDETERMINADA
              </th>
              <th class="text-center font-weight-bold">
                ESTADO
              </th>
              <th class="text-center font-weight-bold">
                ACCIONES
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="pres in presentations"
              :key="pres.id"
            >
              <td>
                <span class="font-weight-medium">{{ pres.name }}</span>
                <VChip
                  v-if="pres.is_base"
                  size="x-small"
                  color="info"
                  variant="tonal"
                  class="ms-2"
                >
                  Unidad Base
                </VChip>
              </td>
              <td>{{ pres.unit?.name }} ({{ pres.unit?.symbol }})</td>
              <td>
                <span class="font-weight-medium">{{ Number(pres.factor) }}</span>
                <span class="text-disabled ms-1">{{ props.item.base_unit?.symbol }}</span>
              </td>
              <td>
                <span
                  v-if="pres.barcode"
                  class="font-monospace"
                >{{ pres.barcode }}</span>
                <span
                  v-else
                  class="text-disabled"
                >—</span>
              </td>
              <td class="text-center">
                <VIcon
                  v-if="pres.is_default"
                  icon="tabler-star-filled"
                  color="warning"
                  size="20"
                />
                <span
                  v-else
                  class="text-disabled"
                >—</span>
              </td>
              <td class="text-center">
                <VChip
                  size="small"
                  :color="pres.is_active ? 'success' : 'secondary'"
                  variant="tonal"
                >
                  {{ pres.is_active ? 'Activa' : 'Inactiva' }}
                </VChip>
              </td>
              <td class="text-center">
                <div class="d-flex justify-center gap-2">
                  <RowActionButton
                    v-if="can('catalog.update')"
                    action="edit"
                    tooltip="Editar presentación"
                    @click="openEditPresentationDrawer(pres)"
                  />
                  <RowActionButton
                    v-if="can('catalog.update') && !pres.is_base && !pres.is_default"
                    action="delete"
                    tooltip="Eliminar presentación"
                    @click="deletePresentation(pres)"
                  />
                </div>
              </td>
            </tr>
          </tbody>
        </VTable>
      </VCardText>
    </VCard>

    <!-- SECCIÓN 2: MATRIZ DE PRECIOS -->
    <VCard variant="outlined">
      <VCardItem>
        <template #title>
          <div class="d-flex align-center justify-space-between w-100 flex-wrap gap-2">
            <div class="d-flex align-center gap-2">
              <VIcon
                icon="tabler-currency-dollar"
                size="24"
                color="primary"
              />
              <span class="text-h6 font-weight-bold">Matriz de Precios por Presentación</span>
            </div>
            <VBtn
              v-if="can('catalog.update_prices')"
              color="primary"
              size="small"
              prepend-icon="tabler-device-floppy"
              :loading="isSavingPrices"
              @click="savePrices"
            >
              Guardar Precios
            </VBtn>
          </div>
        </template>
        <template #subtitle>
          Fija los precios minoristas y mayoristas vigentes. Los valores vacíos eliminarán el precio respectivo.
        </template>
      </VCardItem>

      <VCardText class="pa-0">
        <VTable class="text-no-wrap">
          <thead>
            <tr>
              <th class="text-left font-weight-bold">
                PRESENTACIÓN
              </th>
              <th class="text-left font-weight-bold">
                PRECIO MINORISTA (S/)
              </th>
              <th class="text-left font-weight-bold">
                PRECIO MAYORISTA (S/)
              </th>
              <th class="text-left font-weight-bold">
                CANT. MÍN. MAYORISTA
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in priceMatrix"
              :key="row.presentation_id"
            >
              <td class="py-2">
                <span class="font-weight-medium">{{ row.name }}</span>
                <span class="text-caption text-disabled d-block">
                  Factor: {{ Number(row.factor) }} {{ props.item.base_unit?.symbol }}
                </span>
              </td>
              <td
                class="py-2"
                style="inline-size: 200px;"
              >
                <VTextField
                  v-model="row.retail_price"
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="0.00"
                  density="compact"
                  :disabled="!can('catalog.update_prices')"
                />
              </td>
              <td
                class="py-2"
                style="inline-size: 200px;"
              >
                <VTextField
                  v-model="row.wholesale_price"
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="0.00"
                  density="compact"
                  :disabled="!can('catalog.update_prices')"
                />
              </td>
              <td
                class="py-2"
                style="inline-size: 180px;"
              >
                <VTextField
                  v-model="row.wholesale_min_quantity"
                  type="number"
                  step="1"
                  min="1"
                  placeholder="3"
                  density="compact"
                  :disabled="!can('catalog.update_prices') || !row.wholesale_price"
                />
              </td>
            </tr>
          </tbody>
        </VTable>
      </VCardText>
    </VCard>

    <!-- DRAWER PARA CREAR/EDITAR PRESENTACIÓN -->
    <Teleport to="body">
      <PresentationFormDrawer
        ref="presentationDrawerRef"
        v-model:is-drawer-open="isPresentationDrawerOpen"
        :presentation-data="selectedPresentation"
        :units="props.units"
        :is-submitting="isSubmittingPresentation"
        @submit="submitPresentationForm"
      />
    </Teleport>
  </div>
</template>
