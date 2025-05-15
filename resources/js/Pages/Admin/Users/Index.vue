<template>
  <AdminLayout title="Manajemen User">
    <h1 class="text-xl font-semibold">Halaman Manajemen User</h1>
    <p>Konten akan ditambahkan di sini.</p>
  </AdminLayout>
</template>
<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
defineProps({ users: Array });
</script>