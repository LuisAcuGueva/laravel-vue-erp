<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import PageHeader from '@/components/common/PageHeader.vue'
import WarehouseNoteForm from '@/modules/inventory/components/notes/WarehouseNoteForm.vue'
import WarehouseNoteKardexTable from '@/modules/inventory/components/notes/WarehouseNoteKardexTable.vue'
import { warehouseNotesApi } from '@/modules/inventory/services/warehouseNotesApi'

definePage({
  meta: {
    action: 'warehouse_notes.view',
    title: 'Detalle de nota de almacén',
    icon: 'tabler-file-invoice',
    description: 'Consulta y gestión de la nota de almacén y sus movimientos.',
  },
})

const route = useRoute()
const noteId = route.params.id

const note = ref(null)
const isLoading = ref(true)
const errorMessage = ref(null)

const kardexMovements = computed(() => {
  return note.value?.kardex_movements || []
})

const loadNote = async () => {
  isLoading.value = true
  errorMessage.value = null
  try {
    const res = await warehouseNotesApi.get(noteId)

    note.value = res?.data || null
  }
  catch (err) {
    console.error('Error al cargar nota de almacén', err)
    errorMessage.value = err?.data?.message || err?.message || 'No se pudo cargar la nota de almacén'
  }
  finally {
    isLoading.value = false
  }
}

onMounted(() => {
  loadNote()
})
</script>

<template>
  <div>
    <VCard v-if="isLoading || errorMessage">
      <VCardText>
        <PageHeader />
      </VCardText>

      <VDivider />

      <div
        v-if="isLoading"
        class="d-flex justify-center align-center py-12"
      >
        <VProgressCircular
          indeterminate
          color="primary"
          size="48"
        />
      </div>

      <VCardText v-else>
        <VAlert
          type="error"
          variant="tonal"
        >
          {{ errorMessage }}
        </VAlert>
      </VCardText>
    </VCard>

    <template v-else-if="note">
      <WarehouseNoteForm
        :initial-note="note"
        :is-new="false"
      />

      <!-- Movimientos de Kardex asociados -->
      <WarehouseNoteKardexTable
        class="mt-6"
        :movements="kardexMovements"
      />
    </template>
  </div>
</template>
