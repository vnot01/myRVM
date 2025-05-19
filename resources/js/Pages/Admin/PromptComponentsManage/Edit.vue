<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3'; // router tidak dipakai langsung di sini
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import TextareaInput from '@/Components/TextareaInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import InfoTooltip from '@/Components/InfoTooltip.vue'; // Untuk hover info singkat
import InfoPopover from '@/Components/InfoPopover.vue'; // Jika Anda ingin popover yang bisa dicopy untuk contoh JSON

const props = defineProps({
    promptComponent: Object, // Data komponen yang akan diedit
    availableComponentTypes: Array,
    errors: Object, // Otomatis dari Inertia
});

const form = useForm({
    _method: 'PUT', // Untuk memberitahu Laravel ini adalah request PUT/PATCH
    component_name: props.promptComponent?.component_name || '',
    component_type: props.promptComponent?.component_type || (props.availableComponentTypes && props.availableComponentTypes.length > 0 ? props.availableComponentTypes[0] : ''),
    content: props.promptComponent?.content || '',
    description: props.promptComponent?.description || '',
});

const submit = () => {
    form.put(route('admin.prompt-components.update', props.promptComponent.id), {
        // onSuccess: () => { /* Pesan flash dari backend akan ditangani oleh AdminLayout */ },
        // onError: (errors) => { console.error("Error updating prompt component:", errors); }
    });
};

// Helper untuk memformat label tipe komponen di dropdown
const formatComponentTypeLabel = (typeString) => {
    if (!typeString) return '';
    return typeString.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
};

const placeholderComponentOutputFormat = `Jika berbentuk JSON, gunakan format berikut:
{
"item_type": "LABEL", 
"is_valid": true/false, 
"rejection_reason": "ALASAN_JIKA_DITOLAK_ATAU_NULL"
}`;
const infoTipComponentPlaceholderText = `Masukkan konfigurasi dalam format JSON yang valid. Ini akan digunakan sebagai preset untuk 'generation_config' saat merakit prompt...
Contoh:
{
    "temperature": 0.7,
    "maxOutputTokens": 1024,
    "topK": 1,
    "topP": 0.9,
    "hobi": [
    "membaca",
    "coding"
    ],
    "alamat": {
    "jalan": "Jl. Contoh No. 1",
    "kota": "Kota Contoh"
    }
}`;
const jsonPlaceholderText = `{
    "item_type": "LABEL_DARI_DAFTAR",
    "is_valid": true_atau_false
    "nama": "Contoh Nama",
    "aktif": true,
    "hobi": [
    "membaca",
    "coding"
    ],
    "alamat": {
    "jalan": "Jl. Contoh No. 1",
    "kota": "Kota Contoh"
    }
}`;
const getContentPlaceholderText = () => {
    if (form.component_type === 'generation_config_preset') {
        return jsonPlaceholderText;
    } else if (form.component_type === 'label_options') {
        return 'Contoh: LABEL_SATU, LABEL_DUA, LABEL_TIGA';
    } else if (form.component_type === 'output_format_definition') {
        return placeholderComponentOutputFormat;
    }
    return 'Isi konten atau teks untuk komponen ini...';
};

const getContentInfoText = () => {
    if (form.component_type === 'generation_config_preset') {
        return infoTipComponentPlaceholderText;
    } else if (form.component_type === 'label_options') {
        return `Masukkan daftar label yang diinginkan, dipisahkan dengan koma. Spasi di sekitar koma akan diabaikan.`;
    } else if (form.component_type === 'output_format_definition') {
        return placeholderComponentOutputFormat;
    }
    return `Isi teks atau data utama untuk komponen ini. Ini bisa berupa deskripsi, kondisi, atau format output yang akan digunakan untuk mengisi placeholder di template prompt.`;
};

</script>

<template>
    <Head :title="'Edit Komponen Prompt - ' + form.component_name" />

    <AdminLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Edit Komponen Prompt: {{ form.component_name || props.promptComponent?.component_name }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <form @submit.prevent="submit" class="p-6 space-y-6">
                        <div>
                            <div class="flex items-center space-x-1 mb-1">
                                <InputLabel for="component_name" value="Nama Komponen (Unik)" />
                                <InfoTooltip textToShow="Nama unik untuk identifikasi komponen ini. Contoh: 'Deskripsi Botol PET Kosong', 'Label Sampah Umum'." 
                                    position="right" horizontalAlign="start"/>
                            </div>
                            <TextInput id="component_name" type="text" class="mt-1 block w-full" v-model="form.component_name" required autofocus />
                            <InputError class="mt-2" :message="form.errors.component_name || props.errors.component_name" />
                        </div>

                        <div>
                            <div class="flex items-center space-x-1 mb-1">
                                <InputLabel for="component_type" value="Tipe Komponen" />
                                <InfoTooltip textToShow="Pilih tipe yang paling sesuai dengan isi komponen. Ini membantu dalam mengkategorikan dan menemukan komponen saat merakit prompt di halaman Konfigurasi Prompt." position="top" horizontalAlign="start"/>
                            </div>
                            <select id="component_type" v-model="form.component_type" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" required>
                                <option disabled value="">-- Pilih Tipe --</option>
                                <option v-for="type in props.availableComponentTypes" :key="type" :value="type">
                                    {{ formatComponentTypeLabel(type) }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.component_type || props.errors.component_type" />
                        </div>

                        <div>
                            <div class="flex items-center space-x-1 mb-1">
                                <InputLabel for="content" value="Konten Komponen" />
                                <InfoPopover :textToShow="getContentInfoText()" 
                                    position="right" horizontalAlign="start"/>
                            </div>
                            <!-- Perbesar sedikit untuk JSON -->
                            <TextareaInput
                                id="content"
                                class="mt-1 block w-full font-mono text-sm"
                                v-model="form.content"
                                rows="10" 
                                required
                                :placeholder="getContentPlaceholderText()"
                            />
                            <p v-if="form.component_type === 'label_options'" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Untuk tipe "Label Options", masukkan beberapa label dipisahkan koma.
                            </p>
                             <p v-if="form.component_type === 'generation_config_preset'" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Konten harus berupa format JSON yang valid.
                            </p>
                            <p v-if="form.component_type === 'output_format_definition'" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Jika Konten berbentuk JSON, gunakan format berupa JSON yang valid.
                            </p>
                            <InputError class="mt-2" :message="form.errors.content || props.errors.content" />
                        </div>

                        <div>
                            <div class="flex items-center space-x-1 mb-1">
                                <InputLabel for="description" value="Deskripsi Singkat (Opsional)" />
                                <InfoTooltip textToShow="Penjelasan singkat tentang apa isi atau tujuan dari komponen ini." position="top" horizontalAlign="start"/>
                            </div>
                            <TextareaInput id="description" class="mt-1 block w-full" v-model="form.description" rows="3" />
                            <InputError class="mt-2" :message="form.errors.description || props.errors.description" />
                        </div>

                        <div class="flex items-center justify-end">
                            <Link :href="route('admin.prompt-components.index')" class="text-sm text-gray-600 dark:text-gray-400 hover:underline mr-4">
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
    </AdminLayout>
</template>