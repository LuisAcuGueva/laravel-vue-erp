<script setup>
import { useNotifications } from '@/composables/useNotifications'

const {
  notifications,
  fetchNotifications,
  removeNotification,
  markRead,
  markUnread,
} = useNotifications()

const router = useRouter()

onMounted(fetchNotifications)

const handleNotificationClick = notification => {
  if (!notification.isSeen)
    markRead([notification.id])

  if (notification.to)
    router.push(notification.to)
}
</script>

<template>
  <Notifications
    :notifications="notifications"
    @remove="removeNotification"
    @read="markRead"
    @unread="markUnread"
    @click:notification="handleNotificationClick"
  />
</template>
