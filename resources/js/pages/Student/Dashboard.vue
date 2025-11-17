<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
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
} from 'lucide-vue-next'

type Notification = {
  id: number
  title: string
  message: string
  start_date: string | null
  end_date: string | null
  target_role: string
}

type Account = {
  balance: number
}

type RecentTransaction = {
  id: number
  reference: string
  type: string
  kind: string
  amount: number
  status: string
  created_at: string
  user?: {
    id: number
    name: string
    student_id: string
    email: string
  }
  year?: string
  semester?: string
  payment_channel?: string
  paid_at?: string
  meta?: {
    fee_name?: string
    description?: string
    assessment_id?: number
    subject_code?: string
    subject_name?: string
  }
}

interface Props {
  account: Account
  notifications: Notification[]
  recentTransactions: RecentTransaction[]
  stats: {
    total_fees: number
    total_paid: number
    remaining_balance: number
    pending_charges_count: number
  }
}

const props = defineProps<Props>()

const breadcrumbs = [
  { title: 'Dashboard', href: route('dashboard') },
  { title: 'Student Dashboard' },
]

const showDetailsDialog = ref(false)
const selectedTransaction = ref<RecentTransaction | null>(null)

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
  }).format(amount)
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  })
}

const getPaymentPercentage = computed(() => {
  if (props.stats.total_fees === 0) return 0
  return Math.round((props.stats.total_paid / props.stats.total_fees) * 100)
})

const activeNotifications = computed(() => {
  const now = new Date()
  return props.notifications.filter(n => {
    if (!n.start_date) return true
    const startDate = new Date(n.start_date)
    const endDate = n.end_date ? new Date(n.end_date) : null
    return startDate <= now && (!endDate || endDate >= now)
  })
})

const viewTransaction = (transaction: RecentTransaction) => {
  selectedTransaction.value = transaction
  showDetailsDialog.value = true
}

const closeDetailsDialog = () => {
  showDetailsDialog.value = false
  selectedTransaction.value = null
}

const downloadPDF = () => {
  // Implement download logic if needed
  console.log('Download PDF')
}
</script>

<template>
  <AppLayout>
    <Head title="Student Dashboard" />

    <div class="w-full p-6 space-y-6">
      <Breadcrumbs :items="breadcrumbs" />

      <!-- Welcome Header -->
      <div class="bg-gradient-to-r from-blue-600 to-blue-800 rounded-lg shadow-lg p-6 text-white">
        <h1 class="text-3xl font-bold mb-2">Welcome Back, Student!</h1>
        <p class="text-blue-100">Here's your financial overview and important updates</p>
      </div>

      <!-- Quick Stats -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <!-- Total Fees -->
        <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
          <div class="flex items-center justify-between mb-2">
            <div class="p-3 bg-blue-100 rounded-lg">
              <FileText :size="24" class="text-blue-600" />
            </div>
          </div>
          <p class="text-sm text-gray-600">Total Fees</p>
          <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(stats.total_fees) }}</p>
        </div>

        <!-- Total Paid -->
        <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
          <div class="flex items-center justify-between mb-2">
            <div class="p-3 bg-green-100 rounded-lg">
              <CheckCircle :size="24" class="text-green-600" />
            </div>
          </div>
          <p class="text-sm text-gray-600">Total Paid</p>
          <p class="text-2xl font-bold text-green-600">{{ formatCurrency(stats.total_paid) }}</p>
        </div>

        <!-- Remaining Balance -->
        <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
          <div class="flex items-center justify-between mb-2">
            <div class="p-3 bg-red-100 rounded-lg">
              <Wallet :size="24" class="text-red-600" />
            </div>
          </div>
          <p class="text-sm text-gray-600">Remaining Balance</p>
          <p class="text-2xl font-bold text-red-600">{{ formatCurrency(stats.remaining_balance) }}</p>
        </div>

        <!-- Pending Charges -->
        <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
          <div class="flex items-center justify-between mb-2">
            <div class="p-3 bg-yellow-100 rounded-lg">
              <Clock :size="24" class="text-yellow-600" />
            </div>
          </div>
          <p class="text-sm text-gray-600">Pending Charges</p>
          <p class="text-2xl font-bold text-yellow-600">{{ stats.pending_charges_count }}</p>
        </div>
      </div>

      <!-- Payment Progress -->
      <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-xl font-semibold">Payment Progress</h2>
          <span class="text-2xl font-bold text-blue-600">{{ getPaymentPercentage }}%</span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-4">
          <div
            class="bg-gradient-to-r from-blue-500 to-green-500 h-4 rounded-full transition-all duration-500"
            :style="{ width: `${getPaymentPercentage}%` }"
          ></div>
        </div>
        <div class="flex justify-between mt-2 text-sm text-gray-600">
          <span>{{ formatCurrency(stats.total_paid) }} paid</span>
          <span>{{ formatCurrency(stats.total_fees) }} total</span>
        </div>
      </div>

      <!-- Main Content Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column: Notifications & Recent Transactions -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Important Notifications -->
          <div v-if="activeNotifications.length" class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center gap-2 mb-4">
              <Bell :size="20" class="text-blue-600" />
              <h2 class="text-xl font-semibold">Important Announcements</h2>
            </div>
            <div class="space-y-4">
              <div
                v-for="notification in activeNotifications"
                :key="notification.id"
                class="border-l-4 border-blue-500 bg-blue-50 p-4 rounded-r"
              >
                <h3 class="font-semibold text-blue-900">{{ notification.title }}</h3>
                <p class="text-sm text-gray-700 whitespace-pre-line mt-1">{{ notification.message }}</p>
                <div v-if="notification.start_date" class="text-xs text-gray-500 mt-2">
                  <Calendar :size="12" class="inline mr-1" />
                  {{ formatDate(notification.start_date) }}
                  <span v-if="notification.end_date"> - {{ formatDate(notification.end_date) }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Recent Transactions -->
          <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-4">
              <h2 class="text-xl font-semibold">Recent Transactions</h2>
              <Link
                :href="route('transactions.index')"
                class="text-sm text-blue-600 hover:text-blue-800"
              >
                View All →
              </Link>
            </div>
            <div v-if="recentTransactions.length" class="space-y-3">
              <div
                v-for="transaction in recentTransactions"
                :key="transaction.id"
                class="flex justify-between items-center p-3 hover:bg-gray-50 rounded"
              >
                <div class="flex-1">
                  <p class="font-medium">{{ transaction.type }}</p>
                  <p class="text-sm text-gray-600">{{ transaction.reference }}</p>
                  <p class="text-xs text-gray-500">{{ formatDate(transaction.created_at) }}</p>
                </div>
                <div class="text-right">
                  <p
                    class="font-semibold"
                    :class="transaction.status === 'paid' ? 'text-green-600' : 'text-yellow-600'"
                  >
                    {{ formatCurrency(transaction.amount) }}
                  </p>
                  <span
                    class="text-xs px-2 py-1 rounded"
                    :class="transaction.status === 'paid'
                      ? 'bg-green-100 text-green-800'
                      : 'bg-yellow-100 text-yellow-800'"
                  >
                    {{ transaction.status }}
                  </span>
                  <div class="mt-2 flex gap-2">
                    <button
                      @click="viewTransaction(transaction)"
                      class="text-xs px-2 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors"
                    >
                      View
                    </button>
                    <button
                      v-if="transaction.status === 'paid'"
                      @click="downloadPDF"
                      class="text-xs px-2 py-1 bg-green-600 text-white rounded hover:bg-green-700 transition-colors"
                    >
                      Download
                    </button>
                    <Link
                      v-if="transaction.status === 'pending' && transaction.kind === 'charge'"
                      :href="route('student.account', { tab: 'payment' })"
                      class="text-xs px-2 py-1 bg-red-600 text-white rounded hover:bg-red-700 transition-colors"
                    >
                      Pay Now
                    </Link>
                  </div>
                </div>
              </div>
            </div>
            <p v-else class="text-gray-500 text-center py-4">No recent transactions</p>
          </div>
        </div>

        <!-- Right Column: Quick Actions -->
        <div class="space-y-6">
          <!-- Quick Actions Card -->
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
                :href="route('student.account', { tab: 'payment' })"
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

          <!-- Payment Reminder -->
          <div v-if="stats.remaining_balance > 0" class="bg-red-50 border border-red-200 rounded-lg p-6">
            <div class="flex items-center gap-2 mb-3">
              <AlertCircle :size="20" class="text-red-600" />
              <h3 class="font-semibold text-red-900">Payment Reminder</h3>
            </div>
            <p class="text-sm text-gray-700 mb-4">
              You have an outstanding balance of <strong>{{ formatCurrency(stats.remaining_balance) }}</strong>
            </p>
            <Link
              :href="route('student.account', { tab: 'payment' })"
              class="block w-full text-center px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition-colors"
            >
              Pay Now
            </Link>
          </div>

          <!-- Payment Success -->
          <div v-else class="bg-green-50 border border-green-200 rounded-lg p-6">
            <div class="flex items-center gap-2 mb-3">
              <CheckCircle :size="20" class="text-green-600" />
              <h3 class="font-semibold text-green-900">All Paid!</h3>
            </div>
            <p class="text-sm text-gray-700">
              Great job! You have no outstanding balance.
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Transaction Details Dialog -->
    <Dialog v-model:open="showDetailsDialog">
      <DialogContent class="max-w-2xl max-h-[80vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Transaction Details</DialogTitle>
          <DialogDescription>
            Complete information about this transaction
          </DialogDescription>
        </DialogHeader>

        <div v-if="selectedTransaction" class="space-y-6">
          <!-- Basic Information -->
          <div class="space-y-4">
            <h3 class="font-semibold text-lg border-b pb-2">Basic Information</h3>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <p class="text-sm text-gray-600">Reference Number</p>
                <p class="font-mono font-medium">{{ selectedTransaction.reference }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-600">Date</p>
                <p class="font-medium">{{ formatDate(selectedTransaction.created_at) }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-600">Transaction Type</p>
                <span 
                  class="inline-block px-2 py-1 text-xs font-semibold rounded-full"
                  :class="selectedTransaction.kind === 'charge' 
                    ? 'bg-red-100 text-red-800' 
                    : 'bg-green-100 text-green-800'"
                >
                  {{ selectedTransaction.kind }}
                </span>
              </div>
              <div>
                <p class="text-sm text-gray-600">Status</p>
                <span 
                  class="inline-block px-2 py-1 text-xs font-semibold rounded-full"
                  :class="{
                    'bg-green-100 text-green-800': selectedTransaction.status === 'paid',
                    'bg-yellow-100 text-yellow-800': selectedTransaction.status === 'pending',
                    'bg-red-100 text-red-800': selectedTransaction.status === 'failed',
                    'bg-gray-100 text-gray-800': selectedTransaction.status === 'cancelled'
                  }"
                >
                  {{ selectedTransaction.status }}
                </span>
              </div>
              <div>
                <p class="text-sm text-gray-600">Category</p>
                <p class="font-medium">{{ selectedTransaction.type }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-600">Amount</p>
                <p 
                  class="text-xl font-bold"
                  :class="selectedTransaction.kind === 'charge' ? 'text-red-600' : 'text-green-600'"
                >
                  {{ selectedTransaction.kind === 'charge' ? '+' : '-' }}₱{{ formatCurrency(selectedTransaction.amount).replace('₱', '') }}
                </p>
              </div>
            </div>
          </div>

          <!-- Payment Information (if payment) -->
          <div v-if="selectedTransaction.kind === 'payment'" class="space-y-4">
            <h3 class="font-semibold text-lg border-b pb-2">Payment Information</h3>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <p class="text-sm text-gray-600">Payment Method</p>
                <p class="font-medium capitalize">{{ selectedTransaction.payment_channel || 'N/A' }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-600">Payment Date</p>
                <p class="font-medium">{{ selectedTransaction.paid_at ? formatDate(selectedTransaction.paid_at) : 'N/A' }}</p>
              </div>
            </div>
          </div>

          <!-- Fee Breakdown (if charge with metadata) -->
          <div v-if="selectedTransaction.kind === 'charge'" class="space-y-4">
            <h3 class="font-semibold text-lg border-b pb-2">Fee Breakdown</h3>
            <div class="bg-gray-50 rounded-lg p-4 space-y-3">
              <div class="flex justify-between items-center">
                <span class="text-gray-700">{{ selectedTransaction.type }}</span>
                <span class="font-semibold">₱{{ formatCurrency(selectedTransaction.amount).replace('₱', '') }}</span>
              </div>
              <div v-if="selectedTransaction.year && selectedTransaction.semester" class="text-sm text-gray-600 pt-2 border-t">
                <p>Academic Year: {{ selectedTransaction.year }}</p>
                <p>Semester: {{ selectedTransaction.semester }}</p>
              </div>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="flex justify-end gap-3 pt-4 border-t">
            <Button variant="outline" @click="closeDetailsDialog">
              Close
            </Button>
            <Button v-if="selectedTransaction.status === 'paid'" @click="downloadPDF">
              Download PDF
            </Button>
            <Button 
              v-if="selectedTransaction.status === 'pending' && selectedTransaction.kind === 'charge'"
              variant="destructive"
              as-child
            >
              <Link :href="route('student.account', { tab: 'payment' })" @click="closeDetailsDialog">
                Pay Now
              </Link>
            </Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  </AppLayout>
</template>