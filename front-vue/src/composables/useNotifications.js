import { computed, ref, unref } from 'vue'
import { useApi } from '@/composables/useApi'

/**
 * Notificaciones iniciales de ejemplo para el sistema a medida.
 * Al conectar un endpoint real (`/api/notifications`), este fallback puede ser reemplazado.
 */
const buildMockNotifications = () => [
  {
    id: 'notif-1',
    icon: 'tabler-user-check',
    title: 'Sesión iniciada',
    subtitle: 'Has ingresado correctamente al panel de administración.',
    time: 'Hace un momento',
    isSeen: false,
    color: 'primary',
  },
  {
    id: 'notif-2',
    icon: 'tabler-shield-check',
    title: 'Sistema operativo',
    subtitle: 'Todos los servicios del backend responden con normalidad.',
    time: 'Hoy',
    isSeen: false,
    color: 'success',
  },
  {
    id: 'notif-3',
    icon: 'tabler-bell',
    title: 'Bienvenido al panel',
    subtitle: 'Configura tus módulos y preferencias desde el menú lateral.',
    time: 'Ayer',
    isSeen: true,
    color: 'info',
  },
]

const normalizeNotification = (notification, index) => ({
  id: notification.id ?? `notification-${index}`,
  icon: notification.icon || 'tabler-bell',
  img: notification.img,
  text: notification.text,
  color: notification.color,
  title: notification.title || 'Notificación',
  subtitle: notification.subtitle || notification.message || '',
  time: notification.time || notification.created_at || '',
  isSeen: notification.isSeen ?? notification.read ?? false,
  to: notification.to || null,
})

export const useNotifications = () => {
  const notifications = ref(buildMockNotifications())
  const isLoading = ref(false)
  const hasLoaded = ref(false)
  const source = ref('mock')

  const unreadCount = computed(() => notifications.value.filter(n => !n.isSeen).length)

  const fetchNotifications = async () => {
    if (isLoading.value)
      return

    isLoading.value = true

    try {
      const { data, error } = await useApi('/notifications')
      const payload = unref(data)
      const list = Array.isArray(payload) ? payload : payload?.data

      if (unref(error) || !Array.isArray(list))
        throw new Error('Notificaciones no disponibles en API')

      notifications.value = list.map(normalizeNotification)
      source.value = 'api'
    }
    catch {
      notifications.value = buildMockNotifications()
      source.value = 'mock'
    }
    finally {
      isLoading.value = false
      hasLoaded.value = true
    }
  }

  const removeNotification = id => {
    notifications.value = notifications.value.filter(n => n.id !== id)
  }

  const markRead = ids => {
    notifications.value.forEach(n => {
      if (ids.includes(n.id))
        n.isSeen = true
    })
  }

  const markUnread = ids => {
    notifications.value.forEach(n => {
      if (ids.includes(n.id))
        n.isSeen = false
    })
  }

  return {
    notifications,
    unreadCount,
    isLoading,
    hasLoaded,
    source,
    fetchNotifications,
    removeNotification,
    markRead,
    markUnread,
  }
}
