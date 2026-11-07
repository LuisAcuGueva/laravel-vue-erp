<script setup>
import DrawerHeader from '@/components/common/DrawerHeader.vue'
import { computed, defineEmits, defineProps, ref, watch } from 'vue'

const props = defineProps({
  isDrawerOpen: {
    type: Boolean,
    required: true,
  },
  roleData: {
    type: Object,
    default: null,
  },
  isSubmitting: {
    type: Boolean,
    default: false,
  },
  serverErrors: {
    type: Object,
    default: () => ({}),
  },
})

const emit = defineEmits(['update:isDrawerOpen', 'submit'])

const isEditing = computed(() => Boolean(props.roleData?.id))
const isProtected = computed(() => Boolean(props.roleData?.is_protected))

const defaultForm = {
  name: '',
  description: '',
}

const form = ref({ ...defaultForm })
const errorMessage = ref(null)

watch(() => props.roleData, val => {
  if (val) {
    form.value = {
      name: val.name || '',
      description: val.description || '',
    }
  }
  else {
    form.value = { ...defaultForm }
  }
  errorMessage.value = null
}, { immediate: true })

const handleClose = () => {
  emit('update:isDrawerOpen', false)
  form.value = { ...defaultForm }
  errorMessage.value = null
}

const handleSubmit = () => {
  errorMessage.value = null

  const trimmedName = form.value.name.trim().toLowerCase()
  const trimmedDesc = form.value.description ? form.value.description.trim() : null

  if (!trimmedName) {
    errorMessage.value = 'El nombre del rol es obligatorio.'

    return
  }

  const roleNameRegex = /^[a-z][a-z0-9_-]*$/
  if (!roleNameRegex.test(trimmedName)) {
    errorMessage.value = 'El nombre del rol debe comenzar con letra y contener solo minúsculas, números, guiones y guiones bajos (ej. supervisor, cajero_principal).'

    return
  }

  emit('submit', {
    id: props.roleData?.id,
    name: trimmedName,
    description: trimmedDesc,
  })
}
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
    <!-- 👉 Header -->
    <DrawerHeader
      :title="isEditing ? 'Editar Rol' : 'Nuevo Rol'"
      icon="tabler-shield"
      @close="handleClose"
    />

    <VCard flat>
      <VCardText>
        <VAlert
          v-if="errorMessage"
          type="error"
          variant="tonal"
          class="mb-4"
        >
          {{ errorMessage }}
        </VAlert>

        <VAlert
          v-if="isEditing && isProtected"
          type="warning"
          variant="tonal"
          class="mb-4"
        >
          El rol <strong>{{ form.name }}</strong> está protegido por el sistema.
        </VAlert>

        <VForm @submit.prevent="handleSubmit">
          <VRow>
            <!-- Nombre -->
            <VCol cols="12">
              <VTextField
                v-model="form.name"
                label="Nombre del rol *"
                placeholder="supervisor"
                prepend-inner-icon="tabler-shield"
                :disabled="isEditing && isProtected"
                :error-messages="props.serverErrors?.name"
                hint="Identificador en minúsculas (ej. supervisor, role_admin)"
                persistent-hint
                required
              />
            </VCol>

            <!-- Descripción -->
            <VCol cols="12">
              <VTextarea
                v-model="form.description"
                label="Descripción del rol"
                placeholder="Supervisa las ventas de la sede"
                rows="3"
                prepend-inner-icon="tabler-align-left"
                :error-messages="props.serverErrors?.description"
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
                {{ isEditing ? 'Guardar Cambios' : 'Crear Rol' }}
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
