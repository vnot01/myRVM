<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3'; // Tambahkan useForm
import { ref, watch, computed, onMounted, onUnmounted } from 'vue'; // Tambahkan computed
import debounce from 'lodash/debounce';
import Pagination from '@/Components/Pagination.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue'; // Untuk tombol batal di form edit
import Checkbox from '@/Components/Checkbox.vue'; // Komponen checkbox Breeze
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';   // Impor komponen form
import SelectInput from '@/Components/SelectInput.vue';
// import throttle from 'lodash/throttle';
// import InputError from '@/Components/InputError.vue';
import { IconUserCircle, IconSearch, IconUserPlus, IconMail, IconCalendarEvent, IconShieldCheck, IconShieldOff } from '@tabler/icons-vue';

const props = defineProps({
    users: Object,
    filters: Object,
    availableRolesProp: Array, // <-- Terima dengan nama ini
    availableStatuses: Array,
    availableRoles:Array,
});

const allUsers = ref(props.users && props.users.data ? [...props.users.data] : []);
const currentPage = ref(props.users && props.users.meta ? props.users.meta.current_page : 1);
const lastPage = ref(props.users && props.users.meta ? props.users.meta.last_page : 1);
const isLoadingMore = ref(false);

console.log('Prop users di Index.vue:', JSON.parse(JSON.stringify(props.users)));
console.log('Prop availableRolesProp di Index.vue:', JSON.parse(JSON.stringify(props.availableRolesProp)));
console.log('Prop availableRoles di Index.vue:', JSON.parse(JSON.stringify(props.availableRoles)));
const searchTerm = ref(props.filters.search || '');
watch(searchTerm, debounce((newValue) => {
    router.get(route('admin.users.index'), {
        search: newValue,
    }, { preserveState: true, replace: true, preserveScroll: true });
}, 300));

// Fungsi untuk format tanggal (bisa dibuat helper global nanti)
const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const options = { 
      year: 'numeric', 
      month: 'short', 
      day: 'numeric', 
      /*hour: '2-digit', minute: '2-digit'*/ };
    return new Date(dateString).toLocaleDateString('id-ID', options);
};

const openEditUserModal = (user) => {
    // editingUser.value = user;
    // showEditUserModal.value = true;
    console.log('TODO: Buka modal edit untuk user:', user.name);
    // Untuk sekarang, kita bisa arahkan ke rute edit jika sudah ada
    // router.get(route('admin.users.edit', user.id));
};

const openDeleteUserModal = (user) => {
    // userToDelete.value = user;
    // confirmingUserDeletion.value = true;
    console.log('TODO: Buka modal hapus untuk user:', user.name);
};

const submit = () => {
    form.post(route('admin.users.store'), {
        onError: (errors) => {
            // form.errors akan otomatis terisi
            console.error("Error submitting form:", errors);
        },
        onSuccess: () => {
            // Inertia akan otomatis redirect berdasarkan respons controller
            // Pesan flash akan ditangani oleh AdminLayout
        }
    });
};
const testClickAndNavigate = () => {
    console.log('Tombol Test Diklik! Mencoba navigasi...');
    try {
        const targetRoute = route('admin.users.create'); // Cek apakah ini menghasilkan URL yang benar
        console.log('Target URL:', targetRoute);
        router.get(targetRoute); // Atau router.get(targetRoute);
    } catch (e) {
        console.error('Error saat memanggil route() atau router.visit():', e);
    }
};

watch(() => props.users.data, (newData, oldData) => {
    // Hanya reset allUsers jika ini adalah hasil dari search baru (halaman pertama)
    // atau jika struktur props.users berubah signifikan
    if (props.users && props.users.meta && props.users.meta.current_page === 1) {
        allUsers.value = props.users.data ? [...props.users.data] : [];
        currentPage.value = props.users.meta.current_page;
        lastPage.value = props.users.meta.last_page;
    }
    // Logika untuk onSuccess di loadMoreUsers akan menangani penambahan data
});


const loadMoreUsers = () => {
    // Pastikan props.users.meta ada sebelum mengakses lastPage
    if (isLoadingMore.value || !props.users || !props.users.meta || currentPage.value >= (props.users.meta.last_page || currentPage.value) ) {
        return;
    }
    isLoadingMore.value = true;
    router.get(route('admin.users.index'), {
        search: searchTerm.value,
        page: currentPage.value + 1,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onSuccess: (page) => {
            // Pastikan page.props.users.data dan page.props.users.meta ada
            if (page.props.users && page.props.users.data) {
                allUsers.value = [...allUsers.value, ...page.props.users.data];
            }
            if (page.props.users && page.props.users.meta) {
                currentPage.value = page.props.users.meta.current_page;
                lastPage.value = page.props.users.meta.last_page;
            }
            isLoadingMore.value = false;
        },
        onError: () => {
            isLoadingMore.value = false;
        }
    });
};

</script>

<template>
    <Head title="Manajemen Pengguna" />
    <AdminLayout title="Manajemen Pengguna"> <!-- Menggunakan slot title di AdminLayout jika ada -->
        <!-- Bagian Atas: Judul Halaman, Tombol Sorting, dan Tombol Tambah -->
         <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                Pengguna Terdaftar
            </h1>
            <div class="flex items-center gap-2">
                <!-- Placeholder Tombol Sorting -->
                <button type="button" class="px-3 py-2 text-xs font-medium text-center inline-flex items-center text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-600 focus:ring-4 focus:outline-none focus:ring-gray-200 dark:focus:ring-slate-700">
                    <svg class="w-3 h-3 text-gray-500 dark:text-gray-400 mr-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 3a.997.997 0 0 1 .812.402l3.938 5.004a1 1 0 0 1-.026 1.398l-4.013 4.397a1 1 0 0 1-1.412-.026L5.22 9.82A1 1 0 0 1 5.2 8.422l3.982-5.004A.997.997 0 0 1 10 3Zm0 14.06L5.22 11.98a.997.997 0 0 1 0-1.184l3.982-5.004A1 1 0 0 1 10 5a1 1 0 0 1 .798.812l3.938 5.004a1 1 0 0 1-.026 1.398l-4.013 4.397A.997.997 0 0 1 10 17.06Z"/>
                    </svg>
                    Sort by <span class="sr-only">opsi urutan</span>
                </button>
                <div class="mt-4 sm:mt-0">
                  <Link :href="route('admin.users.create')" class="cursor-pointer select-none px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors text-sm font-medium inline-flex items-center">
                      <IconUserPlus class="w-5 h-5 mr-1 sm:mr-2" />
                      <span class="hidden sm:inline select-none">Tambah Pengguna</span>
                      <span class="sm:hidden">Baru</span>
                  </Link>
              </div>
            </div>
        </div>

        <!-- Kolom Pencarian -->
        <div class="mb-6">
            <div class="relative">
                <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                    <IconSearch class="w-4 h-4 text-gray-500 dark:text-gray-400" />
                </div>
                <TextInput
                    type="search"
                    v-model="searchTerm"
                    :placeholder="'Cari berdasarkan nama atau email...'"
                    class="block w-full p-3 ps-10 text-sm dark:bg-slate-700 dark:text-gray-300 dark:placeholder-gray-400"
                />
            </div>
        </div>

        <!-- Daftar User Card-Style -->
        <div v-if="users.data.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                          <!-- Tambahkan opacity jika tidak aktif -->
          <div v-for="user in users.data" :key="user.id" class="bg-slate-50 dark:bg-slate-700/50 p-4 rounded-lg shadow-md hover:shadow-xl transition-shadow duration-200 flex flex-col justify-between"
              :class="{ 'opacity-60 dark:opacity-50': !user.is_active }">
              <div>
                  <div class="flex items-center mb-3">
                      <!-- Avatar (tetap sama) -->
                      <img v-if="user.avatar" :src="user.avatar" :alt="user.name" class="h-12 w-12 rounded-full object-cover mr-4 border-2 border-slate-300 dark:border-slate-600"/>
                      <div v-else class="h-12 w-12 rounded-full bg-slate-200 dark:bg-slate-600 flex items-center justify-center text-slate-400 dark:text-slate-500 mr-4 border-2 border-slate-300 dark:border-slate-600">
                          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-7 h-7">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                          </svg>
                      </div>
                      <div class="flex-1 min-w-0">
                          <p class="text-md font-semibold text-gray-900 dark:text-white truncate flex items-center">
                              {{ user.name }}
                              <!-- Indikator Verifikasi Email -->
                              <span v-if="user.email_verified_at" title="Email Terverifikasi" class="ml-1.5 text-green-500 dark:text-green-400 inline-block">
                                  <IconShieldCheck class="w-4 h-4" />
                              </span>
                              <span v-else title="Email Belum Terverifikasi" class="ml-1.5 text-yellow-500 dark:text-yellow-400 inline-block">
                                  <!-- Ganti dengan ikon yang sesuai dari Tabler, contoh: -->
                                  <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-mail-off w-4 h-4" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 5h10a2 2 0 0 1 2 2v10m-2 2h-14a2 2 0 0 1 -2 -2v-10a2 2 0 0 1 2 -2" /><path d="M3 3l18 18" /></svg>
                              </span>

                              <!-- Indikator Status Akun Aktif/Nonaktif -->
                              <span v-if="!user.is_active" title="Akun Tidak Aktif" class="ml-1.5 text-red-500 dark:text-red-400 inline-block">
                                  <IconShieldOff class="w-4 h-4" /> <!-- Menggunakan Tabler Icon -->
                              </span>
                          </p>
                          <p class="text-sm text-gray-600 dark:text-gray-400 truncate">{{ user.email }}</p>
                      </div>
                  </div>
                  <div class="border-t border-slate-200 dark:border-slate-600 pt-3 mt-2 text-xs">
                      <div class="flex justify-between text-gray-500 dark:text-gray-400 mb-1">
                          <span>Role: <span class="font-medium text-gray-700 dark:text-gray-200">{{ user.role }}</span></span>
                          <span>Poin: <span class="font-medium text-gray-700 dark:text-gray-200">{{ user.points }}</span></span>
                      </div>
                      <p class="text-gray-500 dark:text-gray-400">Bergabung: {{ formatDate(user.created_at) }}</p>
                  </div>
              </div>
              <div class="mt-4 flex justify-end space-x-2">
                  <button @click.stop="openDeleteUserModal(user)" class="px-3 py-1 text-xs font-medium text-red-700 bg-red-100 rounded-md hover:bg-red-200 dark:bg-red-700 dark:text-red-100 dark:hover:bg-red-600">
                      Hapus
                  </button>
                  <button @click.stop="openEditUserModal(user)" class="px-3 py-1 text-xs font-medium text-sky-700 bg-sky-100 rounded-md hover:bg-sky-200 dark:bg-sky-700 dark:text-sky-100 dark:hover:bg-sky-600">
                      Edit / Detail
                  </button>
              </div>
          </div>
      </div>
      <div v-else class="text-center py-10">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
          <path vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
        </svg>
        <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">
          {{ searchTerm ? 'Pengguna Tidak Ditemukan' : 'Belum Ada Pengguna' }}
        </h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
          {{ searchTerm ? 'Coba kata kunci lain atau hapus filter.' : 'Silakan tambahkan pengguna baru jika diperlukan.' }}
        </p>
      </div>

      <div v-if="props.users && props.users.data && props.users.data.length > 0 && props.users.meta && props.users.meta.links && props.users.meta.last_page && props.users.meta.last_page > 1" class="mt-6">
        <Pagination :links="props.users.meta.links" />
    </div>
    </AdminLayout>
</template>