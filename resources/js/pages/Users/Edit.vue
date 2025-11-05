<template>
  <div class="w-full p-6">
    <!-- Breadcrumbs -->
    <nav class="flex items-center text-sm text-gray-500 mb-6 space-x-1">
      <Link href="/" class="hover:text-blue-600">Dashboard</Link>
      <span>/</span>
      <Link href="/users" class="hover:text-blue-600">Users</Link>
      <span>/</span>
      <span class="text-gray-700 font-medium">Edit {{ user.name }}</span>
    </nav>

    <!-- Page Heading -->
    <h1 class="text-2xl font-bold mb-6">Edit User: {{ user.name }}</h1>

    <!-- Form -->
    <form @submit.prevent="submit" class="bg-white shadow-md rounded-xl p-6 space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
        <input v-model="form.name" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" />
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
        <input v-model="form.email" type="email" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" />
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">New Password (leave blank to keep current)</label>
        <input v-model="form.password" type="password" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" />
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
        <input v-model="form.password_confirmation" type="password" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" />
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Role *</label>
        <select v-model="form.role" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
          <option v-for="role in userRoles" :key="role" :value="role">
            {{ role.charAt(0).toUpperCase() + role.slice(1).replace('_', ' ') }}
          </option>
        </select>
      </div>

      <div class="flex justify-end gap-3 pt-4">
        <button type="button" @click="goBack" class="px-4 py-2 text-gray-600 border rounded-lg hover:bg-gray-50">
          Cancel
        </button>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
          Update User
        </button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { router, Link } from '@inertiajs/vue3'
import { reactive } from 'vue'

const { user, userRoles } = defineProps<{
  user: any
  userRoles: any[]
  message: string
}>()

const form = reactive({
  name: user.name,
  email: user.email,
  password: '',
  password_confirmation: '',
  role: user.role
})

function submit() {
  router.put(`/users/${user.id}`, form)
}

function goBack() {
  window.history.back()
}
</script>