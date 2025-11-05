<template>
  <div class="max-w-2xl mx-auto p-6">
    <h1 class="text-2xl font-bold mb-6">Create New User</h1>

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
        <label class="block text-sm font-medium text-gray-700 mb-1">Password *</label>
        <input v-model="form.password" type="password" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" />
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password *</label>
        <input v-model="form.password_confirmation" type="password" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" />
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Role *</label>
        <select v-model="form.role" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
          <option v-for="role in userRoles" :key="role.value" :value="role.value">
            {{ role.label() }}
          </option>
        </select>
      </div>

      <div class="flex justify-end gap-3 pt-4">
        <button type="button" @click="goBack" class="px-4 py-2 text-gray-600 border rounded-lg hover:bg-gray-50">
          Cancel
        </button>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
          Create User
        </button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { reactive } from 'vue'

const { userRoles } = defineProps<{
  userRoles: any[]
  message: string
}>()

const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role: 'student'
})

function submit() {
  router.post('/users', form)
}
function goBack() {
  window.history.back()
}
</script>