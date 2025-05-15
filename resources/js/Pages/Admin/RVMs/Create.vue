<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
// import { ref, watch, onMounted, onUpdated, defineProps } from 'vue';
import { Head, Link as InertiaLink, useForm, usePage } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue'; // Komponen dari Breeze
import TextInput from '@/Components/TextInput.vue';   // Komponen dari Breeze
import TextareaInput from '@/Components/TextareaInput.vue'; // Komponen Text Area Input
import SelectInput from '@/Components/SelectInput.vue';   // Komponen Select Input
import PrimaryButton from '@/Components/PrimaryButton.vue'; // Komponen dari Breeze
import InputError from '@/Components/InputError.vue';     // Komponen dari Breeze
const page = usePage();

defineProps({
  available_statuses: Array, // Dari controller
  errors: Object, // Otomatis di-pass oleh Inertia jika ada error validasi
});
// statusOptions dibuat dari props.available_statuses
// const statusOptions = page.props.available_statuses.map(status => ({
//   value: status,
//   label: status.charAt(0).toUpperCase() + status.slice(1)
// }));

const form = useForm({
  name: '',
  location_description: '',
  status: 'active', // Default status
});

const submit = () => {
  form.post(route('admin.rvms.store'), {
    // onFinish: () => form.reset(), // Opsional: reset form setelah sukses
    // onSuccess: () => { /* Mungkin ada notifikasi atau redirect */ }
  });
};
</script>

<template>
  <Head title="Tambah RVM Baru" />
  <AdminLayout title="Tambah RVM Baru">
    <div class="max-w-2xl mx-auto p-4 sm:p-6 lg:p-8 bg-white dark:bg-gray-800 shadow-md rounded-lg">
      <form @submit.prevent="submit">
        <div>
          <InputLabel for="name" value="Nama RVM" />
          <TextInput id="name" type="text" class="mt-1 block w-full" v-model="form.name" required autofocus />
          <InputError class="mt-2" :message="form.errors.name" />
        </div>

        <div class="mt-4">
          <InputLabel for="location_description" value="Deskripsi Lokasi" />
          <!-- Anda mungkin perlu komponen TextareaInput kustom -->
          <textarea id="location_description" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" v-model="form.location_description" rows="3"></textarea>
          <InputError class="mt-2" :message="form.errors.location_description" />
        </div>

        <div class="mt-4">
          <InputLabel for="status" value="Status" />
          <!-- Anda mungkin perlu komponen SelectInput kustom -->
          <select id="status" v-model="form.status" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
            <option v-for="statusOption in available_statuses" :key="statusOption" :value="statusOption">
              {{ statusOption.charAt(0).toUpperCase() + statusOption.slice(1) }}
            </option>
          </select>
          <InputError class="mt-2" :message="form.errors.status" />
        </div>

        <div class="flex items-center justify-end mt-6 space-x-3">
          <InertiaLink :href="route('admin.rvms.index')" class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700">
            Batal
          </InertiaLink>
          <PrimaryButton :disabled="form.processing">
            Simpan RVM
          </PrimaryButton>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>