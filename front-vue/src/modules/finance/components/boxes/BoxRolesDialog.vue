<script setup>
import { ref, watch } from 'vue'
import { useApiFormErrors } from '@/composables/useApiFormErrors'

const props = defineProps({
  isDialogOpen: {
    type: Boolean,
    required: true,
  },
  boxData: {
    type: Object,
    default: null,
  },
  rolesList: {
    type: Array,
    default: () => [],
  },
  isSubmitting: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:isDialogOpen', 'submit'])

const { errors, generalError, setErrors, clearErrors, clearFieldError } = useApiFormErrors()

const selectedRoleIds = ref([])

watch(() => [props.isDialogOpen, props.boxData], () => {
  if (props.boxData) {
    selectedRoleIds.value = props.boxData.role_ids
      ? [...props.boxData.role_ids]
      : (props.boxData.roles ? props.boxData.roles.map(r => r.id) : [])
  }
  else {
    selectedRoleIds.value = []
  }
  clearErrors()
}, { immediate: true })

const handleClose = () => {
  emit('update:isDialogOpen', false)
  clearErrors()
}

const handleSubmit = () => {
  clearErrors()
  emit('submit', {
    id: props.boxData?.id,
    'role_ids': selectedRoleIds.value,
  })
}

defineExpose({
  setErrors,
  clearErrors,
})
</script>

<template>
  <VDialog
    :model-value="props.isDialogOpen"
    max-width="520"
    persistent
    @update:model-value="handleClose"
  >
    <VCard>
      <VCardItem class="border-b">
        <template #prepend>
          <VIcon
            icon="tabler-shield-lock"
            class="text-primary me-2"
          />
        </template>
        <VCardTitle>Roles de Acceso a Caja</VCardTitle>
        <VCardSubtitle v-if="props.boxData">
          {{ props.boxData.name }} ({{ props.boxData.code }})
        </VCardSubtitle>
        <template #append>
          <VBtn
            icon
            variant="text"
            size="small"
            aria-label="Cerrar"
            @click="handleClose"
          >
            <VIcon icon="tabler-x" />
          </VBtn>
        </template>
      </VCardItem>

      <VCardText class="pt-4">
        <VAlert
          v-if="generalError"
          type="error"
          variant="tonal"
          class="mb-4"
        >
          {{ generalError }}
        </VAlert>

        <p class="text-body-2 text-medium-emphasis mb-4">
          Solo los usuarios cuyos roles estén seleccionados podrán abrir, operar y cerrar turnos en esta caja.
        </p>

        <VAutocomplete
          v-model="selectedRoleIds"
          :items="props.rolesList"
          item-title="name"
          item-value="id"
          label="Roles Autorizados"
          placeholder="Seleccionar roles con acceso"
          multiple
          chips
          closable-chips
          clearable
          :error-messages="errors.role_ids"
          @update:model-value="clearFieldError('role_ids')"
        />
      </VCardText>

      <VCardActions class="pa-4 border-t justify-end gap-2">
        <VBtn
          variant="tonal"
          color="secondary"
          :disabled="props.isSubmitting"
          @click="handleClose"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          :loading="props.isSubmitting"
          :disabled="props.isSubmitting"
          @click="handleSubmit"
        >
          Guardar Roles
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
