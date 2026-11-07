<script setup>
import { useNotify } from '@/composables/useNotify'
import { reactive, ref } from 'vue'
import { useApiFormErrors } from '@/composables/useApiFormErrors'
import { useProfileStore } from '../stores/profileStore'

const profileStore = useProfileStore()
const { errors, generalError, setErrors, clearErrors, clearFieldError } = useApiFormErrors()

const isCurrentPasswordVisible = ref(false)
const isNewPasswordVisible = ref(false)
const isConfirmPasswordVisible = ref(false)
const notify = useNotify()

const form = reactive({
  currentPassword: '',
  password: '',
  passwordConfirmation: '',
})

const resetForm = () => {
  form.currentPassword = ''
  form.password = ''
  form.passwordConfirmation = ''
}

const handleSubmit = async () => {
  clearErrors()

  try {
    await profileStore.updatePassword({
      'current_password': form.currentPassword,
      'password': form.password,
      'password_confirmation': form.passwordConfirmation,
    })
    notify.success('Contraseña actualizada correctamente. Las demás sesiones han sido invalidadas')
    resetForm()
  }
  catch (err) {
    setErrors(err)
  }
}
</script>

<template>
  <VCardItem>
    <VCardTitle>Seguridad y Cambio de Contraseña</VCardTitle>
  </VCardItem>
  <VCardText>
    <VAlert
      type="info"
      variant="tonal"
      class="mb-6"
    >
      <template #prepend>
        <VIcon icon="tabler-shield-lock" />
      </template>
      Al cambiar tu contraseña, todas tus demás sesiones activas en otros dispositivos y navegadores serán cerradas automáticamente por motivos de seguridad.
    </VAlert>


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
            v-model="form.currentPassword"
            label="Contraseña actual *"
            placeholder="············"
            :type="isCurrentPasswordVisible ? 'text' : 'password'"
            :append-inner-icon="isCurrentPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
            :error-messages="errors.current_password"
            :disabled="profileStore.isSaving"
            @click:append-inner="isCurrentPasswordVisible = !isCurrentPasswordVisible"
            @update:model-value="clearFieldError('current_password')"
          />
        </VCol>
      </VRow>

      <VRow>
        <VCol
          cols="12"
          md="6"
        >
          <VTextField
            v-model="form.password"
            label="Nueva contraseña *"
            placeholder="············"
            :type="isNewPasswordVisible ? 'text' : 'password'"
            :append-inner-icon="isNewPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
            :error-messages="errors.password"
            :disabled="profileStore.isSaving"
            hint="Mínimo 8 caracteres"
            persistent-hint
            @click:append-inner="isNewPasswordVisible = !isNewPasswordVisible"
            @update:model-value="clearFieldError('password')"
          />
        </VCol>

        <VCol
          cols="12"
          md="6"
        >
          <VTextField
            v-model="form.passwordConfirmation"
            label="Confirmar nueva contraseña *"
            placeholder="············"
            :type="isConfirmPasswordVisible ? 'text' : 'password'"
            :append-inner-icon="isConfirmPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
            :error-messages="errors.password_confirmation"
            :disabled="profileStore.isSaving"
            @click:append-inner="isConfirmPasswordVisible = !isConfirmPasswordVisible"
            @update:model-value="clearFieldError('password_confirmation')"
          />
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
              icon="tabler-lock"
            />
            Actualizar contraseña
          </VBtn>
        </VCol>
      </VRow>
    </VForm>
  </VCardText>
</template>
