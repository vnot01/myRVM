<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import debounce from 'lodash/debounce';
import Pagination from '@/Components/Pagination.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
// Kita akan butuh komponen Modal nanti
// import Modal from '@/Components/Modal.vue';
// import SecondaryButton from '@/Components/SecondaryButton.vue';
// import DangerButton from '@/Components/DangerButton.vue';

const props = defineProps({
    users: Object,
    filters: Object,
    // availableRoles: Array, // Mungkin tidak perlu lagi jika filter diimplementasikan berbeda
    // availableStatuses: Array,
});

const searchTerm = ref(props.filters.search || '');

watch(searchTerm, debounce((newValue) => {
    router.get(route('admin.users.index'), {
        search: newValue,
    }, { preserveState: true, replace: true, preserveScroll: true });
}, 300));

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const options = { year: 'numeric', month: 'short', day: 'numeric', /*hour: '2-digit', minute: '2-digit'*/ };
    return new Date(dateString).toLocaleDateString('id-ID', options);
};

// State untuk modal (akan diimplementasikan nanti)
// const confirmingUserDeletion = ref(false);
// const userToDelete = ref(null);
// const editingUser = ref(null);
// const showEditUserModal = ref(false);

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

</script>

<template>
    <Head title="Manajemen Pengguna" />

    <AdminLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Manajemen Pengguna
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">

                        <!-- === BAGIAN ATAS HALAMAN KONTEN (BARU) === -->
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
                            <div>
                                <h3 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                    Pengguna Terdaftar
                                </h3>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    Daftar semua pengguna yang ada di sistem.
                                </p>
                            </div>
                            <div class="mt-4 sm:mt-0">
                                <!-- Tombol Tambah User (akan dibuat nanti) -->
                                <!-- <Link :href="route('admin.users.create')">
                                    <PrimaryButton class="flex items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 mr-2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                        </svg>
                                        Tambah User
                                    </PrimaryButton>
                                </Link> -->
                            </div>
                        </div>
                        <!-- Input Pencarian (setelah baris judul dan tombol) -->
                        <div class="mb-6">
                            <TextInput
                                type="text"
                                class="mt-1 block w-full md:w-2/3 lg:w-1/2"
                                v-model="searchTerm"
                                :placeholder="'Cari berdasarkan nama atau email...'"
                            />
                        </div>
                        <!-- === AKHIR BAGIAN ATAS HALAMAN KONTEN === -->

                        <!-- Daftar User Card-Style -->
                        <div v-if="users.data.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div
                                v-for="user in users.data"
                                :key="user.id"
                                class="bg-slate-50 dark:bg-slate-700/50 p-4 rounded-lg shadow-md hover:shadow-xl transition-shadow duration-200 flex flex-col justify-between"
                            >
                                <div>
                                    <div class="flex items-center mb-3">
                                        <!-- Avatar -->
                                        <img
                                            v-if="user.avatar"
                                            :src="user.avatar"
                                            :alt="user.name"
                                            class="h-12 w-12 rounded-full object-cover mr-4 border-2 border-slate-300 dark:border-slate-600"
                                        />
                                        <div v-else class="h-12 w-12 rounded-full bg-slate-200 dark:bg-slate-600 flex items-center justify-center text-slate-400 dark:text-slate-500 mr-4 border-2 border-slate-300 dark:border-slate-600">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-7 h-7">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                            </svg>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-md font-semibold text-gray-900 dark:text-white truncate items-center">
                                                {{ user.name }}
                                                <span v-if="user.email_verified_at" title="Email Terverifikasi" class="ml-1 text-green-500 dark:text-green-400 inline-block">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.06 0l4.07-5.576z" clip-rule="evenodd" /></svg>
                                                </span>
                                                <span v-if="!user.is_active" title="Akun Tidak Aktif" class="ml-1 text-red-500 dark:text-red-400 inline-block">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" /></svg>
                                                </span>
                                            </p>
                                            <p class="text-sm text-gray-600 dark:text-gray-400 truncate">{{ user.email }}</p>
                                        </div>
                                    </div>
                                    <div class="border-t border-slate-200 dark:border-slate-600 pt-3 mt-2">
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Role: <span class="font-medium">{{ user.role }}</span></p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Poin: <span class="font-medium">{{ user.points }}</span></p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Bergabung: {{ formatDate(user.created_at) }}</p>
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

                        <div v-if="users.data.length > 0 && users.meta && users.meta.links && users.meta.last_page > 1" class="mt-6">
                            <Pagination :links="users.meta.links" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal untuk Edit User akan ditambahkan di sini -->
        <!-- Modal untuk Konfirmasi Hapus User akan ditambahkan di sini -->

    </AdminLayout>
</template>

<style scoped>
/* Jika perlu styling tambahan spesifik */
.max-h-\[60vh\] {
    max-height: 60vh;
}
.md\:max-h-\[70vh\] {
    max-height: 70vh;
}
</style>