<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3'; // Tambahkan useForm
import { ref, watch, onMounted, onUnmounted, nextTick } from 'vue';
import debounce from 'lodash/debounce';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';    // Untuk tombol hapus di modal
import SecondaryButton from '@/Components/SecondaryButton.vue';// Untuk tombol batal di modal
import Modal from '@/Components/Modal.vue';                  // Komponen Modal Breeze
import InputError from '@/Components/InputError.vue';        // Untuk error di modal
// Impor semua ikon Tabler yang digunakan
import {
    IconSearch, IconPlus, IconEdit, IconTrash,
    IconPlayerPlay, IconPlayerStop, IconCopy
} from '@tabler/icons-vue';

const props = defineProps({
    configuredPrompts: Object, // Objek paginator dari Laravel untuk ConfiguredPrompt
    filters: Object,
});
// --- State untuk Infinite Scrolling ---
const allConfiguredPrompts = ref([]);
const currentPage = ref(1);
const lastPage = ref(1);
const isLoadingMore = ref(false);
const initialLoadComplete = ref(false);
const searchTerm = ref(props.filters.search || '');
const scrollContainerRef = ref(null);

// --- State untuk Modal Hapus ---
const confirmingPromptDeletion = ref(false); // Ganti nama agar lebih spesifik
const promptToDelete = ref(null);           // Ganti nama
const deleteConfirmationName = ref('');
const deleteForm = useForm({}); // Form Inertia untuk request DELETE

// Fungsi untuk menyalin teks ke clipboard
const copyToClipboard = (text) => {
    navigator.clipboard.writeText(text).then(() => {
        alert('Teks prompt disalin ke clipboard!');
    }).catch(err => {
        console.error('Gagal menyalin teks: ', err);
        alert('Gagal menyalin teks.');
    });
};

// Fungsi untuk mengisi/mengupdate state dari paginator props
const updateLocalConfiguredPromptState = (paginator) => {
    if (paginator && paginator.data && typeof paginator.current_page !== 'undefined' && typeof paginator.last_page !== 'undefined') {
        if (paginator.current_page === 1) {
            allConfiguredPrompts.value = paginator.data ? [...paginator.data] : [];
        } else {
            const existingIds = new Set(allConfiguredPrompts.value.map(p => p.id));
            const newUniqueItems = (paginator.data || []).filter(p => !existingIds.has(p.id));
            allConfiguredPrompts.value.push(...newUniqueItems);
        }
        currentPage.value = paginator.current_page;
        lastPage.value = paginator.last_page;
    } else {
        allConfiguredPrompts.value = [];
        currentPage.value = 1;
        lastPage.value = 1;
    }
    initialLoadComplete.value = true;
};

onMounted(() => {
    updateLocalConfiguredPromptState(props.configuredPrompts);
    nextTick(() => {
        if (scrollContainerRef.value) {
            scrollContainerRef.value.addEventListener('scroll', handleScroll);
        }
    });
});

onUnmounted(() => {
    if (scrollContainerRef.value) {
        scrollContainerRef.value.removeEventListener('scroll', handleScroll);
    }
});

watch(searchTerm, debounce((newValue) => {
    isLoadingMore.value = false;
    router.get(route('admin.configured-prompts.index'), {
        search: newValue,
        page: 1
    }, { preserveState: true, preserveScroll: false, replace: true });
}, 300));

watch(() => props.configuredPrompts, (newPaginator) => {
    updateLocalConfiguredPromptState(newPaginator);
}, { deep: true });

const loadMoreConfiguredPrompts = () => {
    if (isLoadingMore.value || !initialLoadComplete.value || currentPage.value >= lastPage.value) {
        return;
    }
    isLoadingMore.value = true;
    router.get(route('admin.configured-prompts.index'), {
        search: searchTerm.value,
        page: currentPage.value + 1,
    }, {
        preserveState: true, preserveScroll: true, replace: true,
        onSuccess: (page) => {
            if (page.props.configuredPrompts && page.props.configuredPrompts.data) {
                const existingIds = new Set(allConfiguredPrompts.value.map(p => p.id));
                const newUniqueItems = page.props.configuredPrompts.data.filter(p => !existingIds.has(p.id));
                allConfiguredPrompts.value.push(...newUniqueItems);
            }
            if (page.props.configuredPrompts && typeof page.props.configuredPrompts.current_page !== 'undefined') {
                currentPage.value = page.props.configuredPrompts.current_page;
                lastPage.value = page.props.configuredPrompts.last_page;
            }
            isLoadingMore.value = false;
        },
        onError: () => { isLoadingMore.value = false; }
    });
};

const handleScroll = debounce(() => {
    const el = scrollContainerRef.value;
    if (el) {
        const nearBottom = el.scrollTop + el.clientHeight >= el.scrollHeight - 300;
        if (nearBottom && !isLoadingMore.value && currentPage.value < lastPage.value) {
            loadMoreConfiguredPrompts();
        }
    }
}, 150);

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const options = { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
    return new Date(dateString).toLocaleDateString('id-ID', options);
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

const editConfiguredPrompt = (promptId) => {
    router.get(route('admin.configured-prompts.edit', promptId));
};
// --- Fungsi untuk Modal Hapus ---
const openDeleteConfirmationModal = (prompt) => { // Terima objek prompt
    promptToDelete.value = prompt;
    deleteConfirmationName.value = '';
    confirmingPromptDeletion.value = true;
};

const closeDeleteModal = () => {
    confirmingPromptDeletion.value = false;
    promptToDelete.value = null;
    deleteConfirmationName.value = '';
    deleteForm.reset(); // Reset error form jika ada
};

const confirmAndDeletePrompt = () => { // Ganti nama fungsi
    if (promptToDelete.value && deleteConfirmationName.value === promptToDelete.value.configured_prompt_name) {
        const deletedPromptName = promptToDelete.value.configured_prompt_name; // Simpan nama
        deleteForm.delete(route('admin.configured-prompts.destroy', promptToDelete.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                // console.log(`Konfigurasi prompt "${deletedPromptName}" proses hapus berhasil dikirim.`);
                closeDeleteModal();
                // Daftar akan otomatis refresh karena redirect dari backend & watcher props.configuredPrompts
            },
            onError: (errors) => {
                console.error('Gagal menghapus konfigurasi prompt:', errors);
                // Error akan otomatis dihandle oleh Inertia form jika ada InputError untuk deleteForm.errors
                // Jika Anda ingin pesan kustom di modal:
                // deleteForm.setError('general', 'Gagal menghapus. Silakan coba lagi.');
            }
        });
    } else if (promptToDelete.value) {
        alert(`Nama konfigurasi prompt "${deleteConfirmationName.value}" yang Anda masukkan tidak cocok dengan "${promptToDelete.value.configured_prompt_name}"!`);
        deleteConfirmationName.value = '';
    } else {
        alert('Tidak ada konfigurasi prompt yang dipilih untuk dihapus.');
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

        <!-- Kontainer untuk seluruh konten halaman Index.vue -->
        <div class="flex flex-col h-[calc(100vh-5rem-1rem)]"> <!-- Sesuaikan OFFSET -->
            <!-- Bagian Atas yang Sticky -->
            <div class="flex-shrink-0 bg-gray-100 dark:bg-gray-800 pb-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex flex-col sm:flex-row justify-between items-center gap-2 pt-0 mx-4 sm:mx-6 lg:mx-8">
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                        Konfigurasi Prompt Tersimpan
                    </h1>
                    <div class="flex items-center gap-2">
                        <Link :href="route('admin.configured-prompts.create')">
                            <PrimaryButton class="flex items-center">
                                <IconPlus class="w-5 h-5 mr-2" />
                                Buat Konfigurasi
                            </PrimaryButton>
                        </Link>
                    </div>
                </div>
                <div class="mt-4 mx-4 sm:mx-6 lg:mx-8">
                    <div class="relative">
                        <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                            <IconSearch class="w-4 h-4 text-gray-500 dark:text-gray-400" />
                        </div>
                        <TextInput
                            type="search"
                            v-model="searchTerm"
                            placeholder="Cari berdasarkan nama atau deskripsi..."
                            class="block w-full p-3 ps-10 text-sm dark:bg-slate-700 dark:text-gray-300 dark:placeholder-gray-400"
                        />
                    </div>
                    
                </div>
            </div>

            <!-- Area Scrollable untuk Daftar Panel Prompt -->
            <div ref="scrollContainerRef" class="flex-grow overflow-y-auto px-4 sm:px-6 lg:px-8">
                <div v-if="initialLoadComplete && allConfiguredPrompts.length > 0" class="space-y-4 py-4">
                    <div
                        v-for="configPrompt in allConfiguredPrompts"
                        :key="configPrompt.id"
                        class="rounded-lg shadow-lg hover:shadow-xl transition-shadow duration-200 p-5 border-l-4"
                        :class="{
                            'bg-green-50 dark:bg-green-800/30 border-green-500 dark:border-green-400 animate-pulse': configPrompt.is_active,
                            'bg-slate-50 dark:bg-slate-700/60 border-slate-300 dark:border-slate-600': !configPrompt.is_active
                        }"
                    >
                        <div class="flex flex-col sm:flex-row justify-between sm:items-start">
                            <div class="flex-1 min-w-0 mb-3 sm:mb-0">
                                <div class="flex items-center mb-1">
                                    <h4 class="text-lg font-semibold text-gray-900 dark:text-white truncate" :title="configPrompt.configured_prompt_name">
                                        {{ configPrompt.configured_prompt_name }}
                                    </h4>
                                    <span v-if="configPrompt.is_active" class="ml-2 px-2 py-0.5 text-xs font-semibold bg-green-200 text-green-800 rounded-full dark:bg-green-700 dark:text-green-100">Aktif</span>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">
                                    Template Dasar: <span class="font-medium">{{ configPrompt.template?.template_name || 'Manual / Tanpa template' }}</span>
                                </p>
                                <p class="text-sm text-gray-600 dark:text-gray-300 truncate" :title="configPrompt.description">
                                    {{ configPrompt.description || 'Tidak ada deskripsi.' }}
                                </p>
                                <div class="mt-2 text-xs text-gray-400 dark:text-gray-500">
                                    <span>Versi: {{ configPrompt.version }}</span>
                                    <span class="mx-1.5">|</span>
                                    <span>Terakhir Update: {{ formatDate(configPrompt.updated_at) }}</span>
                                </div>
                            </div>
                            <div class="flex-shrink-0 flex sm:flex-col items-end sm:items-start space-x-2 sm:space-x-0 sm:space-y-2 sm:ml-4">
                                <button @click.stop="copyToClipboard(configPrompt.full_prompt_text_generated || '')" class="p-1.5 text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 bg-blue-100 dark:bg-blue-700/50 hover:bg-blue-200 dark:hover:bg-blue-600/50 rounded-md" title="Salin Full Prompt">
                                    <IconCopy class="w-4 h-4"/>
                                </button>
                                <button @click.stop="activateConfiguredPrompt(configPrompt.id)" v-if="!configPrompt.is_active" class="p-1.5 text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300 bg-green-100 dark:bg-green-700/50 hover:bg-green-200 dark:hover:bg-green-600/50 rounded-md" title="Aktifkan Prompt Ini">
                                    <IconPlayerPlay class="w-4 h-4"/>
                                </button>
                                <button v-else class="p-1.5 text-gray-400 dark:text-gray-500 cursor-not-allowed bg-gray-200 dark:bg-gray-700 rounded-md" title="Prompt Ini Sudah Aktif">
                                     <IconPlayerStop class="w-4 h-4"/>
                                </button>
                                <button @click.stop="editConfiguredPrompt(configPrompt.id)" class="p-1.5 text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 bg-indigo-100 dark:bg-indigo-700/50 hover:bg-indigo-200 dark:hover:bg-indigo-600/50 rounded-md" title="Edit Konfigurasi">
                                    <IconEdit class="w-4 h-4"/>
                                </button>
                                <button @click.stop="openDeleteConfirmationModal(configPrompt)" class="p-1.5 text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 bg-red-100 dark:bg-red-700/50 hover:bg-red-200 dark:hover:bg-red-600/50 rounded-md" title="Hapus Konfigurasi">
                                    <IconTrash class="w-4 h-4"/>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-if="isLoadingMore" class="text-center py-6">
                    <!-- ... Indikator Loading More ... -->
                     <svg class="animate-spin h-8 w-8 text-gray-500 dark:text-gray-400 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <div v-if="initialLoadComplete && allConfiguredPrompts.length === 0 && !isLoadingMore" class="text-center py-10 flex-grow flex items-center justify-center">
                    <!-- ... Pesan Kosong ... -->
                     <div class="flex flex-col items-center">
                      <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                          <path vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                      </svg>
                      <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">
                          {{ searchTerm ? 'Configure Prompts Tidak Ditemukan' : 'Belum Ada Prompt Yang Dibuat' }}
                      </h3>
                      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                          {{ searchTerm ? 'Coba kata kunci lain atau hapus filter.' : 'Silakan tambahkan Prompt baru jika diperlukan.' }}
                      </p>
                    </div>
                </div>
            </div> <!-- Akhir Area Scrollable -->
        </div>

        <!-- MODAL KONFIRMASI HAPUS -->
        <Modal :show="confirmingPromptDeletion" @close="closeDeleteModal">
            <div class="p-6 dark:bg-slate-800">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Hapus Konfigurasi Prompt: {{ promptToDelete?.configured_prompt_name }}
                </h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Apakah Anda yakin? Aksi ini akan menghapus konfigurasi prompt.
                    Jika prompt ini sedang aktif, Anda tidak bisa menghapusnya.
                </p>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Untuk konfirmasi, ketik nama konfigurasi: <strong class="dark:text-gray-200">{{ promptToDelete?.configured_prompt_name }}</strong>
                </p>
                <div class="mt-4">
                    <TextInput
                        type="text"
                        class="mt-1 block w-3/4"
                        :placeholder="promptToDelete?.configured_prompt_name || 'Ketik nama di sini'"
                        v-model="deleteConfirmationName"
                        @keyup.enter="confirmAndDeletePrompt"
                    />
                    <!-- Menampilkan error umum dari deleteForm jika ada -->
                    <InputError :message="deleteForm.errors.general" class="mt-2" />
                </div>
                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="closeDeleteModal"> Batal </SecondaryButton>
                    <DangerButton
                        class="ms-3"
                        :class="{ 'opacity-25': deleteForm.processing }"
                        :disabled="deleteForm.processing || deleteConfirmationName !== promptToDelete?.configured_prompt_name"
                        @click="confirmAndDeletePrompt"
                    >
                        Ya, Hapus Konfigurasi
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AdminLayout>
</template>