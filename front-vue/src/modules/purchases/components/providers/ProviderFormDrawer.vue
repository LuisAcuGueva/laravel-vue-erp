<script setup>
import { computed, ref } from 'vue'
import DrawerHeader from '@/components/common/DrawerHeader.vue'
import ProviderGeneralForm from '@/modules/purchases/components/providers/ProviderGeneralForm.vue'

const props = defineProps({
  isDrawerOpen: {
    type: Boolean,
    required: true,
  },
  providerData: {
    type: Object,
    default: null,
  },
  isSubmitting: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:isDrawerOpen', 'submit'])

const formRef = ref(null)

const isEditing = computed(() => Boolean(props.providerData?.id))

const handleClose = () => {
  emit('update:isDrawerOpen', false)
}

const handleSubmit = payload => {
  emit('submit', { ...payload, id: props.providerData?.id })
}

defineExpose({
  setErrors: err => formRef.value?.setErrors(err),
  clearErrors: () => formRef.value?.clearErrors(),
})
</script>

<template>
  <VNavigationDrawer
    temporary
    location="end"
    :width="480"
    :model-value="props.isDrawerOpen"
    class="scrollable-content"
    @update:model-value="handleClose"
  >
    <DrawerHeader
      :title="isEditing ? 'Editar Proveedor' : 'Nuevo Proveedor'"
      icon="tabler-truck-delivery"
      @close="handleClose"
    />

    <VCardText class="pb-6">
      <ProviderGeneralForm
        ref="formRef"
        :provider="props.providerData"
        :is-submitting="props.isSubmitting"
        :submit-label="isEditing ? 'Guardar Cambios' : 'Crear Proveedor'"
        show-cancel
        @submit="handleSubmit"
        @cancel="handleClose"
      />
    </VCardText>
  </VNavigationDrawer>
</template>
