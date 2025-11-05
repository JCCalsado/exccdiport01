<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { computed, reactive } from 'vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue'

const { student } = defineProps<{
  student: any
}>()

// Payment form state - updated to match database structure
const payment = reactive({
  amount: '',
  description: '',
  payment_method: 'cash',
  reference_number: '',
  status: 'completed',
  paid_at: new Date().toISOString().split('T')[0] // Default to today
})

// Add new payment
function addPayment(studentId: number) {
  router.post(`/students/${studentId}/payments`, payment, {
    onSuccess: () => {
      // Reset form after successful submission
      payment.amount = ''
      payment.description = ''
      payment.reference_number = ''
      payment.paid_at = new Date().toISOString().split('T')[0]
    }
  })
}

const remainingBalance = computed(() => {
  if (!student.payments) return Number(student.total_balance)
  
  const totalPaid = student.payments.reduce((sum: number, payment: any) => {
    return sum + parseFloat(payment.amount)
  }, 0)
  
  return Number(student.total_balance) - totalPaid
})

const breadcrumbs = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Students', href: '/students' },
  { title: student.name }
]
</script>

<template>
  <Head :title="`My Profile - ${student.name}`" />

  <AppLayout>
    <div class="w-full p-6">
      <!-- Header -->
      <Breadcrumbs :items="breadcrumbs" />  
      <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800">My Student Profile</h1>
        <p class="text-gray-500">Student ID: {{ student.student_id }}</p>
      </div>

      <!-- Student Info Card -->
      <div class="bg-white rounded-xl shadow p-6 mb-6">
        <h2 class="text-xl font-medium text-gray-800 mb-4">Personal Information</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div><span class="font-medium">Full Name:</span> {{ student.name }}</div>
          <div><span class="font-medium">Student ID:</span> {{ student.student_id }}</div>
          <div><span class="font-medium">Email:</span> {{ student.email }}</div>
          <div><span class="font-medium">Course:</span> {{ student.course }}</div>
          <div><span class="font-medium">Year:</span> {{ student.year_level }}</div>
          <div v-if="student.phone"><span class="font-medium">Phone:</span> {{ student.phone }}</div>
          <div v-if="student.birthday">
            <span class="font-medium">Birthday:</span> 
            {{ new Date(student.birthday).toLocaleDateString() }}
          </div>
          <div class="md:col-span-2" v-if="student.address">
            <span class="font-medium">Address:</span> {{ student.address }}
          </div>
        </div>
        
        <!-- Balance Display -->
        <div class="mt-6 pt-4 border-t">
          <p class="text-lg font-semibold">
            Current Balance: 
            <span :class="remainingBalance > 0 ? 'text-red-600' : 'text-green-600'">
              ₱{{ Math.abs(Number(remainingBalance)).toFixed(2) }}
            </span>
          </p>
          <p class="text-sm text-gray-500 mt-1">
            Total Assessment: ₱{{ Number(student.total_balance).toFixed(2) }}
          </p>
        </div>
      </div>

      <!-- Payment History -->
      <div class="bg-white rounded-xl shadow p-6">
        <h2 class="text-xl font-medium text-gray-800 mb-4">My Payment History</h2>

        <div v-if="student.payments.length" class="divide-y">
          <div v-for="payment in student.payments" :key="payment.id" class="py-3 flex justify-between items-center">
            <div>
              <p class="font-medium">₱{{ payment.amount }}</p>
              <p class="text-sm text-gray-600">{{ payment.description }}</p>
              <p class="text-xs text-gray-500">{{ payment.payment_method }} 
                <span v-if="payment.reference_number">• Ref: {{ payment.reference_number }}</span>
              </p>
            </div>
            <span class="text-sm text-gray-500">{{ new Date(payment.created_at).toLocaleDateString() }}</span>
          </div>
        </div>
        <p v-else class="text-gray-500">No payment history found.</p>

        <!-- Add Payment Form - Updated for database fields -->
        <form @submit.prevent="addPayment(student.id)" class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="md:col-span-2">
            <h3 class="text-lg font-medium text-gray-800 mb-2">Add New Payment</h3>
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
            <input
              v-model="payment.amount"
              type="number"
              step="0.01"
              min="0"
              placeholder="0.00"
              required
              class="w-full px-4 py-2 border rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"
            />
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
            <select
              v-model="payment.payment_method"
              class="w-full px-4 py-2 border rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"
            >
              <option value="cash">Cash</option>
              <option value="gcash">GCash</option>
              <option value="bank_transfer">Bank Transfer</option>
              <option value="credit_card">Credit Card</option>
              <option value="debit_card">Debit Card</option>
            </select>
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Reference Number</label>
            <input
              v-model="payment.reference_number"
              placeholder="Optional reference number"
              class="w-full px-4 py-2 border rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"
            />
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Payment Date</label>
            <input
              v-model="payment.paid_at"
              type="date"
              required
              class="w-full px-4 py-2 border rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"
            />
          </div>
          
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <input
              v-model="payment.description"
              placeholder="Payment description"
              required
              class="w-full px-4 py-2 border rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"
            />
          </div>
          
          <div class="md:col-span-2">
            <button
              type="submit"
              class="w-full px-5 py-2 bg-indigo-600 text-white rounded-lg shadow hover:bg-indigo-700 transition-colors"
            >
              Record Payment
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>