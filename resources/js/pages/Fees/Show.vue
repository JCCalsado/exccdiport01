<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue'

type Fee = {
  id: number
  code: string
  name: string
  category: string
  amount: number
  year_level: string
  semester: string
  school_year: string
  description: string | null
  is_active: boolean
  created_at: string
  transactions?: any[]
}

const props = defineProps<{
  fee: Fee
}>()

const breadcrumbs = [
  { title: 'Dashboard', href: route('dashboard') },
  { title: 'Fee Management', href: route('fees.index') },
  { title: props.fee.name },
]

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
  }).format(amount)
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
}
</script>

<template>
  <AppLayout>
    <Head :title="fee.name" />

    <div class="w-full p-6">
      <Breadcrumbs :items="breadcrumbs" />

      <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="flex justify-between items-start mb-6">
          <div>
            <h1 class="text-3xl font-bold">{{ fee.name }}</h1>
            <p class="text-gray-600 mt-1">{{ fee.code }}</p>
          </div>
          <Link
            :href="route('fees.edit', fee.id)"
            class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
          >
            Edit Fee
          </Link>
        </div>

        <!-- Fee Details -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
          <h2 class="text-xl font-semibold mb-4">Fee Details</h2>
          
          <div class="grid grid-cols-2 gap-6">
            <div>
              <p class="text-sm text-gray-600">Category</p>
              <p class="font-medium">{{ fee.category }}</p>
            </div>

            <div>
              <p class="text-sm text-gray-600">Amount</p>
              <p class="text-2xl font-bold text-blue-600">{{ formatCurrency(fee.amount) }}</p>
            </div>

            <div>
              <p class="text-sm text-gray-600">Year Level</p>
              <p class="font-medium">{{ fee.year_level }}</p>
            </div>

            <div>
              <p class="text-sm text-gray-600">Semester</p>
              <p class="font-medium">{{ fee.semester }}</p>
            </div>

            <div>
              <p class="text-sm text-gray-600">School Year</p>
              <p class="font-medium">{{ fee.school_year }}</p>
            </div>

            <div>
              <p class="text-sm text-gray-600">Status</p>
              <span
                :class="fee.is_active 
                  ? 'bg-green-100 text-green-800' 
                  : 'bg-red-100 text-red-800'"
                class="px-3 py-1 text-sm rounded-full font-medium"
              >
                {{ fee.is_active ? 'Active' : 'Inactive' }}
              </span>
            </div>
          </div>

          <div v-if="fee.description" class="mt-6 pt-6 border-t">
            <p class="text-sm text-gray-600 mb-2">Description</p>
            <p class="text-gray-800">{{ fee.description }}</p>
          </div>

          <div class="mt-6 pt-6 border-t">
            <p class="text-sm text-gray-600">Created</p>
            <p class="text-gray-800">{{ formatDate(fee.created_at) }}</p>
          </div>
        </div>

        <!-- Recent Transactions -->
        <div v-if="fee.transactions && fee.transactions.length" class="bg-white rounded-lg shadow-md p-6">
          <h2 class="text-xl font-semibold mb-4">Recent Transactions</h2>
          
          <div class="space-y-3">
            <div
              v-for="txn in fee.transactions"
              :key="txn.id"
              class="border-b pb-3 last:border-b-0"
            >
              <div class="flex justify-between items-start">
                <div>
                  <p class="font-medium">{{ txn.user?.name || 'N/A' }}</p>
                  <p class="text-sm text-gray-600">{{ txn.reference }}</p>
                </div>
                <div class="text-right">
                  <p class="font-semibold">{{ formatCurrency(txn.amount) }}</p>
                  <p class="text-sm text-gray-600">{{ formatDate(txn.created_at) }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>