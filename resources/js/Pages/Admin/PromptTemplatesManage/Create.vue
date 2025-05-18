<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import TextareaInput from '@/Components/TextareaInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';

// defineProps jika ada (misalnya, jika controller create mengirim data default)
// const props = defineProps({ errors: Object });

const form = useForm({
    template_name: '',
    description: '',
    template_string: 'Detect {{object_description}}.\nCondition: {{condition}}.\nLabels: {{labels_list}}.\nOutput: {{json_format}}.',
    placeholders_defined_text: 'object_description, condition, labels_list, json_format', // Contoh
});

const submit = () => {
    form.post(route('admin.prompt-templates.store'), {
        // onSuccess: () => form.reset(), // Opsional: reset form setelah sukses
    });
};
</script>

<template>
    <Head title="Tambah Template Prompt Baru" />

    <AdminLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Tambah Template Prompt Dasar Baru
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <form @submit.prevent="submit" class="p-6 space-y-6">
                        <div>
                            <InputLabel for="template_name" value="Nama Template (Unik)" />
                            <TextInput id="template_name" type="text" class="mt-1 block w-full" v-model="form.template_name" required autofocus />
                            <InputError class="mt-2" :message="form.errors.template_name" />
                        </div>

                        <div>
                            <InputLabel for="description" value="Deskripsi Singkat (Opsional)" />
                            <TextareaInput id="description" class="mt-1 block w-full" v-model="form.description" rows="2" />
                            <InputError class="mt-2" :message="form.errors.description" />
                        </div>

                        <div>
                            <InputLabel for="template_string" value="Isi Template (Gunakan {{placeholder}})" />
                            <TextareaInput id="template_string" class="mt-1 block w-full font-mono text-sm" v-model="form.template_string" rows="10" required placeholder="Contoh: Deteksi objek {{nama_objek}} dengan kondisi {{kondisi_objek}}." />
                            <InputError class="mt-2" :message="form.errors.template_string" />
                        </div>

                        <div>
                            <InputLabel for="placeholders_defined_text" value="Placeholder yang Digunakan (pisahkan dengan koma)" />
                            <TextInput id="placeholders_defined_text" type="text" class="mt-1 block w-full" v-model="form.placeholders_defined_text" placeholder="Contoh: nama_objek, kondisi_objek, daftar_label" />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Tulis nama placeholder yang Anda gunakan di atas, tanpa kurung kurawal. Contoh: jika Anda menulis `{{target}}`, tulis `target` di sini.
                            </p>
                            <InputError class="mt-2" :message="form.errors.placeholders_defined_text" />
                        </div>

                        <div class="flex items-center justify-end">
                            <Link :href="route('admin.prompt-templates.index')" class="text-sm text-gray-600 dark:text-gray-400 hover:underline mr-4">
                                Batal
                            </Link>
                            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                Simpan Template
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>