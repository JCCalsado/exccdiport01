<script setup lang="ts">
import { ref, computed, type ComputedRef } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue'

/* --------------------------------------------------------------------------
 *  Types
 * -------------------------------------------------------------------------- */

type Transaction = {
  id: number
  reference: string
  amount: number
  status: string
  type: string // e.g. Prelim, Midterm, Finals
  kind?: string // "charge" | "payment"
  created_at: string
  user?: { id: number; name: string }
  meta?: { items?: { name: string; amount: number }[] }
}

type TransactionsByTerm = Record<string, Transaction[]>

/* --------------------------------------------------------------------------
 *  Props
 * -------------------------------------------------------------------------- */

const props = defineProps<{
  auth: { user: { id: number; name: string; email: string; role: string } }
  transactionsByTerm: TransactionsByTerm
  account?: { balance: number } | null
  currentTerm: string
}>()

/* --------------------------------------------------------------------------
 *  Breadcrumbs
 * -------------------------------------------------------------------------- */

const breadcrumbItems = [
  { title: 'Dashboard', href: route('dashboard') },
  { title: 'Transactions', href: route('transactions.index') },
]

/* --------------------------------------------------------------------------
 *  State
 * -------------------------------------------------------------------------- */

const selectedTransaction = ref<Transaction | null>(null)
const showModal = ref(false)
const showPast = ref(false)

/* --------------------------------------------------------------------------
 *  Modal Controls
 * -------------------------------------------------------------------------- */

const openViewModal = (transaction: Transaction) => {
  selectedTransaction.value = transaction
  showModal.value = true
}

const closeModal = () => (showModal.value = false)

/* --------------------------------------------------------------------------
 *  Helpers
 * -------------------------------------------------------------------------- */

const downloadPdf = (transactionId: number) => {
  window.open(route('transactions.download', transactionId), '_blank')
}

const payNow = (txn: Transaction) => {
  window.location.href = route('transactions.payNow', { id: txn.id })
}

/* --------------------------------------------------------------------------
 *  Computed Data
 * -------------------------------------------------------------------------- */

// Compute term list as tuples [term, Transaction[]]
const terms: ComputedRef<[string, Transaction[]][]> = computed(() => {
  return Object.entries(props.transactionsByTerm) as [string, Transaction[]][]
})

// Compute balance per term
const computeBalance = (transactions: Transaction[]) => {
  const charges = transactions
    .filter(tx => tx.kind === 'charge')
    .reduce((sum, tx) => sum + Number(tx.amount), 0)

  const payments = transactions
    .filter(tx => tx.kind === 'payment' && tx.status === 'paid')
    .reduce((sum, tx) => sum + Number(tx.amount), 0)

  return charges - payments
}

// Currency formatting helper
const fmt = (n: number | undefined | null) =>
  Number(n ?? 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
</script>

<template>
  <AppLayout>
    <Head title="Transactions" />

    <div class="w-full p-6">
      <!-- Breadcrumbs -->
      <Breadcrumbs :items="breadcrumbItems" />

      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">
          {{ props.auth.user.name }}’s Transactions
        </h1>
      </div>

      <!-- ===========================
           CURRENT TERM
      ============================ -->
      <div v-for="([term, transactions], idx) in terms" :key="term">
        <div v-if="term === props.currentTerm" class="mb-6">
          <div class="overflow-hidden rounded-xl shadow-md bg-white">
            <table class="min-w-full text-sm">
              <thead>
                <tr class="bg-gray-50">
                  <th colspan="6" class="px-4 py-3">
                    <div class="flex items-center justify-between">
                      <span class="font-semibold text-lg">{{ term }}</span>
                      <span class="font-semibold text-lg text-blue-600">
                        Balance: ₱{{ fmt(computeBalance(transactions)) }}
                      </span>
                    </div>
                  </th>
                </tr>
                <tr class="bg-gray-100 text-left">
                  <th class="px-4 py-2">Reference</th>
                  <th class="px-4 py-2">Type</th>
                  <th class="px-4 py-2">Amount</th>
                  <th class="px-4 py-2">Status</th>
                  <th class="px-4 py-2">Date</th>
                  <th class="px-4 py-2 text-center">Actions</th>
                </tr>
              </thead>

              <tbody>
                <tr
                  v-for="txn in transactions"
                  :key="txn.id"
                  class="border-t"
                  :class="txn.status !== 'paid' ? 'bg-red-50' : ''"
                >
                  <td class="px-4 py-2">{{ txn.reference }}</td>
                  <td class="px-4 py-2">{{ txn.type }}</td>
                  <td class="px-4 py-2">₱{{ fmt(txn.amount) }}</td>
                  <td class="px-4 py-2">
                    <span
                      :class="txn.status === 'paid'
                        ? 'text-green-600 font-medium'
                        : 'text-yellow-600 font-medium'"
                    >
                      {{ txn.status }}
                    </span>
                  </td>
                  <td class="px-4 py-2">
                    {{ new Date(txn.created_at).toLocaleDateString() }}
                  </td>
                  <td class="px-4 py-2 text-center">
                    <div class="flex justify-center gap-2">
                      <button
                        class="px-3 py-1.5 text-xs rounded bg-blue-500 text-white hover:bg-blue-600"
                        @click="openViewModal(txn)"
                      >
                        View
                      </button>
                      <button
                        class="px-3 py-1.5 text-xs rounded bg-green-500 text-white hover:bg-green-600"
                        @click="downloadPdf(txn.id)"
                      >
                        Download
                      </button>
                      <button
                        v-if="txn.status !== 'paid'"
                        class="px-3 py-1.5 text-xs rounded bg-red-500 text-white hover:bg-red-600"
                        @click="payNow(txn)"
                      >
                        Pay Now
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ===========================
           SHOW PAST BUTTON
      ============================ -->
      <div class="mt-4 mb-4">
        <button
          v-if="terms.length > 1"
          @click="showPast = !showPast"
          class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"
        >
          {{ showPast ? 'Hide Past Semesters' : 'Show Past Semesters' }}
        </button>
      </div>

      <!-- ===========================
           PAST SEMESTERS
      ============================ -->
      <div v-if="showPast" class="space-y-6">
        <div
          v-for="([term, transactions], idx) in terms"
          :key="term"
          v-if="term !== props.currentTerm"
        >
          <div class="overflow-hidden rounded-xl border bg-gray-50">
            <table class="min-w-full text-sm">
              <thead>
                <tr class="bg-gray-100">
                  <th colspan="6" class="px-4 py-3">
                    <div class="flex items-center justify-between">
                      <span class="font-semibold text-lg text-gray-800">
                        {{ term }} — Past Semester
                      </span>
                      <span class="font-semibold text-lg text-gray-700">
                        Balance: ₱{{ fmt(computeBalance(transactions)) }}
                      </span>
                    </div>
                  </th>
                </tr>
                <tr class="bg-gray-200 text-left">
                  <th class="px-4 py-2">Reference</th>
                  <th class="px-4 py-2">Type</th>
                  <th class="px-4 py-2">Amount</th>
                  <th class="px-4 py-2">Status</th>
                  <th class="px-4 py-2">Date</th>
                  <th class="px-4 py-2 text-center">Actions</th>
                </tr>
              </thead>

              <tbody>
                <tr
                  v-for="txn in transactions"
                  :key="txn.id"
                  class="border-t"
                  :class="txn.status !== 'paid' ? 'bg-red-50' : ''"
                >
                  <td class="px-4 py-2">{{ txn.reference }}</td>
                  <td class="px-4 py-2">{{ txn.type }}</td>
                  <td class="px-4 py-2">₱{{ fmt(txn.amount) }}</td>
                  <td class="px-4 py-2">
                    <span
                      :class="txn.status === 'paid'
                        ? 'text-green-600 font-medium'
                        : 'text-yellow-600 font-medium'"
                    >
                      {{ txn.status }}
                    </span>
                  </td>
                  <td class="px-4 py-2">
                    {{ new Date(txn.created_at).toLocaleDateString() }}
                  </td>
                  <td class="px-4 py-2 text-center">
                    <div class="flex justify-center gap-2">
                      <button
                        class="px-3 py-1.5 text-xs rounded bg-blue-500 text-white hover:bg-blue-600"
                        @click="openViewModal(txn)"
                      >
                        View
                      </button>
                      <button
                        class="px-3 py-1.5 text-xs rounded bg-green-500 text-white hover:bg-green-600"
                        @click="downloadPdf(txn.id)"
                      >
                        Download
                      </button>
                      <button
                        v-if="txn.status !== 'paid'"
                        class="px-3 py-1.5 text-xs rounded bg-red-500 text-white hover:bg-red-600"
                        @click="payNow(txn)"
                      >
                        Pay Now
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ===========================
         TRANSACTION MODAL
    ============================ -->
    <div
      v-if="showModal && selectedTransaction"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
    >
      <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md relative">
        <h2 class="text-lg font-bold mb-4">Transaction Details</h2>

        <p>
          <strong>Reference:</strong>
          {{ selectedTransaction.reference }}
        </p>
        <p>
          <strong>Type / Category:</strong>
          {{ selectedTransaction.type }}
        </p>

        <div
          v-if="selectedTransaction.meta?.items?.length"
          class="mt-3"
        >
          <p class="mt-2 font-semibold">Breakdown</p>
          <ul class="list-disc ml-5 text-sm">
            <li
              v-for="(it, idx) in selectedTransaction.meta.items"
              :key="idx"
            >
              {{ it.name }} — ₱{{
                Number(it.amount).toLocaleString(undefined, {
                  minimumFractionDigits: 2,
                  maximumFractionDigits: 2,
                })
              }}
            </li>
          </ul>
        </div>

        <p class="mt-2">
          <strong>Amount:</strong> ₱{{ fmt(selectedTransaction.amount) }}
        </p>
        <p><strong>Status:</strong> {{ selectedTransaction.status }}</p>
        <p>
          <strong>Date:</strong>
          {{ new Date(selectedTransaction.created_at).toLocaleString() }}
        </p>

        <div class="mt-6 flex justify-end">
          <button
            class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600"
            @click="closeModal"
          >
            Close
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
