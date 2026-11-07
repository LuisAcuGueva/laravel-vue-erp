<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import CancelDocumentDialog from '@/components/common/CancelDocumentDialog.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import { useAcl } from '@/composables/useAcl'
import { useConfirm } from '@/composables/useConfirm'
import { useNotify } from '@/composables/useNotify'
import { formatDateTime12h } from '@/utils/dates'
import WarehouseNoteItemsTable from './WarehouseNoteItemsTable.vue'
import { warehousesApi, warehouseReasonsApi } from '@/modules/inventory/services/inventorySettingsApi'
import { warehouseNotesApi } from '@/modules/inventory/services/warehouseNotesApi'

const props = defineProps({
  initialNote: {
    type: Object,
    default: null,
  },
  isNew: {
    type: Boolean,
    default: false,
  },
})

const NOTE_TYPES = [
  { value: 'in', title: 'Ingreso', icon: 'tabler-arrow-down-left' },
  { value: 'out', title: 'Salida', icon: 'tabler-arrow-up-right' },
  { value: 'transfer', title: 'Traslado', icon: 'tabler-arrows-left-right' },
]



const router = useRouter()
const { can } = useAcl()
const { confirm, alert } = useConfirm()
const notify = useNotify()
const { goBack } = useBackNavigation({ name: 'inventory-warehouse-notes' })

const isSubmitting = ref(false)
const cancelDialogOpen = ref(false)
const isCancelling = ref(false)
const errors = ref({})

const warehouses = ref([])
const allReasons = ref([])

const form = ref({
  type: 'in',
  warehouse_id: null,
  target_warehouse_id: null,
  reason_id: null,
  document_date: new Date().toLocaleDateString('en-CA'),
  reference_document: '',
  observation: '',
  items: [],
})

const isReadOnly = computed(() => {
  if (props.isNew) return false
  
  return props.initialNote?.status !== 'draft'
})

const isDraft = computed(() => {
  return props.initialNote?.status === 'draft'
})

const isApproved = computed(() => {
  return props.initialNote?.status === 'approved'
})

const isCancelled = computed(() => {
  return props.initialNote?.status === 'cancelled'
})

// Inicializar si viene nota existente
watch(
  () => props.initialNote,
  note => {
    if (note) {
      form.value = {
        type: note.type,
        warehouse_id: note.warehouse?.id || note.warehouse_id,
        target_warehouse_id: note.target_warehouse?.id || note.target_warehouse_id,
        reason_id: note.reason?.id || note.reason_id,
        document_date: note.document_date ? note.document_date.substring(0, 10) : '',
        reference_document: note.reference_document || '',
        observation: note.observation || '',
        items: (note.items || []).map(it => ({
          catalog_item_id: it.catalog_item?.id || it.catalog_item_id,
          item_presentation_id: it.presentation?.id || it.item_presentation_id,
          code: it.catalog_item?.code || '',
          name: it.catalog_item?.name || '',
          presentation_name: it.presentation?.name || '',
          unit_symbol: it.presentation?.unit?.symbol || it.catalog_item?.base_unit?.symbol || 'UND',
          quantity: it.quantity,
          unit_cost: it.unit_cost,
          total_cost: it.total_cost,
          notes: it.notes || '',
        })),
      }
    }
  },
  { immediate: true },
)

// Filtrar almacenes destino excluyendo origen
const targetWarehouseOptions = computed(() => {
  return warehouses.value.filter(w => w.id !== form.value.warehouse_id)
})

// Motivos de sistema que el backend permite usar en una nota manual
// (`WarehouseNoteService::validateReasonForType()`): el resto (compra, venta,
// ajustes de toma de inventario, etc.) solo se asigna automáticamente.
const MANUALLY_SELECTABLE_SYSTEM_CODES = ['OPENING', 'TRANSFER']

// Filtrar motivos según tipo
const filteredReasons = computed(() => {
  // Se agrega el motivo de la nota existente para que el combo pueda mostrarlo
  // aunque ya no cumpla los filtros de abajo (p. ej. un ajuste de toma de inventario).
  const current = props.initialNote?.reason

  const pool = current && !allReasons.value.some(r => r.id === current.id)
    ? [...allReasons.value, current]
    : allReasons.value

  return pool.filter(r => {
    if (current && r.id === current.id)
      return true

    if (r.is_system && !MANUALLY_SELECTABLE_SYSTEM_CODES.includes(r.code))
      return false

    if (form.value.type === 'in') {
      return r.type === 'in' || r.type === 'adjustment'
    }
    if (form.value.type === 'out') {
      return r.type === 'out' || r.type === 'adjustment'
    }
    if (form.value.type === 'transfer') {
      return r.type === 'transfer'
    }

    return false
  })
})

const selectedReason = computed(() => {
  return filteredReasons.value.find(r => r.id === form.value.reason_id)
})

const canApproveDirectly = computed(() => {
  if (!selectedReason.value) return false
  if (!selectedReason.value.requires_approval) return true
  
  return can('warehouse_notes.approve')
})

const onTypeChange = newType => {
  form.value.type = newType
  form.value.reason_id = null
  if (newType !== 'transfer') {
    form.value.target_warehouse_id = null
  }
}

const buildPayload = (approve = false) => {
  const payload = {
    warehouse_id: form.value.warehouse_id,
    reason_id: form.value.reason_id,
    document_date: form.value.document_date,
    reference_document: form.value.reference_document || null,
    observation: form.value.observation || null,
    items: form.value.items.map(l => ({
      item_presentation_id: l.item_presentation_id,
      quantity: Number(l.quantity),
      unit_cost: form.value.type === 'in' ? Number(l.unit_cost || 0) : null,
      notes: l.notes || null,
    })),
  }

  if (props.isNew) {
    payload.type = form.value.type
    payload.approve = approve
    if (form.value.type === 'transfer') {
      payload.target_warehouse_id = form.value.target_warehouse_id
    }
  } else if (form.value.type === 'transfer') {
    payload.target_warehouse_id = form.value.target_warehouse_id
  }

  return payload
}

const handleSave = async (approve = false) => {
  errors.value = {}

  if (form.value.items.length === 0) {
    errors.value = { items: 'Debe agregar al menos un artículo a la nota.' }
    
    return
  }

  isSubmitting.value = true
  try {
    const payload = buildPayload(approve)
    if (props.isNew) {
      const res = await warehouseNotesApi.create(payload)
      if (res?.meta?.approval_pending) {
        notify.info('Nota registrada en borrador. Requiere aprobación posterior.')
      } else if (approve) {
        notify.success('Nota de almacén registrada y aprobada correctamente')
      } else {
        notify.success('Nota de almacén guardada como borrador')
      }
      const newId = res?.data?.id
      if (newId) {
        router.push({ name: 'inventory-warehouse-notes-id', params: { id: newId } })
      } else {
        router.push({ name: 'inventory-warehouse-notes' })
      }
    } else {
      await warehouseNotesApi.update(props.initialNote.id, payload)
      notify.success('Nota de almacén actualizada correctamente')
      if (approve) {
        await handleApproveExisting()
      } else {
        router.push({ name: 'inventory-warehouse-notes' })
      }
    }
  }
  catch (err) {
    if (err?.data?.errors) {
      errors.value = err.data.errors
    } else {
      const message = err?.data?.message || err?.message || 'Error al guardar la nota'

      alert({ title: 'No se pudo guardar', message })
    }
  }
  finally {
    isSubmitting.value = false
  }
}

const handleApproveExisting = async () => {
  if (!props.initialNote?.id) return

  const confirmed = await confirm({
    title: 'Aprobar nota de almacén',
    message: `¿Estás seguro de aprobar la nota ${props.initialNote.number}? Esta acción afectará el inventario inmediatamente.`,
    confirmText: 'Sí, aprobar',
    cancelText: 'Cancelar',
  })

  if (!confirmed) return

  isSubmitting.value = true
  try {
    await warehouseNotesApi.approve(props.initialNote.id)
    notify.success('Nota de almacén aprobada correctamente')
    router.push({ name: 'inventory-warehouse-notes' })
  }
  catch (err) {
    const message = err?.data?.message || err?.message || 'Error al aprobar la nota'

    alert({ title: 'No se pudo aprobar', message })
  }
  finally {
    isSubmitting.value = false
  }
}

const handleConfirmCancel = async reason => {
  if (!props.initialNote?.id) return

  isCancelling.value = true
  try {
    await warehouseNotesApi.cancel(props.initialNote.id, { reason })
    notify.success('Nota de almacén anulada correctamente')
    cancelDialogOpen.value = false
    router.push({ name: 'inventory-warehouse-notes' })
  }
  catch (err) {
    const message = err?.data?.message || err?.message || 'Error al anular la nota'

    alert({ title: 'No se pudo anular', message })
  }
  finally {
    isCancelling.value = false
  }
}

onMounted(async () => {
  try {
    const [warehousesRes, reasonsRes] = await Promise.all([
      warehousesApi.options(),
      warehouseReasonsApi.options({ include_system: true }),
    ])

    warehouses.value = warehousesRes?.data || []
    allReasons.value = reasonsRes?.data || []

    if (props.isNew && warehouses.value.length > 0 && !form.value.warehouse_id) {
      const defaultWh = warehouses.value.find(w => w.is_default) || warehouses.value[0]

      form.value.warehouse_id = defaultWh.id
    }
  }
  catch (err) {
    console.error('Error al cargar opciones', err)
  }
})
</script>

<template>
  <VCard class="warehouse-note-form">
    <VCardText>
      <PageHeader>
        <template #actions>
          <VBtn
            variant="tonal"
            color="secondary"
            prepend-icon="tabler-arrow-left"
            @click="goBack"
          >
            Volver
          </VBtn>

          <VBtn
            v-if="!isNew && !isCancelled && can('warehouse_notes.cancel')"
            color="error"
            variant="tonal"
            prepend-icon="tabler-ban"
            @click="cancelDialogOpen = true"
          >
            Anular
          </VBtn>

          <VBtn
            v-if="!isReadOnly"
            variant="tonal"
            color="primary"
            prepend-icon="tabler-device-floppy"
            :loading="isSubmitting"
            @click="handleSave(false)"
          >
            Guardar borrador
          </VBtn>

          <VBtn
            v-if="isNew"
            color="primary"
            prepend-icon="tabler-check"
            :loading="isSubmitting"
            :disabled="!canApproveDirectly"
            @click="handleSave(true)"
          >
            Guardar y aprobar
          </VBtn>

          <VBtn
            v-if="!isNew && isDraft && can('warehouse_notes.approve')"
            color="primary"
            prepend-icon="tabler-check"
            :loading="isSubmitting"
            @click="handleApproveExisting"
          >
            Aprobar
          </VBtn>
        </template>
      </PageHeader>
    </VCardText>

    <VDivider />

    <!-- Estado (nota existente) -->
    <template v-if="!isNew">
      <VCardText class="d-flex align-center justify-space-between flex-wrap gap-3">
        <div class="d-flex align-center gap-3">
          <span class="text-h6 font-weight-bold">{{ initialNote?.number }}</span>
          <VChip
            :color="isApproved ? 'success' : isDraft ? 'secondary' : 'error'"
            size="small"
            variant="tonal"
          >
            {{ initialNote?.status_label }}
          </VChip>
          <VChip
            size="small"
            variant="tonal"
            color="info"
          >
            {{ initialNote?.type_label }}
          </VChip>
        </div>

        <div class="d-flex flex-wrap align-center gap-x-6 gap-y-1 text-body-2 text-medium-emphasis">
          <span>
            Creado por: <span class="font-weight-medium text-high-emphasis">{{ initialNote?.created_by?.name || '—' }}</span>
            <span v-if="initialNote?.created_at"> · {{ formatDateTime12h(initialNote.created_at) }}</span>
          </span>
          <span v-if="initialNote?.approved_at">
            Aprobado por: <span class="font-weight-medium text-high-emphasis">{{ initialNote?.approved_by?.name || '—' }}</span>
            · {{ formatDateTime12h(initialNote.approved_at) }}
          </span>
          <span v-if="initialNote?.cancelled_at">
            Anulado por: <span class="font-weight-medium text-high-emphasis">{{ initialNote?.cancelled_by?.name || '—' }}</span>
            · {{ formatDateTime12h(initialNote.cancelled_at) }}
          </span>
        </div>
      </VCardText>

      <VDivider />
    </template>

    <!-- Datos de cabecera -->
    <VCardText>
      <h6 class="text-h6 mb-4">
        Datos de la nota
      </h6>

      <div
        v-if="isNew"
        class="mb-6"
      >
        <div class="text-body-2 text-medium-emphasis mb-2">
          Tipo de movimiento *
        </div>
        <div class="d-flex flex-wrap gap-3">
          <VBtn
            v-for="opt in NOTE_TYPES"
            :key="opt.value"
            :color="form.type === opt.value ? 'primary' : 'secondary'"
            :variant="form.type === opt.value ? 'tonal' : 'outlined'"
            :prepend-icon="opt.icon"
            :aria-pressed="form.type === opt.value"
            @click="onTypeChange(opt.value)"
          >
            {{ opt.title }}
          </VBtn>
        </div>
      </div>

      <VRow>
        <!-- Almacén origen -->
        <VCol
          cols="12"
          sm="6"
          md="4"
        >
          <VSelect
            v-model="form.warehouse_id"
            :items="warehouses"
            item-title="name"
            item-value="id"
            label="Almacén de origen *"
            placeholder="Ej. Almacén Central"
            :disabled="isReadOnly"
            :error-messages="errors.warehouse_id"
          />
        </VCol>

        <!-- Almacén destino (solo traslado) -->
        <VCol
          v-if="form.type === 'transfer'"
          cols="12"
          sm="6"
          md="4"
        >
          <VSelect
            v-model="form.target_warehouse_id"
            :items="targetWarehouseOptions"
            item-title="name"
            item-value="id"
            label="Almacén de destino *"
            placeholder="Ej. Almacén Sucursal"
            :disabled="isReadOnly"
            :error-messages="errors.target_warehouse_id"
          />
        </VCol>

        <!-- Motivo -->
        <VCol
          cols="12"
          sm="6"
          md="4"
        >
          <VSelect
            v-model="form.reason_id"
            :items="filteredReasons"
            item-title="name"
            item-value="id"
            label="Motivo *"
            placeholder="Compra local"
            :disabled="isReadOnly"
            :error-messages="errors.reason_id"
          />
        </VCol>

        <!-- Fecha -->
        <VCol
          cols="12"
          sm="6"
          md="4"
        >
          <VTextField
            v-model="form.document_date"
            type="date"
            label="Fecha del documento *"
            :disabled="isReadOnly"
            :error-messages="errors.document_date"
          />
        </VCol>

        <!-- Documento de referencia -->
        <VCol
          cols="12"
          sm="6"
          md="4"
        >
          <VTextField
            v-model="form.reference_document"
            label="Doc. referencia"
            placeholder="Ej. Guía T-001-00234 o Vale 45"
            :disabled="isReadOnly"
            :error-messages="errors.reference_document"
          />
        </VCol>

        <!-- Observación -->
        <VCol cols="12">
          <VTextarea
            v-model="form.observation"
            label="Observaciones"
            placeholder="Ej. Ingreso por regularización física o donación recibida"
            rows="2"
            auto-grow
            :disabled="isReadOnly"
            :error-messages="errors.observation"
          />
        </VCol>
      </VRow>
    </VCardText>

    <VDivider />

    <!-- Artículos -->
    <VCardText>
      <h6 class="text-h6 mb-4">
        Artículos del movimiento
      </h6>

      <WarehouseNoteItemsTable
        v-model="form.items"
        :note-type="form.type"
        :warehouse-id="form.warehouse_id"
        :readonly="isReadOnly"
        :errors="errors"
      />
    </VCardText>

    <!-- Diálogo de anulación -->
    <CancelDocumentDialog
      v-model="cancelDialogOpen"
      :document-number="initialNote?.number"
      title="Anular nota de almacén"
      :loading="isCancelling"
      @confirm="handleConfirmCancel"
    />
  </VCard>
</template>
