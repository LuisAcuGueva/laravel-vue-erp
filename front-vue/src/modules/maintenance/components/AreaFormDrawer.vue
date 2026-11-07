<script setup>
import DrawerHeader from '@/components/common/DrawerHeader.vue'
import { computed, ref, watch } from 'vue'
import { useApiFormErrors } from '@/composables/useApiFormErrors'

const props = defineProps({
  isDrawerOpen: {
    type: Boolean,
    required: true,
  },
  areaData: {
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

const isEditing = computed(() => Boolean(props.areaData?.id))

const defaultForm = {
  name: '',
  description: '',
  'is_active': true,
}

const form = ref({ ...defaultForm })

watch(() => props.areaData, val => {
  if (val) {
    form.value = {
      name: val.name || '',
      description: val.description || '',
      'is_active': val.is_active !== undefined ? Boolean(val.is_active) : true,
    }
  }
  else {
    form.value = { ...defaultForm }
  }
  clearErrors()
}, { immediate: true })

const handleClose = () => {
  emit('update:isDrawerOpen', false)
  form.value = { ...defaultForm }
  clearErrors()
}

const handleSubmit = () => {
  clearErrors()
  if (!form.value.name || !form.value.name.trim()) {
    errors.value.name = 'El nombre del área es obligatorio.'

    return
  }

  emit('submit', {
    ...form.value,
    name: form.value.name.trim(),
    description: form.value.description?.trim() || null,
    id: props.areaData?.id,
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
    :width="400"
    :model-value="props.isDrawerOpen"
    class="scrollable-content"
    @update:model-value="handleClose"
  >
    <!-- Header -->
    <DrawerHeader
      :title="isEditing ? 'Editar Área' : 'Nueva Área'"
      icon="tabler-sitemap"
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
            <!-- Nombre -->
            <VCol cols="12">
              <VTextField
                v-model="form.name"
                label="Nombre *"
                placeholder="Ventas"
                maxlength="100"
                :error-messages="errors.name"
                @input="clearFieldError('name')"
              />
            </VCol>

            <!-- Descripción -->
            <VCol cols="12">
              <VTextarea
                v-model="form.description"
                label="Descripción"
                placeholder="Equipo comercial y atención al cliente"
                rows="3"
                maxlength="500"
                :error-messages="errors.description"
                @input="clearFieldError('description')"
              />
            </VCol>

            <!-- Estado -->
            <VCol cols="12">
              <VSwitch
                v-model="form.is_active"
                label="Activo"
                color="success"
                :error-messages="errors.is_active"
                @update:model-value="clearFieldError('is_active')"
              />
            </VCol>

            <!-- Acciones -->
            <VCol
              cols="12"
              class="d-flex gap-4 mt-2"
            >
              <VBtn
                type="submit"
                :loading="props.isSubmitting"
                :disabled="props.isSubmitting"
              >
                {{ isEditing ? 'Guardar Cambios' : 'Crear Área' }}
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
