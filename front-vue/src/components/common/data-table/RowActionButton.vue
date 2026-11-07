<script setup>
const props = defineProps({
  action: {
    type: String,
    default: 'custom',
    validator: value => ['view', 'edit', 'delete', 'custom'].includes(value),
  },
  icon: {
    type: String,
    default: undefined,
  },
  color: {
    type: String,
    default: undefined,
  },
  tooltip: {
    type: String,
    default: undefined,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
})

defineEmits(['click'])

// Colores fijos por acción (spec 031, design §8.5): ver = info, editar = success, eliminar = error.
const ACTIONS = {
  view: { icon: 'tabler-eye', color: 'info', tooltip: 'Ver detalle' },
  edit: { icon: 'tabler-pencil', color: 'success', tooltip: 'Editar' },
  delete: { icon: 'tabler-trash', color: 'error', tooltip: 'Eliminar' },
  custom: { icon: 'tabler-dots', color: 'primary', tooltip: 'Acción' },
}

const resolved = computed(() => {
  const base = ACTIONS[props.action] || ACTIONS.custom

  return {
    icon: props.icon ?? base.icon,
    color: props.color ?? base.color,
    tooltip: props.tooltip ?? base.tooltip,
  }
})
</script>

<template>
  <VTooltip :text="resolved.tooltip">
    <template #activator="{ props: tooltipProps }">
      <VBtn
        v-bind="tooltipProps"
        icon
        variant="outlined"
        rounded="circle"
        size="34"
        :color="resolved.color"
        :loading="props.loading"
        :disabled="props.disabled || props.loading"
        :aria-label="resolved.tooltip"
        @click="$emit('click', $event)"
      >
        <VIcon
          :icon="resolved.icon"
          size="18"
        />
      </VBtn>
    </template>
  </VTooltip>
</template>
