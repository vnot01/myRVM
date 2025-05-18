<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3'; // useForm mungkin belum perlu di Index.vue ini
import { ref, watch } from 'vue'; // Hapus onMounted, onUnmounted, nextTick jika tidak ada infinite scroll
import debounce from 'lodash/debounce';
import Pagination from '@/Components/Pagination.vue'; // <-- TAMBAHKAN IMPORT INI
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue'; // Jika dipakai di modal hapus
import { IconPlus, IconEdit, IconTrash, IconSearch /*, IconPlayerPlay, IconPlayerStop, IconCopy, IconEye */ } from '@tabler/icons-vue'; // Impor semua ikon yang dipakai

const props = defineProps({
    promptTemplates: Object, // Objek paginator dari Laravel
    filters: Object,
});

const searchTerm = ref(props.filters.search || '');

watch(searchTerm, debounce((newValue) => {
    router.get(route('admin.prompt-templates.index'), { // Pastikan nama rute benar
        search: newValue,
    }, { preserveState: true, replace: true, preserveScroll: true });
}, 300));

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const options = { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
    return new Date(dateString).toLocaleDateString('id-ID', options);
};

// --- State untuk Modal Hapus Template ---
const confirmingTemplateDeletion = ref(false);
const templateToDelete = ref(null); // Menyimpan objek template yang akan dihapus
// deleteConfirmationName tidak diperlukan jika konfirmasi hanya Ya/Tidak
const deleteTemplateForm = useForm({}); // Form Inertia untuk request DELETE
// --- AKHIR STATE MODAL HAPUS ---


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
    deleteTemplateForm.reset();
    deleteTemplateForm.clearErrors();
};

const confirmAndDeleteTemplate = () => {
    if (!templateToDelete.value) return;

    const deletedTemplateName = templateToDelete.value.template_name;
    deleteTemplateForm.delete(route('admin.prompt-templates.destroy', templateToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeDeleteTemplateModal();
            console.log(`Template Prompt "${deletedTemplateName}" proses hapus berhasil dikirim.`);
            // Notifikasi flash dari backend akan muncul, dan Inertia akan refresh halaman.
        },
        onError: (errors) => {
            console.error('Gagal menghapus template prompt:', errors);
            // Jika ada error spesifik dari backend, bisa ditampilkan di modal
            // atau biarkan notifikasi flash error dari backend yang muncul.
            // deleteTemplateForm.setError('general', 'Gagal menghapus template.');
        }
    });
};
// --- AKHIR FUNGSI MODAL HAPUS ---

</script>

<template>
    <Head title="Manajemen Template Prompt" />

    <AdminLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Manajemen Template Prompt Dasar
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8"> <!-- Disesuaikan max-w-7xl agar tabel lebih lebar -->
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
                            <TextInput
                                type="text"
                                class="mt-1 block w-full md:w-1/2"
                                v-model="searchTerm"
                                placeholder="Cari berdasarkan nama atau deskripsi..."
                            />
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Nama Template</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Deskripsi</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Placeholders</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Dibuat</th>
                                        <th scope="col" class="relative px-6 py-3"><span class="sr-only">Aksi</span></th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-if="promptTemplates.data.length === 0">
                                        <td colspan="5" class="px-6 py-4 whitespace-nowrap text-sm text-center text-gray-500 dark:text-gray-400">
                                            Tidak ada template prompt ditemukan.
                                        </td>
                                    </tr>
                                    <tr v-for="template in promptTemplates.data" :key="template.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">{{ template.template_name }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 max-w-xs truncate" :title="template.description">{{ template.description }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            <div class="flex flex-wrap gap-1 max-w-xs">
                                                <span v-if="template.placeholders_defined && template.placeholders_defined.length > 0"
                                                      v-for="(ph, index) in template.placeholders_defined" :key="index"
                                                      class="inline-block bg-sky-100 dark:bg-sky-700 text-sky-800 dark:text-sky-200 text-xs font-medium px-2 py-0.5 rounded">
                                                    <!-- {{ `{{${ph}}}` }} -->
                                                </span>
                                                <span v-else class="text-xs italic">-</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ formatDate(template.created_at) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                            <button @click="editTemplate(template.id)" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300" title="Edit">
                                                <IconEdit class="w-5 h-5"/>
                                            </button>
                                            <button @click="openDeleteTemplateModal(template)" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300" title="Hapus">
                                                <IconTrash class="w-5 h-5"/>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-if="props.promptTemplates && props.promptTemplates.data && props.promptTemplates.data.length > 0 && props.promptTemplates.meta && props.promptTemplates.meta.links && typeof props.promptTemplates.meta.last_page !== 'undefined' && props.promptTemplates.meta.last_page > 1"
                            class="mt-6">
                            <Pagination :links="props.promptTemplates.meta.links" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL KONFIRMASI HAPUS TEMPLATE PROMPT -->
        <Modal :show="confirmingTemplateDeletion" @close="closeDeleteTemplateModal">
            <div class="p-6 dark:bg-slate-800">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Hapus Template Prompt
                </h2>
                <p v-if="templateToDelete" class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Apakah Anda yakin ingin menghapus template
                    <strong class="dark:text-gray-200">"{{ templateToDelete.template_name }}"</strong>?
                    <br/>
                    Tindakan ini tidak dapat diurungkan. Pastikan template ini tidak lagi digunakan secara aktif oleh Konfigurasi Prompt yang penting.
                </p>
                <!-- Untuk template, konfirmasi nama mungkin tidak perlu, cukup tombol Ya/Tidak -->
                <!-- <div class="mt-4">
                    <InputLabel for="confirm_delete_template_name" :value="`Ketik '${templateToDelete?.template_name}' untuk konfirmasi:`" />
                    <TextInput
                        id="confirm_delete_template_name"
                        type="text"
                        class="mt-1 block w-3/4"
                        v-model="deleteConfirmationName"
                        @keyup.enter="confirmAndDeleteTemplate"
                    />
                    <InputError :message="deleteTemplateForm.errors.general" class="mt-2" />
                </div> -->
                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="closeDeleteTemplateModal">Batal</SecondaryButton>
                    <DangerButton
                        class="ms-3"
                        :class="{ 'opacity-25': deleteTemplateForm.processing }"
                        :disabled="deleteTemplateForm.processing"
                        @click="confirmAndDeleteTemplate"
                    >
                        Ya, Hapus Template Ini
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AdminLayout>
</template>