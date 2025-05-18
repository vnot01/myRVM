// resources/js/Pages/Admin/PromptTemplatesManage/Edit.vue
<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import TextareaInput from '@/Components/TextareaInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    promptTemplate: Object, // Data template yang akan diedit
    errors: Object,
});
// Log props saat komponen di-setup
console.log('[Edit.vue SETUP] props.promptTemplate.template_name:', props.promptTemplate?.template_name);
console.log('[Edit.vue SETUP] props.promptTemplate.description:', props.promptTemplate?.description);
console.log('[Edit.vue SETUP] props.promptTemplate.template_string:', props.promptTemplate?.template_string);
console.log('[Edit.vue SETUP] props.promptTemplate.placeholders_defined_text:', props.promptTemplate?.placeholders_defined_text);
console.log('[Edit.vue SETUP] props.promptTemplate.created_at:', props.promptTemplate?.created_at);

const form = useForm({
   _method: 'PUT',
    template_name: props.promptTemplate?.template_name || '',
    description: props.promptTemplate?.description || '',
    template_string: props.promptTemplate?.template_string || '',
    placeholders_defined_text: props.promptTemplate?.placeholders_defined_text || '',
    // Ubah array placeholders_defined menjadi string dipisah koma untuk input
    // placeholders_defined_text: (props.promptTemplate?.placeholders_defined || []).join(', '),
});
console.log('[EditTemplate.vue SETUP] Received props.promptTemplate:', JSON.parse(JSON.stringify(props.promptTemplate)));
console.log('[Edit.vue SETUP] Received props.errors:', JSON.parse(JSON.stringify(props.errors)));
console.log('[Edit.vue SETUP] Initial form data:', JSON.parse(JSON.stringify(form)));
const submit = () => {
    form.put(route('admin.prompt-templates.update', props.promptTemplate.id), {
        // onSuccess: () => form.reset(), // Tidak perlu reset di edit biasanya
    });
};
</script>

<template>
    <Head :title="'Edit Template Prompt - ' + (form.template_name || props.promptTemplate?.template_name || '')" />

    <AdminLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Edit Template Prompt Dasar: {{ form.template_name || props.promptTemplate?.template_name || 'Memuat...' }}
            </h2>
        </template>

        <div v-if="props.promptTemplate" class="py-12"> <!-- Pastikan props.promptTemplate ada -->
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <form @submit.prevent="submit" class="p-6 space-y-6">
                        <!-- Nama Template -->
                        <div>
                            <InputLabel for="template_name" value="Nama Template (Unik)" />
                            <TextInput id="template_name" type="text" class="mt-1 block w-full" v-model="form.template_name" required autofocus />
                            <InputError class="mt-2" :message="form.errors.template_name || props.errors.template_name" />
                        </div>

                        <!-- Deskripsi -->
                        <div>
                            <InputLabel for="description" value="Deskripsi Singkat (Opsional)" />
                            <TextareaInput id="description" class="mt-1 block w-full" v-model="form.description" rows="2" />
                            <InputError class="mt-2" :message="form.errors.description || props.errors.description" />
                        </div>

                        <!-- Isi Template String -->
                        <div>
                            <InputLabel for="template_string" value="Isi Template (Gunakan {{placeholder}})" />
                            <TextareaInput id="template_string" class="mt-1 block w-full font-mono text-sm" v-model="form.template_string" rows="10" required />
                            <InputError class="mt-2" :message="form.errors.template_string || props.errors.template_string" />
                        </div>

                        <!-- Placeholders Defined Text -->
                        <div>
                            <InputLabel for="placeholders_defined_text" value="Placeholder yang Digunakan (pisahkan dengan koma)" />
                            <TextInput id="placeholders_defined_text" type="text" class="mt-1 block w-full" v-model="form.placeholders_defined_text" placeholder="Contoh: target_desc, item_condition" />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Tulis nama placeholder dari template di atas, tanpa kurung kurawal.
                            </p>
                            <InputError class="mt-2" :message="form.errors.placeholders_defined_text || props.errors.placeholders_defined_text" />
                        </div>

                        <!-- Tombol Aksi -->
                        <div class="flex items-center justify-end">
                            <Link :href="route('admin.prompt-templates.index')" class="text-sm text-gray-600 dark:text-gray-400 hover:underline mr-4">
                                Batal
                            </Link>
                            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                Simpan Perubahan
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div v-else class="py-12 text-center text-gray-500 dark:text-gray-400">
            Memuat data template atau data tidak ditemukan...
        </div>
    </AdminLayout>
</template>