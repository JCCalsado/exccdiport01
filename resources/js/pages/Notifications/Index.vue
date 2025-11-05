<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import { Bell, Calendar, AlertCircle } from 'lucide-vue-next'

type Notification = {
  id: number
  title: string
  message: string
  start_date: string | null
  end_date: string | null
  target_role: string
  created_at: string
}

defineProps<{
  notifications: Notification[]
  role: string
}>()

const breadcrumbs = [
  { title: 'Dashboard', href: route('dashboard') },
  { title: 'Notifications' },
]

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('en-US', {
    month: 'long',
    day: 'numeric',
    year: 'numeric',
  })
}

const isActive = (notification: Notification) => {
  const now = new Date()
  if (!notification.start_date) return true
  
  const startDate = new Date(notification.start_date)
  const endDate = notification.end_date ? new Date(notification.end_date) : null
  
  return startDate <= now && (!endDate || endDate >= now)
}
</script>

<template>
  <AppLayout>
    <Head title="Notifications" />

    <div class="w-full p-6">
      <Breadcrumbs :items="breadcrumbs" />

      <!-- Header -->
      <div class="flex items-center gap-3 mb-6">
        <div class="p-3 bg-blue-100 rounded-lg">
          <Bell :size="28" class="text-blue-600" />
        </div>
        <div>
          <h1 class="text-3xl font-bold">Notifications</h1>
          <p class="text-gray-600">Stay updated with important announcements</p>
        </div>
      </div>

      <!-- Notifications Grid -->
      <div v-if="notifications.length" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        <div
          v-for="notification in notifications"
          :key="notification.id"
          :class="[
            'border rounded-lg p-6 shadow-sm transition-all hover:shadow-md',
            isActive(notification)
              ? 'bg-blue-50 border-blue-200'
              : 'bg-white border-gray-200'
          ]"
        >
          <!-- Active Badge -->
          <div v-if="isActive(notification)" class="flex items-center gap-2 mb-3">
            <span class="px-2 py-1 bg-blue-500 text-white text-xs font-medium rounded">
              ACTIVE
            </span>
          </div>

          <!-- Title -->
          <h2 class="font-bold text-lg mb-3" :class="isActive(notification) ? 'text-blue-900' : 'text-gray-900'">
            {{ notification.title }}
          </h2>

          <!-- Message -->
          <p class="text-gray-700 whitespace-pre-line mb-4 text-sm leading-relaxed">
            {{ notification.message }}
          </p>

          <!-- Date Range -->
          <div v-if="notification.start_date" class="flex items-center gap-2 text-sm text-gray-600 border-t pt-3">
            <Calendar :size="16" />
            <span>
              {{ formatDate(notification.start_date) }}
              <span v-if="notification.end_date">
                - {{ formatDate(notification.end_date) }}
              </span>
            </span>
          </div>

          <!-- Target Role Badge -->
          <div class="mt-3">
            <span class="px-2 py-1 bg-gray-200 text-gray-700 text-xs rounded">
              For: {{ notification.target_role }}
            </span>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="text-center py-12">
        <AlertCircle :size="64" class="text-gray-400 mx-auto mb-4" />
        <p class="text-gray-500 text-lg mb-2">No notifications found</p>
        <p class="text-gray-400 text-sm">Check back later for important announcements</p>
      </div>
    </div>
  </AppLayout>
</template>