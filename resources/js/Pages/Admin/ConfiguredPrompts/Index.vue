<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import debounce from 'lodash/debounce';
import Pagination from '@/Components/Pagination.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { IconPlus, IconEdit, IconTrash, IconPlayerPlay, IconPlayerStop, IconCopy, IconEye } from '@tabler/icons-vue'; // Tambah ikon

const props = defineProps({
    configuredPrompts: Object, // Objek paginator dari Laravel untuk ConfiguredPrompt
    filters: Object,
});

const searchTerm = ref(props.filters.search || '');

watch(searchTerm, debounce((newValue) => {
    router.get(route('admin.configured-prompts.index'), { // Pastikan nama rute benar
        search: newValue,
    }, { preserveState: true, replace: true, preserveScroll: true });
}, 300));

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const options = { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
    return new Date(dateString).toLocaleDateString('id-ID', options);
};

// Fungsi untuk menyalin teks ke clipboard
const copyToClipboard = (text) => {
    navigator.clipboard.writeText(text).then(() => {
        alert('Teks prompt disalin ke clipboard!');
    }).catch(err => {
        console.error('Gagal menyalin teks: ', err);
        alert('Gagal menyalin teks.');
    });
};


// Fungsi placeholder untuk aksi
const activateConfiguredPrompt = (promptId) => {
    if (confirm('Apakah Anda yakin ingin mengaktifkan prompt ini? Ini akan menonaktifkan prompt lain yang sedang aktif.')) {
        router.post(route('admin.configured-prompts.activate', promptId), {}, {
            preserveScroll: true,
            onSuccess: () => { /* Pesan flash ditangani AdminLayout */ }
        });
    }
};

const deleteConfiguredPrompt = (promptId, promptName) => {
    if (confirm(`Apakah Anda yakin ingin menghapus konfigurasi prompt "${promptName}"? Template dan Komponen dasarnya tidak akan terhapus.`)) {
        router.delete(route('admin.configured-prompts.destroy', promptId), {
            preserveScroll: true,
            onSuccess: () => { /* Pesan flash ditangani AdminLayout */ }
        });
    }
};

</script>

<template>
    <Head title="Manajemen Konfigurasi Prompt" />

    <AdminLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Manajemen Konfigurasi Prompt AI
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-full mx-auto sm:px-6 lg:px-8"> <!-- max-w-full agar tabel bisa lebar -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-lg font-medium">Daftar Konfigurasi Prompt Tersimpan</h3>
                            <Link :href="route('admin.configured-prompts.create')">
                                <PrimaryButton class="flex items-center">
                                    <IconPlus class="w-5 h-5 mr-2" />
                                    Buat Konfigurasi Baru
                                </PrimaryButton>
                            </Link>
                        </div>

                        <div class="mb-4">
                            <TextInput
                                type="text"
                                class="mt-1 block w-full md:w-1/2"
                                v-model="searchTerm"
                                placeholder="Cari berdasarkan nama atau deskripsi konfigurasi..."
                            />
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Nama Konfigurasi</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Template Dasar</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Deskripsi</th>
                                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Aktif</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Versi</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Terakhir Update</th>
                                        <th scope="col" class="relative px-6 py-3"><span class="sr-only">Aksi</span></th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-if="configuredPrompts.data.length === 0">
                                        <td colspan="7" class="px-6 py-4 whitespace-nowrap text-sm text-center text-gray-500 dark:text-gray-400">
                                            Tidak ada konfigurasi prompt ditemukan.
                                        </td>
                                    </tr>
                                    <tr v-for="configPrompt in configuredPrompts.data" :key="configPrompt.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">{{ configPrompt.configured_prompt_name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ configPrompt.template?.template_name || 'Manual / Tidak ada template' }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 max-w-xs truncate" :title="configPrompt.description">{{ configPrompt.description }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm">
                                            <span v-if="configPrompt.is_active" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-700 dark:text-green-100 animate-pulse" title="Sedang Aktif">Ya</span>
                                            <span v-else class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-300">Tidak</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ configPrompt.version }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ formatDate(configPrompt.updated_at) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                            <button @click="copyToClipboard(configPrompt.full_prompt_text_generated || '')" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300" title="Salin Full Prompt">
                                                <IconCopy class="w-5 h-5"/>
                                            </button>
                                            <button @click="activateConfiguredPrompt(configPrompt.id)" v-if="!configPrompt.is_active" class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300" title="Aktifkan Prompt Ini">
                                                <IconPlayerPlay class="w-5 h-5"/>
                                            </button>
                                            <button v-else class="text-gray-400 cursor-not-allowed" title="Prompt Ini Sudah Aktif">
                                                 <IconPlayerStop class="w-5 h-5"/>
                                            </button>
                                            <Link :href="route('admin.configured-prompts.edit', configPrompt.id)" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300" title="Edit Konfigurasi">
                                                <IconEdit class="w-5 h-5"/>
                                            </Link>
                                            <button @click="deleteConfiguredPrompt(configPrompt.id, configPrompt.configured_prompt_name)" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300" title="Hapus Konfigurasi">
                                                <IconTrash class="w-5 h-5"/>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-if="configuredPrompts.data.length > 0 && configuredPrompts.meta && configuredPrompts.meta.links && configuredPrompts.meta.last_page > 1" class="mt-6">
                            <Pagination :links="configuredPrompts.meta.links" />
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>