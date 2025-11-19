<script setup>
import { ref, computed } from 'vue'
import { useFormatters } from '@/composables/useFormatters'
import {
  Bell,
  X,
  Check,
  Archive,
  Trash2,
  Search,
  Filter,
  Calendar,
  User,
  DollarSign,
  AlertCircle,
  Info,
  CheckCircle,
} from 'lucide-vue-next'

const props = defineProps({
  modelValue: Boolean,
  notifications: Array,
  unreadCount: Number,
})

const emit = defineEmits(['update:modelValue', 'mark-read', 'mark-all-read', 'delete-notification'])

const { formatDate } = useFormatters()

const isOpen = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

const searchTerm = ref('')
const selectedFilter = ref('all')
const selectedNotifications = ref(new Set())

const filters = [
  { value: 'all', label: 'All Notifications' },
  { value: 'unread', label: 'Unread' },
  { value: 'payment_completed', label: 'Payment Completed' },
  { value: 'payment_failed', label: 'Payment Failed' },
  { value: 'assessment_created', label: 'Assessment Created' },
  { value: 'account_balance_low', label: 'Low Balance' },
]

const getNotificationIcon = (type) => {
  const icons = {
    payment_completed: CheckCircle,
    payment_failed: AlertCircle,
    assessment_created: Info,
    account_balance_low: DollarSign,
    admin_payment_completed: CheckCircle,
    admin_payment_failed: AlertCircle,
    general: Bell,
  }
  return icons[type] || Bell
}

const getNotificationColor = (type) => {
  const colors = {
    payment_completed: 'text-green-600 bg-green-100',
    payment_failed: 'text-red-600 bg-red-100',
    assessment_created: 'text-blue-600 bg-blue-100',
    account_balance_low: 'text-yellow-600 bg-yellow-100',
    admin_payment_completed: 'text-green-600 bg-green-100',
    admin_payment_failed: 'text-red-600 bg-red-100',
    general: 'text-gray-600 bg-gray-100',
  }
  return colors[type] || colors.general
}

const getNotificationTime = (timestamp) => {
  const now = new Date()
  const notificationTime = new Date(timestamp)
  const diffInMinutes = Math.floor((now - notificationTime) / (1000 * 60))

  if (diffInMinutes < 1) return 'Just now'
  if (diffInMinutes < 60) return `${diffInMinutes} minutes ago`
  if (diffInMinutes < 1440) return `${Math.floor(diffInMinutes / 60)} hours ago`
  return `${Math.floor(diffInMinutes / 1440)} days ago`
}

const filteredNotifications = computed(() => {
  let filtered = props.notifications || []

  // Apply search filter
  if (searchTerm.value) {
    const search = searchTerm.value.toLowerCase()
    filtered = filtered.filter(notification =>
      notification.title?.toLowerCase().includes(search) ||
      notification.message?.toLowerCase().includes(search)
    )
  }

  // Apply category filter
  if (selectedFilter.value !== 'all') {
    if (selectedFilter.value === 'unread') {
      filtered = filtered.filter(notification => !notification.read_at)
    } else {
      filtered = filtered.filter(notification =>
        notification.notification_type === selectedFilter.value
      )
    }
  }

  // Sort by created date (newest first)
  return filtered.sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
})

const handleNotificationClick = (notification) => {
  if (!notification.read_at) {
    emit('mark-read', notification.id)
  }
}

const handleSelectAll = () => {
  if (selectedNotifications.value.size === filteredNotifications.value.length) {
    selectedNotifications.value.clear()
  } else {
    selectedNotifications.value = new Set(filteredNotifications.value.map(n => n.id))
  }
}

const handleMarkSelectedAsRead = () => {
  selectedNotifications.value.forEach(id => {
    emit('mark-read', id)
  })
  selectedNotifications.value.clear()
}

const handleDeleteSelected = () => {
  selectedNotifications.value.forEach(id => {
    emit('delete-notification', id)
  })
  selectedNotifications.value.clear()
}

const handleMarkAllAsRead = () => {
  emit('mark-all-read')
}

const getSelectedCount = () => selectedNotifications.value.size
const hasSelected = computed(() => getSelectedCount() > 0)
const isAllSelected = computed(() =>
  getSelectedCount() === filteredNotifications.value.length && filteredNotifications.value.length > 0
)
</script>

<template>
  <div v-if="isOpen" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[80vh] flex flex-col">
      <!-- Header -->
      <div class="flex items-center justify-between p-6 border-b border-gray-200">
        <div class="flex items-center gap-3">
          <div class="p-2 bg-blue-100 rounded-lg">
            <Bell :size="20" class="text-blue-600" />
          </div>
          <div>
            <h2 class="text-xl font-semibold text-gray-900">Notification Center</h2>
            <p class="text-sm text-gray-600">
              {{ filteredNotifications.length }} notifications
              <span v-if="unreadCount > 0" class="text-blue-600 font-medium">
                ({{ unreadCount }} unread)
              </span>
            </p>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <button
            v-if="unreadCount > 0"
            @click="handleMarkAllAsRead"
            class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1"
          >
            <Check :size="16" />
            Mark all as read
          </button>
          <button
            @click="isOpen = false"
            class="text-gray-400 hover:text-gray-600 transition"
          >
            <X :size="24" />
          </button>
        </div>
      </div>

      <!-- Search and Filters -->
      <div class="p-4 border-b border-gray-200 space-y-3">
        <div class="flex gap-3">
          <div class="flex-1 relative">
            <Search :size="18" class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" />
            <input
              v-model="searchTerm"
              type="text"
              placeholder="Search notifications..."
              class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
          </div>
          <div class="relative">
            <Filter :size="18" class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" />
            <select
              v-model="selectedFilter"
              class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 appearance-none"
            >
              <option v-for="filter in filters" :key="filter.value" :value="filter.value">
                {{ filter.label }}
              </option>
            </select>
          </div>
        </div>

        <!-- Bulk Actions -->
        <div v-if="hasSelected" class="flex items-center justify-between bg-blue-50 p-3 rounded-lg">
          <div class="flex items-center gap-3">
            <input
              type="checkbox"
              :checked="isAllSelected"
              @change="handleSelectAll"
              class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
            >
            <span class="text-sm font-medium text-blue-900">
              {{ getSelectedCount() }} selected
            </span>
          </div>
          <div class="flex items-center gap-2">
            <button
              @click="handleMarkSelectedAsRead"
              class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 transition"
            >
              <Check :size="14" class="inline mr-1" />
              Mark as read
            </button>
            <button
              @click="handleDeleteSelected"
              class="px-3 py-1 bg-red-600 text-white text-sm rounded hover:bg-red-700 transition"
            >
              <Trash2 :size="14" class="inline mr-1" />
              Delete
            </button>
          </div>
        </div>
      </div>

      <!-- Notifications List -->
      <div class="flex-1 overflow-y-auto">
        <div v-if="filteredNotifications.length === 0" class="text-center py-12 text-gray-500">
          <Bell :size="48" class="mx-auto mb-4 text-gray-300" />
          <p class="text-lg font-medium">No notifications found</p>
          <p class="text-sm mt-1">
            {{ searchTerm ? 'Try a different search term' : 'Your notifications will appear here' }}
          </p>
        </div>

        <div v-else class="divide-y divide-gray-200">
          <div
            v-for="notification in filteredNotifications"
            :key="notification.id"
            class="p-4 hover:bg-gray-50 cursor-pointer transition-colors"
            :class="{ 'bg-blue-50': !notification.read_at }"
            @click="handleNotificationClick(notification)"
          >
            <div class="flex items-start gap-3">
              <!-- Checkbox for bulk selection -->
              <input
                type="checkbox"
                :checked="selectedNotifications.has(notification.id)"
                @change.stop="selectedNotifications.has(notification.id)
                  ? selectedNotifications.delete(notification.id)
                  : selectedNotifications.add(notification.id)"
                class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
              >

              <!-- Notification Icon -->
              <div :class="[
                'p-2 rounded-lg flex-shrink-0',
                getNotificationColor(notification.notification_type)
              ]">
                <component
                  :is="getNotificationIcon(notification.notification_type)"
                  :size="16"
                />
              </div>

              <!-- Notification Content -->
              <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between">
                  <div class="flex-1">
                    <div class="flex items-center gap-2 mb-1">
                      <h3 class="font-medium text-gray-900">
                        {{ notification.title }}
                      </h3>
                      <div v-if="!notification.read_at"
                           class="w-2 h-2 bg-blue-600 rounded-full flex-shrink-0">
                      </div>
                    </div>
                    <p class="text-sm text-gray-700 whitespace-pre-line">
                      {{ notification.message }}
                    </p>
                  </div>
                </div>

                <!-- Notification Metadata -->
                <div class="flex items-center gap-4 mt-2 text-xs text-gray-500">
                  <div class="flex items-center gap-1">
                    <Calendar :size="12" />
                    <span>{{ getNotificationTime(notification.created_at) }}</span>
                  </div>
                  <div class="flex items-center gap-1">
                    <User :size="12" />
                    <span>{{ notification.user?.name || 'System' }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>