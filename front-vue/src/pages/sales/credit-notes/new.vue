<script setup>
/* eslint-disable camelcase */
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import PageHeader from '@/components/common/PageHeader.vue'
import { useNotify } from '@/composables/useNotify'
import CreditNoteForm from '@/modules/sales/components/credit-notes/CreditNoteForm.vue'
import { creditNotesApi } from '@/modules/sales/services/creditNotesApi'

definePage({
  meta: {
    action: 'credit_notes.create',
    title: 'Nueva Nota de Crédito',
    icon: 'tabler-receipt-refund',
    description: 'Emisión de nota de crédito electrónica sobre comprobantes de venta emitidos.',
  },
})

const route = useRoute()
const router = useRouter()
const notify = useNotify()

const saleId = ref(route.query.sale_id || null)
const prefillData = ref(null)
const isLoading = ref(true)

const loadPrefill = async () => {
  if (!saleId.value) {
    isLoading.value = false

    return
  }

  isLoading.value = true
  try {
    const res = await creditNotesApi.prefill(saleId.value)

    prefillData.value = res?.data || null
  }
  catch (err) {
    notify.error(err?.data?.message || err?.message || 'Error al cargar datos del comprobante')
  }
  finally {
    isLoading.value = false
  }
}

onMounted(() => {
  loadPrefill()
})
</script>

<template>
  <div class="d-flex flex-column gap-6">
    <VCard>
      <VCardText class="py-4">
        <PageHeader>
          <template #actions>
            <VBtn
              variant="tonal"
              color="secondary"
              prepend-icon="tabler-arrow-left"
              @click="router.push('/sales/credit-notes')"
            >
              Notas de Crédito
            </VBtn>
          </template>
        </PageHeader>
      </VCardText>
    </VCard>

    <div
      v-if="isLoading"
      class="text-center py-12"
    >
      <VProgressCircular
        indeterminate
        color="primary"
        size="48"
      />
    </div>

    <!-- Si no se especificó sale_id -->
    <VCard v-else-if="!saleId">
      <VCardText class="text-center py-12">
        <VIcon
          icon="tabler-alert-circle"
          size="48"
          color="warning"
          class="mb-3"
        />
        <div class="text-h6 font-weight-medium">
          Comprobante no especificado
        </div>
        <div class="text-body-2 text-medium-emphasis mb-6">
          Para emitir una nota de crédito, primero seleccione la venta en el historial de ventas.
        </div>
        <VBtn
          color="primary"
          prepend-icon="tabler-file-text"
          @click="router.push('/sales/history')"
        >
          Ir al Historial de Ventas
        </VBtn>
      </VCardText>
    </VCard>

    <!-- Formulario cargado -->
    <CreditNoteForm
      v-else-if="prefillData"
      :prefill-data="prefillData"
    />
  </div>
</template>
