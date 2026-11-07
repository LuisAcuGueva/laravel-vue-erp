<script setup>
const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  icon: {
    type: String,
    required: true,
  },
})

const emit = defineEmits(['close'])
</script>

<!-- Cabecera estándar de los paneles laterales (formularios y detalles): ícono del módulo en color primario, título y botón de cierre. -->
<template>
  <div class="d-flex align-center pa-4 border-b flex-shrink-0">
    <VIcon
      :icon="props.icon"
      class="me-2 text-primary"
    />
    <h6 class="text-h6 font-weight-medium">
      {{ props.title }}
    </h6>
    <VSpacer />
    <VBtn
      icon
      variant="text"
      size="small"
      aria-label="Cerrar"
      @click="emit('close')"
    >
      <VIcon icon="tabler-x" />
    </VBtn>
  </div>
</template>
