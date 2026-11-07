<script setup>
import DrawerHeader from '@/components/common/DrawerHeader.vue'
import { computed, ref, watch } from 'vue'
import { useApiFormErrors } from '@/composables/useApiFormErrors'

const props = defineProps({
  isDrawerOpen: {
    type: Boolean,
    required: true,
  },
  bankData: {
    type: Object,
    default: null,
  },
  isSubmitting: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:isDrawerOpen', 'submit'])

const { errors, generalError, setErrors, clearErrors, clearFieldError } = useApiFormErrors()

const isEditing = computed(() => Boolean(props.bankData?.id))

const defaultForm = {
  name: '',
  'short_name': '',
  'is_active': true,
}

const form = ref({ ...defaultForm })

watch(() => [props.isDrawerOpen, props.bankData], () => {
  const val = props.bankData

  form.value = val
    ? {
      name: val.name || '',
      'short_name': val.short_name || '',
      'is_active': val.is_active !== undefined ? Boolean(val.is_active) : true,
    }
    : { ...defaultForm }
  clearErrors()
}, { immediate: true })

const handleClose = () => {
  emit('update:isDrawerOpen', false)
  clearErrors()
}

const handleSubmit = () => {
  clearErrors()
  emit('submit', {
    id: props.bankData?.id,
    name: form.value.name.trim(),
    'short_name': form.value.short_name.trim(),
    'is_active': Boolean(form.value.is_active),
  })
}

defineExpose({
  setErrors,
  clearErrors,
})
</script>

<template>
  <VNavigationDrawer
    temporary
    location="end"
    :width="420"
    :model-value="props.isDrawerOpen"
    class="scrollable-content"
    @update:model-value="handleClose"
  >
    <DrawerHeader
      :title="isEditing ? 'Editar Banco' : 'Nuevo Banco'"
      icon="tabler-building-bank"
      @close="handleClose"
    />

    <VCard flat>
      <VCardText>
        <VAlert
          v-if="generalError"
          type="error"
          variant="tonal"
          class="mb-4"
        >
          {{ generalError }}
        </VAlert>

        <VForm @submit.prevent="handleSubmit">
          <VRow>
            <VCol cols="12">
              <VTextField
                v-model="form.name"
                label="Razón Social / Nombre *"
                placeholder="Banco de Crédito del Perú"
                maxlength="120"
                :error-messages="errors.name"
                @input="clearFieldError('name')"
              />
            </VCol>

            <VCol cols="12">
              <VTextField
                v-model="form.short_name"
                label="Nombre Corto / Abreviatura *"
                placeholder="BCP"
                maxlength="20"
                :error-messages="errors.short_name"
                @input="clearFieldError('short_name')"
              />
            </VCol>

            <VCol cols="12">
              <VSwitch
                v-model="form.is_active"
                label="Activo"
                color="success"
                :error-messages="errors.is_active"
                @update:model-value="clearFieldError('is_active')"
              />
            </VCol>

            <VCol
              cols="12"
              class="d-flex gap-4 mt-2"
            >
              <VBtn
                type="submit"
                :loading="props.isSubmitting"
                :disabled="props.isSubmitting"
              >
                {{ isEditing ? 'Guardar Cambios' : 'Crear Banco' }}
              </VBtn>
              <VBtn
                variant="tonal"
                color="secondary"
                :disabled="props.isSubmitting"
                @click="handleClose"
              >
                Cancelar
              </VBtn>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
    </VCard>
  </VNavigationDrawer>
</template>
