<template>
  <AdminLayout title="Dashboard">
    <div class="p-6 bg-white rounded-lg shadow">
      <h1 class="text-2xl font-semibold text-gray-700">Admin Dashboard</h1>
      <p class="mt-2 text-gray-600">Selamat datang di Dashboard Admin RVM!</p>
      <p class="mt-4">Anda login sebagai: <span class="font-medium">{{ $page.props.auth.user.name }}</span></p>
      <p>Email: <span class="font-medium">{{ $page.props.auth.user.email }}</span></p>
    </div>
  </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'; // Asumsi Anda akan buat layout ini

// Jika perlu props spesifik dari controller, definisikan di sini
// defineProps({
//   someData: Object,
// });
</script>

<!-- < template> -->
  <!-- <AdminLayout title="Admin Dashboard"> -->
    <!-- <div class="p-6 bg-white rounded-lg shadow"> -->
      <!-- <h1 class="text-2xl font-semibold text-gray-700">Selamat Datang, Admin!</h1> -->
      <!-- <p class="mt-2 text-gray-600">Ini adalah halaman utama Dashboard Admin RVM.</p> -->
      <!-- Tambahkan statistik atau ringkasan di sini nanti -->
    <!-- </div> -->
  <!-- </AdminLayout> -->
<!-- </template> -->

<!-- <script setup> -->
<!-- import AdminLayout from '@/Layouts/AdminLayout.vue'; -->
<!-- Tidak perlu Head karena sudah di AdminLayout -->
<!-- </script> -->