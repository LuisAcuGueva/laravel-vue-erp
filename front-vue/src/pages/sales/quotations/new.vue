<script setup>
/* eslint-disable camelcase */
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import PageHeader from '@/components/common/PageHeader.vue'
import { useNotify } from '@/composables/useNotify'
import QuotationForm from '@/modules/sales/components/quotations/QuotationForm.vue'
import { quotationsApi } from '@/modules/sales/services/quotationsApi'

definePage({
  meta: {
    action: 'quotations.create',
    title: 'Nueva Cotización',
    icon: 'tabler-file-dollar',
    description: 'Creación de cotizaciones para clientes con lista de precios y condiciones comerciales',
  },
})

const route = useRoute()
const router = useRouter()
const notify = useNotify()

const formRef = ref(null)
const isSubmitting = ref(false)

const prefill = {
  client_id: route.query.client_id ? String(route.query.client_id) : null,
}

const handleSubmit = async ({ action, payload }) => {
  isSubmitting.value = true
  try {
    const res = await quotationsApi.create(payload)
    const quote = res?.data

    notify.success(`Cotización ${quote?.number || ''} registrada correctamente`)

    if (action === 'send' && quote?.id) {
      await quotationsApi.send(quote.id)
      notify.success(`Cotización ${quote.number} marcada como enviada al cliente`)
    }

    if (quote?.id) {
      router.push({ name: 'sales-quotations-id', params: { id: quote.id } })
    }
    else {
      router.push({ name: 'sales-quotations' })
    }
  }
  catch (err) {
    if (formRef.value) {
      formRef.value.setErrors(err)
    }
    else {
      notify.error(err?.data?.message || err?.message || 'Error al guardar la cotización')
    }
  }
  finally {
    isSubmitting.value = false
  }
}

const handleCancel = () => {
  router.push({ name: 'sales-quotations' })
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
              @click="handleCancel"
            >
              Volver al listado
            </VBtn>
          </template>
        </PageHeader>
      </VCardText>
    </VCard>

    <QuotationForm
      ref="formRef"
      :prefill="prefill"
      :is-submitting="isSubmitting"
      @submit="handleSubmit"
      @cancel="handleCancel"
    />
  </div>
</template>
