<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import {
  Users,
  TrendingUp,
  DollarSign,
  AlertCircle,
  Receipt,
  FileText,
  CreditCard,
} from 'lucide-vue-next'

interface StudentFeeStats {
  total_assessments: number
  total_assessment_amount: number
  pending_assessments: number
  recent_assessments: number
  recent_payments_amount: number
}

interface Props {
  stats: StudentFeeStats
}

defineProps<Props>()


const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
  }).format(amount)
}
</script>

<template>
  <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-lg shadow-md p-6 border-2 border-blue-200">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
          <FileText :size="24" class="text-blue-600" />
          Student Fee Management
        </h2>
        <p class="text-sm text-gray-600 mt-1">Assessment and fee tracking overview</p>
      </div>
      <Link
        :href="route('student-fees.index')"
        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2 transition-colors"
      >
        <Receipt :size="16" />
        Manage Assessments
      </Link>
    </div>

    <!-- Student Fee Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      <!-- Total Assessments -->
      <div class="bg-white rounded-lg shadow p-4 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div class="flex-1">
            <p class="text-sm text-gray-600 mb-1">Total Assessments</p>
            <p class="text-2xl font-bold text-gray-900">{{ stats.total_assessments }}</p>
            <p class="text-xs text-blue-600 mt-1">Active enrollments</p>
          </div>
          <div class="p-2 bg-blue-100 rounded-lg">
            <Users :size="20" class="text-blue-600" />
          </div>
        </div>
      </div>

      <!-- Total Assessment Amount -->
      <div class="bg-white rounded-lg shadow p-4 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div class="flex-1">
            <p class="text-sm text-gray-600 mb-1">Total Assessment</p>
            <p class="text-xl font-bold text-indigo-600">
              {{ formatCurrency(stats.total_assessment_amount) }}
            </p>
            <p class="text-xs text-gray-500 mt-1">Current term</p>
          </div>
          <div class="p-2 bg-indigo-100 rounded-lg">
            <TrendingUp :size="20" class="text-indigo-600" />
          </div>
        </div>
      </div>

      <!-- Pending Assessments -->
      <div class="bg-white rounded-lg shadow p-4 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div class="flex-1">
            <p class="text-sm text-gray-600 mb-1">Pending Balance</p>
            <p class="text-xl font-bold text-red-600">
              {{ formatCurrency(stats.pending_assessments) }}
            </p>
            <p class="text-xs text-gray-500 mt-1">Outstanding</p>
          </div>
          <div class="p-2 bg-red-100 rounded-lg">
            <AlertCircle :size="20" class="text-red-600" />
          </div>
        </div>
      </div>

      <!-- Recent Payments -->
      <div class="bg-white rounded-lg shadow p-4 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div class="flex-1">
            <p class="text-sm text-gray-600 mb-1">Recent Payments</p>
            <p class="text-xl font-bold text-green-600">
              {{ formatCurrency(stats.recent_payments_amount) }}
            </p>
            <p class="text-xs text-gray-500 mt-1">Last 30 days</p>
          </div>
          <div class="p-2 bg-green-100 rounded-lg">
            <DollarSign :size="20" class="text-green-600" />
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Actions for Student Fees -->
    <div class="mt-4 pt-4 border-t border-blue-200">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <Link
          :href="route('student-fees.create')"
          class="flex items-center gap-2 p-3 bg-white rounded-lg hover:bg-blue-50 transition-colors border border-blue-200"
        >
          <div class="p-2 bg-blue-500 rounded">
            <FileText :size="16" class="text-white" />
          </div>
          <div class="flex-1">
            <p class="text-sm font-medium text-gray-900">Create Assessment</p>
            <p class="text-xs text-gray-600">New student fee</p>
          </div>
        </Link>

        <Link
          :href="route('student-fees.index')"
          class="flex items-center gap-2 p-3 bg-white rounded-lg hover:bg-blue-50 transition-colors border border-blue-200"
        >
          <div class="p-2 bg-indigo-500 rounded">
            <Users :size="16" class="text-white" />
          </div>
          <div class="flex-1">
            <p class="text-sm font-medium text-gray-900">View All Students</p>
            <p class="text-xs text-gray-600">Manage fees</p>
          </div>
        </Link>

        <Link
          :href="route('student-fees.index', { filter: 'outstanding' })"
          class="flex items-center gap-2 p-3 bg-white rounded-lg hover:bg-blue-50 transition-colors border border-blue-200"
        >
          <div class="p-2 bg-red-500 rounded">
            <CreditCard :size="16" class="text-white" />
          </div>
          <div class="flex-1">
            <p class="text-sm font-medium text-gray-900">Outstanding Balance</p>
            <p class="text-xs text-gray-600">{{ stats.pending_assessments > 0 ? 'Needs attention' : 'All clear' }}</p>
          </div>
        </Link>
      </div>
    </div>
  </div>
</template>