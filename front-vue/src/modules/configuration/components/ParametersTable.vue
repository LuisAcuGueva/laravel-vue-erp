<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useAcl } from '@/composables/useAcl'
import { useConfigurationStore } from '../stores/configurationStore'

const configurationStore = useConfigurationStore()
const { can } = useAcl()

const activePanel = ref([0, 1, 2])
const savingId = ref(null)
const rowErrors = reactive({})
const rowSuccess = reactive({})
const localValues = reactive({})

const groupLabels = {
  sales: 'Ventas y Cotizaciones',
  inventory: 'Inventario y Almacén',
  finance: 'Finanzas y Caja',
}

const groupIcons = {
  sales: 'tabler-shopping-cart',
  inventory: 'tabler-box',
  finance: 'tabler-cash',
}

const syncLocalValues = () => {
  for (const param of configurationStore.parameters) {
    if (param.value_type === 'json' && typeof param.value === 'object' && param.value !== null) {
      localValues[param.id] = JSON.stringify(param.value, null, 2)
    }
    else {
      localValues[param.id] = param.value
    }
  }
}

onMounted(async () => {
  try {
    await configurationStore.fetchParameters()
    syncLocalValues()
  }
  catch {
    // Manejo en store
  }
})

const groupedParameters = computed(() => {
  const groups = {}

  for (const param of configurationStore.parameters) {
    const key = param.group_key || 'general'
    if (!groups[key]) {
      groups[key] = {
        key,
        title: groupLabels[key] || key.charAt(0).toUpperCase() + key.slice(1),
        icon: groupIcons[key] || 'tabler-adjustments',
        items: [],
      }
    }
    groups[key].items.push(param)
  }

  return Object.values(groups)
})

const handleSave = async param => {
  if (!param.is_editable || !can('configuration.update'))
    return

  savingId.value = param.id
  delete rowErrors[param.id]
  delete rowSuccess[param.id]

  let val = localValues[param.id]

  if (param.value_type === 'integer') {
    val = Number.parseInt(val, 10)
  }
  else if (param.value_type === 'decimal') {
    val = Number.parseFloat(val)
  }
  else if (param.value_type === 'boolean') {
    val = Boolean(val)
  }
  else if (param.value_type === 'json') {
    try {
      val = typeof val === 'string' ? JSON.parse(val) : val
    }
    catch {
      rowErrors[param.id] = 'El formato JSON ingresado no es válido.'
      savingId.value = null

      return
    }
  }

  try {
    await configurationStore.updateParameter(param.id, val)
    rowSuccess[param.id] = 'Parámetro actualizado exitosamente.'
    setTimeout(() => {
      delete rowSuccess[param.id]
    }, 4000)
  }
  catch (err) {
    rowErrors[param.id] = err?.data?.errors?.value?.[0] || err?.data?.message || 'Error al actualizar parámetro.'
  }
  finally {
    savingId.value = null
  }
}
</script>

<template>
  <div
    v-if="configurationStore.isLoading && !configurationStore.parameters.length"
    class="d-flex justify-center my-12"
  >
    <VProgressCircular
      indeterminate
      color="primary"
      size="48"
    />
  </div>

  <div v-else>
    <VExpansionPanels
      v-model="activePanel"
      multiple
      class="mb-6"
    >
      <VExpansionPanel
        v-for="group in groupedParameters"
        :key="group.key"
      >
        <VExpansionPanelTitle class="font-weight-medium">
          <div class="d-flex align-center gap-2">
            <VIcon
              :icon="group.icon"
              color="primary"
              size="20"
            />
            <span>{{ group.title }}</span>
            <VChip
              size="x-small"
              variant="tonal"
              color="secondary"
              class="ms-2"
            >
              {{ group.items.length }}
            </VChip>
          </div>
        </VExpansionPanelTitle>

        <VExpansionPanelText>
          <VList class="py-0">
            <template
              v-for="(param, index) in group.items"
              :key="param.id"
            >
              <VListItem class="px-0 py-4">
                <VRow
                  align="center"
                  no-gutters
                  class="gap-y-3"
                >
                  <!-- Detalle del Parámetro -->
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <div class="d-flex align-center gap-2 mb-1">
                      <span class="font-weight-medium text-high-emphasis">
                        {{ param.label }}
                      </span>
                      <VChip
                        size="x-small"
                        variant="tonal"
                        :color="param.is_editable ? 'info' : 'secondary'"
                      >
                        {{ param.value_type }}
                      </VChip>
                      <VChip
                        v-if="!param.is_editable"
                        size="x-small"
                        color="warning"
                        variant="tonal"
                      >
                        Solo lectura
                      </VChip>
                    </div>

                    <div class="text-caption text-disabled mb-1">
                      <code>{{ param.key }}</code>
                    </div>

                    <p class="text-caption text-medium-emphasis mb-0">
                      {{ param.description }}
                    </p>

                    <!-- Mensajes inline -->
                    <div
                      v-if="rowSuccess[param.id]"
                      class="text-caption text-success mt-1"
                    >
                      <VIcon
                        icon="tabler-check"
                        size="14"
                        class="me-1"
                      />
                      {{ rowSuccess[param.id] }}
                    </div>
                    <div
                      v-if="rowErrors[param.id]"
                      class="text-caption text-error mt-1"
                    >
                      <VIcon
                        icon="tabler-alert-circle"
                        size="14"
                        class="me-1"
                      />
                      {{ rowErrors[param.id] }}
                    </div>
                  </VCol>

                  <!-- Control de Edición -->
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <div class="d-flex align-center justify-end gap-3">
                      <!-- Switch para boolean -->
                      <div
                        v-if="param.value_type === 'boolean'"
                        class="d-flex align-center"
                      >
                        <VSwitch
                          v-model="localValues[param.id]"
                          :disabled="!param.is_editable || !can('configuration.update') || savingId === param.id"
                          color="primary"
                          hide-details
                          @update:model-value="handleSave(param)"
                        />
                      </div>

                      <!-- Input numérico para integer o decimal -->
                      <div
                        v-else-if="param.value_type === 'integer' || param.value_type === 'decimal'"
                        style="max-inline-size: 220px; inline-size: 100%;"
                      >
                        <VTextField
                          v-model="localValues[param.id]"
                          label="Valor"
                          type="number"
                          :step="param.value_type === 'decimal' ? '0.01' : '1'"
                          density="compact"
                          hide-details
                          :disabled="!param.is_editable || !can('configuration.update') || savingId === param.id"
                        />
                      </div>

                      <!-- Textarea para JSON -->
                      <div
                        v-else-if="param.value_type === 'json'"
                        style="max-inline-size: 320px; inline-size: 100%;"
                      >
                        <VTextarea
                          v-model="localValues[param.id]"
                          label="Valor (JSON)"
                          rows="2"
                          density="compact"
                          hide-details
                          :disabled="!param.is_editable || !can('configuration.update') || savingId === param.id"
                        />
                      </div>

                      <!-- Input de texto para string -->
                      <div
                        v-else
                        style="max-inline-size: 260px; inline-size: 100%;"
                      >
                        <VTextField
                          v-model="localValues[param.id]"
                          label="Valor"
                          density="compact"
                          hide-details
                          :disabled="!param.is_editable || !can('configuration.update') || savingId === param.id"
                        />
                      </div>

                      <!-- Botón guardar para no-booleanos -->
                      <VBtn
                        v-if="param.value_type !== 'boolean' && param.is_editable && can('configuration.update')"
                        size="small"
                        color="primary"
                        variant="tonal"
                        :loading="savingId === param.id"
                        @click="handleSave(param)"
                      >
                        <VIcon
                          icon="tabler-check"
                          start
                        />
                        Guardar
                      </VBtn>
                    </div>
                  </VCol>
                </VRow>
              </VListItem>
              <VDivider v-if="index < group.items.length - 1" />
            </template>
          </VList>
        </VExpansionPanelText>
      </VExpansionPanel>
    </VExpansionPanels>
  </div>
</template>
