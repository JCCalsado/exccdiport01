<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import { computed } from 'vue'

const { student } = defineProps<{ student: any }>()

const remainingBalance = computed(() => {
  if (!student.payments) return student.total_balance
  
  const totalPaid = student.payments.reduce((sum: number, payment: any) => {
    return sum + parseFloat(payment.amount)
  }, 0)

  return student.total_balance - totalPaid
})

const breadcrumbs = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Students', href: '/students' },
  { title: student.name }
]
</script>

<template>
  <Head :title="`View Student - ${student.name}`" />

  <AppLayout>
    <div class="max-w-4xl mx-auto p-6">
      <!-- Breadcrumbs -->
      <Breadcrumbs :items="breadcrumbs" />

      <!-- Header -->
      <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800">{{ student.name }}</h1>
        <p class="text-gray-500">Student ID: {{ student.student_id }}</p>
      </div>

      <!-- Student Info -->
      <div class="bg-white rounded-xl shadow p-6 mb-6">
        <h2 class="text-xl font-medium text-gray-800 mb-4">Student Information</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div><span class="font-medium">Email:</span> {{ student.email }}</div>
          <div><span class="font-medium">Course:</span> {{ student.course }}</div>
          <div><span class="font-medium">Year:</span> {{ student.year_level }}</div>
          <div><span class="font-medium">Phone:</span> {{ student.phone || 'N/A' }}</div>
          <div class="md:col-span-2"><span class="font-medium">Address:</span> {{ student.address || 'N/A' }}</div>
        </div>

        <!-- Balance -->
        <div class="mt-6 pt-4 border-t">
          <p class="text-lg font-semibold">
            Remaining Balance:
            <span :class="remainingBalance > 0 ? 'text-red-600' : 'text-green-600'">
              ₱{{ Math.abs(remainingBalance).toFixed(2) }}
            </span>
          </p>
          <p class="text-sm text-gray-500 mt-1">
            Total Assessment: ₱{{ student.total_balance.toFixed(2) }}
          </p>
        </div>
      </div>

      <!-- Payment History -->
      <div class="bg-white rounded-xl shadow p-6">
        <h2 class="text-xl font-medium text-gray-800 mb-4">Payment History</h2>

        <div v-if="student.payments.length" class="divide-y">
          <div v-for="payment in student.payments" :key="payment.id" class="py-3 flex justify-between items-center">
            <div>
              <p class="font-medium">₱{{ payment.amount }}</p>
              <p class="text-sm text-gray-600">{{ payment.description }}</p>
              <p class="text-xs text-gray-500">
                {{ payment.payment_method }}
                <span v-if="payment.reference_number">• Ref: {{ payment.reference_number }}</span>
              </p>
            </div>
            <span class="text-sm text-gray-500">{{ new Date(payment.created_at).toLocaleDateString() }}</span>
          </div>
        </div>
        <p v-else class="text-gray-500">No payments recorded yet.</p>
      </div>
    </div>
  </AppLayout>
</template>