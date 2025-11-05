<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue'

const props = defineProps<{
  yearLevels: string[]
  semesters: string[]
  categories: string[]
}>()

const breadcrumbs = [
  { title: 'Dashboard', href: route('dashboard') },
  { title: 'Fee Management', href: route('fees.index') },
  { title: 'Create Fee' },
]

const form = useForm({
  name: '',
  category: '',
  amount: '',
  year_level: '',
  semester: '',
  school_year: '',
  description: '',
  is_active: true,
})

const submit = () => {
  form.post(route('fees.store'))
}
</script>

<template>
  <AppLayout>
    <Head title="Create Fee" />

    <div class="w-full p-6">
      <Breadcrumbs :items="breadcrumbs" />

      <div class="max-w-3xl mx-auto">
        <h1 class="text-3xl font-bold mb-6">Create New Fee</h1>

        <form @submit.prevent="submit" class="bg-white rounded-lg shadow-md p-6 space-y-6">
          <!-- Name -->
          <div>
            <label class="block text-sm font-medium mb-2">Fee Name *</label>
            <input
              v-model="form.name"
              type="text"
              class="w-full border rounded px-4 py-2"
              placeholder="e.g., Tuition Fee"
              required
            />
            <div v-if="form.errors.name" class="text-red-500 text-sm mt-1">
              {{ form.errors.name }}
            </div>
          </div>

          <!-- Category -->
          <div>
            <label class="block text-sm font-medium mb-2">Category *</label>
            <select
              v-model="form.category"
              class="w-full border rounded px-4 py-2"
              required
            >
              <option value="">Select Category</option>
              <option v-for="cat in categories" :key="cat" :value="cat">
                {{ cat }}
              </option>
            </select>
            <div v-if="form.errors.category" class="text-red-500 text-sm mt-1">
              {{ form.errors.category }}
            </div>
          </div>

          <!-- Amount -->
          <div>
            <label class="block text-sm font-medium mb-2">Amount *</label>
            <input
              v-model="form.amount"
              type="number"
              step="0.01"
              min="0"
              class="w-full border rounded px-4 py-2"
              placeholder="0.00"
              required
            />
            <div v-if="form.errors.amount" class="text-red-500 text-sm mt-1">
              {{ form.errors.amount }}
            </div>
          </div>

          <!-- Year Level -->
          <div>
            <label class="block text-sm font-medium mb-2">Year Level *</label>
            <select
              v-model="form.year_level"
              class="w-full border rounded px-4 py-2"
              required
            >
              <option value="">Select Year Level</option>
              <option v-for="level in yearLevels" :key="level" :value="level">
                {{ level }}
              </option>
            </select>
            <div v-if="form.errors.year_level" class="text-red-500 text-sm mt-1">
              {{ form.errors.year_level }}
            </div>
          </div>

          <!-- Semester -->
          <div>
            <label class="block text-sm font-medium mb-2">Semester *</label>
            <select
              v-model="form.semester"
              class="w-full border rounded px-4 py-2"
              required
            >
              <option value="">Select Semester</option>
              <option v-for="sem in semesters" :key="sem" :value="sem">
                {{ sem }}
              </option>
            </select>
            <div v-if="form.errors.semester" class="text-red-500 text-sm mt-1">
              {{ form.errors.semester }}
            </div>
          </div>

          <!-- School Year -->
          <div>
            <label class="block text-sm font-medium mb-2">School Year *</label>
            <input
              v-model="form.school_year"
              type="text"
              class="w-full border rounded px-4 py-2"
              placeholder="e.g., 2025-2026"
              required
            />
            <div v-if="form.errors.school_year" class="text-red-500 text-sm mt-1">
              {{ form.errors.school_year }}
            </div>
          </div>

          <!-- Description -->
          <div>
            <label class="block text-sm font-medium mb-2">Description</label>
            <textarea
              v-model="form.description"
              class="w-full border rounded px-4 py-2"
              rows="3"
              placeholder="Optional description..."
            ></textarea>
            <div v-if="form.errors.description" class="text-red-500 text-sm mt-1">
              {{ form.errors.description }}
            </div>
          </div>

          <!-- Is Active -->
          <div class="flex items-center">
            <input
              v-model="form.is_active"
              type="checkbox"
              id="is_active"
              class="mr-2"
            />
            <label for="is_active" class="text-sm font-medium">Active</label>
          </div>

          <!-- Actions -->
          <div class="flex justify-between items-center pt-4 border-t">
            <Link
              :href="route('fees.index')"
              class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600"
            >
              Cancel
            </Link>
            <button
              type="submit"
              class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
              :disabled="form.processing"
            >
              {{ form.processing ? 'Creating...' : 'Create Fee' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>