<script setup>
import { onBeforeUnmount, onMounted } from 'vue'
import { useTheme } from 'vuetify'
import ScrollToTop from '@core/components/ScrollToTop.vue'
import initCore from '@core/initCore'
import {
  initConfigStore,
  useConfigStore,
} from '@core/stores/config'
import { hexToRgb } from '@core/utils/colorConverter'
import { useAuthStore } from '@/modules/auth/stores/authStore'
import { onRemoteLogout } from '@/utils/authSync'
import GlobalConfirmDialog from '@/components/common/GlobalConfirmDialog.vue'
import GlobalSnackbar from '@/components/common/GlobalSnackbar.vue'

const { global } = useTheme()

// ℹ️ Sync current theme with initial loader theme
initCore()
initConfigStore()

const configStore = useConfigStore()
const authStore = useAuthStore()

let stopRemoteLogout = () => {}

onMounted(async () => {
  // Si otra pestaña cierra sesión, esta también (la cookie de sesión es compartida)
  stopRemoteLogout = onRemoteLogout(async () => {
    await authStore.logout({ broadcast: false })

    if (window.location.pathname !== '/login')
      window.location.href = '/login'
  })

  await authStore.initAuth()
})

onBeforeUnmount(() => stopRemoteLogout())
</script>

<template>
  <VLocaleProvider :rtl="configStore.isAppRTL">
    <!-- ℹ️ This is required to set the background color of active nav link based on currently active global theme's primary -->
    <VApp :style="`--v-global-theme-primary: ${hexToRgb(global.current.value.colors.primary)}`">
      <RouterView />

      <ScrollToTop />
      <GlobalConfirmDialog />
      <GlobalSnackbar />
    </VApp>
  </VLocaleProvider>
</template>
