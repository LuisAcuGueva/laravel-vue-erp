<script setup>
import { themeConfig } from '@themeConfig'

const currentYear = new Date().getFullYear()
const isProduction = import.meta.env.PROD
const appTitle = themeConfig.app.title
const appVersion = import.meta.env.VITE_APP_VERSION || 'dev'
</script>

<template>
  <div class="h-100 d-flex flex-wrap align-center justify-md-space-between justify-center gap-2">
    <!-- 👉 Footer: left content -->
    <span class="d-flex align-center gap-1 text-medium-emphasis text-sm">
      &copy; {{ currentYear }}
      <strong>{{ appTitle }}</strong>
      <span>· Todos los derechos reservados</span>
    </span>

    <!-- 👉 Footer: right content -->
    <span class="d-none d-md-flex align-center gap-2 text-sm text-medium-emphasis">
      <VChip
        :color="isProduction ? 'success' : 'warning'"
        size="x-small"
        variant="tonal"
      >
        {{ isProduction ? 'Producción' : 'Local' }}
      </VChip>
      <span>v{{ appVersion }}</span>
    </span>
  </div>
</template>
