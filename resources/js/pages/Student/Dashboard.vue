<script setup lang="ts">
/**
 * Student Dashboard (REAL-TIME ENHANCED)
 * Location: resources/js/pages/Student/Dashboard.vue
 *
 * Key enhancements:
 * - Real-time WebSocket integration for live updates
 * - Live payment status monitoring
 * - Real-time notifications
 * - Auto-refresh of dashboard data
 * - Live balance updates
 * - Real-time transaction status
 */

import { computed, ref, onMounted, onUnmounted } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import Echo from 'laravel-echo'
import AppLayout from '@/layouts/AppLayout.vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import TransactionDetailsDialog from '@/components/TransactionDetailsDialog.vue'
import NotificationCenter from '@/components/NotificationCenter.vue'
import { Button } from '@/components/ui/button'
import { useFormatters } from '@/composables/useFormatters'
import type { Transaction, Account, Notification, TransactionStats } from '@/types/transaction'
import {
  Wallet,
  Calendar,
  AlertCircle,
  CheckCircle,
  TrendingUp,
  Clock,
  FileText,
  CreditCard,
  Bell,
  ArrowRight,
  RefreshCw,
  Wifi,
  WifiOff,
} from 'lucide-vue-next'

interface Props {
  account: Account
  notifications: Notification[]
  recentTransactions: Transaction[]
  stats: TransactionStats
}

const props = defineProps<Props>()

// Use our reusable formatter composable
const { formatCurrency, formatDate, formatPercentage } = useFormatters()

const breadcrumbs = [
  { title: 'Dashboard', href: route('dashboard') },
  { title: 'Student Dashboard' },
]

// Real-time state
const isConnected = ref(false)
const liveNotifications = ref<Notification[]>([...props.notifications])
const liveTransactions = ref<Transaction[]>([...props.recentTransactions])
const liveStats = ref<TransactionStats>({ ...props.stats })
const lastUpdate = ref(new Date())
const showNotificationCenter = ref(false)
const unreadNotifications = ref(0)

// Dialog state
const showDetailsDialog = ref(false)
const selectedTransaction = ref<Transaction | null>(null)

// WebSocket connection
let echo: Echo | null = null

// Computed: Payment percentage (using live stats)
const paymentPercentage = computed(() => {
  if (liveStats.value.total_fees === 0) return 0
  return Math.round((liveStats.value.total_paid / liveStats.value.total_fees) * 100)
})

// Computed: Active notifications (filtered by date, using live notifications)
const activeNotifications = computed(() => {
  const now = new Date()
  return liveNotifications.value.filter(n => {
    if (!n.start_date) return true
    const startDate = new Date(n.start_date)
    const endDate = n.end_date ? new Date(n.end_date) : null
    return startDate <= now && (!endDate || endDate >= now)
  })
})

// Computed: Has outstanding balance (using live stats)
const hasOutstandingBalance = computed(() => liveStats.value.remaining_balance > 0)

// Computed: Connection status display
const connectionStatus = computed(() => ({
  text: isConnected.value ? 'Connected' : 'Offline',
  icon: isConnected.value ? Wifi : WifiOff,
  class: isConnected.value ? 'text-green-600' : 'text-red-600',
}))

// Computed: Recent notifications badge count
const recentNotificationsCount = computed(() => {
  return liveNotifications.value.filter(n =>
    new Date(n.created_at) > new Date(Date.now() - 5 * 60 * 1000) // Last 5 minutes
  ).length
})

// Computed: Status message (using live stats)
const statusMessage = computed(() => {
  if (hasOutstandingBalance.value) {
    return {
      title: 'Payment Reminder',
      message: `You have an outstanding balance of ${formatCurrency(liveStats.value.remaining_balance)}`,
      icon: AlertCircle,
      class: 'bg-red-50 border-red-200 text-red-900',
      buttonClass: 'bg-red-600 hover:bg-red-700',
      buttonText: 'Pay Now',
    }
  }
  return {
    title: 'All Paid!',
    message: 'Great job! You have no outstanding balance.',
    icon: CheckCircle,
    class: 'bg-green-50 border-green-200 text-green-900',
    buttonClass: 'bg-green-600 hover:bg-green-700',
    buttonText: 'View Account',
  }
})

// Real-time lifecycle methods
onMounted(() => {
  initializeWebSocket()
})

onUnmounted(() => {
  disconnectWebSocket()
})

// Initialize WebSocket connection
const initializeWebSocket = () => {
  try {
    echo = new Echo({
      broadcaster: 'pusher',
      key: import.meta.env.VITE_PUSHER_APP_KEY,
      cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
      forceTLS: true,
      authEndpoint: '/broadcasting/auth',
      enabledTransports: ['ws', 'wss'],
    })

    // Connect to private student channel
    const studentChannel = echo.private(`student.${props.account.user_id}`)

    // Listen for payment status changes
    studentChannel.listen('.payment.status.changed', (event: any) => {
      handlePaymentStatusChange(event)
    })

    // Listen for new notifications
    studentChannel.listen('.notification.received', (event: any) => {
      handleNewNotification(event)
    })

    // Listen for balance updates
    studentChannel.listen('.balance.updated', (event: any) => {
      handleBalanceUpdate(event)
    })

    // Listen for new transactions
    studentChannel.listen('.transaction.created', (event: any) => {
      handleNewTransaction(event)
    })

    // Listen for connection events
    studentChannel.subscribed(() => {
      isConnected.value = true
      console.log('Connected to real-time updates')
    })

    studentChannel.error((error: any) => {
      console.error('WebSocket error:', error)
      isConnected.value = false
    })

    echo.connector().pusher.connection.bind('connected', () => {
      isConnected.value = true
    })

    echo.connector().pusher.connection.bind('disconnected', () => {
      isConnected.value = false
    })

  } catch (error) {
    console.error('Failed to initialize WebSocket:', error)
    isConnected.value = false
  }
}

// Disconnect WebSocket
const disconnectWebSocket = () => {
  if (echo) {
    echo.disconnect()
    echo = null
    isConnected.value = false
  }
}

// Handle payment status changes
const handlePaymentStatusChange = (event: any) => {
  // Update transaction in liveTransactions if it exists
  const transactionIndex = liveTransactions.value.findIndex(t => t.id === event.payment_id)
  if (transactionIndex !== -1) {
    liveTransactions.value[transactionIndex] = {
      ...liveTransactions.value[transactionIndex],
      status: event.new_status,
      updated_at: new Date().toISOString(),
    }
  }

  // Show notification toast
  showPaymentStatusNotification(event)
}

// Handle new notifications
const handleNewNotification = (event: any) => {
  const newNotification: Notification = {
    id: event.notification.id,
    title: event.notification.title,
    message: event.notification.message,
    type: event.notification.type,
    created_at: event.notification.created_at,
    start_date: event.notification.start_date,
    end_date: event.notification.end_date,
    read_at: null,
  }

  liveNotifications.value.unshift(newNotification)
  unreadNotifications.value++
}

// Handle balance updates
const handleBalanceUpdate = (event: any) => {
  if (event.balance !== undefined) {
    liveStats.value.remaining_balance = event.balance
    liveStats.value.total_paid = event.total_paid || liveStats.value.total_paid
  }
}

// Handle new transactions
const handleNewTransaction = (event: any) => {
  const newTransaction: Transaction = {
    id: event.transaction.id,
    type: event.transaction.type,
    amount: event.transaction.amount,
    status: event.transaction.status,
    reference: event.transaction.reference,
    created_at: event.transaction.created_at,
    updated_at: event.transaction.updated_at,
  }

  liveTransactions.value.unshift(newTransaction)

  // Keep only recent 10 transactions
  if (liveTransactions.value.length > 10) {
    liveTransactions.value = liveTransactions.value.slice(0, 10)
  }
}

// Show payment status notification
const showPaymentStatusNotification = (event: any) => {
  const statusMessages = {
    completed: 'Payment completed successfully!',
    failed: 'Payment failed. Please try again.',
    cancelled: 'Payment was cancelled.',
    pending: 'Payment is being processed...',
  }

  const message = statusMessages[event.new_status] || `Payment status changed to ${event.new_status}`

  // You could integrate with a toast notification system here
  console.log('Payment notification:', message)
}

// Methods
const viewTransaction = (transaction: Transaction) => {
  selectedTransaction.value = transaction
  showDetailsDialog.value = true
}

const handlePayNow = (transaction?: Transaction) => {
  if (transaction) {
    router.visit(route('student.payment.create'), {
      method: 'get',
      data: { transaction_id: transaction.id },
    })
  } else {
    router.visit(route('student.payment.create'))
  }
}

const handleDownload = (transaction: Transaction) => {
  router.visit(route('transactions.download', {
    transaction: transaction.id
  }))
}

// Manual refresh for disconnected state
const refreshData = async () => {
  try {
    const response = await axios.get('/student/dashboard/refresh')
    if (response.data) {
      liveStats.value = response.data.stats
      liveTransactions.value = response.data.recentTransactions
      liveNotifications.value = response.data.notifications
      lastUpdate.value = new Date()
    }
  } catch (error) {
    console.error('Failed to refresh data:', error)
  }
}
</script>

<template>
  <AppLayout>
    <Head title="Student Dashboard" />

    <div class="w-full p-6 space-y-6">
      <Breadcrumbs :items="breadcrumbs" />

      <!-- Welcome Header with Real-time Status -->
      <div class="bg-gradient-to-r from-blue-600 to-blue-800 rounded-lg shadow-lg p-6 text-white relative">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-3xl font-bold mb-2">Welcome Back, Student!</h1>
            <p class="text-blue-100">Here's your financial overview and important updates</p>
          </div>
          <div class="flex items-center space-x-4">
            <!-- Connection Status -->
            <div class="flex items-center space-x-2 bg-white bg-opacity-20 rounded-lg px-3 py-2">
              <component :is="connectionStatus.icon" :size="16" :class="connectionStatus.class" />
              <span class="text-sm" :class="connectionStatus.class">{{ connectionStatus.text }}</span>
            </div>

            <!-- Notification Center Button -->
            <button
              @click="showNotificationCenter = true"
              class="relative bg-white bg-opacity-20 rounded-lg px-4 py-2 hover:bg-opacity-30 transition-colors"
            >
              <div class="flex items-center space-x-2">
                <Bell :size="16" />
                <span class="text-sm">Notifications</span>
              </div>
              <div
                v-if="recentNotificationsCount > 0"
                class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center"
              >
                {{ recentNotificationsCount }}
              </div>
            </button>

            <!-- Refresh Button (when offline) -->
            <button
              v-if="!isConnected"
              @click="refreshData"
              class="bg-white bg-opacity-20 rounded-lg px-3 py-2 hover:bg-opacity-30 transition-colors"
            >
              <RefreshCw :size="16" />
            </button>
          </div>
        </div>

        <!-- Last Update Timestamp -->
        <div class="absolute bottom-2 right-2 text-xs text-blue-200">
          Last updated: {{ formatDate(lastUpdate, 'time') }}
        </div>
      </div>

      <!-- Quick Stats Grid (Real-time) -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <!-- Total Fees Card -->
        <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow relative">
          <div class="flex items-center justify-between mb-2">
            <div class="p-3 bg-blue-100 rounded-lg">
              <FileText :size="24" class="text-blue-600" />
            </div>
          </div>
          <p class="text-sm text-gray-600">Total Fees</p>
          <p class="text-2xl font-bold text-gray-900">
            {{ formatCurrency(liveStats.total_fees) }}
          </p>
          <!-- Real-time indicator -->
          <div v-if="isConnected && liveStats.total_fees !== props.stats.total_fees"
               class="absolute top-2 right-2 bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">
            Live
          </div>
        </div>

        <!-- Total Paid Card -->
        <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow relative">
          <div class="flex items-center justify-between mb-2">
            <div class="p-3 bg-green-100 rounded-lg">
              <CheckCircle :size="24" class="text-green-600" />
            </div>
          </div>
          <p class="text-sm text-gray-600">Total Paid</p>
          <p class="text-2xl font-bold text-green-600">
            {{ formatCurrency(liveStats.total_paid) }}
          </p>
          <!-- Real-time indicator -->
          <div v-if="isConnected && liveStats.total_paid !== props.stats.total_paid"
               class="absolute top-2 right-2 bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">
            Updated
          </div>
        </div>

        <!-- Remaining Balance Card -->
        <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow relative">
          <div class="flex items-center justify-between mb-2">
            <div :class="[
              'p-3 rounded-lg',
              hasOutstandingBalance ? 'bg-red-100' : 'bg-green-100'
            ]">
              <Wallet :size="24" :class="hasOutstandingBalance ? 'text-red-600' : 'text-green-600'" />
            </div>
          </div>
          <p class="text-sm text-gray-600">Remaining Balance</p>
          <p class="text-2xl font-bold" :class="hasOutstandingBalance ? 'text-red-600' : 'text-green-600'">
            {{ formatCurrency(liveStats.remaining_balance) }}
          </p>
          <!-- Real-time indicator -->
          <div v-if="isConnected && liveStats.remaining_balance !== props.stats.remaining_balance"
               class="absolute top-2 right-2 bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full animate-pulse">
            Live
          </div>
        </div>

        <!-- Pending Charges Card -->
        <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow relative">
          <div class="flex items-center justify-between mb-2">
            <div class="p-3 bg-yellow-100 rounded-lg">
              <Clock :size="24" class="text-yellow-600" />
            </div>
          </div>
          <p class="text-sm text-gray-600">Pending Charges</p>
          <p class="text-2xl font-bold text-yellow-600">
            {{ liveStats.pending_charges_count }}
          </p>
          <!-- Real-time indicator -->
          <div v-if="isConnected"
               class="absolute top-2 right-2 bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded-full">
            Live
          </div>
        </div>
      </div>

      <!-- Payment Progress Bar -->
      <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-xl font-semibold">Payment Progress</h2>
          <span class="text-2xl font-bold text-blue-600">
            {{ formatPercentage(paymentPercentage) }}
          </span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-4">
          <div
            class="bg-gradient-to-r from-blue-500 to-green-500 h-4 rounded-full transition-all duration-500"
            :style="{ width: `${paymentPercentage}%` }"
          ></div>
        </div>
        <div class="flex justify-between mt-2 text-sm text-gray-600">
          <span>{{ formatCurrency(stats.total_paid) }} paid</span>
          <span>{{ formatCurrency(stats.total_fees) }} total</span>
        </div>
      </div>

      <!-- Main Content Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column (2/3 width) -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Notifications (Real-time) -->
          <div v-if="activeNotifications.length" class="bg-white rounded-lg shadow-md p-6 relative">
            <div class="flex items-center justify-between mb-4">
              <div class="flex items-center gap-2">
                <Bell :size="20" class="text-blue-600" />
                <h2 class="text-xl font-semibold">Important Announcements</h2>
                <!-- New notification indicator -->
                <div v-if="recentNotificationsCount > 0" class="bg-red-500 text-white text-xs rounded-full w-6 h-6 flex items-center justify-center animate-pulse">
                  {{ recentNotificationsCount }}
                </div>
              </div>
            </div>
            <div class="space-y-4">
              <div
                v-for="notification in activeNotifications"
                :key="notification.id"
                class="border-l-4 border-blue-500 bg-blue-50 p-4 rounded-r relative"
              >
                <!-- New notification badge -->
                <div v-if="new Date(notification.created_at) > new Date(Date.now() - 5 * 60 * 1000)"
                     class="absolute top-2 right-2 bg-green-500 text-white text-xs px-2 py-1 rounded-full animate-pulse">
                  New
                </div>
                <h3 class="font-semibold text-blue-900">{{ notification.title }}</h3>
                <p class="text-sm text-gray-700 whitespace-pre-line mt-1">
                  {{ notification.message }}
                </p>
                <div v-if="notification.start_date" class="text-xs text-gray-500 mt-2 flex items-center gap-1">
                  <Calendar :size="12" />
                  {{ formatDate(notification.start_date, 'short') }}
                  <span v-if="notification.end_date">
                    - {{ formatDate(notification.end_date, 'short') }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Recent Transactions (Real-time) -->
          <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-4">
              <h2 class="text-xl font-semibold">Recent Transactions</h2>
              <Link
                :href="route('transactions.index')"
                class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1"
              >
                View All <ArrowRight :size="16" />
              </Link>
            </div>

            <!-- Transactions List -->
            <div v-if="liveTransactions.length" class="space-y-3">
              <div
                v-for="transaction in liveTransactions"
                :key="transaction.id"
                class="flex justify-between items-center p-3 hover:bg-gray-50 rounded cursor-pointer relative"
                @click="viewTransaction(transaction)"
              >
                <!-- New transaction indicator -->
                <div v-if="new Date(transaction.created_at) > new Date(Date.now() - 2 * 60 * 1000)"
                     class="absolute top-2 right-2 bg-green-500 text-white text-xs px-2 py-1 rounded-full animate-pulse">
                  New
                </div>

                <div class="flex-1">
                  <p class="font-medium">{{ transaction.type }}</p>
                  <p class="text-sm text-gray-600">{{ transaction.reference }}</p>
                  <p class="text-xs text-gray-500">{{ formatDate(transaction.created_at, 'short') }}</p>
                </div>
                <div class="text-right">
                  <p
                    class="font-semibold"
                    :class="{
                      'text-green-600': transaction.status === 'paid' || transaction.status === 'completed',
                      'text-yellow-600': transaction.status === 'pending',
                      'text-red-600': transaction.status === 'failed'
                    }"
                  >
                    {{ formatCurrency(transaction.amount) }}
                  </p>
                  <span
                    class="text-xs px-2 py-1 rounded"
                    :class="{
                      'bg-green-100 text-green-800': transaction.status === 'paid' || transaction.status === 'completed',
                      'bg-yellow-100 text-yellow-800': transaction.status === 'pending',
                      'bg-red-100 text-red-800': transaction.status === 'failed'
                    }"
                  >
                    {{ transaction.status }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Empty State -->
            <p v-else class="text-gray-500 text-center py-8">
              No recent transactions
            </p>
          </div>
        </div>

        <!-- Right Column (1/3 width) -->
        <div class="space-y-6">
          <!-- Quick Actions -->
          <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">Quick Actions</h2>
            <div class="space-y-3">
              <Link
                :href="route('student.account')"
                class="flex items-center gap-3 p-3 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors"
              >
                <div class="p-2 bg-blue-500 rounded">
                  <Wallet :size="20" class="text-white" />
                </div>
                <div>
                  <p class="font-medium text-gray-900">View Account</p>
                  <p class="text-xs text-gray-600">Check balance & fees</p>
                </div>
              </Link>

              <Link
                :href="route('student.payment.create')"
                class="flex items-center gap-3 p-3 bg-green-50 rounded-lg hover:bg-green-100 transition-colors"
              >
                <div class="p-2 bg-green-500 rounded">
                  <CreditCard :size="20" class="text-white" />
                </div>
                <div>
                  <p class="font-medium text-gray-900">Make Payment</p>
                  <p class="text-xs text-gray-600">Pay your fees online</p>
                </div>
              </Link>

              <Link
                :href="route('transactions.index')"
                class="flex items-center gap-3 p-3 bg-purple-50 rounded-lg hover:bg-purple-100 transition-colors"
              >
                <div class="p-2 bg-purple-500 rounded">
                  <FileText :size="20" class="text-white" />
                </div>
                <div>
                  <p class="font-medium text-gray-900">View History</p>
                  <p class="text-xs text-gray-600">Transaction history</p>
                </div>
              </Link>
            </div>
          </div>

          <!-- Status Card -->
          <div :class="['border rounded-lg p-6', statusMessage.class]">
            <div class="flex items-center gap-2 mb-3">
              <component :is="statusMessage.icon" :size="20" />
              <h3 class="font-semibold">{{ statusMessage.title }}</h3>
            </div>
            <p class="text-sm mb-4">{{ statusMessage.message }}</p>
            <Button
              :class="['w-full', statusMessage.buttonClass]"
              @click="handlePayNow()"
            >
              {{ statusMessage.buttonText }}
            </Button>
          </div>
        </div>
      </div>
    </div>

    <!-- Transaction Details Dialog -->
    <TransactionDetailsDialog
      v-model:open="showDetailsDialog"
      :transaction="selectedTransaction"
      :show-pay-now-button="true"
      :show-download-button="true"
      @pay-now="handlePayNow"
      @download="handleDownload"
    />

    <!-- Notification Center Modal -->
    <NotificationCenter
      v-model:open="showNotificationCenter"
      :notifications="liveNotifications"
      :unread-count="unreadNotifications"
      @mark-read="() => unreadNotifications.value--"
    />
  </AppLayout>
</template>
