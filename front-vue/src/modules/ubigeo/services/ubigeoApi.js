import { $api } from '@/utils/api'

export const ubigeoApi = {
  // El navegador gestiona ETag / If-None-Match y max-age: aquí no se maneja el 304.
  catalog() {
    return $api('/v1/ubigeo', {
      method: 'GET',
    })
  },
}

export default ubigeoApi
