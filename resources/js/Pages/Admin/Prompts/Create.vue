// resources/js/Pages/Admin/Prompts/Create.vue
<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3'; // Tambahkan router
import { ref } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import TextareaInput from '@/Components/TextareaInput.vue'; // Asumsi Anda punya ini
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import axios from 'axios'; // Untuk AJAX call ke endpoint testPrompt

// defineProps jika ada yang dikirim dari controller create (misalnya, default values)
// const props = defineProps({ /* ... */ });

const form = useForm({
    name: '',
    description: '',
    target_prompt: '',
    condition_prompt: '',
    label_guidance: '',
    output_instructions: '{\n  "item_type": "NAMA_LABEL_SINGKAT",\n  "is_valid_for_deposit": true,\n  "rejection_reason": null\n}', // Contoh default
    generation_config_json: '{\n  "temperature": 0.7,\n  "maxOutputTokens": 1024,\n  "topK": 1,\n  "topP": 1\n}', // Contoh default
    // is_active tidak di-set di form create
});

// --- State untuk Test Prompt Cepat ---
const testImageFile = ref(null);
const testImagePreview = ref(null);
const testResult = ref(null);
const isTestingPrompt = ref(false);
const testError = ref(null);
// --- Akhir State Test Prompt ---

const handleTestImageChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        testImageFile.value = file;
        const reader = new FileReader();
        reader.onload = (e) => {
            testImagePreview.value = e.target.result;
        };
        reader.readAsDataURL(file);
        testResult.value = null; // Reset hasil tes sebelumnya
        testError.value = null;
    } else {
        testImageFile.value = null;
        testImagePreview.value = null;
    }
};

const submitTestPrompt = async () => {
    if (!testImageFile.value) {
        alert('Silakan pilih gambar contoh untuk pengujian.');
        return;
    }
    if (!form.target_prompt || !form.condition_prompt || !form.label_guidance || !form.output_instructions) {
        alert('Harap isi semua field prompt utama (Target, Kondisi, Panduan Label, Instruksi Output) sebelum menguji.');
        return;
    }

    isTestingPrompt.value = true;
    testResult.value = null;
    testError.value = null;

    const formData = new FormData();
    formData.append('image', testImageFile.value);
    formData.append('target_prompt', form.target_prompt);
    formData.append('condition_prompt', form.condition_prompt);
    formData.append('label_guidance', form.label_guidance);
    formData.append('output_instructions', form.output_instructions);
    if (form.generation_config_json) {
        formData.append('generation_config_json', form.generation_config_json);
    }

    try {
        // Kita menggunakan axios karena ini bukan Inertia visit standar, tapi API call biasa
        const response = await axios.post(route('admin.prompt-templates.test'), formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
                // Tambahkan header CSRF jika endpoint test ada di grup 'web'
                // 'X-XSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'), // Jika perlu
            }
        });
        testResult.value = response.data;
        // console.log('Test Prompt Result:', response.data);
    } catch (error) {
        console.error('Error testing prompt:', error);
        if (error.response) {
            testError.value = error.response.data.error || (error.response.data.errors ? JSON.stringify(error.response.data.errors) : 'Terjadi kesalahan saat pengujian.');
            testResult.value = error.response.data; // Tampilkan juga respons error jika ada
        } else {
            testError.value = 'Tidak dapat terhubung ke server untuk pengujian.';
        }
    } finally {
        isTestingPrompt.value = false;
    }
};


const submitCreateForm = () => {
    form.post(route('admin.prompt-templates.store'), {
        onError: (errors) => {
            console.error("Error creating prompt template:", errors);
        },
        onSuccess: () => {
            // Inertia akan redirect, pesan flash ditangani AdminLayout
        }
    });
};
</script>

<template>

    <Head title="Tambah Template Prompt Baru" />

    <AdminLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Tambah Template Prompt AI Baru
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <form @submit.prevent="submitCreateForm" class="p-6 space-y-6">
                        <div>
                            <InputLabel for="name" value="Nama Template (Unik)" />
                            <TextInput id="name" type="text" class="mt-1 block w-full" v-model="form.name" required />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <div>
                            <InputLabel for="description" value="Deskripsi (Opsional)" />
                            <TextareaInput id="description" class="mt-1 block w-full" v-model="form.description"
                                rows="2" />
                            <InputError class="mt-2" :message="form.errors.description" />
                        </div>

                        <div>
                            <InputLabel for="target_prompt" value="Target Prompt" />
                            <TextareaInput id="target_prompt" class="mt-1 block w-full font-mono text-sm"
                                v-model="form.target_prompt" rows="4" required />
                            <InputError class="mt-2" :message="form.errors.target_prompt" />
                        </div>

                        <div>
                            <InputLabel for="condition_prompt" value="Condition Prompt" />
                            <TextareaInput id="condition_prompt" class="mt-1 block w-full font-mono text-sm"
                                v-model="form.condition_prompt" rows="4" required />
                            <InputError class="mt-2" :message="form.errors.condition_prompt" />
                        </div>

                        <div>
                            <InputLabel for="label_guidance" value="Label Guidance" />
                            <TextareaInput id="label_guidance" class="mt-1 block w-full font-mono text-sm"
                                v-model="form.label_guidance" rows="4" required />
                            <InputError class="mt-2" :message="form.errors.label_guidance" />
                        </div>

                        <div>
                            <InputLabel for="output_instructions" value="Output Instructions (JSON Format)" />
                            <TextareaInput id="output_instructions" class="mt-1 block w-full font-mono text-sm"
                                v-model="form.output_instructions" rows="5" required />
                            <InputError class="mt-2" :message="form.errors.output_instructions" />
                        </div>

                        <div>
                            <InputLabel for="generation_config_json" value="Generation Config (JSON, Opsional)" />
                            <TextareaInput id="generation_config_json" class="mt-1 block w-full font-mono text-sm"
                                v-model="form.generation_config_json" rows="5"
                                placeholder='Contoh: {"temperature": 0.7, "maxOutputTokens": 1024}' />
                            <InputError class="mt-2" :message="form.errors.generation_config_json" />
                        </div>


                        <!-- === BAGIAN TEST PROMPT CEPAT === -->
                        <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Uji Prompt Ini</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                                Unggah gambar contoh untuk menguji konfigurasi prompt di atas secara langsung.
                            </p>
                            <div class="mb-4">
                                <InputLabel for="test_image" value="Gambar Contoh untuk Pengujian" />
                                <input class="block w-full text-sm text-slate-500 mt-1
                                    file:mr-4 file:py-2 file:px-4
                                    file:rounded-full file:border-0
                                    file:text-sm file:font-semibold
                                    file:bg-violet-50 dark:file:bg-slate-700 file:text-violet-700 dark:file:text-slate-300
                                    hover:file:bg-violet-100 dark:hover:file:bg-slate-600
                                    dark:text-slate-400" id="test_image" type="file" @change="handleTestImageChange"
                                    accept="image/*" />
                                <InputError class="mt-2" :message="form.errors.image" />
                                <!-- Jika ada error validasi dari submit utama -->
                            </div>

                            <div v-if="testImagePreview" class="mb-4">
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Preview Gambar Tes:</p>
                                <img :src="testImagePreview" alt="Preview Gambar Tes"
                                    class="mt-2 rounded-md max-h-60 border border-gray-300 dark:border-gray-600" />
                            </div>

                            <PrimaryButton type="button" @click="submitTestPrompt"
                                :disabled="isTestingPrompt || !testImageFile">
                                <span v-if="isTestingPrompt">Menguji...</span>
                                <span v-else>Uji Prompt dengan Gambar Ini</span>
                            </PrimaryButton>

                            <div v-if="isTestingPrompt" class="mt-4 text-center">
                                <!-- Komentar dipindahkan ke luar atau dihapus -->
                                <svg class="animate-spin h-6 w-6 text-gray-700 dark:text-gray-300 mx-auto"
                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                        stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                    </path>
                                </svg>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Menghubungi Gemini Vision...</p>
                            </div>

                            <div v-if="testError"
                                class="mt-4 p-3 bg-red-100 dark:bg-red-700/30 border border-red-300 dark:border-red-600 rounded-md">
                                <p class="font-semibold text-red-700 dark:text-red-300">Error Pengujian:</p>
                                <pre class="mt-1 text-xs text-red-600 dark:text-red-400 whitespace-pre-wrap">{{ testError }}
                        </pre>
                            </div>

                            <div v-if="testResult && testResult.success"
                                class="mt-4 p-3 bg-green-50 dark:bg-green-700/30 border border-green-300 dark:border-green-600 rounded-md">
                                <p class="font-semibold text-green-700 dark:text-green-300">Hasil Pengujian Sukses:</p>
                                <h4 class="mt-2 font-medium text-sm">Teks Respons Mentah dari Gemini:</h4>
                                <pre class="mt-1 text-xs bg-gray-100 dark:bg-gray-800 p-2 rounded whitespace-pre-wrap">{{
                                    testResult.gemini_raw_response_text }}</pre>
                                <h4 class="mt-3 font-medium text-sm">Hasil Parsing (Jika JSON):</h4>
                                <pre class="mt-1 text-xs bg-gray-100 dark:bg-gray-800 p-2 rounded whitespace-pre-wrap">{{
                                    JSON.stringify(testResult.parsed_results, null, 2) }}</pre>
                                <h4 class="mt-3 font-medium text-sm">Prompt yang Dikirim:</h4>
                                <pre class="mt-1 text-xs bg-gray-100 dark:bg-gray-800 p-2 rounded whitespace-pre-wrap">{{
                            testResult.full_prompt_sent }}</pre>
                                <h4 class="mt-3 font-medium text-sm">Config Generasi yang Digunakan:</h4>
                                <pre class="mt-1 text-xs bg-gray-100 dark:bg-gray-800 p-2 rounded whitespace-pre-wrap">{{
                                    JSON.stringify(testResult.generation_config_used, null, 2) }}</pre>
                            </div>
                        </div>
                        <!-- === AKHIR BAGIAN TEST PROMPT CEPAT === -->


                        <div
                            class="flex items-center justify-end mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                            <Link :href="route('admin.prompt-templates.index')"
                                class="text-sm text-gray-600 dark:text-gray-400 hover:underline mr-4">
                            Batal
                            </Link>
                            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                Simpan Template Prompt
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>