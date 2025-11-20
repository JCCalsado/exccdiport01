<script setup lang="ts">
/**
 * Student Account Overview (IMPROVED)
 * Location: resources/js/pages/Student/AccountOverview.vue
 * 
 * Key improvements:
 * - Uses reusable composables and components
 * - Better tab management with URL sync
 * - Improved payment form validation
 * - Better error handling
 * - Cleaner computed properties
 */

import { ref, computed, watch, onMounted } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import TransactionDetailsDialog from '@/components/TransactionDetailsDialog.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { useFormatters } from '@/composables/useFormatters'
import type { Transaction, Account, Assessment, PaymentMethod } from '@/types/transaction'
import {
  CreditCard,
  Calendar,
  CheckCircle,
  AlertCircle,
  Clock,
  Receipt,
  History,
  DollarSign,
} from 'lucide-vue-next'

interface Fee {
  name: string
  amount: number
  category?: string
}

interface Props {
  account: Account
  transactions: Transaction[]
  fees: Fee[]
  currentTerm?: { year: number; semester: string }
  tab?: 'fees' | 'history' | 'payment'
  latestAssessment?: Assessment
}

const props = withDefaults(defineProps<Props>(), {
  currentTerm: () => ({
    year: new Date().getFullYear(),
    semester: '1st Sem'
  }),
  tab: 'fees'
})

const { formatCurrency, formatDate, formatPercentage } = useFormatters()

const breadcrumbs = [
  { title: 'Dashboard', href: route('dashboard') },
  { title: 'My Account' },
]

// Tab Management
const getTabFromUrl = (): 'fees' | 'history' | 'payment' => {
  const urlParams = new URLSearchParams(window.location.search)
  const tab = urlParams.get('tab')
  return (tab === 'payment' || tab === 'history') ? tab : 'fees'
}

const activeTab = ref<'fees' | 'history' | 'payment'>(
  props.tab || getTabFromUrl()
)

// Dialog state
const showDetailsDialog = ref(false)
const selectedTransaction = ref<Transaction | null>(null)

// Payment Form
const paymentForm = useForm({
  amount: 0,
  payment_method: 'cash' as PaymentMethod,
  reference_number: '',
  paid_at: new Date().toISOString().split('T')[0],
  description: 'Payment for fees',
})

// Computed: Assessment totals
const totalAssessmentFee = computed(() => {
  return props.latestAssessment?.total_assessment ??
    (props.fees || []).reduce((sum, fee) => sum + Number(fee.amount || 0), 0)
})

const totalPaid = computed(() => {
  return (props.transactions || [])
    .filter(t => t.kind === 'payment' && t.status === 'paid')
    .reduce((sum, t) => sum + Number(t.amount || 0), 0)
})

const remainingBalance = computed(() => {
  const charges = (props.transactions || [])
    .filter(t => t.kind === 'charge')
    .reduce((sum, t) => sum + Number(t.amount || 0), 0)

  const payments = (props.transactions || [])
    .filter(t => t.kind === 'payment' && t.status === 'paid')
    .reduce((sum, t) => sum + Number(t.amount || 0), 0)

  const diff = charges - payments
  return Math.max(0, Math.round(diff * 100) / 100)
})

// Computed: Payment percentage
const paymentPercentage = computed(() => {
  if (totalAssessmentFee.value === 0) return 0
  return Math.min(100, Math.round((totalPaid.value / totalAssessmentFee.value) * 100))
})

// Computed: Grouped fees by category
const feesByCategory = computed(() => {
  const grouped = (props.fees || []).reduce((acc, fee) => {
    const category = fee.category || 'Other'
    if (!acc[category]) acc[category] = []
    acc[category].push(fee)
    return acc
  }, {} as Record<string, Fee[]>)

  return Object.entries(grouped).map(([category, fees]) => ({
    category,
    fees,
    total: fees.reduce((sum, f) => sum + Number(f.amount || 0), 0)
  }))
})

// Computed: Payment history
const paymentHistory = computed(() => {
  return (props.transactions || [])
    .filter(t => t.kind === 'payment')
    .sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime())
})

// Computed: Pending charges
const pendingCharges = computed(() => {
  return (props.transactions || [])
    .filter(t => t.kind === 'charge' && t.status === 'pending')
})

// Computed: Can submit payment
const canSubmitPayment = computed(() => {
  return remainingBalance.value > 0 && 
    paymentForm.amount > 0 && 
    paymentForm.amount <= remainingBalance.value &&
    !paymentForm.processing
})

// Computed: Payment form errors
const paymentFormErrors = computed(() => {
  const errors: string[] = []
  
  if (paymentForm.amount <= 0) {
    errors.push('Amount must be greater than zero')
  }
  if (paymentForm.amount > remainingBalance.value) {
    errors.push('Amount cannot exceed remaining balance')
  }
  if (!paymentForm.payment_method) {
    errors.push('Please select a payment method')
  }
  if (!paymentForm.paid_at) {
    errors.push('Please select a payment date')
  }
  
  return errors
})

// Watch for tab changes
watch(() => props.tab, (newTab) => {
  if (newTab) activeTab.value = newTab
})

onMounted(() => {
  const urlTab = getTabFromUrl()
  if (urlTab) activeTab.value = urlTab
})

// Methods
const viewTransaction = (transaction: Transaction) => {
  selectedTransaction.value = transaction
  showDetailsDialog.value = true
}

const handlePayNow = (transaction: Transaction) => {
  // Pre-fill payment form with transaction data
  paymentForm.amount = transaction.amount
  paymentForm.description = `Payment for ${transaction.type}`
  activeTab.value = 'payment'
}

const submitPayment = () => {
  // Validate form
  if (!canSubmitPayment.value) {
    if (paymentFormErrors.value.length > 0) {
      paymentForm.setError('amount', paymentFormErrors.value[0])
    }
    return
  }

  paymentForm.post(route('account.pay-now'), {
    preserveScroll: true,
    onSuccess: () => {
      // Reset form
      paymentForm.reset()
      paymentForm.amount = 0
      paymentForm.payment_method = 'cash'
      paymentForm.paid_at = new Date().toISOString().split('T')[0]
      paymentForm.description = 'Payment for fees'
      
      // Switch to history tab
      activeTab.value = 'history'
    },
    onError: (errors) => {
      console.error('Payment errors:', errors)
    },
  })
}

const downloadPDF = () => {
  // Implement download logic
  console.log('Download PDF')
}

// Quick payment presets
const setPaymentAmount = (percentage: number) => {
  paymentForm.amount = Math.round(remainingBalance.value * (percentage / 100) * 100) / 100
}
</script>

<template>
  <AppLayout>
    <Head title="My Account" />

    <div class="w-full p-6">
      <Breadcrumbs :items="breadcrumbs" />

      <!-- Header -->
      <div class="mb-6">
        <h1 class="text-3xl font-bold">My Account Overview</h1>
        <p v-if="currentTerm" class="text-gray-600 mt-1">
          {{ currentTerm.semester }} - {{ currentTerm.year }}-{{ currentTerm.year + 1 }}
        </p>
        <p v-if="latestAssessment" class="text-sm text-gray-500 mt-1">
          Assessment No: {{ latestAssessment.assessment_number }}
        </p>
      </div>

      <!-- Balance Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- Total Assessment -->
        <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
          <div class="flex items-center justify-between mb-2">
            <div class="p-3 bg-blue-100 rounded-lg">
              <Receipt :size="24" class="text-blue-600" />
            </div>
          </div>
          <h3 class="text-sm font-medium text-gray-600 mb-2">Total Assessment Fee</h3>
          <p class="text-3xl font-bold text-blue-600">
            {{ formatCurrency(totalAssessmentFee) }}
          </p>
          <p v-if="latestAssessment" class="text-xs text-gray-500 mt-2">
            Tuition: {{ formatCurrency(latestAssessment.tuition_fee) }} • 
            Other: {{ formatCurrency(latestAssessment.other_fees) }}
          </p>
        </div>

        <!-- Total Paid -->
        <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
          <div class="flex items-center justify-between mb-2">
            <div class="p-3 bg-green-100 rounded-lg">
              <CheckCircle :size="24" class="text-green-600" />
            </div>
          </div>
          <h3 class="text-sm font-medium text-gray-600 mb-2">Total Paid</h3>
          <p class="text-3xl font-bold text-green-600">
            {{ formatCurrency(totalPaid) }}
          </p>
          <p class="text-xs text-gray-500 mt-2">
            {{ paymentHistory.length }} payment(s) made
          </p>
        </div>

        <!-- Current Balance -->
        <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
          <div class="flex items-center justify-between mb-2">
            <div :class="[
              'p-3 rounded-lg',
              remainingBalance > 0 ? 'bg-red-100' : 'bg-green-100'
            ]">
              <component 
                :is="remainingBalance > 0 ? AlertCircle : CheckCircle" 
                :size="24" 
                :class="remainingBalance > 0 ? 'text-red-600' : 'text-green-600'" 
              />
            </div>
          </div>
          <h3 class="text-sm font-medium text-gray-600 mb-2">Current Balance</h3>
          <p class="text-3xl font-bold" :class="remainingBalance > 0 ? 'text-red-600' : 'text-green-600'">
            {{ formatCurrency(remainingBalance) }}
          </p>
          <p class="text-xs text-gray-500 mt-2">
            {{ remainingBalance > 0 ? 'Amount due' : 'Fully paid' }}
          </p>
        </div>
      </div>

      <!-- Payment Progress Bar -->
      <div v-if="totalAssessmentFee > 0" class="bg-white rounded-lg shadow-md p-6 mb-6">
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
          <span>{{ formatCurrency(totalPaid) }} paid</span>
          <span>{{ formatCurrency(totalAssessmentFee) }} total</span>
        </div>
      </div>

      <!-- Tabs -->
      <div class="bg-white rounded-lg shadow-md mb-6">
        <div class="border-b">
          <nav class="flex gap-4 px-6">
            <button
              @click="activeTab = 'fees'"
              :class="[
                'py-4 px-2 border-b-2 font-medium text-sm transition-colors flex items-center gap-2',
                activeTab === 'fees'
                  ? 'border-blue-600 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700',
              ]"
            >
              <Receipt :size="16" />
              Fees & Assessment
            </button>
            <button
              @click="activeTab = 'history'"
              :class="[
                'py-4 px-2 border-b-2 font-medium text-sm transition-colors flex items-center gap-2',
                activeTab === 'history'
                  ? 'border-blue-600 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700',
              ]"
            >
              <History :size="16" />
              Payment History
            </button>
            <button
              @click="activeTab = 'payment'"
              :class="[
                'py-4 px-2 border-b-2 font-medium text-sm transition-colors flex items-center gap-2',
                activeTab === 'payment'
                  ? 'border-blue-600 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700',
              ]"
            >
              <CreditCard :size="16" />
              Make Payment
            </button>
          </nav>
        </div>

        <!-- Tab Content -->
        <div class="p-6">
          <!-- Fees Tab -->
          <div v-if="activeTab === 'fees'" class="space-y-6">
            <h2 class="text-lg font-semibold">CURRENT ASSESSMENT</h2>
            
            <!-- Assessment Info Banner -->
            <div v-if="latestAssessment" class="bg-blue-50 rounded-lg p-4 border border-blue-200">
              <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                  <span class="text-gray-600">Assessment No:</span>
                  <p class="font-semibold">{{ latestAssessment.assessment_number }}</p>
                </div>
                <div>
                  <span class="text-gray-600">School Year:</span>
                  <p class="font-semibold">{{ latestAssessment.school_year }}</p>
                </div>
                <div>
                  <span class="text-gray-600">Semester:</span>
                  <p class="font-semibold">{{ latestAssessment.semester }}</p>
                </div>
                <div>
                  <span class="text-gray-600">Status:</span>
                  <span :class="[
                    'px-2 py-1 text-xs font-semibold rounded-full',
                    latestAssessment.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                  ]">
                    {{ latestAssessment.status }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Fees by Category -->
            <div v-if="feesByCategory.length" class="space-y-6">
              <div v-for="categoryGroup in feesByCategory" :key="categoryGroup.category" class="space-y-2">
                <h3 class="font-semibold text-gray-700 uppercase text-sm border-b pb-2">
                  {{ categoryGroup.category }}
                </h3>
                <div
                  v-for="(fee, index) in categoryGroup.fees"
                  :key="index"
                  class="flex justify-between py-2 pl-4"
                >
                  <span class="text-gray-700">{{ fee.name }}</span>
                  <span class="font-medium">{{ formatCurrency(fee.amount) }}</span>
                </div>
                <div class="flex justify-between font-semibold text-sm pt-2 pl-4 border-t">
                  <span>{{ categoryGroup.category }} Subtotal</span>
                  <span>{{ formatCurrency(categoryGroup.total) }}</span>
                </div>
              </div>

              <div class="flex justify-between font-bold border-t-2 pt-4 text-lg">
                <span>TOTAL ASSESSMENT FEE</span>
                <span class="text-blue-600">{{ formatCurrency(totalAssessmentFee) }}</span>
              </div>
            </div>

            <p v-else class="text-gray-500 text-center py-4">
              No fees assigned yet.
            </p>

            <!-- Pending Charges -->
            <div v-if="pendingCharges.length" class="border-t pt-6">
              <h3 class="text-md font-semibold mb-4 text-red-700 flex items-center gap-2">
                <Clock :size="20" />
                PENDING CHARGES
              </h3>
              <div class="space-y-3">
                <div
                  v-for="charge in pendingCharges"
                  :key="charge.id"
                  class="flex justify-between items-center p-3 bg-red-50 rounded border border-red-200 cursor-pointer hover:bg-red-100"
                  @click="viewTransaction(charge)"
                >
                  <div>
                    <p class="font-medium text-gray-900">
                      {{ charge.fee?.name || charge.meta?.fee_name || charge.meta?.subject_name || charge.type }}
                    </p>
                    <p class="text-xs text-gray-600">{{ charge.reference }}</p>
                    <p v-if="charge.meta?.subject_code" class="text-xs text-gray-500">
                      {{ charge.meta.subject_code }}
                    </p>
                  </div>
                  <div class="text-right flex flex-col items-end gap-2">
                    <div>
                      <p class="text-lg font-semibold text-red-600">{{ formatCurrency(charge.amount) }}</p>
                      <span class="text-xs px-2 py-1 bg-yellow-100 text-yellow-800 rounded">Pending</span>
                    </div>
                    <Button
                      size="sm"
                      variant="destructive"
                      @click.stop="handlePayNow(charge)"
                    >
                      Pay Now
                    </Button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Payment History Tab -->
          <div v-if="activeTab === 'history'" class="space-y-4">
            <h2 class="text-lg font-semibold">Payment History</h2>
            
            <div v-if="paymentHistory.length" class="space-y-3">
              <div
                v-for="payment in paymentHistory"
                :key="payment.id"
                class="flex justify-between items-center p-4 border rounded-lg hover:bg-gray-50 transition-colors cursor-pointer"
                @click="viewTransaction(payment)"
              >
                <div class="flex items-center gap-3">
                  <div class="p-2 bg-green-100 rounded">
                    <CheckCircle :size="20" class="text-green-600" />
                  </div>
                  <div>
                    <p class="font-medium text-gray-900">{{ payment.meta?.description || payment.type }}</p>
                    <p class="text-sm text-gray-600">
                      {{ formatDate(payment.created_at, 'short') }}
                    </p>
                    <p class="text-xs text-gray-500">{{ payment.reference }}</p>
                  </div>
                </div>
                <div class="text-right">
                  <p class="text-lg font-semibold text-green-600">{{ formatCurrency(payment.amount) }}</p>
                  <span class="text-xs px-2 py-1 bg-green-100 text-green-800 rounded">
                    {{ payment.status }}
                  </span>
                </div>
              </div>
            </div>

            <div v-else class="text-center py-12">
              <History :size="48" class="text-gray-400 mx-auto mb-3" />
              <p class="text-gray-500">No payment history yet</p>
              <p class="text-sm text-gray-400 mt-1">Your payments will appear here after you make them</p>
            </div>
          </div>

          <!-- Payment Form Tab -->
          <div v-if="activeTab === 'payment'" class="space-y-6">
            <h2 class="text-2xl font-bold">Add New Payment</h2>
            
            <!-- No Balance Message -->
            <div v-if="remainingBalance <= 0" class="p-4 bg-green-50 border border-green-200 rounded-lg">
              <div class="flex items-center gap-2">
                <CheckCircle :size="20" class="text-green-600" />
                <p class="text-green-800 font-medium">You have no outstanding balance!</p>
              </div>
              <p class="text-sm text-green-700 mt-1">All fees have been paid in full.</p>
            </div>

            <form @submit.prevent="submitPayment" class="space-y-6">

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Amount -->
                <div class="space-y-2">
                  <Label for="amount">Amount *</Label>
                  <Input
                    id="amount"
                    v-model.number="paymentForm.amount"
                    type="number"
                    step="0.01"
                    min="0"
                    :max="remainingBalance"
                    placeholder="0.00"
                    required
                    :disabled="remainingBalance <= 0"
                  />
                  <p class="text-xs text-gray-500">
                    Maximum: {{ formatCurrency(remainingBalance) }}
                  </p>
                  <p v-if="paymentForm.errors.amount" class="text-red-500 text-sm">
                    {{ paymentForm.errors.amount }}
                  </p>
                </div>

                <!-- Payment Method -->
                <div class="space-y-2">
                  <Label for="payment_method">Payment Method *</Label>
                  <select
                    id="payment_method"
                    v-model="paymentForm.payment_method"
                    :disabled="remainingBalance <= 0"
                    class="w-full px-4 py-2 border rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none disabled:bg-gray-100 disabled:cursor-not-allowed"
                  >
                    <option value="cash">Cash</option>
                    <option value="gcash">GCash</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="credit_card">Credit Card</option>
                    <option value="debit_card">Debit Card</option>
                  </select>
                  <p v-if="paymentForm.errors.payment_method" class="text-red-500 text-sm">
                    {{ paymentForm.errors.payment_method }}
                  </p>
                </div>

                <!-- Reference Number Info -->
                <div class="space-y-2">
                  <Label>
                    Reference Number
                    <span class="text-xs text-gray-500">(Auto-generated)</span>
                  </Label>
                  <Input
                    value="System will generate after submission"
                    disabled
                    class="bg-gray-100 cursor-not-allowed text-gray-500"
                  />
                  <p class="text-xs text-gray-500">
                    Reference number will be automatically generated
                  </p>
                </div>

                <!-- Payment Date -->
                <div class="space-y-2">
                  <Label for="paid_at">Payment Date *</Label>
                  <Input
                    id="paid_at"
                    v-model="paymentForm.paid_at"
                    type="date"
                    required
                    :disabled="remainingBalance <= 0"
                  />
                  <p v-if="paymentForm.errors.paid_at" class="text-red-500 text-sm">
                    {{ paymentForm.errors.paid_at }}
                  </p>
                </div>

                <!-- Description -->
                <div class="md:col-span-2 space-y-2">
                  <Label for="description">Description *</Label>
                  <Input
                    id="description"
                    v-model="paymentForm.description"
                    placeholder="Payment description"
                    required
                    :disabled="remainingBalance <= 0"
                  />
                  <p v-if="paymentForm.errors.description" class="text-red-500 text-sm">
                    {{ paymentForm.errors.description }}
                  </p>
                </div>
              </div>

              <!-- Submit Button -->
              <Button
                type="submit"
                class="w-full"
                :disabled="!canSubmitPayment || paymentForm.processing"
              >
                <DollarSign v-if="!paymentForm.processing" class="w-4 h-4 mr-2" />
                <span v-if="paymentForm.processing">Processing...</span>
                <span v-else-if="remainingBalance <= 0">No Balance to Pay</span>
                <span v-else>Record Payment</span>
              </Button>
            </form>
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
      @download="downloadPDF"
    />
  </AppLayout>
</template>
