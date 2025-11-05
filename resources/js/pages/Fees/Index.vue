<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
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
}

const props = defineProps<{
  fees: {
    data: Fee[]
    links?: any[]
    meta?: any
  }
  filters: {
    search?: string
    year_level?: string
    semester?: string
    school_year?: string
    is_active?: boolean
  }
  yearLevels: string[]
  semesters: string[]
  categories: string[]
}>()

const breadcrumbs = [
  { title: 'Dashboard', href: route('dashboard') },
  { title: 'Fee Management', href: route('fees.index') },
]

const searchForm = useForm({
  search: props.filters.search || '',
  year_level: props.filters.year_level || '',
  semester: props.filters.semester || '',
  school_year: props.filters.school_year || '',
  is_active: props.filters.is_active !== undefined ? props.filters.is_active : '',
})

const search = () => {
  searchForm.get(route('fees.index'), {
    preserveState: true,
    replace: true,
  })
}

const clearFilters = () => {
  searchForm.reset()
  search()
}

const deleteFee = (feeId: number) => {
  if (confirm('Are you sure you want to delete this fee?')) {
    router.delete(route('fees.destroy', feeId), {
      preserveScroll: true,
    })
  }
}

const toggleStatus = (feeId: number) => {
  router.post(route('fees.toggleStatus', feeId), {}, {
    preserveScroll: true,
  })
}

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
  }).format(amount)
}
</script>

<template>
  <AppLayout>
    <Head title="Fee Management" />

    <div class="w-full p-6">
      <Breadcrumbs :items="breadcrumbs" />

      <!-- Header -->
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold">Fee Management</h1>
        <Link
          :href="route('fees.create')"
          class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
        >
          Create New Fee
        </Link>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4">Filters</h2>
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">Search</label>
            <input
              v-model="searchForm.search"
              type="text"
              placeholder="Name, code, category..."
              class="w-full border rounded px-3 py-2"
              @keyup.enter="search"
            />
          </div>

          <div>
            <label class="block text-sm font-medium mb-1">Year Level</label>
            <select
              v-model="searchForm.year_level"
              class="w-full border rounded px-3 py-2"
              @change="search"
            >
              <option value="">All</option>
              <option v-for="level in yearLevels" :key="level" :value="level">
                {{ level }}
              </option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium mb-1">Semester</label>
            <select
              v-model="searchForm.semester"
              class="w-full border rounded px-3 py-2"
              @change="search"
            >
              <option value="">All</option>
              <option v-for="sem in semesters" :key="sem" :value="sem">
                {{ sem }}
              </option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium mb-1">School Year</label>
            <input
              v-model="searchForm.school_year"
              type="text"
              placeholder="e.g., 2025-2026"
              class="w-full border rounded px-3 py-2"
              @keyup.enter="search"
            />
          </div>

          <div>
            <label class="block text-sm font-medium mb-1">Status</label>
            <select
              v-model="searchForm.is_active"
              class="w-full border rounded px-3 py-2"
              @change="search"
            >
              <option value="">All</option>
              <option :value="true">Active</option>
              <option :value="false">Inactive</option>
            </select>
          </div>
        </div>

        <div class="mt-4 flex gap-2">
          <button
            @click="search"
            class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
          >
            Search
          </button>
          <button
            @click="clearFilters"
            class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600"
          >
            Clear Filters
          </button>
        </div>
      </div>

      <!-- Fees Table -->
      <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div v-if="fees.data && fees.data.length > 0">
          <table class="min-w-full">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Year/Sem</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">School Year</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr v-for="fee in fees.data" :key="fee.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 text-sm font-medium">{{ fee.code }}</td>
                <td class="px-6 py-4 text-sm">{{ fee.name }}</td>
                <td class="px-6 py-4 text-sm">
                  <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                    {{ fee.category }}
                  </span>
                </td>
                <td class="px-6 py-4 text-sm font-semibold">{{ formatCurrency(fee.amount) }}</td>
                <td class="px-6 py-4 text-sm">
                  {{ fee.year_level }}<br />
                  <span class="text-xs text-gray-500">{{ fee.semester }}</span>
                </td>
                <td class="px-6 py-4 text-sm">{{ fee.school_year }}</td>
                <td class="px-6 py-4 text-sm">
                  <button
                    @click="toggleStatus(fee.id)"
                    :class="fee.is_active 
                      ? 'bg-green-100 text-green-800' 
                      : 'bg-red-100 text-red-800'"
                    class="px-2 py-1 text-xs rounded-full font-medium"
                  >
                    {{ fee.is_active ? 'Active' : 'Inactive' }}
                  </button>
                </td>
                <td class="px-6 py-4 text-sm text-right space-x-2">
                  <Link
                    :href="route('fees.show', fee.id)"
                    class="text-blue-600 hover:text-blue-900"
                  >
                    View
                  </Link>
                  <Link
                    :href="route('fees.edit', fee.id)"
                    class="text-green-600 hover:text-green-900"
                  >
                    Edit
                  </Link>
                  <button
                    @click="deleteFee(fee.id)"
                    class="text-red-600 hover:text-red-900"
                  >
                    Delete
                  </button>
                </td>
              </tr>
            </tbody>
          </table>

          <!-- Pagination -->
          <div v-if="fees.meta" class="px-6 py-4 border-t">
            <div class="flex items-center justify-between">
              <div class="text-sm text-gray-700">
                <span v-if="fees.meta.from && fees.meta.to && fees.meta.total">
                  Showing {{ fees.meta.from }} to {{ fees.meta.to }} of {{ fees.meta.total }} results
                </span>
                <span v-else>
                  Showing {{ fees.data.length }} result(s)
                </span>
              </div>
              <div v-if="fees.links && fees.links.length > 3" class="flex gap-2">
                <Link
                  v-for="(link, index) in fees.links"
                  :key="index"
                  :href="link.url || '#'"
                  v-html="link.label"
                  :class="[
                    'px-3 py-1 border rounded',
                    link.active ? 'bg-blue-600 text-white' : 'bg-white hover:bg-gray-50',
                    !link.url ? 'opacity-50 cursor-not-allowed' : ''
                  ]"
                  :disabled="!link.url"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- No Data State -->
        <div v-else class="text-center py-12">
          <p class="text-gray-500 text-lg mb-4">No fees found</p>
          <Link
            :href="route('fees.create')"
            class="inline-block px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
          >
            Create Your First Fee
          </Link>
        </div>
      </div>
    </div>
  </AppLayout>
</template>