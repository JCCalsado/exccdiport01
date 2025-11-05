<script setup>
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
  <Head title="Admin Dashboard" />

  <div class="p-8 space-y-8 bg-gray-50 min-h-screen">
    <!-- Header -->
    <header class="flex justify-between items-center">
      <h1 class="text-3xl font-bold text-gray-800">🛠 Admin Dashboard</h1>
      <Link
        href="/settings/profile"
        class="text-blue-600 hover:underline font-medium"
      >
        Edit Profile
      </Link>
    </header>

    <!-- Stats Overview -->
    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
      <div class="bg-blue-600 text-white p-5 rounded-lg shadow">
        <p class="text-sm opacity-80">Registered Students</p>
        <h2 class="text-3xl font-bold mt-2">1,256</h2>
      </div>
      <div class="bg-green-600 text-white p-5 rounded-lg shadow">
        <p class="text-sm opacity-80">Active Accounts</p>
        <h2 class="text-3xl font-bold mt-2">923</h2>
      </div>
      <div class="bg-yellow-500 text-white p-5 rounded-lg shadow">
        <p class="text-sm opacity-80">Pending Payments</p>
        <h2 class="text-3xl font-bold mt-2">₱45,820</h2>
      </div>
      <div class="bg-purple-600 text-white p-5 rounded-lg shadow">
        <p class="text-sm opacity-80">Accounting Staff</p>
        <h2 class="text-3xl font-bold mt-2">5</h2>
      </div>
    </section>

    <!-- Quick Links -->
    <section>
      <h2 class="text-xl font-semibold text-gray-700 mb-4">Quick Actions</h2>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <Link
          href="/users"
          class="block bg-white p-6 rounded-lg border hover:shadow-md transition"
        >
          <h3 class="text-lg font-semibold text-gray-800">👥 Manage Users</h3>
          <p class="text-sm text-gray-600 mt-2">
            Add, edit, or remove system users.
          </p>
        </Link>

        <Link
          href="/students"
          class="block bg-white p-6 rounded-lg border hover:shadow-md transition"
        >
          <h3 class="text-lg font-semibold text-gray-800">🎓 Manage Students</h3>
          <p class="text-sm text-gray-600 mt-2">
            View and manage student records.
          </p>
        </Link>

        <Link
          href="/fees"
          class="block bg-white p-6 rounded-lg border hover:shadow-md transition"
        >
          <h3 class="text-lg font-semibold text-gray-800">💰 Manage Fees</h3>
          <p class="text-sm text-gray-600 mt-2">
            View and adjust tuition and fee structures.
          </p>
        </Link>

        <Link
          href="/notifications"
          class="block bg-white p-6 rounded-lg border hover:shadow-md transition"
        >
          <h3 class="text-lg font-semibold text-gray-800">📢 Announcements</h3>
          <p class="text-sm text-gray-600 mt-2">
            Post and manage system notifications.
          </p>
        </Link>
      </div>
    </section>
  </div>
</template>