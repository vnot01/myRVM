<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch, onMounted, onUnmounted, nextTick } from 'vue';
import debounce from 'lodash/debounce';
import Pagination from '@/Components/Pagination.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue'; // Atau <select> HTML biasa
import Modal from '@/Components/Modal.vue';
import { IconPlus, IconEdit, IconTrash, IconFilter, IconSearch } from '@tabler/icons-vue';

const props = defineProps({
    promptComponents: Object, // Objek paginator
    filters: Object, // { search: '...', type: '...' }
    availableComponentTypes: Array, // Daftar tipe komponen untuk filter
});
// --- State untuk Infinite Scrolling ---
const allPromptComponents = ref([]); // Menyimpan semua komponen yang dimuat
const currentPage = ref(1);
const lastPage = ref(1);
const isLoadingMore = ref(false);
const initialLoadComplete = ref(false);
const searchTerm = ref(props.filters.search || '');
const selectedTypeFilter = ref(props.filters.type || 'all'); // State untuk filter tipe // 'all' untuk tanpa filter tipe
const scrollContainerRef = ref(null);

// Fungsi untuk mengisi/mengupdate state dari paginator props
const updateLocalComponentState = (paginator) => {
    if (paginator && paginator.data && typeof paginator.current_page !== 'undefined' && typeof paginator.last_page !== 'undefined') {
        if (paginator.current_page === 1) {
            allPromptComponents.value = paginator.data ? [...paginator.data] : [];
        } else {
            const existingIds = new Set(allPromptComponents.value.map(c => c.id));
            const newUniqueItems = (paginator.data || []).filter(c => !existingIds.has(c.id));
            allPromptComponents.value.push(...newUniqueItems);
        }
        currentPage.value = paginator.current_page;
        lastPage.value = paginator.last_page;
    } else {
        allPromptComponents.value = [];
        currentPage.value = 1;
        lastPage.value = 1;
    }
    initialLoadComplete.value = true;
};

onMounted(() => {
    updateLocalComponentState(props.promptComponents);
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

// Fungsi terpusat untuk search dan filter
const applyFiltersAndSearch = debounce(() => {
    isLoadingMore.value = false; // Reset saat filter/search baru
    const queryParams = { page: 1 }; // Selalu mulai dari page 1
    if (searchTerm.value.trim() !== '') {
        queryParams.search = searchTerm.value.trim();
    }
    if (selectedTypeFilter.value !== 'all' && selectedTypeFilter.value !== '') {
        queryParams.type = selectedTypeFilter.value;
    }
    router.get(route('admin.prompt-components.index'), queryParams, {
        preserveState: true,
        preserveScroll: false, // Scroll ke atas untuk hasil baru
        replace: true,
    });
    // props.promptComponents akan diupdate oleh Inertia, watcher di bawah akan menangani
}, 300);

watch(searchTerm, applyFiltersAndSearch);
watch(selectedTypeFilter, applyFiltersAndSearch);

watch(() => props.promptComponents, (newPaginator) => {
    updateLocalComponentState(newPaginator);
}, { deep: true });

const loadMoreComponents = () => {
    if (isLoadingMore.value || !initialLoadComplete.value || currentPage.value >= lastPage.value) {
        return;
    }
    isLoadingMore.value = true;
    router.get(route('admin.prompt-components.index'), {
        search: searchTerm.value,
        type: selectedTypeFilter.value === 'all' ? '' : selectedTypeFilter.value,
        page: currentPage.value + 1,
    }, {
        preserveState: true, preserveScroll: true, replace: true,
        onSuccess: (page) => {
            if (page.props.promptComponents && page.props.promptComponents.data) {
                const existingIds = new Set(allPromptComponents.value.map(c => c.id));
                const newUniqueItems = page.props.promptComponents.data.filter(c => !existingIds.has(c.id));
                allPromptComponents.value.push(...newUniqueItems);
            }
            if (page.props.promptComponents && typeof page.props.promptComponents.current_page !== 'undefined') {
                currentPage.value = page.props.promptComponents.current_page;
                lastPage.value = page.props.promptComponents.last_page;
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
            loadMoreComponents();
        }
    }
}, 150);

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const options = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
    return new Date(dateString).toLocaleDateString('id-ID', options);
};

const formatComponentTypeLabel = (typeString) => {
    if (!typeString) return '';
    return typeString.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
};

const editComponent = (componentId) => {
    router.get(route('admin.prompt-components.edit', componentId));
};

const deleteComponent = (componentId, componentName) => { // Placeholder, akan butuh modal
    if (confirm(`Apakah Anda yakin ingin menghapus komponen "${componentName}"?`)) {
        router.delete(route('admin.prompt-components.destroy', componentId), {
            preserveScroll: true,
        });
    }
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
    const deletedTemplateName = templateToDelete.value.component_name;
    deleteTemplateForm.delete(route('admin.prompt-components.destroy', templateToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => { closeDeleteTemplateModal();
            //  console.log(`Template "${deletedTemplateName}" dihapus.`); 
            },
        onError: (errors) => { 
            // console.error('Gagal hapus:', errors); 
        }
    });
};
// --- AKHIR FUNGSI MODAL HAPUS ---
</script>

<template>
    <Head title="Pustaka Komponen Prompt" />
    <AdminLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Pustaka Komponen Prompt
            </h2>
        </template>

        <div class="flex flex-col h-[calc(100vh-5rem-1rem)]"> <!-- Sesuaikan OFFSET -->
            <!-- Bagian Atas yang Sticky -->
            <div class="flex-shrink-0 bg-gray-100 dark:bg-gray-800 pb-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex flex-col sm:flex-row justify-between items-center gap-2 pt-0 mx-4 sm:mx-6 lg:mx-8">
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                        Komponen Tersimpan
                    </h1>
                    <Link :href="route('admin.prompt-components.create')" class="cursor-pointer select-none px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors text-sm font-medium inline-flex items-center">
                        <IconPlus class="w-5 h-5 mr-2" />
                        <span class="hidden sm:inline select-none">Tambah Komponen</span>
                        <span class="sm:hidden">Baru</span>
                    </Link>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4 mx-4 sm:mx-6 lg:mx-8">
                    <div class="relative">
                        <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                            <IconSearch class="w-4 h-4 text-gray-500 dark:text-gray-400" />
                        </div>
                        <TextInput
                            type="search"
                            v-model="searchTerm"
                            placeholder="Cari nama, deskripsi, atau konten..."
                            class="block w-full p-3 ps-10 text-sm dark:bg-slate-700 dark:text-gray-300 dark:placeholder-gray-400"
                        />
                    </div>
                    <div>
                        <label for="type_filter_component" class="sr-only">Filter Tipe Komponen</label>
                        <select id="type_filter_component" v-model="selectedTypeFilter" class="block w-full p-3 text-sm border-gray-300 dark:border-gray-700 dark:bg-slate-700 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                            <option value="all">Semua Tipe Komponen</option>
                            <option v-for="type in props.availableComponentTypes" :key="type" :value="type">
                                {{ formatComponentTypeLabel(type) }}
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Area Scrollable untuk Daftar Panel Komponen -->
            <div ref="scrollContainerRef" class="flex-grow overflow-y-auto px-4 sm:px-6 lg:px-8">
                <div v-if="initialLoadComplete && allPromptComponents.length > 0" class="space-y-3 py-4">
                    <div
                        v-for="component in allPromptComponents"
                        :key="component.id"
                        class="bg-slate-50 dark:bg-slate-700/60 p-4 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-200 border-l-4 border-slate-300 dark:border-slate-600"
                    >
                        <div class="flex flex-col sm:flex-row justify-between sm:items-start">
                            <!-- Konten Utama Panel -->
                            <div class="flex-1 min-w-0 mb-3 sm:mb-0">
                                <h4 class="text-md font-semibold text-gray-900 dark:text-white truncate" :title="component.component_name">
                                    {{ component.component_name }}
                                </h4>
                                <span class="px-2 py-0.5 mt-1 mb-1 inline-flex text-xs font-semibold leading-tight rounded-full animate-pulse"
                                      :class="{
                                        'bg-blue-100 text-blue-700 dark:bg-blue-700 dark:text-blue-100': component.component_type.includes('description'),
                                        'bg-indigo-100 text-indigo-700 dark:bg-indigo-700 dark:text-indigo-100': component.component_type.includes('condition'),
                                        'bg-purple-100 text-purple-700 dark:bg-purple-700 dark:text-purple-100': component.component_type.includes('label'),
                                        'bg-pink-100 text-pink-700 dark:bg-pink-700 dark:text-pink-100': component.component_type.includes('output') || component.component_type.includes('format'),
                                        'bg-teal-100 text-teal-700 dark:bg-teal-700 dark:text-teal-100': component.component_type.includes('config'),
                                        'bg-gray-200 text-gray-700 dark:bg-gray-600 dark:text-gray-200': true
                                      }">
                                    {{ formatComponentTypeLabel(component.component_type) }}
                                </span>
                                <p class="text-xs text-gray-600 dark:text-gray-300 mt-1 truncate" :title="component.description">
                                    {{ component.description || 'Tidak ada deskripsi.' }}
                                </p>
                                <div class="mt-2">
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Konten:</p>
                                    <pre class="text-xs text-gray-500 dark:text-gray-400 bg-slate-100 dark:bg-slate-800 p-2 rounded whitespace-pre-wrap max-h-20 overflow-y-auto">{{ component.content }}</pre>
                                </div>
                                <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">
                                    Update: {{ formatDate(component.updated_at) }}
                                </p>
                            </div>

                            <!-- Tombol Aksi di Kanan -->
                            <div class="flex-shrink-0 flex items-center sm:flex-col sm:items-end space-x-2 sm:space-x-0 sm:space-y-2 sm:ml-4 mt-3 sm:mt-0">
                                <button @click.stop="editComponent(component.id)" class="p-1.5 text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 bg-indigo-100 dark:bg-indigo-700/50 hover:bg-indigo-200 dark:hover:bg-indigo-600/50 rounded-md" title="Edit Komponen">
                                    <IconEdit class="w-4 h-4"/>
                                </button>
                                <button @click.stop="openDeleteTemplateModal(component)" class="p-1.5 text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 bg-red-100 dark:bg-red-700/50 hover:bg-red-200 dark:hover:bg-red-600/50 rounded-md" title="Hapus Komponen">
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
                <!-- Pesan Jika Tidak Ada Komponen -->
                <div v-if="initialLoadComplete && allPromptComponents.length === 0 && !isLoadingMore" class="text-center py-10 flex-grow flex items-center justify-center">
                    <!-- ... Pesan kosong Anda ... -->
                     <div class="flex flex-col items-center">
                      <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                          <path vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                      </svg>
                      <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">
                          {{ searchTerm ? 'Components Prompt Tidak Ditemukan' : 'Belum Ada Components Prompt' }}
                      </h3>
                      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                          {{ searchTerm ? 'Coba kata kunci lain atau hapus filter.' : 'Silakan tambahkan Components Prompt baru jika diperlukan.' }}
                      </p>
                    </div>
                </div>
            </div> <!-- Akhir Area Scrollable -->
        </div>
        <!-- Modal Konfirmasi Hapus (jika Anda ingin implementasi di sini) -->
         <Modal :show="confirmingTemplateDeletion" @close="closeDeleteTemplateModal">
            <div class="p-6 dark:bg-slate-800">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Hapus Template Prompt
                </h2>
                <p v-if="templateToDelete" class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Apakah Anda yakin ingin menghapus component prompt
                    <strong class="dark:text-gray-200">"{{ templateToDelete.component_name }}"</strong>?
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