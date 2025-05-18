<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import debounce from 'lodash/debounce';
import Pagination from '@/Components/Pagination.vue'; // Asumsi Anda punya komponen ini
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { IconPlus, IconEdit, IconTrash, IconPlayerPlay, IconPlayerStop } from '@tabler/icons-vue'; // Tambah ikon

const props = defineProps({
    promptTemplates: Object, // Objek paginator dari Laravel
    filters: Object,
});

const searchTerm = ref(props.filters.search || '');

watch(searchTerm, debounce((newValue) => {
    router.get(route('admin.prompt-templates.index'), {
        search: newValue,
    }, { preserveState: true, replace: true, preserveScroll: true });
}, 300));

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const options = { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
    return new Date(dateString).toLocaleDateString('id-ID', options);
};

// Fungsi placeholder untuk aksi, akan diimplementasikan nanti
const activateTemplate = (templateId) => {
    console.log('TODO: Activate template ID:', templateId);
    // router.post(route('admin.prompt-templates.activate', templateId), {}, { preserveScroll: true });
};
const editTemplate = (templateId) => {
    console.log('TODO: Edit template ID:', templateId);
    // router.get(route('admin.prompt-templates.edit', templateId));
};
const deleteTemplate = (templateId, templateName) => {
    console.log('TODO: Delete template ID:', templateId, 'Name:', templateName);
    // if (confirm(`Apakah Anda yakin ingin menghapus template "${templateName}"?`)) {
    //     router.delete(route('admin.prompt-templates.destroy', templateId), { preserveScroll: true });
    // }
};

</script>

<template>

    <Head title="Manajemen Prompt AI" />

    <AdminLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Manajemen Template Prompt AI
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-lg font-medium">Daftar Template Prompt</h3>
                            <Link :href="route('admin.prompt-templates.create')">
                            <PrimaryButton class="flex items-center">
                                <IconPlus class="w-5 h-5 mr-2" />
                                Tambah Template
                            </PrimaryButton>
                            </Link>
                        </div>

                        <div class="mb-4">
                            <TextInput type="text" class="mt-1 block w-full md:w-1/2" v-model="searchTerm"
                                placeholder="Cari berdasarkan nama atau deskripsi..." />
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th scope="col"
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Nama Template</th>
                                        <th scope="col"
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Deskripsi</th>
                                        <th scope="col"
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Aktif</th>
                                        <th scope="col"
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Dibuat</th>
                                        <th scope="col" class="relative px-6 py-3"><span class="sr-only">Aksi</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-if="promptTemplates.data.length === 0">
                                        <td colspan="5"
                                            class="px-6 py-4 whitespace-nowrap text-sm text-center text-gray-500 dark:text-gray-400">
                                            Tidak ada template prompt ditemukan.
                                        </td>
                                    </tr>
                                    <tr v-for="template in promptTemplates.data" :key="template.id">
                                        <td
                                            class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                            {{ template.template_name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 max-w-xs truncate"
                                            :title="template.description">{{ template.description }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <span v-if="template.is_active"
                                                class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-700 dark:text-green-100">Ya</span>
                                            <span v-else
                                                class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-700 dark:text-red-100">Tidak</span>
                                        </td>
                                        <td
                                            class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ formatDate(template.created_at) }}</td>
                                        <td
                                            class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                            <button @click="activateTemplate(template.id)" v-if="!template.is_active"
                                                class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300"
                                                title="Aktifkan">
                                                <IconPlayerPlay class="w-5 h-5" />
                                            </button>
                                            <button v-else class="text-gray-400 cursor-not-allowed" title="Sudah Aktif">
                                                <IconPlayerStop class="w-5 h-5" />
                                            </button>
                                            <Link :href="route('admin.prompt-templates.edit', template.id)"
                                                class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
                                                title="Edit">
                                            <IconEdit class="w-5 h-5" />
                                            </Link>
                                            <button @click="deleteTemplate(template.id, template.template_name)"
                                                class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                                title="Hapus">
                                                <IconTrash class="w-5 h-5" />
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-if="promptTemplates && promptTemplates.data && promptTemplates.data.length > 0 && promptTemplates.meta && promptTemplates.meta.links && typeof promptTemplates.meta.last_page !== 'undefined' && promptTemplates.meta.last_page > 1"
                            class="mt-6">
                            <Pagination :links="promptTemplates.meta.links" />
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>