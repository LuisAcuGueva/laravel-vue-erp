<script setup>
import { useRouter } from 'vue-router'
import { layoutConfig } from '@layouts'
import { can } from '@layouts/plugins/casl'
import { useLayoutConfigStore } from '@layouts/stores/config'
import {
  getComputedNavLinkToProp,
  getDynamicI18nProps,
  isNavLinkActive,
  resolveNavLinkRouteName,
} from '@layouts/utils'

const props = defineProps({
  item: {
    type: null,
    required: true,
  },
})

const router = useRouter()
const configStore = useLayoutConfigStore()
const hideTitleAndBadge = configStore.isVerticalNavMini()

const hasMatchingRoute = computed(() => {
  if (!props.item?.to)
    return false

  return Boolean(resolveNavLinkRouteName(props.item, router))
})
</script>

<template>
  <li
    v-if="can(item.action, item.subject)"
    class="nav-link"
    :class="{ disabled: item.disable }"
  >
    <Component
      :is="hasMatchingRoute ? 'RouterLink' : 'a'"
      v-bind="hasMatchingRoute ? getComputedNavLinkToProp(item) : { href: 'javascript:void(0)' }"
      :class="{
        'router-link-active router-link-exact-active': isNavLinkActive(
          item,
          $router,
        ),
      }"
    >
      <Component
        :is="layoutConfig.app.iconRenderer || 'div'"
        v-bind="
          item.icon && typeof item.icon === 'object' && item.icon !== null
            ? item.icon
            : layoutConfig.verticalNav.defaultNavItemIconProps || {}
        "
        class="nav-item-icon"
      />
      <TransitionGroup name="transition-slide-x">
        <!-- 👉 Title -->
        <Component
          :is="layoutConfig.app.i18n.enable ? 'i18n-t' : 'span'"
          v-show="!hideTitleAndBadge"
          key="title"
          class="nav-item-title"
          v-bind="getDynamicI18nProps(item.title, 'span')"
        >
          {{ item.title }}
        </Component>

        <!-- 👉 Badge -->
        <Component
          :is="layoutConfig.app.i18n.enable ? 'i18n-t' : 'span'"
          v-if="item.badgeContent"
          v-show="!hideTitleAndBadge"
          key="badge"
          class="nav-item-badge"
          :class="item.badgeClass"
          v-bind="getDynamicI18nProps(item.badgeContent, 'span')"
        >
          {{ item.badgeContent }}
        </Component>
      </TransitionGroup>
    </Component>
  </li>
</template>

<style lang="scss">
.layout-vertical-nav {
  .nav-link a {
    display: flex;
    align-items: center;
  }
}
</style>
