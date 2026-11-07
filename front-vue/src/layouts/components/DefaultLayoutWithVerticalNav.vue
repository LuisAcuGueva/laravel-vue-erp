<script setup>
import { useNavigationMenu } from '@/composables/useNavigationMenu'

// Components
import BranchSwitcher from '@/components/common/BranchSwitcher.vue'
import Footer from '@/layouts/components/Footer.vue'
import NavBarNotifications from '@/layouts/components/NavBarNotifications.vue'
import NavSearchBar from '@/layouts/components/NavSearchBar.vue'
import NavbarThemeSwitcher from '@/layouts/components/NavbarThemeSwitcher.vue'
import PageBreadcrumbs from '@/layouts/components/PageBreadcrumbs.vue'
import UserProfile from '@/layouts/components/UserProfile.vue'
import VerticalNavBrand from '@/layouts/components/VerticalNavBrand.vue'
import ExchangeRateBanner from '@/components/common/ExchangeRateBanner.vue'

// @layouts plugin
import { VerticalNavLayout } from '@layouts'

const { navItems } = useNavigationMenu()
</script>

<template>
  <VerticalNavLayout :nav-items="navItems">
    <!-- 👉 Brand -->
    <template #vertical-nav-header="{ hideTitleAndIcon, toggleIsOverlayNavActive }">
      <VerticalNavBrand
        :hide-title-and-icon="hideTitleAndIcon"
        :toggle-is-overlay-nav-active="toggleIsOverlayNavActive"
      />
    </template>


    <!-- 👉 navbar -->
    <template #navbar="{ toggleVerticalOverlayNavActive }">
      <div class="d-flex h-100 align-center gap-2">
        <IconBtn
          id="vertical-nav-toggle-btn"
          class="ms-n3 d-lg-none"
          aria-label="Abrir menú de navegación"
          @click="toggleVerticalOverlayNavActive(true)"
        >
          <VIcon
            size="26"
            icon="tabler-menu-2"
          />
        </IconBtn>

        <PageBreadcrumbs class="me-2" />

        <VSpacer />

        <NavSearchBar />
        <BranchSwitcher />
        <NavBarNotifications />
        <NavbarThemeSwitcher />
        <UserProfile />
      </div>
    </template>

    <!-- 👉 Pages -->
    <ExchangeRateBanner />
    <slot />

    <!-- 👉 Footer -->
    <template #footer>
      <Footer />
    </template>

    <!-- 👉 Customizer -->
    <!-- <TheCustomizer /> -->
  </VerticalNavLayout>
</template>
