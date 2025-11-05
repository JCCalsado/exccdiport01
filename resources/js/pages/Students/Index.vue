<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue'

const props = defineProps<{
  students: any
  filters: { search?: string }
  auth: { user: { role: string } } // 👈 make sure you pass this from controller
}>()

const search = ref(props.filters.search || '')

// Debounced search
let timeout: number
watch(search, (value) => {
  clearTimeout(timeout)
  timeout = setTimeout(() => {
    router.get('/students', { search: value }, { preserveState: true, replace: true })
  }, 300)
})

const breadcrumbs = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Students' }
]
</script>

<template>
  <Head title="Students Archive" />

  <AppLayout>
    <div class="px-6 py-4">
      <!-- Header -->
      <Breadcrumbs :items="breadcrumbs" />
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <h1 class="text-2xl font-semibold text-gray-800">Students Archive</h1>
        <div class="flex items-center gap-3 mt-4 sm:mt-0">
          <input
            v-model="search"
            type="text"
            placeholder="Search students..."
            class="px-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 w-64"
          />
          <Link
            href="/students/create"
            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors"
          >
            ➕ Add Student
          </Link>
        </div>
      </div>

      <!-- Students Table -->
      <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Student ID</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Name</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Course</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Year Level</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Status</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 bg-white">
            <tr v-for="student in students.data" :key="student.id" class="hover:bg-gray-50">
              <td class="px-6 py-4 text-sm text-gray-700">{{ student.student_id }}</td>
              <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ student.name }}</td>
              <td class="px-6 py-4 text-sm text-gray-700">{{ student.course }}</td>
              <td class="px-6 py-4 text-sm text-gray-700">{{ student.year_level }}</td>
              <td class="px-6 py-4 text-sm font-semibold capitalize">
                <span 
                  :class="{
                    'text-green-600': student.status === 'enrolled',
                    'text-blue-600': student.status === 'graduated',
                    'text-gray-600': student.status === 'inactive'
                  }"
                >
                  {{ student.status || 'Unknown' }}
                </span>
              </td>
              <td class="px-6 py-4 text-sm flex gap-3">
                <!-- View -->
                <Link :href="`/students/${student.id}`" class="text-blue-600 hover:text-blue-800">
                  View
                </Link>

                <!-- Edit (only for admins/super_admins) -->
                <Link
                  v-if="['admin', 'super_admin'].includes(props.auth.user.role)"
                  :href="`/students/${student.id}/edit`"
                  class="text-green-600 hover:text-green-800"
                >
                  Edit
                </Link>
              </td>
            </tr>
            <tr v-if="students.data.length === 0">
              <td colspan="6" class="px-6 py-6 text-center text-gray-500">No students found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="flex justify-center mt-6 space-x-2">
        <Link
          v-for="link in students.links"
          :key="link.label"
          :href="link.url || '#'"
          class="px-4 py-2 text-sm border rounded-lg transition-colors"
          :class="{
            'bg-blue-600 text-white border-blue-600': link.active,
            'text-gray-600 hover:bg-gray-100': !link.active,
            'cursor-not-allowed opacity-50': !link.url
          }"
          v-html="link.label"
        />
      </div>
    </div>
  </AppLayout>
</template>