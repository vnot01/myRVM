<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, watch, computed, reactive, nextTick } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import TextareaInput from '@/Components/TextareaInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import InfoTooltip from '@/Components/InfoTooltip.vue';
// import SelectInput from '@/Components/SelectInput.vue'; // Anda menggunakan <select> HTML biasa
import axios from 'axios'; // Pastikan diimpor jika belum untuk testPrompt

const props = defineProps({
    promptTemplates: Array,
    promptComponents: Array,
    errors: Object,
});

const form = useForm({
    configured_prompt_name: '',
    prompt_template_id: null,
    description: '',
    full_prompt_text_generated: '', // Akan diisi oleh rakitan
    target_prompt_segment: '',
    condition_prompt_segment: '',
    label_guidance_segment: '',
    output_instructions_segment: '',
    outputJsonFieldsManual: reactive([{ key: '', value_description: '' }]), // Untuk UI Key-Value di mode manual global
    outputJsonFields: reactive([{ key: '', value_description: '' }]),
    mappings: [],
    gen_temperature: 0.4,
    gen_max_output_tokens: 1024,
    gen_top_k: 32,
    gen_top_p: 1.0,
    gen_stop_sequences_text: '',
});

const selectedTemplateObject = ref(null);
const placeholderValues = reactive({}); // { placeholderName: { type: 'component'/'manual', value: componentId/null, manualText: '' } }
const showAdvancedGenerationConfig = ref(false);

// Fungsi untuk merakit generation_config menjadi JSON string
const assembleGenerationConfigJson = () => {
    const config = {
        temperature: parseFloat(form.gen_temperature) || 0.4,
        maxOutputTokens: parseInt(form.gen_max_output_tokens) || 1024,
        topK: parseInt(form.gen_top_k) || 32,
        topP: parseFloat(form.gen_top_p) || 1.0,
    };
    if (form.gen_stop_sequences_text && form.gen_stop_sequences_text.trim() !== '') {
        config.stopSequences = form.gen_stop_sequences_text.split(',').map(s => s.trim()).filter(s => s);
    }
    return JSON.stringify(config, null, 2);
};

const assembleOutputInstructionsSegmentFromKeyValue = (fieldsArray) => {
    if (!fieldsArray || fieldsArray.length === 0 || (fieldsArray.length === 1 && !fieldsArray[0].key && !fieldsArray[0].value_description)) {
        return '{\n  "item_type": "LABEL_DARI_DAFTAR",\n  "is_valid": true_atau_false\n}'; // Default
    }
    const jsonObject = {};
    fieldsArray.forEach(field => {
        if (field.key && field.key.trim() !== '') {
            jsonObject[field.key.trim()] = field.value_description.trim() || `CONTOH_UNTUK_${field.key.trim().toUpperCase()}`;
        }
    });
    return JSON.stringify(jsonObject, null, 2);
};

// --- Perakitan Prompt ---
const assembleFullPrompt = () => {
    let finalOutputSegmentForTemplate = ''; // Untuk menyimpan hasil rakitan output jika template dipakai
    if (selectedTemplateObject.value && selectedTemplateObject.value.template_string) {
        let assembled = selectedTemplateObject.value.template_string;
        for (const placeholder in placeholderValues) {
            const phData = placeholderValues[placeholder];
            let replacementText = `{{${placeholder}}}`; // Default

            const isOutputPlaceholder = placeholder.toLowerCase().includes('output') || placeholder.toLowerCase().includes('format');

            if (phData.type === 'component' && phData.value) {
                const component = props.promptComponents.find(c => c.id === phData.value);
                replacementText = component ? component.content : `{{${placeholder}}}`;
            } else if (phData.type === 'manual_textarea') {
                replacementText = (phData.manualText && phData.manualText.trim() !== '') ? phData.manualText : `{{${placeholder}}}`;
            } else if (isOutputPlaceholder && phData.type === 'manual_keyvalue') {
                replacementText = assembleOutputInstructionsSegmentFromKeyValue(phData.keyValueFields);
            }
            
            if (isOutputPlaceholder) {
                finalOutputSegmentForTemplate = replacementText; // Simpan ini
            }
            assembled = assembled.replace(new RegExp(`{{${placeholder}}}`, 'g'), replacementText);
        }
        form.full_prompt_text_generated = assembled;
        form.output_instructions_segment = finalOutputSegmentForTemplate; // Isi dari placeholder output
    } else {
        // Mode manual global
        form.output_instructions_segment = assembleOutputInstructionsSegmentFromKeyValue(form.outputJsonFieldsManual);
        form.full_prompt_text_generated = `Target: ${form.target_prompt_segment}\nCondition: ${form.condition_prompt_segment}\nLabel Guidance: ${form.label_guidance_segment}\nOutput Instructions: ${form.output_instructions_segment}`;
    }
    // console.log('[assembleFullPrompt] Generated:', form.full_prompt_text_generated);
};

// immediate true agar assembleFullPrompt dipanggil saat load awal jika ada template terpilih
watch(() => form.prompt_template_id, (newTemplateId) => {
    Object.keys(placeholderValues).forEach(key => delete placeholderValues[key]);
    if (newTemplateId) {
        selectedTemplateObject.value = props.promptTemplates.find(t => t.id === newTemplateId);
        if (selectedTemplateObject.value && selectedTemplateObject.value.placeholders_defined) {
            selectedTemplateObject.value.placeholders_defined.forEach(ph => {
                const isOutputPh = ph.toLowerCase().includes('output') || ph.toLowerCase().includes('format');
                placeholderValues[ph] = {
                    type: 'component', // Default ke komponen
                    value: null,
                    manualText: isOutputPh ? '{\n  "default_key": "default_value"\n}' : '',
                    keyValueFields: reactive([{ key: '', value_description: '' }]),
                };
            });
        }
    } else {
        selectedTemplateObject.value = null;
    }
    assembleFullPrompt();
}, { immediate: true });

watch(placeholderValues, assembleFullPrompt, { deep: true });

// Watch segmen manual juga untuk merakit ulang jika tidak ada template dipilih
watch(() => [form.target_prompt_segment, form.condition_prompt_segment, form.label_guidance_segment], () => {
    if (!form.prompt_template_id) { assembleFullPrompt(); }
}, { deep: true });

// Watch outputJsonFieldsManual untuk mode manual global
watch(form.outputJsonFieldsManual, () => {
    if (!form.prompt_template_id) { assembleFullPrompt(); }
}, { deep: true });
const relevantComponents = (placeholderName) => {
    let typeToFilter = placeholderName.toLowerCase();
    if (typeToFilter.includes('target')) typeToFilter = 'target_description';
    else if (typeToFilter.includes('condition')) typeToFilter = 'condition_details';
    else if (typeToFilter.includes('label')) typeToFilter = 'label_options';
    else if (typeToFilter.includes('output') || typeToFilter.includes('format')) typeToFilter = 'output_format_definition';
    return props.promptComponents.filter(c => c.component_type === typeToFilter);
};
// Fungsi untuk UI JSON Dinamis (baik global maupun per placeholder)
const addFieldTo = (fieldsArray) => { fieldsArray.push({ key: '', value_description: '' }); assembleFullPrompt(); };
const removeFieldFrom = (fieldsArray, index) => { fieldsArray.splice(index, 1); assembleFullPrompt(); };
// Fungsi spesifik untuk placeholder key-value
const addPhKeyValueField = (placeholderName) => {
    if (placeholderValues[placeholderName] && placeholderValues[placeholderName].keyValueFields) {
        placeholderValues[placeholderName].keyValueFields.push({ key: '', value_description: '' });
        assembleFullPrompt();
    }
};
const removePhKeyValueField = (placeholderName, index) => {
    if (placeholderValues[placeholderName] && placeholderValues[placeholderName].keyValueFields) {
        placeholderValues[placeholderName].keyValueFields.splice(index, 1);
        assembleFullPrompt();
    }
};
const submitCreateConfiguredPrompt = () => {
    assembleFullPrompt();
    const finalGenerationConfigJson = assembleGenerationConfigJson();
    const mappingsForBackend = [];
    // Hanya kirim mapping jika template dipilih dan ada placeholder values
    if (selectedTemplateObject.value && selectedTemplateObject.value.placeholders_defined) {
        for (const placeholder in placeholderValues) {
            const phData = placeholderValues[placeholder];
            // Hanya kirim jika tipenya 'component' dan ada component ID yang dipilih
            if (phData.type === 'component' && phData.value) {
                mappingsForBackend.push({
                    placeholder_in_template: placeholder,
                    prompt_component_id: phData.value,
                });
            }
        }
    }
    // Kirim data yang bersih ke backend
    router.post(route('admin.configured-prompts.store'), {
        configured_prompt_name: form.configured_prompt_name,
        prompt_template_id: form.prompt_template_id,
        description: form.description,
        full_prompt_text_generated: form.full_prompt_text_generated,
        generation_config_final_json: finalGenerationConfigJson, // Kirim JSON string yang sudah dirakit
        mappings: mappingsForBackend,
        // Tidak perlu mengirim field gen_* atau outputJsonFields secara individual ke backend
    }, {
        onError: (errors) => { console.error("Error creating configured prompt:", errors); },
    });
};
// --- Logika untuk Test Prompt Cepat ---
const testImageFile = ref(null);
const testImagePreview = ref(null);
const testResult = ref(null);
const isTestingPrompt = ref(false);
const testError = ref(null);
const handleTestImageChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        testImageFile.value = file;
        const reader = new FileReader();
        reader.onload = (e) => { testImagePreview.value = e.target.result; };
        reader.readAsDataURL(file);
        testResult.value = null; testError.value = null;
    } else {
        testImageFile.value = null; testImagePreview.value = null;
    }
};
const submitTestPrompt = async () => {
    assembleFullPrompt(); // Merakit form.full_prompt_text_generated
    const currentGeneratedConfigJson = assembleGenerationConfigJson(); // Merakit config JSON
    if (!testImageFile.value) {
        alert('Silakan pilih gambar contoh untuk pengujian.');
        return;
    }
    if (!form.full_prompt_text_generated || form.full_prompt_text_generated.includes('{{')) {
        // Cek jika masih ada placeholder yang belum terisi di hasil rakitan
        alert('Harap lengkapi semua placeholder di prompt atau isi segmen prompt sebelum menguji.');
        return;
    }
    isTestingPrompt.value = true;
    testResult.value = null;
    testError.value = null;
    const formDataTest = new FormData();
    formDataTest.append('image', testImageFile.value);
    formDataTest.append('full_prompt', form.full_prompt_text_generated); // Kirim hasil rakitan
    formDataTest.append('generation_config_json', currentGeneratedConfigJson); // Kirim hasil rakitan
    try {
        const response = await axios.post(route('admin.configured-prompts.test'), formDataTest, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
        testResult.value = response.data;
        // console.log('Test Prompt Result:', response.data);
    } catch (error) {
        // console.error('Error testing prompt:', error);
        if (error.response && error.response.data) { // Cek error.response.data dulu
            testError.value = error.response.data.error || // Dari JSON {error: "pesan"}
                (error.response.data.errors ? JSON.stringify(error.response.data.errors) : // Dari validasi Laravel
                    (typeof error.response.data === 'string' ? error.response.data : 'Terjadi kesalahan server yang tidak diketahui.')); // Jika responsnya string
        } else if (error.request) {
            testError.value = 'Tidak ada respons dari server. Periksa koneksi atau log backend.';
        } else {
            testError.value = 'Terjadi kesalahan saat menyiapkan request pengujian: ' + error.message;
        }
    } finally {
        isTestingPrompt.value = false;
    }
};

// // Watch perubahan pada field gen_* untuk mengupdate preview JSON jika perlu,
// // atau panggil assembleGenerationConfigJson() sebelum submitTestPrompt dan submitCreateConfiguredPrompt
// watch(() => [form.gen_temperature, form.gen_max_output_tokens, form.gen_top_k, form.gen_top_p, form.gen_stop_sequences_text], () => {
//     // Jika Anda ingin live update field form.generation_config_final_json (jika masih ada)
//     // form.generation_config_final_json = assembleGenerationConfigJson();
//     // Atau panggil assembleFullPrompt() jika generation_config adalah bagian dari full prompt
// }, { deep: true });

// // const addOutputJsonField = () => {
// //     form.outputJsonFields.push({ key: '', value_description: '' });
// //     assembleFullPrompt(); // Panggil juga saat struktur berubah
// // };

// // const removeOutputJsonField = (index) => {
// //     form.outputJsonFields.splice(index, 1);
// //     assembleFullPrompt(); // Panggil juga saat struktur berubah
// // };

// // Fungsi untuk merakit output_instructions_segment dari outputJsonFields
// const assembleOutputInstructionsSegment = () => {
//     if (form.outputJsonFields.length === 0 || (form.outputJsonFields.length === 1 && !form.outputJsonFields[0].key && !form.outputJsonFields[0].value_description) ) {
//         return '{\n  "item_type": "LABEL_DARI_DAFTAR",\n  "is_valid": true_atau_false\n}'; // Contoh default jika kosong
//     }
//     const jsonObject = {};
//     form.outputJsonFields.forEach(field => {
//         if (field.key.trim() !== '') {
//             // Untuk value, kita simpan deskripsinya, bukan nilai aktual boolean/string
//             jsonObject[field.key.trim()] = field.value_description.trim() || `CONTOH_VALUE_UNTUK_${field.key.trim().toUpperCase()}`;
//         }
//     });
//     return JSON.stringify(jsonObject, null, 2); // Format dengan indentasi
// };

// // Watch outputJsonFields juga
// watch(form.outputJsonFields, () => {
//     assembleFullPrompt();
// }, { deep: true });

</script>

<template>
    <Head title="Buat Konfigurasi Prompt Baru" />
    <AdminLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Buat atau Rakit Konfigurasi Prompt AI Baru
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <form @submit.prevent="submitCreateConfiguredPrompt" class="p-6 space-y-6">
                        <!-- Nama Konfigurasi & Deskripsi -->
                        <div>
                            <InputLabel for="configured_prompt_name" value="Nama Konfigurasi (Unik)" />
                            <TextInput id="configured_prompt_name" type="text" class="mt-1 block w-full" v-model="form.configured_prompt_name" required />
                            <InputError class="mt-2" :message="form.errors.configured_prompt_name" />
                        </div>
                        <div>
                            <InputLabel for="description" value="Deskripsi (Opsional)" />
                            <TextareaInput id="description" class="mt-1 block w-full" v-model="form.description" rows="2" />
                        </div>

                        <!-- Pemilihan Template Dasar -->
                        <div>
                            <InputLabel for="prompt_template_id" value="Gunakan Template Dasar (Opsional)" />
                            <select id="prompt_template_id" v-model="form.prompt_template_id" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                <option :value="null">-- Rakit Manual / Tanpa Template Dasar --</option>
                                <option v-for="template in props.promptTemplates" :key="template.id" :value="template.id">
                                    {{ template.template_name }}
                                </option>
                            </select>
                        </div>

                        <!-- <InputLabel :for="'ph_' + placeholder" :value="`Placeholder: {{${placeholder}}}`" class="italic mb-1" /> -->
                                     <!-- <select v-model="placeholderValues[placeholder].type" @change="assembleFullPrompt" class="text-xs rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 h-10"> -->
                                        <!--  -->
                                     <!-- </select> -->
                                    <!-- Jika placeholder untuk format output, gunakan UI JSON Dinamis -->
                                     <!-- <button type="button" @click="removeOutputJsonField(index)" v-if="form.outputJsonFields.length > 1" class="text-red-500 hover:text-red-700"> -->
                                                    <!-- <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg> -->
                                                <!-- </button> -->
                                    <!-- <button type="button" @click="addOutputJsonField" class="mt-2 text-sm text-sky-600 hover:text-sky-800 dark:text-sky-400 dark:hover:text-sky-300"> -->
                                                <!-- + Tambah Field JSON untuk Output -->
                                            <!-- </button> -->
                        <!-- Bagian Pengisian Prompt (Berdasarkan Template atau Manual) -->
                        <!-- Bagian Pengisian Prompt -->
                        <div class="space-y-4 mt-4 p-4 border dark:border-gray-700 rounded-md">
                            <template v-if="selectedTemplateObject && selectedTemplateObject.placeholders_defined && selectedTemplateObject.placeholders_defined.length > 0">
                                <h4 class="text-md font-semibold text-gray-700 dark:text-gray-300">Isi Placeholder dari Template: "{{ selectedTemplateObject.template_name }}"</h4>
                                <div v-for="(phData, placeholder) in placeholderValues" :key="placeholder" class="ml-2 py-3 border-b dark:border-gray-700 last:border-b-0">
                                    <InputLabel :for="'ph_' + placeholder" :value="`Placeholder: {{${placeholder}}}`" class="italic mb-2" />

                                    <!-- Kontainer Flex untuk Mode Input dan Pilihan Komponen/Input Manual -->
                                    <div class="flex flex-col sm:flex-row sm:items-start gap-2 mb-2">
                                        <!-- Dropdown Mode Input -->
                                        <div class="flex-shrink-0 sm:w-48"> <!-- Beri lebar tetap atau flex-shrink-0 -->
                                            <InputLabel :for="'ph_mode_' + placeholder" value="Mode Input" class="text-xs mb-1 sr-only" />
                                            <select :id="'ph_mode_' + placeholder" v-model="phData.type" @change="assembleFullPrompt" class="text-xs rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 h-10 w-full">
                                                <option value="component">Pilih Komponen</option>
                                                <option value="manual_textarea">Ketik Teks Manual</option>
                                                <option v-if="placeholder.toLowerCase().includes('output') || placeholder.toLowerCase().includes('format')" value="manual_keyvalue">
                                                    Input Key-Value
                                                </option>
                                            </select>
                                        </div>

                                        <!-- Pilihan Komponen (jika mode 'component') -->
                                        <div v-if="phData.type === 'component'" class="flex-grow min-w-0"> <!-- flex-grow agar mengambil sisa ruang -->
                                            <InputLabel :for="'ph_comp_' + placeholder" value="Pilih Komponen" class="text-xs mb-1 sr-only" />
                                            <select :id="'ph_comp_' + placeholder"
                                                    v-model="phData.value"
                                                    @change="assembleFullPrompt"
                                                    class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 h-10 w-full">
                                                <option :value="null">-- Pilih Komponen untuk {{placeholder}} --</option>
                                                <option v-for="comp in relevantComponents(placeholder)" :key="comp.id" :value="comp.id">
                                                    {{ comp.component_name }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Preview Konten Komponen (jika mode 'component' dan komponen dipilih) -->
                                    <div v-if="phData.type === 'component' && phData.value" class="mt-1 mb-2 p-2 bg-slate-100 dark:bg-slate-700 rounded text-xs max-h-24 overflow-y-auto">
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Preview Konten Komponen:</p>
                                        <pre class="whitespace-pre-wrap">{{ props.promptComponents.find(c => c.id === phData.value)?.content }}</pre>
                                    </div>

                                    <!-- Textarea untuk Input Manual (jika mode 'manual_textarea') -->
                                    <TextareaInput v-if="phData.type === 'manual_textarea'"
                                        :id="'ph_manual_textarea_' + placeholder"
                                        class="mt-1 block w-full font-mono text-sm"
                                        v-model="phData.manualText"
                                        @input="assembleFullPrompt"
                                        rows="3"
                                        :placeholder="`Ketik manual untuk {{${placeholder}}}`"
                                    />
                                    <p v-if="placeholder.toLowerCase().includes('label') && phData.type === 'manual_textarea'" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Masukkan beberapa label dipisahkan koma.
                                    </p>

                                    <!-- UI Key-Value Dinamis (jika mode 'manual_keyvalue' untuk placeholder output) -->
                                    <div v-if="phData.type === 'manual_keyvalue' && (placeholder.toLowerCase().includes('output') || placeholder.toLowerCase().includes('format'))" class="mt-1 space-y-3 border dark:border-gray-600 p-3 rounded-md">
                                        <div v-for="(field, index) in phData.keyValueFields" :key="index" class="flex items-center space-x-2">
                                            <TextInput type="text" v-model="field.key" placeholder="Nama Field (Key)" @input="assembleFullPrompt" class="flex-1 text-sm" />
                                            <span class="dark:text-gray-300">:</span>
                                            <TextInput type="text" v-model="field.value_description" placeholder="Contoh Value / Tipe Data" @input="assembleFullPrompt" class="flex-1 text-sm" />
                                            <button type="button" @click="removePhKeyValueField(placeholder, index)" v-if="phData.keyValueFields.length > 1" class="text-red-500 hover:text-red-700">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            </button>
                                        </div>
                                        <button type="button" @click="addPhKeyValueField(placeholder)" class="mt-2 text-sm text-sky-600 hover:text-sky-800 dark:text-sky-400 dark:hover:text-sky-300">
                                            + Tambah Field JSON
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <!-- Input Manual jika tidak ada template dipilih -->
                            <template v-else>
                                <h4 class="text-md font-semibold text-gray-700 dark:text-gray-300">Isi Segmen Prompt Secara Manual:</h4>
                                <div>
                                    <InputLabel for="target_prompt_segment" value="Segmen: Target Deskripsi" />
                                    <TextareaInput id="target_prompt_segment" v-model="form.target_prompt_segment" @input="assembleFullPrompt" rows="3" required />
                                </div>
                                <div>
                                    <InputLabel for="condition_prompt_segment" value="Segmen: Detail Kondisi" />
                                    <TextareaInput id="condition_prompt_segment" v-model="form.condition_prompt_segment" @input="assembleFullPrompt" rows="3" required />
                                </div>
                                <div>
                                    <InputLabel for="label_guidance_segment" value="Segmen: Panduan Label" />
                                    <TextareaInput id="label_guidance_segment" v-model="form.label_guidance_segment" @input="assembleFullPrompt" rows="3" required />
                                     <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Masukkan beberapa label dipisahkan koma.
                                    </p>
                                </div>
                                <!-- UI JSON Dinamis untuk Output Instructions jika mode manual global -->
                                <div>
                                    <InputLabel value="Segmen: Instruksi Format Output (JSON Dinamis)" />
                                    <div class="mt-2 p-4 border dark:border-gray-600 rounded-md space-y-3">
                                        <div v-for="(field, index) in form.outputJsonFieldsManual" :key="index" class="flex items-center space-x-2">
                                            <TextInput type="text" v-model="field.key" placeholder="Nama Field (Key)" @input="assembleFullPrompt" class="flex-1 text-sm" />
                                            <span class="dark:text-gray-300">:</span>
                                            <TextInput type="text" v-model="field.value_description" placeholder="Contoh Value / Tipe Data" @input="assembleFullPrompt" class="flex-1 text-sm" />
                                            <button type="button" @click="removeFieldFrom(form.outputJsonFieldsManual, index)" v-if="form.outputJsonFieldsManual.length > 1" class="text-red-500 hover:text-red-700">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            </button>
                                        </div>
                                        <button type="button" @click="addFieldTo(form.outputJsonFieldsManual)" class="mt-2 text-sm text-sky-600 hover:text-sky-800 dark:text-sky-400 dark:hover:text-sky-300">
                                            + Tambah Field JSON untuk Output
                                        </button>
                                    </div>
                                    <InputError class="mt-2" :message="form.errors.output_instructions_segment" />
                                </div>
                            </template>
                        </div>
                         <!-- Akhir Bagian Pengisian Prompt -->
                        <!-- Preview Full Prompt -->
                        <div class="mt-6">
                            <InputLabel value="Preview Full Prompt (Hasil Rakitan)" />
                            <TextareaInput class="mt-1 block w-full font-mono text-sm bg-gray-50 dark:bg-gray-700/50" v-model="form.full_prompt_text_generated" rows="8" readonly />
                        </div>

                        <!-- Generation Config -->
                        <!-- <div> -->
                            <!-- <InputLabel for="generation_config_final_json" value="Konfigurasi Generasi Final (JSON)" /> -->
                            <!-- <TextareaInput id="generation_config_final_json" class="mt-1 block w-full font-mono text-sm" v-model="form.generation_config_final_json" rows="5" required /> -->
                            <!-- <InputError class="mt-2" :message="form.errors.generation_config_final_json" /> -->
                        <!-- </div> -->

                        <div class="mt-6 pt-6 border-t dark:border-gray-700">
                            <div
                                @click="showAdvancedGenerationConfig = !showAdvancedGenerationConfig"
                                class="flex justify-between items-center cursor-pointer py-2 hover:bg-slate-50 dark:hover:bg-slate-700/50 px-2 rounded-md">
                                <span class="text-md font-semibold text-gray-700 dark:text-gray-300">
                                    Pengaturan Generasi Lanjutan (Gemini Config)
                                </span>
                                <svg :class="{'rotate-180': showAdvancedGenerationConfig, 'text-indigo-500 dark:text-indigo-400': showAdvancedGenerationConfig}" class="w-5 h-5 text-gray-500 dark:text-gray-400 transition-transform duration-200" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                </svg>
                            </div>

                            <transition
                                enter-active-class="transition ease-out duration-200"
                                enter-from-class="transform opacity-0 -translate-y-2"
                                enter-to-class="transform opacity-100 translate-y-0"
                                leave-active-class="transition ease-in duration-150"
                                leave-from-class="transform opacity-100 translate-y-0"
                                leave-to-class="transform opacity-0 -translate-y-2">
                                <div v-if="showAdvancedGenerationConfig" class="space-y-4 p-4 border dark:border-gray-600 rounded-md mt-2">
                                    <!-- Temperature -->
                                    <div>
                                       <div class="flex items-center space-x-1">
                                            <InputLabel for="gen_temperature" value="Temperature (Keacakan)" />
                                            <InfoTooltip textToShow="Mengontrol keacakan (0.0 - 1.0). Lebih tinggi = lebih kreatif." position="top" horizontalAlign="start" />
                                        </div>
                                        <input id="gen_temperature" type="range" min="0" max="1" step="0.01" v-model.number="form.gen_temperature" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer dark:bg-gray-700 mt-1" />
                                        <TextInput type="number" min="0" max="1" step="0.01" v-model.number="form.gen_temperature" class="mt-1 w-24 text-sm" />
                                        <InputError class="mt-2" :message="form.errors.gen_temperature" />
                                    </div>
                                    <!-- Max Output Tokens -->
                                    <div>
                                        <div class="flex items-center space-x-1">
                                            <InputLabel for="gen_max_output_tokens" value="Max Output Tokens" />
                                            <!-- Tooltip di atas, rata kiri dengan ikon -->
                                            <InfoTooltip textToShow="Jumlah maksimum token (kata/bagian kata) yang dapat dihasilkan oleh model dalam satu respons."
                                                position="top" horizontalAlign="start" 
                                            />
                                        </div>
                                        <TextInput id="gen_max_output_tokens" type="number" min="1" step="1" v-model.number="form.gen_max_output_tokens" class="mt-1 block w-full" />
                                        <InputError class="mt-2" :message="form.errors.gen_max_output_tokens" />
                                    </div>
                                    <!-- Top K -->
                                    <div>
                                        <div class="flex items-center space-x-1">
                                            <InputLabel for="gen_top_k" value="Top K" />
                                            <InfoTooltip textToShow="Memilih dari K kata paling mungkin. (Contoh: 0-40, 0 untuk tidak menggunakan)" position="top" horizontalAlign="start"/>
                                        </div>
                                        <TextInput id="gen_top_k" type="number" min="0" step="1" v-model.number="form.gen_top_k" class="mt-1 block w-full" />
                                        <InputError class="mt-2" :message="form.errors.gen_top_k" />
                                    </div>
                                    <!-- Top P -->
                                    <div>
                                        <div class="flex items-center space-x-1">
                                            <InputLabel for="gen_top_p" value="Top P" />
                                            <InfoTooltip textToShow="Probabilitas kumulatif. (0.0 - 1.0)" position="top" horizontalAlign="start"/>
                                        </div>
                                        <input id="gen_top_p" type="range" min="0" max="1" step="0.01" v-model.number="form.gen_top_p" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer dark:bg-gray-700 mt-1" />
                                        <TextInput type="number" min="0" max="1" step="0.01" v-model.number="form.gen_top_p" class="mt-1 w-24 text-sm" />
                                        <InputError class="mt-2" :message="form.errors.gen_top_p" />
                                    </div>
                                    <!-- Stop Sequences -->
                                    <div>
                                        <div class="flex items-center space-x-1">
                                            <InputLabel for="gen_stop_sequences_text" value="Stop Sequences (pisahkan dengan koma)" />
                                            <!-- <info-tooltip text="This is some helpful information"> -->
                                            <!-- Hover over me for info -->
                                            <!-- </info-tooltip> -->
                                            <InfoTooltip textToShow="Kata/frasa yang menghentikan output." position="top" horizontalAlign="start" />
                                        </div>
                                        <TextareaInput id="gen_stop_sequences_text"
                                            class="mt-1 block w-full font-mono text-sm"
                                            v-model="form.gen_stop_sequences_text"
                                            rows="2"
                                            :placeholder="'Contoh: ###, END_OF_RESPONSE'"
                                        />
                                        <InputError class="mt-2" :message="form.errors.gen_stop_sequences_text" />
                                    </div>
                                    <!-- Preview JSON Generation Config (Opsional untuk Debugging) -->
                                    <!-- <div class="mt-2"> -->
                                        <!-- <InputLabel value="Preview Generation Config JSON (Debug)" class="text-xs"/> -->
                                        <!-- <pre class="mt-1 text-xs bg-slate-100 dark:bg-slate-700 p-2 rounded whitespace-pre-wrap">{{ assembleGenerationConfigJson() }}</pre> -->
                                    <!-- </div> -->
                                </div>
                            </transition>
                        </div>

                        <!-- Bagian Test Prompt Cepat -->
                        <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Uji Prompt Ini</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                                Unggah gambar contoh untuk menguji konfigurasi prompt di atas secara langsung.
                            </p>
                            <div class="mb-4">
                                <InputLabel for="test_image" value="Gambar Contoh untuk Pengujian" />
                                <input class="block w-full text-sm text-slate-500 mt-1 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-violet-50 dark:file:bg-slate-700 file:text-violet-700 dark:file:text-slate-300 hover:file:bg-violet-100 dark:hover:file:bg-slate-600 dark:text-slate-400"
                                    id="test_image" type="file" @change="handleTestImageChange" accept="image/*" />
                            </div>

                            <div v-if="testImagePreview" class="mb-4">
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Preview Gambar Tes:</p>
                                <img :src="testImagePreview" alt="Preview Gambar Tes" class="mt-2 rounded-md max-h-60 border border-gray-300 dark:border-gray-600" />
                            </div>

                            <PrimaryButton type="button" @click="submitTestPrompt" :disabled="isTestingPrompt || !testImageFile">
                                <span v-if="isTestingPrompt">Menguji...</span>
                                <span v-else>Uji Prompt dengan Gambar Ini</span>
                            </PrimaryButton>

                            <div v-if="isTestingPrompt" class="mt-4 text-center">
                                <svg class="animate-spin h-6 w-6 text-gray-700 dark:text-gray-300 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Menghubungi Gemini Vision...</p>
                            </div>

                            <div v-if="testError" class="mt-4 p-3 bg-red-100 dark:bg-red-700/30 border border-red-300 dark:border-red-600 rounded-md">
                                <p class="font-semibold text-red-700 dark:text-red-300">Error Pengujian:</p>
                                <pre class="mt-1 text-xs text-red-600 dark:text-red-400 whitespace-pre-wrap">{{ testError }}</pre>
                            </div>

                            <div v-if="testResult && testResult.success" class="mt-4 p-3 bg-green-50 dark:bg-green-700/30 border border-green-300 dark:border-green-600 rounded-md animate-pulse">
                                <p class="font-semibold text-green-700 dark:text-green-300">Hasil Pengujian Sukses:</p>
                                <h4 class="mt-2 font-medium text-sm text-gray-700 dark:text-gray-300">Teks Respons Mentah dari Gemini:</h4>
                                <pre class="mt-1 text-xs bg-gray-100 dark:bg-gray-800 p-2 rounded whitespace-pre-wrap text-gray-700 dark:text-gray-300">{{ testResult.gemini_raw_response_text }}</pre>
                                <h4 class="mt-3 font-medium text-sm text-gray-700 dark:text-gray-300">Hasil Parsing (Jika JSON):</h4>
                                <pre class="mt-1 text-xs bg-gray-100 dark:bg-gray-800 p-2 rounded whitespace-pre-wrap text-gray-700 dark:text-gray-300">{{ JSON.stringify(testResult.parsed_results, null, 2) }}</pre>
                                <h4 class="mt-3 font-medium text-sm text-gray-700 dark:text-gray-300">Prompt yang Dikirim (Debug):</h4>
                                <pre class="mt-1 text-xs bg-gray-100 dark:bg-gray-800 p-2 rounded whitespace-pre-wrap text-gray-700 dark:text-gray-300">{{ testResult.full_prompt_sent }}</pre>
                                <h4 class="mt-3 font-medium text-sm text-gray-700 dark:text-gray-300">Config Generasi yang Digunakan (Debug):</h4>
                                <pre class="mt-1 text-xs bg-gray-100 dark:bg-gray-800 p-2 rounded whitespace-pre-wrap text-gray-700 dark:text-gray-300">{{ JSON.stringify(testResult.generation_config_used, null, 2) }}</pre>
                            </div>
                        </div>

                        <!-- Tombol Aksi Form -->
                        <div class="flex items-center justify-end mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                            <Link :href="route('admin.configured-prompts.index')" class="text-sm text-gray-600 dark:text-gray-400 hover:underline mr-4">
                                Batal
                            </Link>
                            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                Simpan Konfigurasi Prompt
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>