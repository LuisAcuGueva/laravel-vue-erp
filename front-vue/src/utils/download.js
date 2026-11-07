import { $api } from '@/utils/api'

/**
 * Descarga un archivo desde la API utilizando $api con responseType: 'blob'.
 * Incluye automáticamente Authorization y X-Branch-Id.
 *
 * @param {string} url - Endpoint de la API
 * @param {object} [params={}] - Parámetros de consulta
 * @param {string} [defaultFilename='descarga'] - Nombre de archivo por defecto
 */
export async function downloadFile(url, params = {}, defaultFilename = 'descarga') {
  const response = await $api.raw(url, {
    method: 'GET',
    params,
    responseType: 'blob',
  })

  const blob = response._data || response

  let filename = defaultFilename
  const disposition = response.headers?.get?.('content-disposition')

  if (disposition) {
    const filenameMatch = disposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/)
    if (filenameMatch && filenameMatch[1]) {
      filename = filenameMatch[1].replace(/['"]/g, '')
    }
  }

  const blobUrl = window.URL.createObjectURL(blob)
  const link = document.createElement('a')

  link.href = blobUrl
  link.setAttribute('download', filename)
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  window.URL.revokeObjectURL(blobUrl)

  return filename
}

/**
 * Abre un archivo de la API en una pestaña nueva (blob autenticado).
 * La pestaña se abre antes de la petición para evitar el bloqueo de popups.
 *
 * @param {string} url - Endpoint de la API
 * @param {object} [params={}] - Parámetros de consulta
 */
export async function openFile(url, params = {}) {
  const tab = window.open('', '_blank')

  try {
    const response = await $api.raw(url, {
      method: 'GET',
      params,
      responseType: 'blob',
    })

    const blobUrl = window.URL.createObjectURL(response._data || response)

    if (tab)
      tab.location.href = blobUrl
    else
      window.open(blobUrl, '_blank')

    setTimeout(() => window.URL.revokeObjectURL(blobUrl), 60000)
  }
  catch (err) {
    tab?.close()
    throw err
  }
}

export default downloadFile
