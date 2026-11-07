<script setup>
import { computed, ref } from 'vue'
import { useNotify } from '@/composables/useNotify'
import SupplierInvoiceForm from '@/modules/purchases/components/invoices/SupplierInvoiceForm.vue'
import { supplierInvoicesApi } from '@/modules/purchases/services/supplierInvoicesApi'

/**
 * Registro de un gasto directo desde la caja: factura de tipo gasto con "Pagar ahora"
 * activado, que usa la sesión abierta de la caja seleccionada.
 */
const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const notify = useNotify()
const formRef = ref(null)
const isSubmitting = ref(false)
const formKey = ref(0)

const isOpen = computed({
  get: () => props.modelValue,
  set: value => emit('update:modelValue', value),
})

const handleSubmit = async formData => {
  isSubmitting.value = true
  formRef.value?.clearErrors()
  try {
    const res = await supplierInvoicesApi.create(formData)

    notify.success('Gasto registrado correctamente')
    emit('saved', res?.data)
    isOpen.value = false
    formKey.value++
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
  <VDialog
    v-model="isOpen"
    max-width="1100"
    persistent
    scrollable
  >
    <VCard>
      <VCardItem>
        <VCardTitle>Registrar gasto</VCardTitle>
        <VCardSubtitle>El gasto se paga en el acto desde la caja abierta.</VCardSubtitle>
      </VCardItem>

      <VCardText>
        <SupplierInvoiceForm
          :key="formKey"
          ref="formRef"
          fixed-kind="expense"
          pay-now
          :is-submitting="isSubmitting"
          @submit="handleSubmit"
          @cancel="isOpen = false"
        />
      </VCardText>
    </VCard>
  </VDialog>
</template>
