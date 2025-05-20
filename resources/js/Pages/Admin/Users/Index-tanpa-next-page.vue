<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3'; // Hapus useForm jika tidak dipakai di sini
import { ref, watch, onMounted, onUnmounted } from 'vue'; // Tambahkan onMounted, onUnmounted
import debounce from 'lodash/debounce';
// import Pagination from '@/Components/Pagination.vue'; // Kita tidak pakai ini untuk infinite scroll
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue'; // Untuk tombol tambah nanti
import { IconSearch, IconUserPlus, IconShieldCheck, IconShieldOff, IconMailOff /* ganti dengan ikon yang benar jika perlu */ } from '@tabler/icons-vue';

const props = defineProps({
    users: Object, // Objek paginator dari Laravel
    filters: Object,
    availableRolesProp: Array, 
    availableStatuses: Array,
    availableRoles:Array,
});
// console.log('[Index.vue SETUP] Initial props.users:', JSON.parse(JSON.stringify(props.users)));
// console.log('[Index.vue SETUP] Initial props.filters:', JSON.parse(JSON.stringify(props.filters)));
// console.log('[Index.vue SETUP] Initial props.availableRoles:', JSON.parse(JSON.stringify(props.availableRoles)));
// console.log('[Index.vue SETUP] Initial props.availableStatuses:', JSON.parse(JSON.stringify(props.availableStatuses)));
// console.log('[Index.vue SETUP] Initial props.availableRolesProp:', JSON.parse(JSON.stringify(props.availableRolesProp)));

// ... (state refs: allUsers, currentPage, lastPage, isLoadingMore, initialLoadComplete) ...
const allUsers = ref([]); // Default
const currentPage = ref(1); // Default
const lastPage = ref(1); // Default
const isLoadingMore = ref(false); // Default
const initialLoadComplete = ref(false); // PENTING: Awalnya false
const searchTerm = ref(props.filters.search || '');

const updateLocalUserState = (paginator) => {
    // console.log('[updateLocalUserState] Received paginator:', JSON.parse(JSON.stringify(paginator)));
    // Akses langsung properti paginasi dari level atas objek paginator
    if (paginator && paginator.data && typeof paginator.current_page !== 'undefined' && typeof paginator.last_page !== 'undefined') {
        if (paginator.current_page === 1) {
            allUsers.value = paginator.data ? [...paginator.data] : [];
            // console.log('[updateLocalUserState] RESET allUsers (page 1):', allUsers.value.length, 'items');
        } else {
            const existingIds = new Set(allUsers.value.map(u => u.id));
            const newUniqueUsers = (paginator.data || []).filter(u => !existingIds.has(u.id));
            allUsers.value.push(...newUniqueUsers);
            // console.log('[updateLocalUserState] ADDED to allUsers:', newUniqueUsers.length, 'new items. Total:', allUsers.value.length);
        }
        currentPage.value = paginator.current_page;
        lastPage.value = paginator.last_page;
    } else {
        // console.warn('[updateLocalUserState] Invalid paginator structure. Resetting allUsers.');
        allUsers.value = [];
        currentPage.value = 1;
        lastPage.value = 1;
    }
    initialLoadComplete.value = true;
    // console.log('[updateLocalUserState] States updated. initialLoadComplete:', initialLoadComplete.value, 'allUsers.length:', allUsers.value.length, 'currentPage:', currentPage.value, 'lastPage:', lastPage.value);
};

onMounted(() => {
    // console.log('[onMounted] Initial props.users:', JSON.parse(JSON.stringify(props.users)));
    updateLocalUserState(props.users);
    window.addEventListener('scroll', handleScroll);
});

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll);
});

watch(searchTerm, debounce((newValue) => {
    // console.log('[watch searchTerm] New search term:', newValue);
    isLoadingMore.value = false;
    router.get(route('admin.users.index'), {
        search: newValue,
        page: 1
    }, {
        preserveState: true, preserveScroll: false, replace: true,
        onSuccess: (page) => {
            // props.users akan terupdate, biarkan watcher props.users yang handle
            // atau jika ingin lebih direct:
            // updateLocalUserState(page.props.users);
        }
    });
}, 300));

watch(() => props.users, (newPaginator) => {
    // console.log('[watch props.users] props.users changed. New current_page:', newPaginator?.current_page);
    updateLocalUserState(newPaginator); // Panggil update setiap kali props.users berubah
}, { deep: true });


const loadMoreUsers = () => {
    // console.log('[loadMoreUsers] Attempting. isLoadingMore:', isLoadingMore.value, 'currentPage:', currentPage.value, 'lastPage:', lastPage.value);
    if (isLoadingMore.value || !initialLoadComplete.value || currentPage.value >= lastPage.value) {
        return;
    }
    isLoadingMore.value = true;
    // console.log(`[loadMoreUsers] Loading page ${currentPage.value + 1}...`);

    router.get(route('admin.users.index'), {
        search: searchTerm.value,
        page: currentPage.value + 1,
    }, {
        preserveState: true, preserveScroll: true, replace: true,
        onSuccess: (page) => {
            // console.log('[loadMoreUsers onSuccess] More users loaded. Page props:', JSON.parse(JSON.stringify(page.props.users)));
            if (page.props.users && page.props.users.data) {
                const existingIds = new Set(allUsers.value.map(u => u.id));
                const newUniqueUsers = page.props.users.data.filter(u => !existingIds.has(u.id));
                allUsers.value.push(...newUniqueUsers);
                // console.log('[loadMoreUsers onSuccess] Added to allUsers:', newUniqueUsers.length, 'new items. Total:', allUsers.value.length);
            }
            // Update currentPage dan lastPage dari respons baru
            if (page.props.users && typeof page.props.users.current_page !== 'undefined' && typeof page.props.users.last_page !== 'undefined') {
                currentPage.value = page.props.users.current_page;
                lastPage.value = page.props.users.last_page;
            }
            isLoadingMore.value = false;
        },
        onError: () => {
            isLoadingMore.value = false;
            console.error("[loadMoreUsers onError] Failed to load more users.");
        }
    });
};

const handleScroll = debounce(() => {
    const nearBottom = window.innerHeight + window.scrollY >= document.documentElement.offsetHeight - 300; // Offset 300px
    if (nearBottom) {
        loadMoreUsers();
    }
}, 150);

const formatDate = (dateString) => { /* ... (fungsi Anda) ... */
    if (!dateString) return 'N/A';
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('id-ID', options);
};
const openEditUserModal = (user) => {  
    // console.log('TODO: Edit user:', user.name); 
    router.get(route('admin.users.edit', user.id)); 
};
const openDeleteUserModal = (user) => {  
    // console.log('TODO: Hapus user:', user.name);
};

// Hapus fungsi submit dan testClickAndNavigate jika tidak digunakan di sini
// const submit = () => { ... };
// const testClickAndNavigate = () => { ... };

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
        <div v-if="initialLoadComplete && allUsers.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <!-- Gunakan allUsers -->
            <div v-for="user in allUsers" :key="user.id" class="bg-slate-50 dark:bg-slate-700/50 p-4 rounded-lg shadow-md hover:shadow-xl transition-shadow duration-200 flex flex-col justify-between" :class="{ 'opacity-60 dark:opacity-50': !user.is_active }">
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
      <!-- Indikator Loading More -->
        <div v-if="isLoadingMore" class="text-center py-6">
            <svg class="animate-spin h-8 w-8 text-gray-500 dark:text-gray-400 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Memuat lebih banyak...</p>
        </div>

        <!-- Pesan Jika Tidak Ada User (setelah load awal selesai) -->
        <div v-if="initialLoadComplete && allUsers.length === 0 && !isLoadingMore" class="text-center py-10">
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

        <!-- Hapus Paginasi Tradisional -->
        <!-- <div v-if="props.users && props.users.data && props.users.data.length > 0 && props.users.meta && props.users.meta.links && props.users.meta.last_page && props.users.meta.last_page > 1" class="mt-6">
            <Pagination :links="props.users.meta.links" />
        </div> -->
    </AdminLayout>
</template>