<script setup>
import { useNotify } from '@/composables/useNotify'
/* eslint-disable camelcase */
import { onMounted, reactive, ref, watch } from 'vue'
import { useAcl } from '@/composables/useAcl'
import { useApiFormErrors } from '@/composables/useApiFormErrors'
import { useConfigurationStore } from '../stores/configurationStore'

const configurationStore = useConfigurationStore()
const { can } = useAcl()
const { errors, generalError, setErrors, clearErrors, clearFieldError } = useApiFormErrors()

const notify = useNotify()
const logoFile = ref(null)
const logoPreview = ref(null)
const isUploadingLogo = ref(false)
const logoError = ref('')
const logoSuccess = ref('')

const form = reactive({
  legal_name: '',
  trade_name: '',
  legal_representative: '',
  ruc: '',
  address: '',
  ubigeo: null,
  phone: '',
  email: '',
  website: '',
  default_currency_code: 'PEN',
  default_tax_rate: 18.00,
  prices_include_tax: true,
  terms_conditions: '',
  warranty_policy: '',
  social_facebook: '',
  social_instagram: '',
  social_tiktok: '',
  social_youtube: '',
  social_linkedin: '',
  social_twitter: '',
})

const syncForm = () => {
  const company = configurationStore.company
  if (!company)
    return

  form.legal_name = company.legal_name || ''
  form.trade_name = company.trade_name || ''
  form.legal_representative = company.legal_representative || ''
  form.ruc = company.ruc || ''
  form.address = company.address || ''
  form.ubigeo = company.ubigeo || null
  form.phone = company.phone || ''
  form.email = company.email || ''
  form.website = company.website || ''
  form.default_currency_code = company.default_currency_code || 'PEN'
  form.default_tax_rate = company.default_tax_rate !== undefined ? Number(company.default_tax_rate) : 18.00
  form.prices_include_tax = company.prices_include_tax !== undefined ? Boolean(company.prices_include_tax) : true
  form.terms_conditions = company.terms_conditions || ''
  form.warranty_policy = company.warranty_policy || ''

  const links = company.social_links || {}

  form.social_facebook = links.facebook || ''
  form.social_instagram = links.instagram || ''
  form.social_tiktok = links.tiktok || ''
  form.social_youtube = links.youtube || ''
  form.social_linkedin = links.linkedin || ''
  form.social_twitter = links.twitter || ''
}

watch(() => configurationStore.company, syncForm, { immediate: true })

onMounted(async () => {
  if (!configurationStore.company) {
    try {
      await configurationStore.fetchCompany()
      syncForm()
    }
    catch (err) {
      setErrors(err)
    }
  }
})

const onLogoFileChange = event => {
  const file = event.target?.files?.[0] || (Array.isArray(logoFile.value) ? logoFile.value[0] : logoFile.value)
  if (file && file instanceof File) {
    logoPreview.value = URL.createObjectURL(file)
    logoFile.value = file
  }
}

const handleUploadLogo = async () => {
  if (!logoFile.value)
    return

  isUploadingLogo.value = true
  logoError.value = ''
  logoSuccess.value = ''

  try {
    const file = logoFile.value instanceof File ? logoFile.value : (Array.isArray(logoFile.value) ? logoFile.value[0] : null)
    if (!file)
      return

    await configurationStore.uploadLogo(file)
    logoSuccess.value = 'Logo de la empresa actualizado correctamente.'
    logoPreview.value = null
    logoFile.value = null
  }
  catch (err) {
    logoError.value = err?.data?.errors?.logo?.[0] || err?.data?.message || 'Error al subir el logo.'
  }
  finally {
    isUploadingLogo.value = false
  }
}

const handleSubmit = async () => {
  clearErrors()

  const socialLinks = {}
  if (form.social_facebook.trim())
    socialLinks.facebook = form.social_facebook.trim()
  if (form.social_instagram.trim())
    socialLinks.instagram = form.social_instagram.trim()
  if (form.social_tiktok.trim())
    socialLinks.tiktok = form.social_tiktok.trim()
  if (form.social_youtube.trim())
    socialLinks.youtube = form.social_youtube.trim()
  if (form.social_linkedin.trim())
    socialLinks.linkedin = form.social_linkedin.trim()
  if (form.social_twitter.trim())
    socialLinks.twitter = form.social_twitter.trim()

  const payload = {
    legal_name: form.legal_name,
    trade_name: form.trade_name || null,
    legal_representative: form.legal_representative || null,
    ruc: form.ruc,
    address: form.address || null,
    ubigeo: form.ubigeo || null,
    phone: form.phone || null,
    email: form.email || null,
    website: form.website || null,
    default_tax_rate: Number(form.default_tax_rate),
    prices_include_tax: Boolean(form.prices_include_tax),
    terms_conditions: form.terms_conditions || null,
    warranty_policy: form.warranty_policy || null,
    social_links: Object.keys(socialLinks).length > 0 ? socialLinks : null,
  }

  try {
    await configurationStore.updateCompany(payload)
    notify.success('Configuración de la empresa actualizada correctamente')
  }
  catch (err) {
    setErrors(err)
  }
}
</script>

<template>
  <div
    v-if="configurationStore.isLoading && !configurationStore.company"
    class="d-flex justify-center my-12"
  >
    <VProgressCircular
      indeterminate
      color="primary"
      size="48"
    />
  </div>

  <div v-else>
    <!-- Alertas globales -->

    <VAlert
      v-if="generalError"
      type="error"
      variant="tonal"
      class="mb-6"
      closable
      @click:close="generalError = null"
    >
      {{ generalError }}
    </VAlert>

    <VRow>
      <!-- 👉 Columna Izquierda: Logo y Resumen Visual -->
      <VCol
        cols="12"
        md="4"
      >
        <VCard
          title="Logotipo de la Empresa"
          class="mb-6"
        >
          <VCardText class="text-center">
            <div class="d-flex justify-center mb-4">
              <VAvatar
                rounded
                size="160"
                variant="outlined"
                color="secondary"
                class="bg-var-theme-background"
              >
                <VImg
                  v-if="logoPreview || configurationStore.company?.logo_url"
                  :src="logoPreview || configurationStore.company?.logo_url"
                  cover
                />
                <VIcon
                  v-else
                  size="64"
                  icon="tabler-photo"
                  class="text-disabled"
                />
              </VAvatar>
            </div>

            <p class="text-caption text-medium-emphasis mb-4">
              Formatos aceptados: PNG, JPG, JPEG, WEBP o SVG. Tamaño máximo: 2 MB.
            </p>

            <VAlert
              v-if="logoSuccess"
              type="success"
              variant="tonal"
              density="compact"
              class="mb-3"
              closable
              @click:close="logoSuccess = ''"
            >
              {{ logoSuccess }}
            </VAlert>

            <VAlert
              v-if="logoError"
              type="error"
              variant="tonal"
              density="compact"
              class="mb-3"
              closable
              @click:close="logoError = ''"
            >
              {{ logoError }}
            </VAlert>

            <div v-if="can('configuration.update')">
              <VFileInput
                v-model="logoFile"
                label="Seleccionar imagen de logo"
                accept="image/png,image/jpeg,image/webp,image/svg+xml"
                prepend-icon=""
                prepend-inner-icon="tabler-upload"
                density="compact"
                class="mb-3"
                @change="onLogoFileChange"
              />

              <VBtn
                color="secondary"
                variant="tonal"
                block
                :disabled="!logoFile || isUploadingLogo"
                :loading="isUploadingLogo"
                @click="handleUploadLogo"
              >
                <VIcon
                  start
                  icon="tabler-cloud-upload"
                />
                Subir Logo
              </VBtn>
            </div>
            <div
              v-else
              class="text-caption text-disabled"
            >
              Solo lectura. No tienes permisos para cambiar el logo.
            </div>
          </VCardText>
        </VCard>

        <!-- Tarjeta de Moneda e Impuestos -->
        <VCard
          title="Impuestos y Moneda"
          class="mb-6"
        >
          <VCardText>
            <VRow>
              <VCol cols="12">
                <VTextField
                  :model-value="configurationStore.summary?.default_currency_code || form.default_currency_code || 'PEN'"
                  label="Moneda Base"
                  placeholder="PEN"
                  readonly
                  prepend-inner-icon="tabler-currency-dollar"
                  messages="La moneda base se gestiona en Finanzas › Configuración"
                >
                  <template #append-inner>
                    <VTooltip text="Gestionar monedas">
                      <template #activator="{ props: tooltipProps }">
                        <RouterLink
                          v-bind="tooltipProps"
                          :to="{ name: 'finance-settings', query: { tab: 'currencies' } }"
                          class="d-flex align-center text-primary"
                        >
                          <VIcon
                            icon="tabler-external-link"
                            size="20"
                          />
                        </RouterLink>
                      </template>
                    </VTooltip>
                  </template>
                </VTextField>
              </VCol>

              <VCol cols="12">
                <VTextField
                  v-model="form.default_tax_rate"
                  type="number"
                  step="0.01"
                  min="0"
                  max="100"
                  label="Tasa de Impuesto (%)"
                  placeholder="18.00"
                  suffix="%"
                  :error-messages="errors.default_tax_rate"
                  :disabled="!can('configuration.update') || configurationStore.isSaving"
                  @update:model-value="clearFieldError('default_tax_rate')"
                />
              </VCol>

              <VCol cols="12">
                <VSwitch
                  v-model="form.prices_include_tax"
                  label="Los precios de catálogo incluyen impuesto"
                  color="primary"
                  :disabled="!can('configuration.update') || configurationStore.isSaving"
                />
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VCol>

      <!-- 👉 Columna Derecha: Información Legal, Contacto y Políticas -->
      <VCol
        cols="12"
        md="8"
      >
        <VForm @submit.prevent="handleSubmit">
          <!-- Datos Fiscales y Legales -->
          <VCard
            title="Datos Fiscales y Legales"
            class="mb-6"
          >
            <VCardText>
              <VRow>
                <VCol
                  cols="12"
                  md="4"
                >
                  <VTextField
                    v-model="form.ruc"
                    label="RUC *"
                    placeholder="20131312955"
                    maxlength="11"
                    inputmode="numeric"
                    :error-messages="errors.ruc"
                    :disabled="!can('configuration.update') || configurationStore.isSaving"
                    @update:model-value="clearFieldError('ruc')"
                  />
                </VCol>

                <VCol
                  cols="12"
                  md="8"
                >
                  <VTextField
                    v-model="form.legal_name"
                    label="Razón Social *"
                    placeholder="Mi Empresa S.A.C."
                    :error-messages="errors.legal_name"
                    :disabled="!can('configuration.update') || configurationStore.isSaving"
                    @update:model-value="clearFieldError('legal_name')"
                  />
                </VCol>

                <VCol
                  cols="12"
                  md="6"
                >
                  <VTextField
                    v-model="form.trade_name"
                    label="Nombre Comercial"
                    placeholder="Mi Marca"
                    :error-messages="errors.trade_name"
                    :disabled="!can('configuration.update') || configurationStore.isSaving"
                    @update:model-value="clearFieldError('trade_name')"
                  />
                </VCol>

                <VCol
                  cols="12"
                  md="6"
                >
                  <VTextField
                    v-model="form.legal_representative"
                    label="Representante Legal"
                    placeholder="Juan Pérez Gómez"
                    maxlength="150"
                    :error-messages="errors.legal_representative"
                    :disabled="!can('configuration.update') || configurationStore.isSaving"
                    @update:model-value="clearFieldError('legal_representative')"
                  />
                </VCol>
              </VRow>
            </VCardText>
          </VCard>

          <!-- Contacto y Ubicación -->
          <VCard
            title="Contacto y Ubicación"
            class="mb-6"
          >
            <VCardText>
              <VRow>
                <VCol
                  cols="12"
                  md="6"
                >
                  <VTextField
                    v-model="form.address"
                    label="Dirección Fiscal"
                    placeholder="Av. Ejemplo 123, Urb. Centro"
                    :error-messages="errors.address"
                    :disabled="!can('configuration.update') || configurationStore.isSaving"
                    @update:model-value="clearFieldError('address')"
                  />
                </VCol>

                <VCol
                  cols="12"
                  md="6"
                >
                  <UbigeoSelect
                    v-model="form.ubigeo"
                    label="Ubigeo (Departamento / Provincia / Distrito)"
                    :error-messages="errors.ubigeo"
                    :disabled="!can('configuration.update') || configurationStore.isSaving"
                    @update:model-value="clearFieldError('ubigeo')"
                  />
                </VCol>

                <VCol
                  cols="12"
                  md="4"
                >
                  <VTextField
                    v-model="form.phone"
                    label="Teléfono / Celular"
                    placeholder="+51 987 654 321"
                    :error-messages="errors.phone"
                    :disabled="!can('configuration.update') || configurationStore.isSaving"
                    @update:model-value="clearFieldError('phone')"
                  />
                </VCol>

                <VCol
                  cols="12"
                  md="4"
                >
                  <VTextField
                    v-model="form.email"
                    type="email"
                    label="Correo Electrónico de Contacto"
                    placeholder="contacto@empresa.com"
                    :error-messages="errors.email"
                    :disabled="!can('configuration.update') || configurationStore.isSaving"
                    @update:model-value="clearFieldError('email')"
                  />
                </VCol>

                <VCol
                  cols="12"
                  md="4"
                >
                  <VTextField
                    v-model="form.website"
                    label="Sitio Web"
                    placeholder="https://empresa.com"
                    :error-messages="errors.website"
                    :disabled="!can('configuration.update') || configurationStore.isSaving"
                    @update:model-value="clearFieldError('website')"
                  />
                </VCol>
              </VRow>
            </VCardText>
          </VCard>

          <!-- Términos, Políticas y Redes Sociales -->
          <VCard
            title="Políticas y Redes Sociales"
            class="mb-6"
          >
            <VCardText>
              <VRow>
                <VCol
                  cols="12"
                  md="6"
                >
                  <VTextarea
                    v-model="form.terms_conditions"
                    label="Términos y Condiciones Generales"
                    placeholder="Términos que se imprimirán en cotizaciones y comprobantes..."
                    rows="3"
                    :error-messages="errors.terms_conditions"
                    :disabled="!can('configuration.update') || configurationStore.isSaving"
                    @update:model-value="clearFieldError('terms_conditions')"
                  />
                </VCol>

                <VCol
                  cols="12"
                  md="6"
                >
                  <VTextarea
                    v-model="form.warranty_policy"
                    label="Políticas de Garantía"
                    placeholder="Términos de cobertura y garantía comercial..."
                    rows="3"
                    :error-messages="errors.warranty_policy"
                    :disabled="!can('configuration.update') || configurationStore.isSaving"
                    @update:model-value="clearFieldError('warranty_policy')"
                  />
                </VCol>

                <VCol
                  cols="12"
                  md="6"
                >
                  <VTextField
                    v-model="form.social_facebook"
                    label="Facebook URL"
                    placeholder="https://facebook.com/empresa"
                    prepend-inner-icon="tabler-brand-facebook"
                    :disabled="!can('configuration.update') || configurationStore.isSaving"
                  />
                </VCol>

                <VCol
                  cols="12"
                  md="6"
                >
                  <VTextField
                    v-model="form.social_instagram"
                    label="Instagram URL"
                    placeholder="https://instagram.com/empresa"
                    prepend-inner-icon="tabler-brand-instagram"
                    :disabled="!can('configuration.update') || configurationStore.isSaving"
                  />
                </VCol>

                <VCol
                  cols="12"
                  md="6"
                >
                  <VTextField
                    v-model="form.social_tiktok"
                    label="TikTok URL"
                    placeholder="https://tiktok.com/@empresa"
                    prepend-inner-icon="tabler-brand-tiktok"
                    :disabled="!can('configuration.update') || configurationStore.isSaving"
                  />
                </VCol>

                <VCol
                  cols="12"
                  md="6"
                >
                  <VTextField
                    v-model="form.social_youtube"
                    label="Canal de YouTube URL"
                    placeholder="https://youtube.com/@empresa"
                    prepend-inner-icon="tabler-brand-youtube"
                    :disabled="!can('configuration.update') || configurationStore.isSaving"
                  />
                </VCol>
              </VRow>
            </VCardText>
          </VCard>

          <!-- Botón de guardar -->
          <div class="d-flex justify-end gap-3 mt-4">
            <VBtn
              type="submit"
              color="primary"
              size="large"
              :disabled="!can('configuration.update') || configurationStore.isSaving"
              :loading="configurationStore.isSaving"
            >
              <VIcon
                start
                icon="tabler-device-floppy"
              />
              Guardar Configuración
            </VBtn>
          </div>
        </VForm>
      </VCol>
    </VRow>
  </div>
</template>
