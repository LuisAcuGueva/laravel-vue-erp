<script setup>
import { onMounted, ref } from 'vue'
import { useProfileStore } from '@/modules/profile/stores/profileStore'
import AccountDetailsTab from '@/modules/profile/components/AccountDetailsTab.vue'
import SecurityTab from '@/modules/profile/components/SecurityTab.vue'
import SessionsTab from '@/modules/profile/components/SessionsTab.vue'
import PageHeader from '@/components/common/PageHeader.vue'

definePage({
  meta: {
    // Accesible para cualquier usuario autenticado
    title: 'Mi cuenta',
    icon: 'tabler-user-circle',
    description: 'Gestiona tu información personal, credenciales de acceso y dispositivos conectados',
  },
})

const profileStore = useProfileStore()
const activeTab = ref('account')

onMounted(() => {
  profileStore.fetchProfile()
})
</script>

<template>
  <div class="profile-page">
    <VCard>
      <VCardText>
        <PageHeader />
      </VCardText>

      <VTabs
        v-model="activeTab"
        class="v-tabs-pill mx-6 mb-4"
      >
        <VTab
          value="account"
          prepend-icon="tabler-user"
        >
          Cuenta
        </VTab>

        <VTab
          value="security"
          prepend-icon="tabler-lock"
        >
          Seguridad
        </VTab>

        <VTab
          value="sessions"
          prepend-icon="tabler-devices"
        >
          Sesiones y Dispositivos
        </VTab>
      </VTabs>

      <VDivider />

      <VWindow
        v-model="activeTab"
        :touch="false"
        class="disable-tab-transition"
      >
        <VWindowItem value="account">
          <AccountDetailsTab />
        </VWindowItem>

        <VWindowItem value="security">
          <SecurityTab />
        </VWindowItem>

        <VWindowItem value="sessions">
          <SessionsTab />
        </VWindowItem>
      </VWindow>
    </VCard>
  </div>
</template>

<style scoped>
.profile-page {
  max-width: 1200px;
}
</style>
