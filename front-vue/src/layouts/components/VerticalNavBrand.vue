<script setup>
import { layoutConfig } from '@layouts'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { useLayoutConfigStore } from '@layouts/stores/config'
import { themeConfig } from '@themeConfig'

const props = defineProps({
  hideTitleAndIcon: {
    type: Boolean,
    default: false,
  },
  toggleIsOverlayNavActive: {
    type: Function,
    default: () => {},
  },
})

const configStore = useLayoutConfigStore()
const displayName = computed(() => themeConfig.app.title)
</script>

<template>
  <RouterLink
    to="/"
    class="app-logo app-title-wrapper"
    :aria-label="`Ir al inicio de ${displayName}`"
  >
    <span class="app-logo-mark">
      <VNodeRenderer :nodes="layoutConfig.app.logo" />
    </span>

    <Transition name="vertical-nav-app-title">
      <span
        v-show="!props.hideTitleAndIcon"
        class="app-logo-title"
      >
        {{ displayName }}
      </span>
    </Transition>
  </RouterLink>

  <!-- 👉 Vertical nav actions -->
  <!-- Show toggle collapsible in >md and close button in <md -->
  <div class="header-action">
    <Component
      :is="layoutConfig.app.iconRenderer || 'div'"
      v-show="configStore.isVerticalNavCollapsed"
      class="d-none nav-unpin"
      :class="configStore.isVerticalNavCollapsed && 'd-lg-block'"
      v-bind="layoutConfig.icons.verticalNavUnPinned"
      @click="configStore.isVerticalNavCollapsed = !configStore.isVerticalNavCollapsed"
    />
    <Component
      :is="layoutConfig.app.iconRenderer || 'div'"
      v-show="!configStore.isVerticalNavCollapsed"
      class="d-none nav-pin"
      :class="!configStore.isVerticalNavCollapsed && 'd-lg-block'"
      v-bind="layoutConfig.icons.verticalNavPinned"
      @click="configStore.isVerticalNavCollapsed = !configStore.isVerticalNavCollapsed"
    />
    <Component
      :is="layoutConfig.app.iconRenderer || 'div'"
      class="d-lg-none"
      v-bind="layoutConfig.icons.close"
      @click="props.toggleIsOverlayNavActive(false)"
    />
  </div>
</template>

<style lang="scss" scoped>
.app-logo {
  display: flex;
  align-items: center;
  column-gap: 0.75rem;
  min-inline-size: 0;

  .app-logo-mark {
    display: inline-flex;
    flex-shrink: 0;
    line-height: 0;
  }

  .app-logo-title {
    overflow: hidden;
    font-size: 1.125rem;
    font-weight: 700;
    letter-spacing: 0.15px;
    line-height: 1.375rem;
    text-overflow: ellipsis;
    text-transform: capitalize;
    white-space: nowrap;
  }
}
</style>
