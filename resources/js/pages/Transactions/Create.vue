<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'

defineProps<{
  users: { id: number; name: string; email: string }[]
}>()

// Inertia form
const form = useForm({
  user_id: '',
  type: 'charge',
  amount: '',
  payment_channel: '',
})
const submit = () => {
  form.post(route('transactions.store'))
}
</script>

<template>
  <AppLayout>
    <Head title="Create Transaction" />

    <div class="max-w-2xl mx-auto mt-8 bg-white shadow rounded-lg p-6">
      <h1 class="text-xl font-bold mb-4">Create Transaction</h1>

      <form @submit.prevent="submit" class="space-y-5">
        <!-- User -->
        <div>
          <label class="block text-sm font-medium mb-1">Student</label>
          <select
            v-model="form.user_id"
            class="w-full border rounded px-3 py-2"
          >
            <option value="">Select a student</option>
            <option v-for="user in users" :key="user.id" :value="user.id">
              {{ user.name }} ({{ user.email }})
            </option>
          </select>
          <div v-if="form.errors.user_id" class="text-red-500 text-sm">{{ form.errors.user_id }}</div>
        </div>

        <!-- Type -->
        <div>
          <label class="block text-sm font-medium mb-1">Type</label>
          <select v-model="form.type" class="w-full border rounded px-3 py-2">
            <option value="charge">Charge</option>
            <option value="payment">Payment</option>
          </select>
          <div v-if="form.errors.type" class="text-red-500 text-sm">{{ form.errors.type }}</div>
        </div>

        <!-- Amount -->
        <div>
          <label class="block text-sm font-medium mb-1">Amount</label>
          <input
            v-model="form.amount"
            type="number"
            step="0.01"
            class="w-full border rounded px-3 py-2"
            placeholder="Enter amount"
          />
          <div v-if="form.errors.amount" class="text-red-500 text-sm">{{ form.errors.amount }}</div>
        </div>

        <!-- Payment Channel -->
        <div>
          <label class="block text-sm font-medium mb-1">Payment Channel</label>
          <input
            v-model="form.payment_channel"
            type="text"
            class="w-full border rounded px-3 py-2"
            placeholder="e.g. GCash, PayMaya, Cash"
          />
          <div v-if="form.errors.payment_channel" class="text-red-500 text-sm">
            {{ form.errors.payment_channel }}
          </div>
        </div>

        <!-- Buttons -->
        <div class="flex justify-between items-center">
          <Link
            :href="route('transactions.index')"
            class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600"
          >
            Cancel
          </Link>
          <button
            type="submit"
            class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
            :disabled="form.processing"
          >
            Save
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
