<script setup>
import { ref } from 'vue'
import PageHeader from '@/components/common/PageHeader.vue'
import { useNotify } from '@/composables/useNotify'
import SupplierInvoiceForm from '@/modules/purchases/components/invoices/SupplierInvoiceForm.vue'
import { supplierInvoicesApi } from '@/modules/purchases/services/supplierInvoicesApi'

definePage({
  meta: {
    action: 'supplier_invoices.create',
    title: 'Nueva factura de proveedor',
    icon: 'tabler-receipt-tax',
    description: 'Registra la factura de un proveedor por compras o un gasto directo, con sus cuotas y pago opcional.',
  },
})

const router = useRouter()
const notify = useNotify()

const formRef = ref(null)
const isSubmitting = ref(false)

const handleSubmit = async formData => {
  isSubmitting.value = true
  formRef.value?.clearErrors()
  try {
    const res = await supplierInvoicesApi.create(formData)

    notify.success('Factura de proveedor registrada correctamente')
    router.push({
      name: 'purchases-supplier-invoices-id',
      params: { id: res?.data?.id },
      state: { warnings: res?.meta?.warnings || [] },
    })
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
        <PageHeader />
      </VCardText>
    </VCard>

    <VCard>
      <VCardText>
        <SupplierInvoiceForm
          ref="formRef"
          :is-submitting="isSubmitting"
          @submit="handleSubmit"
          @cancel="router.push({ name: 'purchases-supplier-invoices' })"
        />
      </VCardText>
    </VCard>
  </div>
</template>
