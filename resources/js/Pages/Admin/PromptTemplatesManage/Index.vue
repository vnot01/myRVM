<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3'; // useForm mungkin belum perlu di Index.vue ini
import { ref, watch, onMounted, onUnmounted, nextTick } from 'vue'; // Hapus onMounted, onUnmounted, nextTick jika tidak ada infinite scroll
import debounce from 'lodash/debounce';
import Pagination from '@/Components/Pagination.vue'; // <-- TAMBAHKAN IMPORT INI
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue'; // Jika dipakai di modal hapus
import { IconPlus, IconEdit, IconTrash, IconSearch /*, IconPlayerPlay, IconPlayerStop, IconCopy, IconEye */ } from '@tabler/icons-vue'; // Impor semua ikon yang dipakai
// import { IconSearch, IconPlus, IconEdit, IconTrash } from '@tabler/icons-vue';
const props = defineProps({
    promptTemplates: Object, // Objek paginator dari Laravel
    filters: Object,
});
// --- State untuk Infinite Scrolling ---
const allPromptTemplates = ref([]); // Menyimpan semua template yang dimuat
const currentPage = ref(1);
const lastPage = ref(1);
const isLoadingMore = ref(false);
const initialLoadComplete = ref(false);
const searchTerm = ref(props.filters.search || '');
const scrollContainerRef = ref(null);
// --- State untuk Modal Hapus ---
const confirmingTemplateDeletion = ref(false);
const templateToDelete = ref(null); // Menyimpan objek template yang akan dihapus
const deleteTemplateForm = useForm({}); // Untuk request DELETE Inertia // deleteConfirmationName tidak diperlukan jika konfirmasi hanya Ya/Tidak

// Fungsi untuk mengisi/mengupdate state dari paginator props
const updateLocalTemplateState = (paginator) => {
    if (paginator && paginator.data && typeof paginator.current_page !== 'undefined' && typeof paginator.last_page !== 'undefined') {
        if (paginator.current_page === 1) {
            allPromptTemplates.value = paginator.data ? [...paginator.data] : [];
        } else {
            const existingIds = new Set(allPromptTemplates.value.map(t => t.id));
            const newUniqueItems = (paginator.data || []).filter(t => !existingIds.has(t.id));
            allPromptTemplates.value.push(...newUniqueItems);
        }
        currentPage.value = paginator.current_page;
        lastPage.value = paginator.last_page;
    } else {
        allPromptTemplates.value = [];
        currentPage.value = 1;
        lastPage.value = 1;
    }
    initialLoadComplete.value = true;
};
onMounted(() => {
    updateLocalTemplateState(props.promptTemplates);
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
    isLoadingMore.value = false; // Reset
    router.get(route('admin.prompt-templates.index'), {
        search: newValue,
        page: 1
    }, { preserveState: true, preserveScroll: false, replace: true });
}, 300));
watch(() => props.promptTemplates, (newPaginator) => {
    updateLocalTemplateState(newPaginator);
}, { deep: true });

const loadMoreTemplates = () => {
    if (isLoadingMore.value || !initialLoadComplete.value || currentPage.value >= lastPage.value) {
        return;
    }
    isLoadingMore.value = true;
    router.get(route('admin.prompt-templates.index'), {
        search: searchTerm.value,
        page: currentPage.value + 1,
    }, {
        preserveState: true, preserveScroll: true, replace: true,
        onSuccess: (page) => {
            if (page.props.promptTemplates && page.props.promptTemplates.data) {
                const existingIds = new Set(allPromptTemplates.value.map(t => t.id));
                const newUniqueItems = page.props.promptTemplates.data.filter(t => !existingIds.has(t.id));
                allPromptTemplates.value.push(...newUniqueItems);
            }
            if (page.props.promptTemplates && typeof page.props.promptTemplates.current_page !== 'undefined') {
                currentPage.value = page.props.promptTemplates.current_page;
                lastPage.value = page.props.promptTemplates.last_page;
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
            loadMoreTemplates();
        }
    }
}, 150);

const editTemplate = (templateId) => {
    console.log('Index.vue: Attempting to edit template with ID:', templateId); // <-- LOG ID
    router.get(route('admin.prompt-templates.edit', templateId));
};
// --- Fungsi BARU untuk Modal Hapus Template ---
const openDeleteTemplateModal = (template) => {
    templateToDelete.value = template;
    confirmingTemplateDeletion.value = true;
};
const closeDeleteTemplateModal = () => {
    confirmingTemplateDeletion.value = false;
    templateToDelete.value = null;
    deleteTemplateForm.reset(); deleteTemplateForm.clearErrors();
};
const confirmAndDeleteTemplate = () => {
    if (!templateToDelete.value) return;
    const deletedTemplateName = templateToDelete.value.template_name;
    deleteTemplateForm.delete(route('admin.prompt-templates.destroy', templateToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => { closeDeleteTemplateModal(); console.log(`Template "${deletedTemplateName}" dihapus.`); },
        onError: (errors) => { console.error('Gagal hapus:', errors); }
    });
};
// --- AKHIR FUNGSI MODAL HAPUS ---
const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const options = { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
    return new Date(dateString).toLocaleDateString('id-ID', options);
};
</script>

<template>
    <Head title="Pustaka Template Prompt" />
    <AdminLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Pustaka Template Prompt Dasar
            </h2>
        </template>

        <div class="flex flex-col h-[calc(100vh-5rem-1rem)]"> <!-- Sesuaikan OFFSET -->
            <!-- Bagian Atas yang Sticky -->
            <div class="flex-shrink-0 bg-gray-100 dark:bg-gray-800 pb-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex flex-col sm:flex-row justify-between items-center gap-2 pt-0 mx-4 sm:mx-6 lg:mx-8">
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                        Template Tersimpan
                    </h1>
                    <Link :href="route('admin.prompt-templates.create')" class="cursor-pointer select-none px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors text-sm font-medium inline-flex items-center">
                        <IconPlus class="w-5 h-5 mr-2" />
                        <span class="hidden sm:inline select-none">Tambah Template</span>
                        <span class="sm:hidden">Baru</span>
                    </Link>
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

            <!-- Area Scrollable untuk Daftar Panel Template -->
            <div ref="scrollContainerRef" class="flex-grow overflow-y-auto px-4 sm:px-6 lg:px-8">
                <div v-if="initialLoadComplete && allPromptTemplates.length > 0" class="space-y-4 py-4">
                    <div
                        v-for="template in allPromptTemplates"
                        :key="template.id"
                        class="bg-slate-50 dark:bg-slate-700/60 p-5 rounded-lg shadow-lg hover:shadow-xl transition-shadow duration-200 border-l-4 border-slate-300 dark:border-slate-600"
                    >
                        <div class="flex flex-col sm:flex-row justify-between sm:items-start">
                            <!-- Konten Utama Panel -->
                            <div class="flex-1 min-w-0 mb-3 sm:mb-0">
                                <h4 class="text-lg font-semibold text-gray-900 dark:text-white truncate" :title="template.template_name">
                                    {{ template.template_name }}
                                </h4>
                                <p class="text-sm text-gray-600 dark:text-gray-300 mt-1 truncate" :title="template.description">
                                    {{ template.description || 'Tidak ada deskripsi.' }}
                                </p>
                                <div class="mt-2">
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Placeholders:</p>
                                    <div class="flex flex-wrap gap-1 mt-1">
                                         <span v-if="template.placeholders_defined && template.placeholders_defined.length > 0"
                                              v-for="(ph, index) in template.placeholders_defined" :key="index"
                                              class="inline-block bg-sky-100 dark:bg-sky-700 text-sky-800 dark:text-sky-200 text-xs font-medium px-2 py-0.5 rounded">
                                            {{ '{' + '{' + ph + '}' + '}' }}
                                        </span>
                                        <span v-else class="text-xs italic text-gray-400 dark:text-gray-500">- Tidak ada -</span>
                                    </div>
                                </div>
                                <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">
                                    Dibuat: {{ formatDate(template.created_at) }}
                                </p>
                            </div>

                            <!-- Tombol Aksi di Kanan -->
                            <div class="flex-shrink-0 flex items-center sm:flex-col sm:items-end space-x-2 sm:space-x-0 sm:space-y-2 sm:ml-4 mt-3 sm:mt-0">
                                <button @click.stop="editTemplate(template.id)" class="p-1.5 text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 bg-indigo-100 dark:bg-indigo-700/50 hover:bg-indigo-200 dark:hover:bg-indigo-600/50 rounded-md" title="Edit Template">
                                    <IconEdit class="w-4 h-4"/>
                                </button>
                                <button @click.stop="openDeleteTemplateModal(template)" class="p-1.5 text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 bg-red-100 dark:bg-red-700/50 hover:bg-red-200 dark:hover:bg-red-600/50 rounded-md" title="Hapus Template">
                                    <IconTrash class="w-4 h-4"/>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Indikator Loading More -->
                <div v-if="isLoadingMore" class="text-center py-6">
                    <!-- ... SVG loading ... -->
                    <svg class="animate-spin h-8 w-8 text-gray-500 dark:text-gray-400 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <!-- Pesan Jika Tidak Ada Template -->
                <div v-if="initialLoadComplete && allPromptTemplates.length === 0 && !isLoadingMore" class="text-center py-10 flex-grow flex items-center justify-center">
                    <!-- ... Pesan kosong Anda ... -->
                     <div class="flex flex-col items-center">
                      <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                          <path vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                      </svg>
                      <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">
                          {{ searchTerm ? 'Templates Prompt Tidak Ditemukan' : 'Belum Ada Templates Prompt' }}
                      </h3>
                      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                          {{ searchTerm ? 'Coba kata kunci lain atau hapus filter.' : 'Silakan tambahkan Templates Prompt baru jika diperlukan.' }}
                      </p>
                    </div>
                </div>
            </div> <!-- Akhir Area Scrollable -->
        </div>

        <!-- MODAL KONFIRMASI HAPUS TEMPLATE -->
        <Modal :show="confirmingTemplateDeletion" @close="closeDeleteTemplateModal">
            <div class="p-6 dark:bg-slate-800">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Hapus Template Prompt
                </h2>
                <p v-if="templateToDelete" class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Apakah Anda yakin ingin menghapus template prompt
                    <strong class="dark:text-gray-200">"{{ templateToDelete.template_name }}"</strong>?
                    <br/>
                    Tindakan ini tidak dapat diurungkan. Pastikan tidak lagi digunakan.
                </p>
                <InputError :message="deleteTemplateForm.errors.general" class="mt-2" />
                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="closeDeleteTemplateModal">Batal</SecondaryButton>
                    <DangerButton
                        class="ms-3"
                        :class="{ 'opacity-25': deleteTemplateForm.processing }"
                        :disabled="deleteTemplateForm.processing"
                        @click="confirmAndDeleteTemplate"
                    >
                        Ya, Hapus Template
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AdminLayout>
</template>