<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, ref, watch } from 'vue'
import CatalogItemPicker from '@/components/common/CatalogItemPicker.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import { useApiFormErrors } from '@/composables/useApiFormErrors'
import { warehousesApi } from '@/modules/inventory/services/inventorySettingsApi'
import { areasApi } from '@/modules/maintenance/services/areasApi'
import { PRIORITY_OPTIONS } from '@/modules/purchases/utils/status'

/**
 * Formulario de un requerimiento de compra (alta y edición del borrador).
 * Emite `submit` con el payload y la acción (`save` | `send`); la página guarda y navega.
 */
const props = defineProps({
  request: {
    type: Object,
    default: null,
  },
  isSubmitting: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['submit', 'cancel'])

const { errors, generalError, setErrors, clearErrors, clearFieldError } = useApiFormErrors()

const isEditing = computed(() => Boolean(props.request?.id))

const areaOptions = ref([])
const warehouseOptions = ref([])

let keySeed = 0

const newLine = (free = false) => ({
  key: `rq-line-${keySeed++}`,
  item_presentation_id: null,
  initialItem: null,
  description: free ? '' : null,
  is_free: free,
  quantity: '1',
  notes: '',
})

const today = () => new Date().toISOString().slice(0, 10)

const form = ref({
  area_id: null,
  warehouse_id: null,
  required_date: today(),
  priority: 'normal',
  notes: '',
  items: [newLine()],
})

const loadCatalogs = async () => {
  try {
    const [areasRes, warehousesRes] = await Promise.all([areasApi.options(), warehousesApi.options()])

    areaOptions.value = (areasRes?.data || []).map(a => ({ title: a.name, value: a.id }))
    warehouseOptions.value = (warehousesRes?.data || []).map(w => ({ title: `${w.code} - ${w.name}`, value: w.id }))

    if (!form.value.warehouse_id && warehouseOptions.value.length === 1)
      form.value.warehouse_id = warehouseOptions.value[0].value
  }
  catch {
    areaOptions.value = []
    warehouseOptions.value = []
  }
}

onMounted(loadCatalogs)

watch(() => props.request, request => {
  if (!request)
    return

  form.value = {
    area_id: request.area_id,
    warehouse_id: request.warehouse_id,
    required_date: request.required_date,
    priority: request.priority,
    notes: request.notes || '',
    items: (request.items || []).map(item => ({
      key: `rq-line-${keySeed++}`,
      item_presentation_id: item.item_presentation_id,
      initialItem: item.presentation
        ? {
          presentation_id: item.item_presentation_id,
          code: item.catalog_item?.code || '',
          name: item.catalog_item?.name || '',
          presentation_name: item.presentation.name,
        }
        : null,
      description: item.item_presentation_id ? null : item.description,
      is_free: !item.item_presentation_id,
      quantity: item.quantity,
      notes: item.notes || '',
    })),
  }
}, { immediate: true })

const isFree = row => row.is_free

const addLine = (free = false) => form.value.items.push(newLine(free))
const removeLine = index => form.value.items.splice(index, 1)

const handleSubmit = action => {
  clearErrors()

  emit('submit', {
    action,
    payload: {
      area_id: form.value.area_id,
      warehouse_id: form.value.warehouse_id,
      required_date: form.value.required_date,
      priority: form.value.priority,
      notes: form.value.notes?.trim() || null,
      items: form.value.items.map(row => ({
        item_presentation_id: isFree(row) ? null : row.item_presentation_id,
        description: isFree(row) ? row.description?.trim() : null,
        quantity: row.quantity,
        notes: row.notes?.trim() || null,
      })),
    },
  })
}

defineExpose({ setErrors, clearErrors })
</script>

<template>
  <VForm @submit.prevent="handleSubmit('save')">
    <VAlert
      v-if="generalError"
      type="error"
      density="compact"
      class="mb-4"
    >
      {{ generalError }}
    </VAlert>

    <VRow>
      <VCol
        cols="12"
        md="3"
      >
        <VAutocomplete
          v-model="form.area_id"
          :items="areaOptions"
          label="Área solicitante *"
          placeholder="Mantenimiento"
          :error-messages="errors.area_id"
          @update:model-value="clearFieldError('area_id')"
        />
      </VCol>

      <VCol
        cols="12"
        md="3"
      >
        <VAutocomplete
          v-model="form.warehouse_id"
          :items="warehouseOptions"
          label="Almacén de destino *"
          placeholder="ALM-01 - Almacén Central"
          :error-messages="errors.warehouse_id"
          @update:model-value="clearFieldError('warehouse_id')"
        />
      </VCol>

      <VCol
        cols="12"
        md="3"
      >
        <VTextField
          v-model="form.required_date"
          label="Fecha requerida *"
          type="date"
          :min="today()"
          :error-messages="errors.required_date"
          @input="clearFieldError('required_date')"
        />
      </VCol>

      <VCol
        cols="12"
        md="3"
      >
        <VSelect
          v-model="form.priority"
          :items="PRIORITY_OPTIONS"
          label="Prioridad *"
          placeholder="Normal"
          :error-messages="errors.priority"
          @update:model-value="clearFieldError('priority')"
        />
      </VCol>

      <VCol cols="12">
        <VTextarea
          v-model="form.notes"
          label="Notas"
          placeholder="Para el mantenimiento programado de la obra norte"
          rows="2"
          maxlength="1000"
          :error-messages="errors.notes"
          @input="clearFieldError('notes')"
        />
      </VCol>
    </VRow>

    <div class="text-subtitle-1 font-weight-medium mt-4 mb-2">
      Ítems solicitados
    </div>

    <VAlert
      v-if="errors.items"
      type="error"
      density="compact"
      class="mb-3"
    >
      {{ errors.items }}
    </VAlert>

    <VTable density="comfortable">
      <thead>
        <tr>
          <th style="width: 36px;">
            #
          </th>
          <th style="min-width: 280px;">
            ARTÍCULO / DESCRIPCIÓN
          </th>
          <th style="width: 130px;">
            CANTIDAD
          </th>
          <th style="min-width: 200px;">
            NOTAS
          </th>
          <th style="width: 60px;" />
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="(row, index) in form.items"
          :key="row.key"
        >
          <td>{{ index + 1 }}</td>
          <td>
            <VTextField
              v-if="isFree(row)"
              v-model="row.description"
              label="Descripción *"
              placeholder="Alquiler de andamio por una semana"
              density="compact"
              maxlength="255"
              :error-messages="errors[`items.${index}.description`]"
            />
            <CatalogItemPicker
              v-else
              v-model="row.item_presentation_id"
              context="purchase"
              :initial-item="row.initialItem"
              label="Artículo / Presentación *"
              density="compact"
              :clearable="false"
              :error-messages="errors[`items.${index}.item_presentation_id`]"
            />
          </td>
          <td>
            <VTextField
              v-model="row.quantity"
              label="Cantidad *"
              placeholder="10"
              type="number"
              min="0"
              step="0.0001"
              density="compact"
              :error-messages="errors[`items.${index}.quantity`]"
            />
          </td>
          <td>
            <VTextField
              v-model="row.notes"
              label="Notas"
              placeholder="Marca preferida"
              density="compact"
              maxlength="255"
            />
          </td>
          <td class="text-center">
            <RowActionButton
              action="delete"
              tooltip="Quitar ítem"
              :disabled="form.items.length === 1"
              @click="removeLine(index)"
            />
          </td>
        </tr>
      </tbody>
    </VTable>

    <div class="d-flex flex-wrap gap-3 mt-4">
      <VBtn
        variant="tonal"
        prepend-icon="tabler-plus"
        @click="addLine(false)"
      >
        Agregar artículo
      </VBtn>
      <VBtn
        variant="tonal"
        color="secondary"
        prepend-icon="tabler-text-plus"
        @click="addLine(true)"
      >
        Agregar ítem no catalogado
      </VBtn>
    </div>

    <VDivider class="my-6" />

    <div class="d-flex flex-wrap gap-3">
      <VBtn
        type="submit"
        color="primary"
        prepend-icon="tabler-device-floppy"
        :loading="props.isSubmitting"
      >
        {{ isEditing ? 'Guardar cambios' : 'Guardar borrador' }}
      </VBtn>
      <VBtn
        color="success"
        variant="tonal"
        prepend-icon="tabler-send"
        :disabled="props.isSubmitting"
        @click="handleSubmit('send')"
      >
        Guardar y enviar
      </VBtn>
      <VBtn
        variant="tonal"
        color="secondary"
        :disabled="props.isSubmitting"
        @click="emit('cancel')"
      >
        Cancelar
      </VBtn>
    </div>
  </VForm>
</template>
