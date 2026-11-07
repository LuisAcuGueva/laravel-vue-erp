<script setup>
/* eslint-disable camelcase */
import { ref, watch } from 'vue'
import { useNotify } from '@/composables/useNotify'
import { purchaseRequestsApi } from '@/modules/purchases/services/purchaseRequestsApi'
import { formatDateDMY } from '@/utils/dates'

/**
 * Lista las líneas de requerimientos aprobados con saldo por ordenar (del almacén indicado)
 * y emite las elegidas para convertirlas en líneas de la OC.
 */
const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  warehouseId: {
    type: String,
    default: null,
  },

  // Ids de líneas de requerimiento que la OC ya incluye (no se vuelven a ofrecer).
  excludeIds: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update:modelValue', 'select'])

const notify = useNotify()

const items = ref([])
const selected = ref([])
const isLoading = ref(false)

const load = async () => {
  if (!props.warehouseId)
    return

  isLoading.value = true
  selected.value = []
  try {
    const res = await purchaseRequestsApi.pendingItems(props.warehouseId)

    items.value = (res?.data || []).filter(item => !props.excludeIds.includes(item.id))
  }
  catch {
    items.value = []
    notify.error('No se pudieron cargar las líneas pendientes.')
  }
  finally {
    isLoading.value = false
  }
}

watch(() => props.modelValue, open => {
  if (open)
    load()
})

const label = item => (item.catalog_item
  ? `${item.catalog_item.code} · ${item.catalog_item.name}${item.presentation ? ` (${item.presentation.name})` : ''}`
  : item.description)

const confirm = () => {
  emit('select', items.value.filter(item => selected.value.includes(item.id)))
  emit('update:modelValue', false)
}
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="860"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard>
      <VCardTitle class="pt-4">
        Agregar desde requerimientos
      </VCardTitle>
      <VCardSubtitle>Líneas aprobadas con saldo por ordenar</VCardSubtitle>

      <VCardText>
        <VProgressLinear
          v-if="isLoading"
          indeterminate
          class="mb-4"
        />

        <VAlert
          v-if="!props.warehouseId"
          type="info"
          variant="tonal"
          density="compact"
        >
          Selecciona primero el almacén de entrega.
        </VAlert>

        <VTable
          v-else
          density="comfortable"
        >
          <thead>
            <tr>
              <th style="width: 40px;" />
              <th>REQUERIMIENTO</th>
              <th>ARTÍCULO / DESCRIPCIÓN</th>
              <th class="text-end">
                PENDIENTE
              </th>
              <th>REQUERIDO PARA</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!items.length && !isLoading">
              <td
                colspan="5"
                class="text-center text-medium-emphasis py-6"
              >
                No hay líneas pendientes de ordenar para este almacén.
              </td>
            </tr>
            <tr
              v-for="item in items"
              :key="item.id"
            >
              <td>
                <VCheckbox
                  v-model="selected"
                  :value="item.id"
                  density="compact"
                  hide-details
                />
              </td>
              <td>
                <span class="font-monospace font-weight-medium">{{ item.request?.number }}</span>
                <div class="text-caption text-medium-emphasis">
                  {{ item.request?.area?.name }}
                </div>
              </td>
              <td>{{ label(item) }}</td>
              <td class="text-end">
                {{ Number(item.pending_quantity) }}
              </td>
              <td>
                {{ formatDateDMY(item.request?.required_date) }}
                <VChip
                  v-if="item.request?.priority === 'urgent'"
                  size="x-small"
                  color="error"
                  class="ms-1"
                >
                  Urgente
                </VChip>
              </td>
            </tr>
          </tbody>
        </VTable>
      </VCardText>

      <VCardActions class="px-6 pb-4">
        <VSpacer />
        <VBtn
          variant="tonal"
          color="secondary"
          @click="emit('update:modelValue', false)"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          :disabled="!selected.length"
          @click="confirm"
        >
          Agregar {{ selected.length || '' }} línea{{ selected.length === 1 ? '' : 's' }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
