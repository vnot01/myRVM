<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, Head, useForm, router } from '@inertiajs/vue3'; // Tambahkan router
import Pagination from '@/Components/Pagination.vue';
import { ref, watch } from 'vue'; // Tambahkan ref dan watch
import debounce from 'lodash/debounce'; // Untuk debounce search input
import Modal from '@/Components/Modal.vue'; // Komponen Modal dari 
import SecondaryButton from '@/Components/SecondaryButton.vue'; // Dari Breeze
import DangerButton from '@/Components/DangerButton.vue';     // Dari Breeze
import TextInput from '@/Components/TextInput.vue';         // Dari Breeze
import InputLabel from '@/Components/InputLabel.vue';       // Dari Breeze

const props = defineProps({
  rvms: Object,
  filters: Object, // filters.search akan berisi query pencarian saat ini
});

// State untuk input pencarian
const searchTerm = ref(props.filters.search || '');
const localRvms = ref([...props.rvms.data]);
// State untuk Modal Delete
const confirmingRvmDeletion = ref(false);
const rvmToDelete = ref(null);
const rvmNameToConfirm = ref('');

// watch(() => props.rvms, (newRvmsProp) => { /* ... logika update localRvms ... */ }, { deep: true });

// Fungsi untuk menangani copy API Key
const copyApiKey = async (apiKey) => {
  try {
    await navigator.clipboard.writeText(apiKey);
    // Tampilkan notifikasi sukses (bisa pakai library notifikasi atau SnackBar sederhana)
    alert('API Key disalin ke clipboard!'); // Ganti dengan notifikasi yang lebih baik
  } catch (err) {
    console.error('Gagal menyalin API Key: ', err);
    alert('Gagal menyalin API Key.');
  }
};

// Watch searchTerm dan lakukan Inertia visit dengan debounce
// Ini akan memanggil controller RvmManagementController@index dengan parameter 'search'
watch(searchTerm, debounce((newValue) => {
  // console.log('Search term changed:', newValue);
  router.get(route('admin.rvms.index'), { search: newValue }, {
    preserveState: true, // Pertahankan state lain (seperti paginasi) sebisa mungkin
    replace: true,       // Ganti URL tanpa menambah history
  });
}, 300)); // Tunggu 300ms setelah user berhenti mengetik

const getStatusColorClass = (status) => {
  const lowerStatus = status ? status.toLowerCase() : '';
  if (lowerStatus === 'active') return 'bg-green-500';
  if (lowerStatus === 'maintenance') return 'bg-yellow-500'; // maintenance -> kuning
  if (lowerStatus === 'inactive') return 'bg-red-500'; // inactive -> merah
  if (lowerStatus === 'full') return 'bg-purple-500'; // Jika ada status 'full'
  return 'bg-gray-400';
};
const getStatus = (status) => {
  const lowerStatus = status ? status.toLowerCase() : '';
  if (lowerStatus === 'active') return 'Aktif';
  if (lowerStatus === 'maintenance') return 'Maintenance';
  if (lowerStatus === 'full') return 'Full';
//   if (lowerStatus === 'inactive') return 'Nonaktif';
  return 'Nonaktif';
};

const confirmRvmDeletion = (rvm) => {
  rvmToDelete.value = rvm;
  rvmNameToConfirm.value = ''; // Reset input konfirmasi
  confirmingRvmDeletion.value = true;
};

const deleteRvm = () => {
  if (rvmToDelete.value && rvmNameToConfirm.value === rvmToDelete.value.name) {
    router.delete(route('admin.rvms.destroy', rvmToDelete.value.id), {
      preserveScroll: true, // Agar tidak scroll ke atas setelah delete
      onSuccess: () => closeModal(),
      // onError: () => { /* handle error jika perlu */ }
    });
  } else {
    // Tampilkan pesan error jika nama tidak cocok (atau biarkan tombol disabled)
    alert('Nama RVM yang dimasukkan tidak cocok!');
  }
};

const closeModal = () => {
  confirmingRvmDeletion.value = false;
  rvmToDelete.value = null;
  rvmNameToConfirm.value = '';
};
</script>

<template>
  <Head title="Manajemen RVM" />
  <AdminLayout title="Manajemen RVM">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
      <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Mesin RVM Terdaftar</h1>
      <div class="flex items-center gap-2">
        <!-- Placeholder Tombol Sorting -->
        <!-- <button type="button" class="px-3 py-2 text-xs font-medium text-center inline-flex items-center text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 focus:ring-4 focus:outline-none focus:ring-gray-200 dark:focus:ring-gray-700"> -->
          <!-- <svg class="w-3 h-3 text-gray-500 dark:text-gray-400 mr-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20"> -->
            <!-- <path d="M10 3a.997.997 0 0 1 .812.402l3.938 5.004a1 1 0 0 1-.026 1.398l-4.013 4.397a1 1 0 0 1-1.412-.026L5.22 9.82A1 1 0 0 1 5.2 8.422l3.982-5.004A.997.997 0 0 1 10 3Zm0 14.06L5.22 11.98a.997.997 0 0 1 0-1.184l3.982-5.004A1 1 0 0 1 10 5a1 1 0 0 1 .798.812l3.938 5.004a1 1 0 0 1-.026 1.398l-4.013 4.397A.997.997 0 0 1 10 17.06Z"/> -->
          <!-- </svg> -->
          <!-- Sort by <span class="sr-only">opsi urutan</span> -->
        <!-- </button> -->
        <Link :href="route('admin.rvms.create')" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors text-sm font-medium inline-flex items-center">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 mr-1 sm:mr-2">
            <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" />
          </svg>
          <span class="hidden sm:inline">Tambah RVM</span>
          <span class="sm:hidden">Baru</span>
        </Link>
      </div>
    </div>

    <!-- Kolom Pencarian -->
    <div class="mb-6">
        <div class="relative">
            <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z"/>
                </svg>
            </div>
            <input type="search" id="rvm-search" v-model="searchTerm"
                   class="block w-full p-3 ps-10 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                   placeholder="Cari berdasarkan nama atau lokasi..." />
            <!-- Tombol filter lanjutan (seperti ikon pensil di contoh Anda) bisa ditambahkan di sini jika perlu -->
        </div>
    </div>


    <div v-if="rvms.data.length === 0 && !searchTerm" class="text-center py-10">
      <!-- {/* ... pesan tidak ada data ... */} -->
    </div>
     <div v-if="rvms.data.length === 0 && searchTerm" class="text-center py-10">
      <p class="text-gray-500 dark:text-gray-400">Tidak ada RVM yang cocok dengan pencarian "<span class="font-semibold">{{ searchTerm }}</span>".</p>
    </div>

    
    <div v-else class="space-y-3">
      <div v-for="rvm in rvms.data" :key="rvm.id"
           class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4 hover:shadow-md transition-shadow duration-200">
        <div class="flex items-center justify-between">
          <div class="flex items-center space-x-3 min-w-0"> <!-- Tambahkan min-w-0 agar truncate bekerja */} -->
            <span :class="getStatusColorClass(rvm.status)" class="w-2.5 h-2.5 rounded-full flex-shrink-0" :title="getStatus(rvm.status)"></span>
            
            <Link :href="route('admin.rvms.edit', rvm.id)" class="text-base font-semibold text-gray-900 dark:text-gray-100 hover:underline truncate">
              {{ rvm.name.length > 40 ? rvm.name.substring(0, 30) + '...' : rvm.name }}
            </Link>
          </div>
          <div class="flex items-center space-x-3 flex-shrink-0">
            <Link :href="route('admin.rvms.edit', rvm.id)" class="px-3 py-1 text-xs font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600">
              Edit
            </Link>

            <button @click="confirmRvmDeletion(rvm)"
                    class="px-3 py-1 text-xs font-medium text-red-600 dark:text-red-400 bg-red-100 dark:bg-red-700/50 rounded-md hover:bg-red-200 dark:hover:bg-red-600/50">
              Hapus
            </button>
            <!-- <InertiaLink :href="route('admin.rvms.edit', rvm.id)"> <!~~  Panah ini mungkin tidak perlu jika sudah ada tombol Edit ~~> -->
              <!-- <!~~ <svg ...>...</svg> ~~> -->
            <!-- </InertiaLink> -->
          </div>
        </div>
        <div class="mt-2 pl-[18px]">
          <p class="text-sm text-gray-500 dark:text-gray-400 truncate">
            {{ rvm.location_description.length > 70 ? rvm.location_description.substring(0, 60) + '...' : rvm.location_description }}
          </p>
          <div class="mt-2 flex items-center text-xs text-gray-400 dark:text-gray-500">
            <span>Dibuat: {{ rvm.created_at_formatted }}</span>
            <span class="mx-1 font-mono">·</span>
            <span>API Key: ...{{ rvm.api_key.slice(-8) }}</span>
            <button @click="copyApiKey(rvm.api_key)" title="Salin API Key" class="ml-2 p-1 hover:bg-gray-200 dark:hover:bg-gray-700 rounded">
              <svg class="w-3.5 h-3.5 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 18 20">
                <path d="M16 1h-3.278A1.992 1.992 0 0 0 11 0H7a1.993 1.993 0 0 0-1.722 1H2a2 2 0 0 0-2 2v15a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2Zm-3 14H5a1 1 0 0 1 0-2h8a1 1 0 0 1 0 2Zm0-4H5a1 1 0 0 1 0-2h8a1 1 0 0 1 0 2Zm0-4H5a1 1 0 0 1 0-2h8a1 1 0 0 1 0 2Z"/>
              </svg>
              <span class="sr-only">Salin API Key</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <Pagination v-if="rvms.links && rvms.links.length > 3 && rvms.data.length > 0" :links="rvms.links" class="mt-6 text-gray-900 dark:text-gray-100" />

        <!-- Modal Konfirmasi Delete -->
    <Modal :show="confirmingRvmDeletion" @close="closeModal">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Hapus Mesin RVM?
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400" v-if="rvmToDelete">
                Apakah Anda yakin ingin menghapus RVM "<strong>{{ rvmToDelete.name }}</strong>"? Tindakan ini tidak dapat diurungkan.
                <br/>
                Untuk konfirmasi, ketik ulang nama RVM di bawah ini:
            </p>
            <div class="mt-4" v-if="rvmToDelete">
                <InputLabel for="confirm_rvm_name" :value="`Ketik '${rvmToDelete.name}'`" class="sr-only" />
                <TextInput
                    id="confirm_rvm_name"
                    v-model="rvmNameToConfirm"
                    type="text"
                    class="mt-1 block w-3/4"
                    :placeholder="rvmToDelete.name"
                    @keyup.enter="deleteRvm"
                />
                <!-- InputError bisa ditambahkan jika perlu -->
            </div>
            <div class="mt-6 flex justify-end space-x-3">
                <SecondaryButton @click="closeModal"> Batal </SecondaryButton>
                <DangerButton
                    @click="deleteRvm"
                    :disabled="!rvmToDelete || rvmNameToConfirm !== rvmToDelete.name"
                >
                    Ya, Hapus RVM Ini
                </DangerButton>
            </div>
        </div>
    </Modal>
  </AdminLayout>
</template>