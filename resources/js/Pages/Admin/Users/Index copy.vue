<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3'; // Tambahkan useForm
import { ref, watch, computed } from 'vue'; // Tambahkan computed
import throttle from 'lodash/throttle';
import Pagination from '@/Components/Pagination.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';   // Impor komponen form
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue'; // Untuk tombol batal di form edit
import SelectInput from '@/Components/SelectInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { IconUserCircle, IconSearch, IconUserPlus, IconMail, IconCalendarEvent, IconShieldCheck, IconShieldOff } from '@tabler/icons-vue';

const props = defineProps({
    users: Object, // Objek paginator dari Laravel
    filters: Object, // Filter yang aktif (search, role, status)
    availableRolesProp: Array, // Ganti nama agar tidak konflik dengan computed
    availableStatuses: Array, // Ganti nama agar tidak konflik dengan computed
});
// console.log('Prop users di Index.vue:', JSON.parse(JSON.stringify(props.users)));
const searchTerm = ref(props.filters.search || '');

// Watcher untuk searchTerm dengan debounce
watch(searchTerm, throttle((newValue) => {
    router.get(route('admin.users.index'), { search: newValue, role: activeRoleFilter.value, status: activeStatusFilter.value }, { // Sertakan filter lain
        preserveState: true, // Agar state komponen lain tidak hilang
        replace: true, // Ganti URL tanpa menambah histori browser
        preserveScroll: true, // Pertahankan posisi scroll
    });
}, 300)); // Debounce 300ms

// Fungsi untuk format tanggal (bisa dibuat helper global nanti)
const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        // hour: '2-digit',
        // minute: '2-digit'
    });
};

// Fungsi untuk tooltip hover (implementasi dasar)
const showTooltip = ref(false);
const tooltipUser = ref(null);
const tooltipPosition = ref({ top: 0, left: 0 });
let hoverTimeout = null;

const handleMouseEnter = (event, user) => {
    clearTimeout(hoverTimeout); // Hapus timeout sebelumnya jika ada
    hoverTimeout = setTimeout(() => {
        const rect = event.target.getBoundingClientRect();
        tooltipPosition.value = {
            top: rect.bottom + window.scrollY + 5, // Sedikit di bawah elemen
            left: rect.left + window.scrollX + (rect.width / 2) // Tengah elemen
        };
        tooltipUser.value = user;
        showTooltip.value = true;
    }, 700); // Tunda 700ms sebelum tooltip muncul
};

const handleMouseLeave = () => {
    clearTimeout(hoverTimeout);
    showTooltip.value = false;
    tooltipUser.value = null;
};

// Untuk panel detail/edit (akan diimplementasikan nanti)
const selectedUser = ref(null);
const showDetailPanel = ref(false);

// Gunakan useForm untuk form edit
const editForm = useForm({
    _method: 'PATCH', // atau PUT
    name: '',
    email: '',
    role: '',
    phone_number: '',
    citizenship: 'WNI',
    identity_type: '',
    identity_number: '',
    is_active: true,
    avatar: null, // Untuk file upload baru
    password: '',
    password_confirmation: '',
});

// Untuk preview avatar
const avatarPreview = ref(null);
// Opsi role untuk form edit (tidak termasuk Admin jika yang diedit bukan diri sendiri)
const availableRolesForEdit = computed(() => {
    if (selectedUser.value && selectedUser.value.id === usePage().props.auth.user.id && selectedUser.value.role === 'Admin') {
        return ['Admin', 'Operator', 'User']; // Admin bisa ubah role diri sendiri (hati-hati)
    }
    // Untuk user lain, Admin bisa set ke Operator atau User
    // Jika Anda ingin konsisten dengan form create, hanya 'Operator', 'User'
    return props.availableRolesProp || ['Operator', 'User'];
});

const viewUserDetails = (user) => {
    selectedUser.value = user;
    // Isi form edit dengan data user yang dipilih
    editForm.name = user.name;
    editForm.email = user.email;
    editForm.role = user.role;
    editForm.phone_number = user.phone_number || '';
    editForm.citizenship = user.citizenship || 'WNI';
    editForm.identity_type = user.identity_type || '';
    editForm.identity_number = user.identity_number || '';
    editForm.is_active = user.is_active ?? true;
    editForm.avatar = null; // Reset input file avatar
    avatarPreview.value = user.avatar_url || user.avatar || null; // Gunakan avatar_url jika ada (dari accessor)
    editForm.password = ''; // Selalu kosongkan field password
    editForm.password_confirmation = '';
    editForm.errors = {}; // Bersihkan error validasi sebelumnya
    showDetailPanel.value = true;
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
function selectNewAvatar(event) {
    const file = event.target.files[0];
    if (file) {
        editForm.avatar = file;
        avatarPreview.value = URL.createObjectURL(file);
    }
}
const submitEditForm = () => {
    if (!selectedUser.value) return;
    editForm.post(route('admin.users.update', selectedUser.value.id), {
        preserveScroll: true,
        onError: (formErrors) => {
            if (formErrors.password) {
                editForm.reset('password', 'password_confirmation');
            }
        },
        onSuccess: () => {
            showDetailPanel.value = false; // Tutup panel setelah sukses
            selectedUser.value = null; // Reset user terpilih
            // Notifikasi flash akan muncul otomatis dari redirect backend
        }
    });
};
const closeDetailPanel = () => {
    showDetailPanel.value = false;
    selectedUser.value = null;
    editForm.reset();
    editForm.clearErrors();
    avatarPreview.value = null;
};

// Logika kondisional untuk Tipe Identitas (sama seperti Create.vue)
const citizenshipOptions = [
    { value: 'WNI', label: 'WNI (Warga Negara Indonesia)' },
    { value: 'WNA', label: 'WNA (Warga Negara Asing)' },
];

const identityTypeOptions = computed(() => {
    if (editForm.citizenship === 'WNI') {
        return [ { value: '', label: '-- Pilih Tipe --'}, { value: 'KTP', label: 'KTP' }, { value: 'Pasport', label: 'Pasport' }];
    } else if (editForm.citizenship === 'WNA') {
        return [ { value: '', label: '-- Pilih Tipe --'}, { value: 'Pasport', label: 'Pasport' }];
    }
    return [{ value: '', label: '-- Pilih Kewarganegaraan Dahulu --'}];
});

watch(() => editForm.citizenship, (newCitizenship, oldCitizenship) => {
    if (newCitizenship !== oldCitizenship && showDetailPanel.value) { // Hanya jika panel terbuka
        const currentOptions = identityTypeOptions.value.map(opt => opt.value);
        if (!currentOptions.includes(editForm.identity_type)) {
            editForm.identity_type = currentOptions.length > 0 ? currentOptions[0] : '';
        }
        editForm.identity_number = '';
    }
});
watch(() => editForm.identity_type, (newType, oldType) => {
    if (newType !== oldType && showDetailPanel.value) {
        editForm.identity_number = '';
    }
});

// Filter state (jika Anda ingin implementasi filter di header)
const activeRoleFilter = ref(props.filters.role || 'all');
const activeStatusFilter = ref(props.filters.status || 'all');

// Watcher untuk filter role dan status
watch([activeRoleFilter, activeStatusFilter], ([newRole, newStatus]) => {
    router.get(route('admin.users.index'), {
        search: searchTerm.value,
        role: newRole === 'all' ? undefined : newRole,
        status: newStatus === 'all' ? undefined : newStatus,
    }, { preserveState: true, replace: true, preserveScroll: true });
});
</script>

<template>
  <!-- <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        Halaman Daftar Pengguna akan ada di sini.
                        <pre>{{ users }}</pre> <! -- Untuk debugging data awal -- >
                    </div>
                </div>
            </div>
        </div> -->
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
                <!-- Tombol Tambah Pengguna (Placeholder, rute akan dibuat nanti) -->
                 <!-- Ganti dengan route('admin.users.create') nanti -->
                <!-- <InertiaLink @click="testClickAndNavigate" :href="route('admin.users.create')" -->
                    <!-- class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors text-sm font-medium inline-flex items-center"> -->
                    <!-- <IconUserPlus class="w-5 h-5 mr-1 sm:mr-2" /> -->
                    <!-- <span class="hidden sm:inline">Tambah Pengguna</span> -->
                    <!-- <span class="sm:hidden">Baru</span> -->
                <!-- </InertiaLink> -->
                <InertiaLink @click="testClickAndNavigate" :href="route('admin.users.create')"
                  class="cursor-pointer select-none px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors text-sm font-medium inline-flex items-center">
                  <IconUserPlus class="w-5 h-5 mr-1 sm:mr-2" />
                  <span class="hidden sm:inline select-none">Tambah Pengguna</span>
                  <span class="sm:hidden">Baru</span>
              </InertiaLink>
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
                    placeholder="Cari berdasarkan nama atau email..."
                    class="block w-full p-3 ps-10 text-sm dark:bg-slate-700 dark:text-gray-300 dark:placeholder-gray-400"
                />
            </div>
        </div>

        <!-- Kontainer Utama untuk Daftar User dan Panel Detail -->
        <div class="flex flex-col md:flex-row gap-6">
            <!-- Kolom Kiri: Daftar Pengguna -->
            <div class="w-full md:w-2/3 lg:w-3/4">
                 <!-- Pesan jika tidak ada data atau hasil pencarian kosong -->
                <div v-if="users.data.length === 0 && !searchTerm" class="text-center py-10 bg-white dark:bg-slate-800 shadow-sm sm:rounded-lg p-6">
                    <IconUserCircle class="h-16 w-16 text-gray-300 dark:text-gray-600 mx-auto" />
                    <p class="mt-4 text-lg text-gray-500 dark:text-slate-400">Belum ada pengguna terdaftar.</p>
                    <p class="text-sm text-gray-400 dark:text-slate-500">Mulai dengan menambahkan pengguna baru.</p>
                </div>
                <div v-else-if="users.data.length === 0 && searchTerm" class="text-center py-10 bg-white dark:bg-slate-800 shadow-sm sm:rounded-lg p-6">
                    <IconSearch class="h-16 w-16 text-gray-300 dark:text-gray-600 mx-auto" />
                    <p class="mt-4 text-lg text-gray-500 dark:text-slate-400">
                        Tidak ada pengguna ditemukan untuk "<span class="font-semibold">{{ searchTerm }}</span>".
                    </p>
                    <p class="text-sm text-gray-400 dark:text-slate-500">Coba kata kunci pencarian lain.</p>
                </div>

                <!-- Daftar Pengguna (Card Style) -->
                <div v-else class="space-y-3">
                    <div
                        v-for="user in users.data"
                        :key="user.id"
                        @mouseenter="handleMouseEnter($event, user)"
                        @mouseleave="handleMouseLeave"
                        @click="viewUserDetails(user)"
                        class="bg-white dark:bg-slate-800 p-4 rounded-lg shadow-sm hover:shadow-lg transition-all duration-200 cursor-pointer"
                        :class="{'ring-2 ring-brand-teal dark:ring-teal-500': selectedUser && selectedUser.id === user.id}"
                    >
                        <!-- ... (konten kartu user sama seperti sebelumnya: Avatar, Info Utama) ... -->
                        <div class="flex items-center space-x-4">
                            <div class="flex-shrink-0">
                                <img v-if="user.avatar" :src="user.avatar" alt="Avatar" class="h-12 w-12 rounded-full object-cover"/>
                                <IconUserCircle v-else class="h-12 w-12 text-gray-400 dark:text-gray-500" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-800 dark:text-gray-100 truncate">
                                    {{ user.name }}
                                    <IconShieldCheck v-if="user.email_verified_at" class="h-4 w-4 inline-block text-green-500 ml-1" title="Email Terverifikasi" />
                                    <IconShieldOff v-else class="h-4 w-4 inline-block text-red-500 ml-1" title="Email Belum Terverifikasi" />
                                </p>
                                <p class="text-xs text-gray-500 dark:text-slate-400 truncate flex items-center">
                                    <IconMail class="h-3 w-3 mr-1" /> {{ user.email }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-slate-400 truncate flex items-center">
                                    <IconCalendarEvent class="h-3 w-3 mr-1" /> Bergabung: {{ formatDate(user.created_at) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Paginasi -->
                <div v-if="users && users.data && users.data.length > 0 && users.meta && users.meta.last_page && users.meta.last_page > 1" class="mt-6">
                    <Pagination v-if="users.meta.links" :links="users.meta.links" />

                    <div class="flex items-center space-x-4">
                      <!-- ... Avatar dan Info Utama ... -->
                      <div class="flex-1 min-w-0">
                          <p @click="viewUserDetails(user)" class="text-sm font-semibold text-gray-800 dark:text-gray-100 truncate hover:underline">
                              {{ user.name }}
                              <!-- ... ikon verifikasi ... -->
                          </p>
                          <!-- ... email dan tanggal ... -->
                      </div>
                      <!-- Tombol aksi di kartu user -->
                      <div class="flex-shrink-0">
                          <button @click="viewUserDetails(user)"
                                  class="px-3 py-1 text-xs font-medium text-indigo-600 dark:text-indigo-400 bg-indigo-100 dark:bg-indigo-700/50 rounded-md hover:bg-indigo-200 dark:hover:bg-indigo-600/50">
                              Detail/Edit
                          </button>
                      </div>
                  </div>

                </div>
            </div>

            <!-- Kolom Kanan: Panel Detail/Edit User -->
             <!-- self-start agar panel tidak scroll bersama list -->
            <div class="w-full md:w-1/3 lg:w-1/4 sticky top-24 self-start">
                <div v-if="!showDetailPanel || !selectedUser" class="bg-white dark:bg-slate-800 shadow-sm sm:rounded-lg p-6 text-center text-gray-500 dark:text-slate-400 h-full flex flex-col justify-center items-center">
                    <IconUserCircle class="h-20 w-20 text-gray-300 dark:text-gray-600 mx-auto mb-4" />
                    <p>Pilih pengguna dari daftar di kiri untuk melihat atau mengedit detailnya.</p>
                </div>

                <!-- Form Edit akan ditampilkan di sini jika selectedUser ada -->
                <div v-if="showDetailPanel && selectedUser"
                    class="bg-white dark:bg-slate-800 shadow-lg rounded-lg p-0 overflow-hidden transition-all duration-300 ease-in-out">
                    <!-- Header Panel Edit -->
                    <div class="p-4 sm:p-6 border-b dark:border-slate-700">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Edit: {{ selectedUser.name }}</h3>
                            <button @click="showDetailPanel = false; selectedUser = null" class="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300 p-1 rounded-full hover:bg-slate-100 dark:hover:bg-slate-700">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    </div>
                    <!-- Komponen Form Edit User (atau form inline) -->
                    <div class="p-4 sm:p-6 max-h-[calc(100vh-10rem)] overflow-y-auto">
                        <!-- Kita akan pindahkan konten Edit.vue ke sini sebagai komponen atau inline -->
                        <p class="dark:text-gray-300">Form edit untuk {{ selectedUser.email }} akan ada di sini.</p>
                        <!-- Contoh: <UserEditForm :user="selectedUser" @updated="showDetailPanel = false" @cancelled="showDetailPanel = false" /> -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Tooltip on Hover -->
        <div
            v-if="showTooltip && tooltipUser"
            :style="{ top: tooltipPosition.top + 'px', left: tooltipPosition.left + 'px', transform: 'translateX(-50%)' }"
            class="fixed z-[100] px-2 py-1 text-xs font-normal text-white bg-gray-900 rounded-md shadow-sm dark:bg-slate-700"
            role="tooltip"
        >
            Klik untuk Detail
        </div>
    </AdminLayout>
</template>