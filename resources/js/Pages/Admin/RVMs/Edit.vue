<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link as InertiaLink, useForm } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import TextareaInput from '@/Components/TextareaInput.vue';
import SelectInput from '@/Components/SelectInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
  rvm: Object, // Data RVM yang akan diedit
  available_statuses: Array,
  errors: Object,
});

// Inisialisasi form dengan data RVM yang ada
const form = useForm({
  name: props.rvm.name,
  location_description: props.rvm.location_description || '', // Beri default string kosong jika null
  status: props.rvm.status,
});

// Siapkan options untuk SelectInput
const statusOptions = props.available_statuses.map(status => ({
  value: status,
  label: status.charAt(0).toUpperCase() + status.slice(1)
}));

const submitUpdate = () => {
  form.patch(route('admin.rvms.update', props.rvm.id), { // Gunakan PATCH dan kirim ID RVM
    // onFinish: () => {}, // Opsional
    // onSuccess: () => {} // Opsional
  });
};
</script>

<template>
  <Head :title="'Edit RVM: ' + rvm.name" />
  <AdminLayout :title="'Edit RVM: ' + rvm.name">
    <div class="max-w-2xl mx-auto p-4 sm:p-6 lg:p-8 bg-white dark:bg-gray-800 shadow-md rounded-lg">
      <form @submit.prevent="submitUpdate">
        <div>
          <InputLabel for="name" value="Nama RVM" />
          <TextInput id="name" type="text" class="mt-1 block w-full" v-model="form.name" required autofocus />
          <InputError class="mt-2" :message="form.errors.name" />
        </div>

        <div class="mt-4">
          <InputLabel for="location_description" value="Deskripsi Lokasi" />
          <TextareaInput id="location_description" class="mt-1 block w-full" v-model="form.location_description" rows="3" />
          <InputError class="mt-2" :message="form.errors.location_description" />
        </div>

        <div class="mt-4">
          <InputLabel for="status" value="Status" />
          <SelectInput
            id="status"
            class="mt-1 block w-full"
            v-model="form.status"
            :options="statusOptions"
            valueKey="value"
            labelKey="label"
          />
          <InputError class="mt-2" :message="form.errors.status" />
        </div>

        <div class="flex items-center justify-end mt-6 space-x-3">
          <InertiaLink :href="route('admin.rvms.index')" class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700">
            Batal
          </InertiaLink>
          <PrimaryButton :disabled="form.processing">
            Simpan Perubahan
          </PrimaryButton>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>