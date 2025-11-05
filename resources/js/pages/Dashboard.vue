<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';
import { computed } from 'vue'

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];


// const notifications = computed(() => usePage().props.notifications ?? [])
// const page = usePage()
// const notifications = computed(() => page.props.notifications ?? [])
// ✅ Define only the new data you need to *merge* with global PageProps
type Notification = {
  id: number
  title: string
  message: string
  start_date: string | null
  end_date: string | null
  target_role: string
}

// ✅ Safely extend the global PageProps type with an intersection
type ExtendedProps = import('@inertiajs/core').PageProps & {
  notifications?: Notification[]
}

// ✅ Use your extended type
const page = usePage<ExtendedProps>()

// ✅ Extract notifications safely (with fallback)
const notifications = computed(() => page.props.notifications ?? [])
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <PlaceholderPattern />
                </div>
                <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <PlaceholderPattern />
                </div>
                <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <PlaceholderPattern />
                </div>
            </div>
            <div class="relative min-h-[100vh] flex-1 rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
                <PlaceholderPattern />
            </div>
        </div> -->

        <div class="p-6 space-y-6">
            <h1 class="text-2xl font-bold">Welcome back!</h1>

            <!-- <div v-if="notifications.length" class="bg-white shadow rounded-lg p-4">
                <h2 class="font-semibold mb-2 text-blue-700">Upcoming Payables</h2>
                <ul class="list-disc ml-5 text-sm text-gray-700">
                <li
                    v-for="n in notifications"
                    :key="n.id"
                    class="whitespace-pre-line"
                >
                    <strong>{{ n.title }}:</strong> {{ n.message }}
                </li>
                </ul>
            </div> -->
            <div v-if="notifications.length" class="bg-white rounded-lg shadow p-4 mt-6">
                <h2 class="text-lg font-semibold text-blue-700 mb-2">📅 Upcoming Payables & Schedules</h2>
                <ul class="list-disc ml-5 text-sm text-gray-700">
                    <li
                    v-for="n in notifications"
                    :key="n.id"
                    class="whitespace-pre-line mb-1"
                    >
                    <strong>{{ n.title }}:</strong> {{ n.message }}
                    </li>
                </ul>
                </div>
        </div>
    </AppLayout>
</template>