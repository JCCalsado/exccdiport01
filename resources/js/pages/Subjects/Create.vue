<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue'

const props = defineProps<{
  yearLevels: string[]
  semesters: string[]
  courses: string[]
}>()

const breadcrumbs = [
  { title: 'Dashboard', href: route('dashboard') },
  { title: 'Subjects', href: route('subjects.index') },
  { title: 'Create Subject' },
]

const form = useForm({
  code: '',
  name: '',
  units: 3,
  price_per_unit: 350,
  year_level: '',
  semester: '',
  course: '',
  description: '',
  has_lab: false,
  lab_fee: 0,
  is_active: true,
})

const submit = () => {
  form.post(route('subjects.store'))
}
</script>

<template>
  <AppLayout>
    <Head title="Create Subject" />

    <div class="w-full p-6">
      <Breadcrumbs :items="breadcrumbs" />

      <div class="max-w-3xl mx-auto">
        <h1 class="text-3xl font-bold mb-6">Create New Subject</h1>

        <form @submit.prevent="submit" class="bg-white rounded-lg shadow-md p-6 space-y-6">
          <!-- Subject Code -->
          <div>
            <label class="block text-sm font-medium mb-2">Subject Code *</label>
            <input
              v-model="form.code"
              type="text"
              class="w-full border rounded px-4 py-2"
              placeholder="e.g., CS101"
              required
            />
            <div v-if="form.errors.code" class="text-red-500 text-sm mt-1">
              {{ form.errors.code }}
            </div>
          </div>

          <!-- Subject Name -->
          <div>
            <label class="block text-sm font-medium mb-2">Subject Name *</label>
            <input
              v-model="form.name"
              type="text"
              class="w-full border rounded px-4 py-2"
              placeholder="e.g., Introduction to Programming"
              required
            />
            <div v-if="form.errors.name" class="text-red-500 text-sm mt-1">
              {{ form.errors.name }}
            </div>
          </div>

          <!-- Units and Price -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium mb-2">Units *</label>
              <input
                v-model.number="form.units"
                type="number"
                min="1"
                max="10"
                class="w-full border rounded px-4 py-2"
                required
              />
              <div v-if="form.errors.units" class="text-red-500 text-sm mt-1">
                {{ form.errors.units }}
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium mb-2">Price per Unit *</label>
              <input
                v-model.number="form.price_per_unit"
                type="number"
                step="0.01"
                min="0"
                class="w-full border rounded px-4 py-2"
                required
              />
              <div v-if="form.errors.price_per_unit" class="text-red-500 text-sm mt-1">
                {{ form.errors.price_per_unit }}
              </div>
            </div>
          </div>

          <!-- Year Level and Semester -->
          <div class="grid grid-cols-2 gap-4">
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
          </div>

          <!-- Course -->
          <div>
            <label class="block text-sm font-medium mb-2">Course *</label>
            <select
              v-model="form.course"
              class="w-full border rounded px-4 py-2"
              required
            >
              <option value="">Select Course</option>
              <option v-for="course in courses" :key="course" :value="course">
                {{ course }}
              </option>
            </select>
            <div v-if="form.errors.course" class="text-red-500 text-sm mt-1">
              {{ form.errors.course }}
            </div>
          </div>

          <!-- Has Lab -->
          <div class="flex items-center">
            <input
              v-model="form.has_lab"
              type="checkbox"
              id="has_lab"
              class="mr-2"
            />
            <label for="has_lab" class="text-sm font-medium">Has Laboratory Component</label>
          </div>

          <!-- Lab Fee -->
          <div v-if="form.has_lab">
            <label class="block text-sm font-medium mb-2">Laboratory Fee</label>
            <input
              v-model.number="form.lab_fee"
              type="number"
              step="0.01"
              min="0"
              class="w-full border rounded px-4 py-2"
              placeholder="0.00"
            />
            <div v-if="form.errors.lab_fee" class="text-red-500 text-sm mt-1">
              {{ form.errors.lab_fee }}
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
              :href="route('subjects.index')"
              class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600"
            >
              Cancel
            </Link>
            <button
              type="submit"
              class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
              :disabled="form.processing"
            >
              {{ form.processing ? 'Creating...' : 'Create Subject' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>