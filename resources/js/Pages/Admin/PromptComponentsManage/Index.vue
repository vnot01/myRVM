<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import debounce from 'lodash/debounce';
import Pagination from '@/Components/Pagination.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue'; // Atau <select> HTML biasa
import { IconPlus, IconEdit, IconTrash, IconFilter, IconSearch } from '@tabler/icons-vue';

const props = defineProps({
    promptComponents: Object, // Objek paginator
    filters: Object, // { search: '...', type: '...' }
    availableComponentTypes: Array, // Daftar tipe komponen untuk filter
});

const searchTerm = ref(props.filters.search || '');
const selectedTypeFilter = ref(props.filters.type || 'all'); // 'all' untuk tanpa filter tipe

// Fungsi terpusat untuk melakukan request Inertia dengan filter
const applyFiltersAndSearch = () => {
    const queryParams = {};
    if (searchTerm.value.trim() !== '') {
        queryParams.search = searchTerm.value.trim();
    }
    if (selectedTypeFilter.value !== 'all' && selectedTypeFilter.value !== '') {
        queryParams.type = selectedTypeFilter.value;
    }
    // Jika queryParams kosong, Inertia akan mengirim request tanpa query string search/type,
    // yang seharusnya diinterpretasikan backend sebagai "ambil semua".

    console.log('Applying filters/search with params:', queryParams);
    router.get(route('admin.prompt-components.index'), queryParams, {
        preserveState: true, // Pertahankan state input field
        preserveScroll: true, // Pertahankan posisi scroll
        replace: true,       // Ganti history, jangan tambah baru
    });
};

// Debounce hanya untuk searchTerm agar tidak terlalu sering hit API saat mengetik
watch(searchTerm, debounce(() => {
    applyFiltersAndSearch();
}, 300));

// Untuk selectedTypeFilter, langsung panggil applyFiltersAndSearch
watch(selectedTypeFilter, () => {
    applyFiltersAndSearch();
});


const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const options = { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
    return new Date(dateString).toLocaleDateString('id-ID', options);
};
const deleteComponent = (componentId, componentName) => {
    if (confirm(`Apakah Anda yakin ingin menghapus komponen "${componentName}"? Ini tidak bisa diurungkan.`)) {
        router.delete(route('admin.prompt-components.destroy', componentId), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head title="Pustaka Komponen Prompt" />

    <AdminLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Pustaka Komponen Prompt
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
                            <h3 class="text-lg font-medium">Daftar Komponen Prompt</h3>
                            <Link :href="route('admin.prompt-components.create')">
                                <PrimaryButton class="flex items-center">
                                    <IconPlus class="w-5 h-5 mr-2" />
                                    Tambah Komponen
                                </PrimaryButton>
                            </Link>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <!-- Input Pencarian -->
                            <div class="relative">
                                <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                                    <IconSearch class="w-4 h-4 text-gray-500 dark:text-gray-400" />
                                </div>
                                <TextInput
                                    type="search"
                                    class="mt-1 block w-full p-3 ps-10 text-sm"
                                    v-model="searchTerm"
                                    :placeholder="'Cari nama, deskripsi, atau konten...'"
                                />
                            </div>
                            <!-- Filter Tipe -->
                            <div>
                                <label for="type_filter" class="sr-only">Filter Tipe Komponen</label>
                                <select id="type_filter" v-model="selectedTypeFilter" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                    <option value="all">Semua Tipe</option>
                                    <option v-for="type in props.availableComponentTypes" :key="type" :value="type">
                                        {{ type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) }}
                                    </option>
                                </select>
                            </div>
                        </div>


                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 ...">Nama Komponen</th>
                                        <th class="px-6 py-3 ...">Tipe</th>
                                        <th class="px-6 py-3 ...">Preview Konten</th>
                                        <!-- <th class="px-6 py-3 ...">Deskripsi</th> -->
                                        <th class="px-6 py-3 ...">Update Terakhir</th>
                                        <th class="relative px-6 py-3"><span class="sr-only">Aksi</span></th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-if="!props.promptComponents || !props.promptComponents.data || props.promptComponents.data.length === 0">
                                        <td colspan="6" class="px-6 py-4 whitespace-nowrap text-sm text-center text-gray-500 dark:text-gray-400">
                                            {{ searchTerm || (selectedTypeFilter !== 'all' && selectedTypeFilter !== '') ? 'Tidak ada komponen cocok dengan filter.' : 'Belum ada komponen prompt.' }}
                                        </td>
                                    </tr>
                                    <tr v-for="component in props.promptComponents.data" :key="component.id">
                                        <td class="px-6 py-4 ... font-medium ...">{{ component.component_name }}</td>
                                        <td class="px-6 py-4 ...">
                                            <span class="px-2 py-1 text-xs font-semibold leading-tight rounded-full"
                                                  :class="{
                                                    'bg-blue-100 text-blue-700 dark:bg-blue-700 dark:text-blue-100': component.component_type.includes('description'),
                                                    'bg-indigo-100 text-indigo-700 dark:bg-indigo-700 dark:text-indigo-100': component.component_type.includes('condition'),
                                                    'bg-purple-100 text-purple-700 dark:bg-purple-700 dark:text-purple-100': component.component_type.includes('label'),
                                                    'bg-pink-100 text-pink-700 dark:bg-pink-700 dark:text-pink-100': component.component_type.includes('output') || component.component_type.includes('format'),
                                                    'bg-teal-100 text-teal-700 dark:bg-teal-700 dark:text-teal-100': component.component_type.includes('config'),
                                                    'bg-gray-100 text-gray-700 dark:bg-gray-600 dark:text-gray-200': true // default
                                                  }">
                                                {{ component.component_type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 ... max-w-sm truncate" :title="component.content">
                                            <pre class="whitespace-pre-wrap text-xs">{{ component.content }}</pre>
                                        </td>
                                        <!-- <td class="px-6 py-4 ... max-w-xs truncate" :title="component.description">{{ component.description }}</td> -->
                                        <td class="px-6 py-4 ...">{{ formatDate(component.updated_at) }}</td>
                                        <td class="px-6 py-4 ... text-right space-x-2">
                                            <Link :href="route('admin.prompt-components.edit', component.id)" class="text-indigo-600 hover:text-indigo-900 ..." title="Edit">
                                                <IconEdit class="w-5 h-5"/>
                                            </Link>
                                            <button @click="deleteComponent(component.id, component.component_name)" class="text-red-600 hover:text-red-900 ..." title="Hapus">
                                                <IconTrash class="w-5 h-5"/>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-if="props.promptComponents && props.promptComponents.data && props.promptComponents.data.length > 0 && props.promptComponents.meta && props.promptComponents.meta.links && typeof props.promptComponents.meta.last_page !== 'undefined' && props.promptComponents.meta.last_page > 1" class="mt-6">
                            <Pagination :links="props.promptComponents.meta.links" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>