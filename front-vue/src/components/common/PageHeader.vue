<script setup>
const props = defineProps({
  icon: {
    type: String,
    default: undefined,
  },
  title: {
    type: String,
    default: undefined,
  },
  description: {
    type: String,
    default: undefined,
  },
})

// Encabezado uniforme de página (spec 031, design §8.4).
// Lee ícono, título y descripción de `route.meta`; las props los sobrescriben.
// Se usa dentro del `VCardText` superior de la tarjeta principal de la página.
const route = useRoute()

const resolvedIcon = computed(() => props.icon ?? route.meta?.icon ?? 'tabler-file')
const resolvedTitle = computed(() => props.title ?? route.meta?.title ?? '')
const resolvedDescription = computed(() => props.description ?? route.meta?.description ?? '')
</script>

<template>
  <div class="page-header d-flex flex-wrap align-center gap-4">
    <div class="d-flex align-center gap-4 flex-grow-1 page-header__info">
      <VAvatar
        variant="tonal"
        color="primary"
        rounded
        size="42"
      >
        <VIcon
          :icon="resolvedIcon"
          size="26"
        />
      </VAvatar>

      <div class="page-header__text">
        <h5 class="text-h5 font-weight-bold">
          {{ resolvedTitle }}
        </h5>
        <p
          v-if="resolvedDescription"
          class="text-body-2 text-medium-emphasis mb-0"
        >
          {{ resolvedDescription }}
        </p>
      </div>
    </div>

    <div
      v-if="$slots.actions"
      class="page-header__actions d-flex flex-wrap align-center gap-2"
    >
      <slot name="actions" />
    </div>
  </div>
</template>

<style lang="scss" scoped>
.page-header {
  &__info,
  &__text {
    min-inline-size: 0;
  }

  @media (max-width: 599.98px) {
    &__actions {
      inline-size: 100%;
    }
  }
}
</style>
