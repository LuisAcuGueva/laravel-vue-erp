<script setup>
import { computed, onMounted, ref } from 'vue'
import { useConfirm } from '@/composables/useConfirm'
import { useNotify } from '@/composables/useNotify'
import { useProfileStore } from '../stores/profileStore'

const profileStore = useProfileStore()
const { confirm } = useConfirm()
const notify = useNotify()

const errorMessage = ref('')

const loadSessions = async () => {
  try {
    await profileStore.fetchSessions()
  }
  catch (err) {
    errorMessage.value = err?.data?.message || err?.message || 'Error al cargar las sesiones'
  }
}

onMounted(() => {
  loadSessions()
})

const otherSessionsCount = computed(() => {
  return profileStore.sessions.filter(s => !s.is_current).length
})

const DEVICE_ICONS = {
  mobile: 'tabler-device-mobile',
  tablet: 'tabler-device-tablet',
  desktop: 'tabler-device-laptop',
}

const getDeviceIcon = deviceType => DEVICE_ICONS[deviceType] ?? 'tabler-device-unknown'

const formatDate = dateString => {
  if (!dateString)
    return '—'

  try {
    return new Date(dateString).toLocaleString('es-ES', {
      dateStyle: 'medium',
      timeStyle: 'short',
    })
  }
  catch {
    return dateString
  }
}

const handleRevokeOtherSessions = async () => {
  errorMessage.value = ''

  const ok = await confirm({
    title: '¿Cerrar sesión en otros dispositivos?',
    message: 'Esta acción desconectará todas las sesiones abiertas en otros navegadores y terminales. Tu sesión actual permanecerá activa.',
    confirmText: 'Sí, cerrar sesiones',
    cancelText: 'Cancelar',
    color: 'warning',
    icon: 'tabler-logout',
  })

  if (!ok)
    return

  try {
    await profileStore.revokeOtherSessions()
    notify.success('Se cerraron las demás sesiones correctamente')
    await loadSessions()
  }
  catch (err) {
    errorMessage.value = err?.data?.message || err?.message || 'Error al revocar las sesiones'
  }
}
</script>

<template>
  <VCardItem>
    <VCardTitle>Dispositivos y Sesiones Activas</VCardTitle>
  </VCardItem>
  <VCardText>
    <div class="d-flex flex-wrap justify-space-between align-center mb-6 gap-4">
      <div>
        <p class="text-body-1 mb-1">
          Revisa los navegadores y dispositivos donde tu cuenta ha iniciado sesión recientemente.
        </p>
        <span class="text-caption text-medium-emphasis">
          Si detectas alguna actividad inusual, puedes cerrar todas las demás sesiones inmediatamente.
        </span>
      </div>

      <VBtn
        color="warning"
        variant="tonal"
        :disabled="otherSessionsCount === 0 || profileStore.isLoading"
        :loading="profileStore.isLoading"
        @click="handleRevokeOtherSessions"
      >
        <VIcon
          start
          icon="tabler-logout"
        />
        Cerrar sesión en los demás dispositivos
      </VBtn>
    </div>

    <VAlert
      v-if="errorMessage"
      type="error"
      variant="tonal"
      class="mb-4"
      closable
      @click:close="errorMessage = ''"
    >
      {{ errorMessage }}
    </VAlert>

    <VTable class="text-no-wrap">
      <thead>
        <tr>
          <th>DISPOSITIVO / NAVEGADOR</th>
          <th>DIRECCIÓN IP</th>
          <th>INICIO DE SESIÓN</th>
          <th>ÚLTIMA ACTIVIDAD</th>
          <th class="text-center">
            ESTADO
          </th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="session in profileStore.sessions"
          :key="session.id"
        >
          <td>
            <div class="d-flex align-center gap-3">
              <VAvatar
                size="38"
                :color="session.is_current ? 'primary' : 'secondary'"
                variant="tonal"
              >
                <VIcon
                  size="22"
                  :icon="getDeviceIcon(session.device_type)"
                />
              </VAvatar>
              <div>
                <div class="font-weight-medium">
                  {{ session.browser || 'Navegador web' }}
                </div>
                <span
                  v-if="session.platform"
                  class="text-caption text-medium-emphasis"
                >
                  {{ session.platform }}
                </span>
              </div>
            </div>
          </td>

          <td>
            <code class="text-sm">{{ session.ip_address || '—' }}</code>
          </td>

          <td>
            {{ formatDate(session.created_at) }}
          </td>

          <td>
            {{ formatDate(session.last_used_at) }}
          </td>

          <td class="text-center">
            <VChip
              v-if="session.is_current"
              color="success"
              size="small"
              variant="tonal"
            >
              <VIcon
                start
                icon="tabler-circle-check"
                size="14"
              />
              Sesión actual
            </VChip>
            <VChip
              v-else
              color="secondary"
              size="small"
              variant="tonal"
            >
              Activa
            </VChip>
          </td>
        </tr>

        <tr v-if="!profileStore.sessions.length && !profileStore.isLoading">
          <td
            colspan="5"
            class="text-center py-6 text-disabled"
          >
            No hay sesiones registradas.
          </td>
        </tr>
      </tbody>
    </VTable>
  </VCardText>
</template>
