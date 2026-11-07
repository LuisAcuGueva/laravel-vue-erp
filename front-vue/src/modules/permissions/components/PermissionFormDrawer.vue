<script setup>
import DrawerHeader from '@/components/common/DrawerHeader.vue'
import { computed, defineEmits, defineProps, ref, watch } from 'vue'

const props = defineProps({
  isDrawerOpen: {
    type: Boolean,
    required: true,
  },
  permissionData: {
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

const isEditing = computed(() => Boolean(props.permissionData?.id))

const defaultForm = {
  name: '',
  slug: '',
  module: '',
  description: '',
}

const form = ref({ ...defaultForm })
const errorMessage = ref(null)

const originalSlug = ref('')

watch(() => props.permissionData, val => {
  if (val) {
    form.value = {
      name: val.name || '',
      slug: val.slug || '',
      module: val.module || '',
      description: val.description || '',
    }
    originalSlug.value = val.slug || ''
  }
  else {
    form.value = { ...defaultForm }
    originalSlug.value = ''
  }
  errorMessage.value = null
}, { immediate: true })

const isSlugChanged = computed(() => {
  return props.isDrawerOpen
    && isEditing.value
    && Boolean(form.value.slug)
    && Boolean(originalSlug.value)
    && form.value.slug !== originalSlug.value
})

const handleClose = () => {
  emit('update:isDrawerOpen', false)
  form.value = { ...defaultForm }
  originalSlug.value = ''
  errorMessage.value = null
}

const handleSubmit = () => {
  errorMessage.value = null

  const trimmedName = form.value.name.trim()
  const trimmedSlug = form.value.slug.trim().toLowerCase()
  const trimmedModule = form.value.module ? form.value.module.trim().toLowerCase() : ''
  const trimmedDesc = form.value.description ? form.value.description.trim() : null

  if (!trimmedName) {
    errorMessage.value = 'El nombre del permiso es obligatorio.'

    return
  }

  if (!trimmedSlug) {
    errorMessage.value = 'El slug del permiso es obligatorio.'

    return
  }

  const slugRegex = /^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/
  if (!slugRegex.test(trimmedSlug)) {
    errorMessage.value = 'El slug debe tener el formato modulo.accion en minúsculas (ej. clients.view).'

    return
  }

  const prefix = trimmedSlug.split('.')[0]
  if (trimmedModule && trimmedModule !== prefix) {
    errorMessage.value = 'El módulo debe coincidir con el prefijo del slug.'

    return
  }

  emit('submit', {
    id: props.permissionData?.id,
    name: trimmedName,
    slug: trimmedSlug,
    module: trimmedModule || prefix,
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
      :title="isEditing ? 'Editar Permiso' : 'Nuevo Permiso'"
      icon="tabler-key"
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
          v-if="isSlugChanged"
          type="warning"
          variant="tonal"
          class="mb-4"
        >
          Atención: Cambiar el slug puede afectar la verificación de permisos en las vistas y rutas protegidas.
        </VAlert>

        <VForm @submit.prevent="handleSubmit">
          <VRow>
            <!-- Nombre -->
            <VCol cols="12">
              <VTextField
                v-model="form.name"
                label="Nombre del permiso *"
                placeholder="Ver clientes"
                prepend-inner-icon="tabler-key"
                :error-messages="props.serverErrors?.name"
                required
              />
            </VCol>

            <!-- Slug -->
            <VCol cols="12">
              <VTextField
                v-model="form.slug"
                label="Slug / identificador *"
                placeholder="clients.view"
                prepend-inner-icon="tabler-code"
                :error-messages="props.serverErrors?.slug"
                hint="Formato: modulo.accion en minúsculas"
                persistent-hint
                required
              />
            </VCol>

            <!-- Módulo -->
            <VCol cols="12">
              <VTextField
                v-model="form.module"
                label="Módulo"
                placeholder="clients"
                hint="Se deduce del slug si se omite"
                persistent-hint
                prepend-inner-icon="tabler-folder"
                :error-messages="props.serverErrors?.module"
              />
            </VCol>

            <!-- Descripción -->
            <VCol cols="12">
              <VTextarea
                v-model="form.description"
                label="Descripción"
                placeholder="Permite listar y ver el detalle de clientes"
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
                {{ isEditing ? 'Guardar Cambios' : 'Crear Permiso' }}
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
