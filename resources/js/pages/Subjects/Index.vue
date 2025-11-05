<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue'

type Subject = {
  id: number
  code: string
  name: string
  units: number
  price_per_unit: number
  year_level: string
  semester: string
  course: string
  has_lab: boolean
  lab_fee: number
  is_active: boolean
  total_cost: number
}

const props = defineProps<{
  subjects: {
    data: Subject[]
    links?: any[]
    meta?: any
  }
  filters: {
    search?: string
    year_level?: string
    semester?: string
    course?: string
  }
  yearLevels: string[]
  semesters: string[]
  courses: string[]
}>()

const breadcrumbs = [
  { title: 'Dashboard', href: route('dashboard') },
  { title: 'Subjects', href: route('subjects.index') },
]

const searchForm = useForm({
  search: props.filters.search || '',
  year_level: props.filters.year_level || '',
  semester: props.filters.semester || '',
  course: props.filters.course || '',
})

const search = () => {
  searchForm.get(route('subjects.index'), {
    preserveState: true,
    replace: true,
  })
}

const clearFilters = () => {
  searchForm.reset()
  search()
}

const deleteSubject = (subjectId: number) => {
  if (confirm('Are you sure you want to delete this subject?')) {
    router.delete(route('subjects.destroy', subjectId), {
      preserveScroll: true,
    })
  }
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
    <Head title="Subject Management" />

    <div class="w-full p-6">
      <Breadcrumbs :items="breadcrumbs" />

      <!-- Header -->
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold">Subject Management</h1>
        <Link
          :href="route('subjects.create')"
          class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
        >
          Create New Subject
        </Link>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4">Filters</h2>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">Search</label>
            <input
              v-model="searchForm.search"
              type="text"
              placeholder="Code, name, course..."
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
            <label class="block text-sm font-medium mb-1">Course</label>
            <select
              v-model="searchForm.course"
              class="w-full border rounded px-3 py-2"
              @change="search"
            >
              <option value="">All</option>
              <option v-for="course in courses" :key="course" :value="course">
                {{ course }}
              </option>
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

      <!-- Subjects Table -->
      <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div v-if="subjects.data && subjects.data.length > 0">
          <table class="min-w-full">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Units</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price/Unit</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Lab Fee</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Cost</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Course</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr v-for="subject in subjects.data" :key="subject.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 text-sm font-medium">{{ subject.code }}</td>
                <td class="px-6 py-4 text-sm">
                  {{ subject.name }}
                  <br />
                  <span class="text-xs text-gray-500">
                    {{ subject.year_level }} - {{ subject.semester }}
                  </span>
                </td>
                <td class="px-6 py-4 text-sm">{{ subject.units }}</td>
                <td class="px-6 py-4 text-sm">{{ formatCurrency(subject.price_per_unit) }}</td>
                <td class="px-6 py-4 text-sm">
                  <span v-if="subject.has_lab">{{ formatCurrency(subject.lab_fee) }}</span>
                  <span v-else class="text-gray-400">—</span>
                </td>
                <td class="px-6 py-4 text-sm font-semibold text-blue-600">
                  {{ formatCurrency(subject.total_cost) }}
                </td>
                <td class="px-6 py-4 text-sm">{{ subject.course }}</td>
                <td class="px-6 py-4 text-sm text-right space-x-2">
                  <Link
                    :href="route('subjects.edit', subject.id)"
                    class="text-green-600 hover:text-green-900"
                  >
                    Edit
                  </Link>
                  <button
                    @click="deleteSubject(subject.id)"
                    class="text-red-600 hover:text-red-900"
                  >
                    Delete
                  </button>
                </td>
              </tr>
            </tbody>
          </table>

          <!-- Pagination -->
          <div v-if="subjects.meta" class="px-6 py-4 border-t">
            <div class="flex items-center justify-between">
              <div class="text-sm text-gray-700">
                <span v-if="subjects.meta.from && subjects.meta.to && subjects.meta.total">
                  Showing {{ subjects.meta.from }} to {{ subjects.meta.to }} of {{ subjects.meta.total }} results
                </span>
                <span v-else>
                  Showing {{ subjects.data.length }} result(s)
                </span>
              </div>
              <div v-if="subjects.links && subjects.links.length > 3" class="flex gap-2">
                <Link
                  v-for="(link, index) in subjects.links"
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
          <p class="text-gray-500 text-lg mb-4">No subjects found</p>
          <Link
            :href="route('subjects.create')"
            class="inline-block px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
          >
            Create Your First Subject
          </Link>
        </div>
      </div>
    </div>
  </AppLayout>
</template>