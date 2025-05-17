// resources/js/Pages/Admin/Prompts/Index.vue
<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3'; // Import Link

defineProps({
    promptTemplates: Object, // Objek paginator dari Laravel
    filters: Object,
});
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
                        Halaman Daftar Template Prompt akan ada di sini.
                        <div class="mt-4">
                            <Link :href="route('admin.prompt-templates.create')" class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600">
                                Tambah Template Baru
                            </Link>
                        </div>
                        <pre class="mt-4 text-xs bg-gray-100 dark:bg-gray-900 p-2 rounded whitespace-pre-wrap">{{ promptTemplates }}</pre>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>