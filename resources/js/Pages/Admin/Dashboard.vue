<template>
  <AdminLayout title="Admin Dashboard"> 
    <div class="p-6 bg-white dark:bg-gray-800 rounded-lg shadow dark:text-gray-800">
      <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Admin Dashboard RVM</h1>
      <p class="mt-2 text-gray-900 dark:text-gray-100">Selamat datang, {{ $page.props.auth.user.name }}!</p>
      <p class="mt-1 text-gray-900 dark:text-gray-100">Anda login sebagai: <span class="font-medium">{{ $page.props.auth.user.role }}</span></p>
      <p class="mt-1 text-gray-900 dark:text-gray-100">Email: <span class="font-medium">{{ $page.props.auth.user.email }}</span></p>
    </div>
  </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
// Tidak perlu Head karena sudah di AdminLayout
</script>