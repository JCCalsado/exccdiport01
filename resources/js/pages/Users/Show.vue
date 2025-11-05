<template>
  <AppLayout>
    <div class="max-w-3xl mx-auto p-6">
      <!-- Breadcrumbs -->
      <nav class="flex items-center text-sm text-gray-500 mb-6 space-x-1">
        <Link href="/" class="hover:text-blue-600">Dashboard</Link>
        <span>/</span>
        <Link href="/users" class="hover:text-blue-600">Users</Link>
        <span>/</span>
        <span class="text-gray-700 font-medium">{{ user.name }}</span>
      </nav>

      <!-- Page Heading -->
      <h1 class="text-2xl font-semibold text-gray-800 mb-6">
        User Details
      </h1>

      <!-- User Card -->
      <div class="bg-white shadow-md rounded-xl p-6 space-y-4">
        <div>
          <p class="text-sm text-gray-500">Name</p>
          <p class="text-lg font-medium text-gray-800">{{ user.name }}</p>
        </div>

        <div>
          <p class="text-sm text-gray-500">Email</p>
          <p class="text-lg font-medium text-gray-800">{{ user.email }}</p>
        </div>

        <div>
          <p class="text-sm text-gray-500">Role</p>
          <span
            class="inline-flex px-3 py-1 text-sm font-medium rounded-full"
            :class="{
              'bg-blue-100 text-blue-800': user.role === 'admin',
              'bg-green-100 text-green-800': user.role === 'student',
              'bg-purple-100 text-purple-800': user.role === 'accounting',
              'bg-gray-100 text-gray-800': user.role === 'super_admin',
            }"
          >
            {{ formatRole(user.role) }}
          </span>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex justify-end gap-3 mt-6">
        <Link
          :href="`/users/${user.id}/edit`"
          class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
        >
          Edit
        </Link>
        <Link
          href="/users"
          class="px-4 py-2 text-gray-600 border rounded-lg hover:bg-gray-50"
        >
          Back to Users
        </Link>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'

const { user } = defineProps<{
  user: any
}>()

// Format role for display
function formatRole(role: string) {
  return role.charAt(0).toUpperCase() + role.slice(1).replace('_', ' ')
}
</script>
