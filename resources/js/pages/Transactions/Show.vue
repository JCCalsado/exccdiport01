<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import Breadcrumbs from '@/components/Breadcrumbs.vue'

const props = defineProps<{
  transaction: {
    id: number
    reference: string
    amount: number
    status: string
    type: string
    subtype?: string | null
    created_at: string
    user?: { id: number; name: string; email: string }
  }
}>()

const breadcrumbs = [
  { title: 'Dashboard', href: '/' },
  { title: 'Transactions', href: '/transactions' },
  { title: `#${props.transaction.reference || props.transaction.id}`, href: `/transactions/${props.transaction.id}` }
]
</script>

<template>
  <div class="w-full p-6">
    <Head :title="`Transaction ${props.transaction.reference}`" />
    <Breadcrumbs :items="breadcrumbs" class="mb-4" />

    <div class="bg-white shadow-md rounded-xl p-6">
      <h1 class="text-2xl font-bold mb-4">
        Transaction #{{ props.transaction.reference || props.transaction.id }}
      </h1>

      <p><strong>Type:</strong> {{ props.transaction.type }}</p>
      <p v-if="props.transaction.subtype"><strong>Sub-type:</strong> {{ props.transaction.subtype }}</p>
      <p><strong>Amount:</strong> ₱{{ props.transaction.amount.toLocaleString() }}</p>
      <p><strong>Status:</strong> {{ props.transaction.status }}</p>
      <p><strong>Date:</strong> {{ new Date(props.transaction.created_at).toLocaleString() }}</p>
      <p><strong>User:</strong> {{ props.transaction.user?.name || 'N/A' }}</p>
    </div>

    <div class="mt-4">
      <Link href="/transactions" class="text-blue-600 hover:underline">← Back to Transactions</Link>
    </div>
  </div>
</template>
