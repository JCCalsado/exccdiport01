<template>
   <AppLayout>
        <div class="w-full p-6">
        <!-- Header -->
        <Breadcrumbs :items="breadcrumbs" />
           
          <div class="p-6">
            <h1 class="text-2xl font-bold mb-6">User Management</h1>
            
            <div class="flex justify-between items-center mb-6">
              <p class="text-gray-600">{{ message }}</p>
              <Link
                href="/users/create"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
              >
                ➕ Add User
              </Link>
            </div>

            <!-- Users Table -->
            <div class="bg-white shadow-md rounded-lg overflow-hidden">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Role</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Created</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                  <tr v-for="user in users.data" :key="user.id" class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ user.name }}</td>
                    <td class="px-6 py-4 text-sm text-gray-700">{{ user.email }}</td>
                    <td class="px-6 py-4 text-sm text-gray-700 capitalize">{{ user.role?.replace('_', ' ') }}</td>
                    <td class="px-6 py-4 text-sm text-gray-700">{{ new Date(user.created_at).toLocaleDateString() }}</td>
                    <td class="px-6 py-4 text-sm flex gap-2">
                      <!-- View button -->
                      <Link
                        :href="`/users/${user.id}`"
                        as="button"
                        class="px-3 py-1.5 text-xs font-medium rounded-lg bg-blue-500 text-white hover:bg-blue-600 transition"
                      >
                        View
                      </Link>

                      <!-- Edit button -->
                      <Link
                        :href="`/users/${user.id}/edit`"
                        as="button"
                        class="px-3 py-1.5 text-xs font-medium rounded-lg bg-green-500 text-white hover:bg-green-600 transition"
                      >
                        Edit
                      </Link>
                    </td>

                  </tr>
                  <tr v-if="users.data.length === 0">
                    <td colspan="5" class="px-6 py-6 text-center text-gray-500">No users found.</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Pagination -->
            <div class="flex justify-center mt-6 space-x-2">
              <Link
                v-for="link in users.links"
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

        </div>
   </AppLayout>
 
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3'
import Breadcrumbs from '@/components/Breadcrumbs.vue'

defineProps<{
  users: any
  userRoles: any[]
  message: string
}>()
const breadcrumbs = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Users', href: '/users' },
]
</script>