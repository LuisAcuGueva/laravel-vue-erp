<script setup>
/* eslint-disable camelcase */
import { onMounted, ref } from 'vue'
import PageHeader from '@/components/common/PageHeader.vue'
import { useNotify } from '@/composables/useNotify'
import PurchaseOrderForm from '@/modules/purchases/components/orders/PurchaseOrderForm.vue'
import { purchaseOrdersApi } from '@/modules/purchases/services/purchaseOrdersApi'
import { purchaseRequestsApi } from '@/modules/purchases/services/purchaseRequestsApi'

definePage({
  meta: {
    action: 'purchase_orders.create',
    title: 'Nueva orden de compra',
    icon: 'tabler-receipt-2',
    description: 'Emite una orden de compra a un proveedor, con impuestos y condición de pago.',
  },
})

const route = useRoute()
const router = useRouter()
const notify = useNotify()

const formRef = ref(null)
const isSubmitting = ref(false)
const prefill = ref(null)

// Viene desde un requerimiento aprobado: se precargan sus líneas con saldo por ordenar.
onMounted(async () => {
  const requestId = route.query.request
  if (!requestId)
    return

  try {
    const request = (await purchaseRequestsApi.get(requestId))?.data
    const pending = (await purchaseRequestsApi.pendingItems(request.warehouse_id))?.data || []

    prefill.value = {
      warehouse_id: request.warehouse_id,
      lines: pending.filter(item => item.request?.id === request.id),
    }
  }
  catch {
    notify.error('No se pudo cargar el requerimiento de origen.')
  }
})

const handleSubmit = async ({ action, payload }) => {
  isSubmitting.value = true
  formRef.value?.clearErrors()
  try {
    const res = await purchaseOrdersApi.create(payload)
    const id = res?.data?.id

    if (action === 'send' && id) {
      const sent = await purchaseOrdersApi.submit(id)

      notify.success(sent?.data?.status === 'approved' ? 'Orden enviada y aprobada automáticamente' : 'Orden enviada a aprobación')
    }
    else {
      notify.success('Orden de compra creada correctamente')
    }

    router.push({ name: 'purchases-orders-id', params: { id } })
  }
  catch (err) {
    formRef.value?.setErrors(err)
  }
  finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <div>
    <VCard class="mb-6">
      <VCardText>
        <PageHeader>
          <template #actions>
            <VBtn
              variant="tonal"
              color="secondary"
              prepend-icon="tabler-arrow-left"
              @click="router.push({ name: 'purchases-orders' })"
            >
              Volver
            </VBtn>
          </template>
        </PageHeader>
      </VCardText>
    </VCard>

    <VCard>
      <VCardText>
        <PurchaseOrderForm
          ref="formRef"
          :prefill="prefill"
          :is-submitting="isSubmitting"
          @submit="handleSubmit"
          @cancel="router.push({ name: 'purchases-orders' })"
        />
      </VCardText>
    </VCard>
  </div>
</template>
