<template>
  <div class="p-6">
    <h1 class="text-2xl font-bold mb-4">Payment Analytics</h1>
    <p class="text-gray-600">{{ message }}</p>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  paymentsByMethod: any[]
  message: string
}>()
</script>