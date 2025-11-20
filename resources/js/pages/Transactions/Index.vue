<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import TransactionDetailsDialog from '@/components/TransactionDetailsDialog.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { useFormatters } from '@/composables/useFormatters'
import type { 
  Transaction, 
  Account, 
  TransactionsByTerm, 
  TermSummary 
} from '@/types/transaction'
import { 
  ChevronDown, 
  Search, 
  Download, 
  Eye, 
  CreditCard,
  TrendingUp,
  TrendingDown,
  Minus,
} from 'lucide-vue-next'

interface Props {
  auth: {
    user: {
      id: number
      name: string
      role: string
    }
  }
  transactionsByTerm?: TransactionsByTerm
  account?: Account
  currentTerm?: string
}

const props = withDefaults(defineProps<Props>(), {
  transactionsByTerm: () => ({}),
  account: () => ({ id: 0, user_id: 0, balance: 0 }),
  currentTerm: () => ''
})

const { formatCurrency, formatDate } = useFormatters()

const breadcrumbs = [
  { title: 'Dashboard', href: route('dashboard') },
  { title: 'Transaction History' },
]

// State
const search = ref('')
const expanded = ref<Record<string, boolean>>({})
const showPastSemesters = ref(false)
const selectedTransaction = ref<Transaction | null>(null)
const showDetailsDialog = ref(false)

// Initialize current term as expanded
if (props.currentTerm && props.transactionsByTerm && props.transactionsByTerm[props.currentTerm]) {
  expanded.value[props.currentTerm] = true
}

// Computed: Is user staff
const isStaff = computed(() => {
  return ['admin', 'accounting'].includes(props.auth.user.role)
})

// Computed: Total terms count
const totalTermsCount = computed(() => {
  return Object.keys(props.transactionsByTerm || {}).length
})

// Computed: Filtered transactions by term
const filteredTransactionsByTerm = computed(() => {
  if (!props.transactionsByTerm) return {}
  
  let terms = props.transactionsByTerm

  // Filter out past semesters if not showing them
  if (!showPastSemesters.value && props.currentTerm && terms[props.currentTerm]) {
    terms = { [props.currentTerm]: terms[props.currentTerm] }
  }

  // Apply search filter
  if (!search.value) return terms

  const searchLower = search.value.toLowerCase()
  const filtered: TransactionsByTerm = {}

  Object.entries(terms).forEach(([term, transactions]) => {
    if (!transactions || !Array.isArray(transactions)) return
    
    const matchingTransactions = transactions.filter(txn => 
      txn.reference?.toLowerCase().includes(searchLower) ||
      txn.type?.toLowerCase().includes(searchLower) ||
      txn.user?.name?.toLowerCase().includes(searchLower) ||
      txn.user?.student_id?.toLowerCase().includes(searchLower)
    )

    if (matchingTransactions.length > 0) {
      filtered[term] = matchingTransactions
    }
  })

  return filtered
})

// Methods
const toggle = (key: string) => {
  expanded.value[key] = !expanded.value[key]
}

const calculateTermSummary = (transactions: Transaction[]): TermSummary => {
  if (!transactions || !Array.isArray(transactions)) {
    return {
      total_assessment: 0,
      total_paid: 0,
      current_balance: 0,
      transaction_count: 0,
    }
  }
  
  const charges = transactions
    .filter(t => t && t.kind === 'charge')
    .reduce((sum, t) => sum + parseFloat(String(t.amount || 0)), 0)
  
  const payments = transactions
    .filter(t => t && t.kind === 'payment' && t.status === 'paid')
    .reduce((sum, t) => sum + parseFloat(String(t.amount || 0)), 0)
  
  return {
    total_assessment: charges,
    total_paid: payments,
    current_balance: charges - payments,
    transaction_count: transactions.length,
  }
}

const viewTransaction = (transaction: Transaction) => {
  selectedTransaction.value = transaction
  showDetailsDialog.value = true
}

const downloadPDF = (termKey?: string) => {
  const params = termKey ? { term: termKey } : {}
  router.get(route('transactions.download', params), {}, { 
    preserveScroll: true 
  })
}

const payNow = (transaction: Transaction) => {
  router.visit(route('student.account', {
    tab: 'payment',
    transaction_id: transaction.id,
    reference: transaction.reference,
    amount: transaction.amount,
    category: transaction.type
  }))
}

const handleDownloadFromDialog = (transaction: Transaction) => {
  downloadPDF(`${transaction.year} ${transaction.semester}`)
}

// Status badge config
const getStatusBadge = (status: string) => {
  const configs = {
    paid: { variant: 'success' as const, label: 'Paid' },
    pending: { variant: 'warning' as const, label: 'Pending' },
    failed: { variant: 'destructive' as const, label: 'Failed' },
    cancelled: { variant: 'secondary' as const, label: 'Cancelled' },
  }
  return configs[status as keyof typeof configs] || configs.pending
}

const getKindBadge = (kind: string) => {
  return kind === 'charge'
    ? { variant: 'destructive' as const, label: 'Charge' }
    : { variant: 'success' as const, label: 'Payment' }
}
</script>

<template>
  <AppLayout>
    <Head title="Transaction History" />

    <div class="space-y-6 w-full p-6">
      <Breadcrumbs :items="breadcrumbs" />

      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h1 class="text-3xl font-bold">Transaction History</h1>
          <p class="text-gray-500">View all your financial transactions by term</p>
        </div>
        <div class="flex gap-2">
          <Button 
            v-if="totalTermsCount > 1"
            variant="outline"
            @click="showPastSemesters = !showPastSemesters"
          >
            {{ showPastSemesters ? 'Hide Past Semesters' : 'Show Past Semesters' }}
          </Button>
          <Button 
            variant="outline"
            @click="downloadPDF()"
          >
            <Download :size="16" class="mr-2" />
            Export All
          </Button>
        </div>
      </div>

      <!-- Current Balance Card (Students only) -->
      <div v-if="!isStaff && account" class="p-6 rounded-xl border bg-gradient-to-r from-blue-50 to-indigo-50 shadow-sm">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="font-semibold text-lg">Current Balance</h2>
            <p class="text-gray-500 text-sm">Your outstanding balance</p>
          </div>
          <div class="text-right">
            <p 
              class="text-4xl font-bold"
              :class="(account.balance || 0) > 0 ? 'text-red-600' : 'text-green-600'"
            >
              {{ formatCurrency(Math.abs(account.balance || 0)) }}
            </p>
            <p class="text-sm text-gray-600 mt-1">
              {{ (account.balance || 0) > 0 ? 'Amount Due' : 'All Paid' }}
            </p>
          </div>
        </div>
      </div>

      <!-- Search Bar (Admin + Accounting only) -->
      <div v-if="isStaff" class="p-4 border rounded-xl shadow-sm bg-white">
        <div class="relative">
          <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" :size="20" />
          <Input
            v-model="search"
            type="text"
            class="pl-10"
            placeholder="Search by reference, type, or student..."
          />
        </div>
      </div>

      <!-- No Results -->
      <div v-if="Object.keys(filteredTransactionsByTerm).length === 0" class="text-center py-12 bg-white rounded-lg shadow-sm">
        <Search :size="48" class="text-gray-400 mx-auto mb-3" />
        <p class="text-gray-500 text-lg">No transactions found</p>
        <p class="text-sm text-gray-400 mt-2">Try adjusting your search criteria</p>
      </div>

      <!-- Terms -->
      <div 
        v-for="(transactions, termKey) in filteredTransactionsByTerm" 
        :key="termKey" 
        class="border rounded-xl shadow-sm bg-white overflow-hidden"
      >
        <!-- Summary Header -->
        <div
          class="flex justify-between items-center p-5 cursor-pointer hover:bg-gray-50 transition-colors"
          @click="toggle(termKey)"
        >
          <div>
            <div class="flex items-center gap-3">
              <h2 class="font-bold text-xl">{{ termKey }}</h2>
              <Badge 
                v-if="termKey === currentTerm"
                variant="info"
              >
                Current
              </Badge>
            </div>
            <p class="text-gray-500 mt-1">
              {{ transactions.length }} transaction{{ transactions.length !== 1 ? 's' : '' }}
            </p>
          </div>

          <!-- Summary Stats -->
          <div class="flex items-center gap-8">
            <!-- Total Assessment -->
            <div class="text-right hidden md:block">
              <p class="text-xs text-gray-500 flex items-center justify-end gap-1">
                <TrendingUp :size="14" />
                Total Assessment
              </p>
              <p class="text-red-600 font-bold">
                {{ formatCurrency(calculateTermSummary(transactions).total_assessment) }}
              </p>
            </div>

            <!-- Total Paid -->
            <div class="text-right hidden md:block">
              <p class="text-xs text-gray-500 flex items-center justify-end gap-1">
                <TrendingDown :size="14" />
                Total Paid
              </p>
              <p class="text-green-600 font-bold">
                {{ formatCurrency(calculateTermSummary(transactions).total_paid) }}
              </p>
            </div>

            <!-- Current Balance -->
            <div class="text-right">
              <p class="text-xs text-gray-500 flex items-center justify-end gap-1">
                <Minus :size="14" />
                Balance
              </p>
              <p 
                class="font-bold"
                :class="calculateTermSummary(transactions).current_balance > 0 ? 'text-red-600' : 'text-green-600'"
              >
                {{ formatCurrency(Math.abs(calculateTermSummary(transactions).current_balance)) }}
              </p>
            </div>

            <!-- Download Button -->
            <Button
              variant="outline"
              size="sm"
              @click.stop="downloadPDF(termKey)"
            >
              <Download :size="16" />
            </Button>

            <!-- Expand Icon -->
            <ChevronDown
              :class="['transition-transform', expanded[termKey] && 'rotate-180']"
              :size="24"
            />
          </div>
        </div>

        <!-- Expanded Table -->
        <div v-if="expanded[termKey]" class="p-5 border-t bg-gray-50">
          <div class="overflow-x-auto bg-white rounded-lg">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="bg-gray-100 text-gray-600 text-sm">
                  <th class="p-3 font-medium">Reference</th>
                  <th v-if="isStaff" class="p-3 font-medium">Student</th>
                  <th class="p-3 font-medium">Type</th>
                  <th class="p-3 font-medium">Category</th>
                  <th class="p-3 font-medium">Amount</th>
                  <th class="p-3 font-medium">Status</th>
                  <th class="p-3 font-medium">Date</th>
                  <th class="p-3 font-medium text-right">Actions</th>
                </tr>
              </thead>

              <tbody>
                <tr
                  v-for="t in transactions"
                  :key="t.id"
                  class="border-b hover:bg-gray-50 transition-colors"
                >
                  <!-- Reference -->
                  <td class="p-3 font-mono text-sm">{{ t.reference }}</td>
                  
                  <!-- Student Info (Staff only) -->
                  <td v-if="isStaff" class="p-3 text-sm">
                    <div>
                      <p class="font-medium">{{ t.user?.name }}</p>
                      <p class="text-xs text-gray-500">{{ t.user?.student_id }}</p>
                    </div>
                  </td>
                  
                  <!-- Type Badge -->
                  <td class="p-3">
                    <Badge :variant="getKindBadge(t.kind).variant">
                      {{ getKindBadge(t.kind).label }}
                    </Badge>
                  </td>
                  
                  <!-- Category -->
                  <td class="p-3 text-sm">{{ t.type }}</td>
                  
                  <!-- Amount -->
                  <td 
                    class="p-3 font-semibold"
                    :class="t.kind === 'charge' ? 'text-red-600' : 'text-green-600'"
                  >
                    {{ t.kind === 'charge' ? '+' : '-' }}{{ formatCurrency(t.amount) }}
                  </td>
                  
                  <!-- Status Badge -->
                  <td class="p-3">
                    <Badge :variant="getStatusBadge(t.status).variant">
                      {{ getStatusBadge(t.status).label }}
                    </Badge>
                  </td>
                  
                  <!-- Date -->
                  <td class="p-3 text-sm text-gray-600">{{ formatDate(t.created_at, 'short') }}</td>
                  
                  <!-- Actions -->
                  <td class="p-3">
                    <div class="flex justify-end gap-2">
                      <Button 
                        size="sm"
                        variant="outline"
                        @click="viewTransaction(t)"
                      >
                        <Eye :size="14" class="mr-1" />
                        View
                      </Button>
                      <Button 
                        v-if="t.status === 'pending' && t.kind === 'charge' && !isStaff"
                        size="sm"
                        variant="destructive"
                        @click="payNow(t)"
                      >
                        <CreditCard :size="14" class="mr-1" />
                        Pay
                      </Button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Transaction Details Dialog -->
    <TransactionDetailsDialog
      v-model:open="showDetailsDialog"
      :transaction="selectedTransaction"
      :show-student-info="isStaff"
      :show-pay-now-button="!isStaff"
      :show-download-button="true"
      @pay-now="payNow"
      @download="handleDownloadFromDialog"
    />
  </AppLayout>
</template>