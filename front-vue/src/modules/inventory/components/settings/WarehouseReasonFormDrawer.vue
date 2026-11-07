<script setup>
import DrawerHeader from '@/components/common/DrawerHeader.vue'
import { computed, ref, watch } from 'vue'
import { useApiFormErrors } from '@/composables/useApiFormErrors'
import { WAREHOUSE_REASON_TYPE_OPTIONS } from '../../utils/options'

const props = defineProps({
  isDrawerOpen: {
    type: Boolean,
    required: true,
  },
  reasonData: {
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

const isEditing = computed(() => Boolean(props.reasonData?.id))

// Los motivos de sistema los usan compras, ventas y traslados por su código, y el seeder los resincroniza:
// solo se edita el nombre y el estado.
const isSystem = computed(() => Boolean(props.reasonData?.is_system))

const defaultForm = {
  code: '',
  name: '',
  type: 'out',
  'requires_approval': true,
  'is_active': true,
}

const form = ref({ ...defaultForm })

watch(() => [props.isDrawerOpen, props.reasonData], () => {
  const val = props.reasonData

  form.value = val
    ? {
      code: val.code || '',
      name: val.name || '',
      type: val.type || 'out',
      'requires_approval': Boolean(val.requires_approval),
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
    id: props.reasonData?.id,
    code: form.value.code.trim().toUpperCase(),
    name: form.value.name.trim(),
    type: form.value.type,
    'requires_approval': Boolean(form.value.requires_approval),
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
      :title="isEditing ? 'Editar Motivo' : 'Nuevo Motivo'"
      icon="tabler-arrows-left-right"
      @close="handleClose"
    />

    <VCard flat>
      <VCardText>
        <VAlert
          v-if="isSystem"
          type="info"
          variant="tonal"
          density="compact"
          icon="tabler-lock"
          class="mb-4"
        >
          Motivo de sistema: lo usan compras, ventas y traslados por su código. Solo se pueden cambiar el nombre y el estado.
        </VAlert>

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
                v-model="form.code"
                label="Código *"
                placeholder="SHRINKAGE"
                maxlength="30"
                :disabled="isSystem"
                :error-messages="errors.code"
                @input="clearFieldError('code')"
              />
            </VCol>

            <VCol cols="12">
              <VTextField
                v-model="form.name"
                label="Nombre *"
                placeholder="Merma"
                maxlength="120"
                :error-messages="errors.name"
                @input="clearFieldError('name')"
              />
            </VCol>

            <VCol cols="12">
              <VSelect
                v-model="form.type"
                label="Tipo *"
                placeholder="Salida"
                :items="WAREHOUSE_REASON_TYPE_OPTIONS"
                :disabled="isSystem"
                :error-messages="errors.type"
                @update:model-value="clearFieldError('type')"
              />
            </VCol>

            <VCol cols="12">
              <VSwitch
                v-model="form.requires_approval"
                label="Requiere aprobación"
                color="primary"
                :disabled="isSystem"
                :error-messages="errors.requires_approval"
                @update:model-value="clearFieldError('requires_approval')"
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
                {{ isEditing ? 'Guardar Cambios' : 'Crear Motivo' }}
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
