<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { reactive } from 'vue'

const form = reactive({
  student_id: '',
  name: '',
  email: '',
  course: '',
  year_level: '',
  birthday: '',
  phone: '',
  address: '',
  total_balance: ''
})

function submit() {
  router.post('/students', form)
}
</script>

<template>
  <Head title="Add New Student" />

  <AppLayout>
    <div class="max-w-2xl mx-auto p-6">
      <h1 class="text-2xl font-semibold text-gray-800 mb-6">Add New Student</h1>

      <form @submit.prevent="submit" class="bg-white shadow-md rounded-xl p-6 space-y-4">
        <!-- Student ID -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Student ID *</label>
          <input v-model="form.student_id" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="2024-0001" />
        </div>

        <!-- Name -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
          <input v-model="form.name" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="Juan Dela Cruz" />
        </div>

        <!-- Email -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
          <input v-model="form.email" type="email" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="student@ccdi.edu.ph" />
        </div>

        <!-- Course & Year Level -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Course *</label>
            <input v-model="form.course" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="BS Information Technology" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Year Level *</label>
            <input v-model="form.year_level" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="1st Year" />
          </div>
        </div>

        <!-- Total Balance -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Total Balance (₱) *</label>
          <input v-model="form.total_balance" type="number" step="0.01" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="25000.00" />
        </div>

        <!-- Birthday -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Birthday</label>
          <input v-model="form.birthday" type="date" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" />
        </div>

        <!-- Phone -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
          <input v-model="form.phone" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="+63 912 345 6789" />
        </div>

        <!-- Address -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
          <textarea v-model="form.address" rows="3" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="Complete address"></textarea>
        </div>

        <!-- Buttons -->
        <div class="flex justify-end gap-3 pt-4">
          <button type="button" @click="router.visit('/students')" class="px-4 py-2 text-gray-600 border rounded-lg hover:bg-gray-50">
            Cancel
          </button>
          <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
            Create Student
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>