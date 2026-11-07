<script setup>
import { useNotify } from '@/composables/useNotify'
import { onMounted, reactive, ref, watch } from 'vue'
import { useApiFormErrors } from '@/composables/useApiFormErrors'
import { useProfileStore } from '../stores/profileStore'

const profileStore = useProfileStore()
const { errors, generalError, setErrors, clearErrors, clearFieldError } = useApiFormErrors()

const form = reactive({
  name: '',
  email: '',
})

const notify = useNotify()

const syncForm = () => {
  if (profileStore.profile) {
    form.name = profileStore.profile.name || ''
    form.email = profileStore.profile.email || ''
  }
}

watch(() => profileStore.profile, syncForm, { immediate: true })

onMounted(async () => {
  if (!profileStore.profile) {
    await profileStore.fetchProfile()
    syncForm()
  }
})

const handleSubmit = async () => {
  clearErrors()

  try {
    await profileStore.updateProfile({
      name: form.name,
      email: form.email,
    })
    notify.success('Información personal actualizada correctamente')
  }
  catch (err) {
    setErrors(err)
  }
}
</script>

<template>
  <VCardItem>
    <VCardTitle>Información de la Cuenta</VCardTitle>
  </VCardItem>
  <VCardText>
    <VAlert
      v-if="generalError"
      type="error"
      variant="tonal"
      class="mb-4"
      closable
      @click:close="generalError = null"
    >
      {{ generalError }}
    </VAlert>

    <VForm @submit.prevent="handleSubmit">
      <VRow>
        <VCol
          cols="12"
          md="6"
        >
          <VTextField
            v-model="form.name"
            label="Nombre completo *"
            placeholder="Juan Pérez"
            :error-messages="errors.name"
            :disabled="profileStore.isSaving"
            @update:model-value="clearFieldError('name')"
          />
        </VCol>

        <VCol
          cols="12"
          md="6"
        >
          <VTextField
            v-model="form.email"
            type="email"
            label="Correo electrónico *"
            placeholder="juan@empresa.com"
            :error-messages="errors.email"
            :disabled="profileStore.isSaving"
            @update:model-value="clearFieldError('email')"
          />
        </VCol>

        <VCol cols="12">
          <div class="text-sm font-weight-medium mb-2">
            Roles asignados
          </div>
          <div class="d-flex flex-wrap gap-2">
            <VChip
              v-for="role in (profileStore.profile?.roles || [])"
              :key="role"
              color="primary"
              size="small"
              variant="tonal"
            >
              {{ role }}
            </VChip>
            <span
              v-if="!profileStore.profile?.roles?.length"
              class="text-sm text-disabled"
            >
              Sin roles asignados
            </span>
          </div>
        </VCol>

        <VCol
          cols="12"
          class="d-flex justify-end gap-3 mt-4"
        >
          <VBtn
            type="submit"
            color="primary"
            :loading="profileStore.isSaving"
          >
            <VIcon
              start
              icon="tabler-check"
            />
            Guardar cambios
          </VBtn>
        </VCol>
      </VRow>
    </VForm>
  </VCardText>
</template>
