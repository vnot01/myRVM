<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, watch, computed, reactive, onMounted } from 'vue'; // Tambahkan onMounted
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import TextareaInput from '@/Components/TextareaInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import InfoTooltip from '@/Components/InfoTooltip.vue'; // Jika sudah dibuat
import axios from 'axios';

const props = defineProps({
    configuredPrompt: Object, // Data prompt yang akan diedit
    promptTemplates: Array,
    promptComponents: Array,
    errors: Object, // Error validasi dari backend (otomatis dari Inertia)
});

// Helper untuk parse JSON dengan aman
const safeJsonParse = (jsonString, defaultValue = null) => {
    if (!jsonString) return defaultValue;
    try {
        const parsed = JSON.parse(jsonString);
        return parsed;
    } catch (e) {
        // console.warn("Failed to parse JSON string:", jsonString, e);
        return defaultValue;
    }
};

// Inisialisasi form dengan data dari props.configuredPrompt
const initialGenerationConfig = computed(() => {
    return props.configuredPrompt?.generation_config_final || // Jika sudah objek/array
           safeJsonParse(props.configuredPrompt?.generation_config_final_json, {}); // Jika masih string JSON
});
// Untuk mengisi outputJsonFieldsManual jika tidak ada template atau placeholder output tidak pakai komponen
const parseFullPromptToSegments = (fullPrompt) => {
    // Ini adalah parsing yang SANGAT SEDERHANA dan rapuh.
    // Anda mungkin perlu regex yang lebih canggih jika formatnya kompleks.
    const segments = {
        target: '', condition: '', labels: '', output: ''
    };
    if (!fullPrompt) return segments;

    const targetMatch = fullPrompt.match(/Target:\s*([\s\S]*?)\nCondition:/i);
    if (targetMatch) segments.target = targetMatch[1].trim();

    const conditionMatch = fullPrompt.match(/Condition:\s*([\s\S]*?)\nLabel Guidance:/i);
    if (conditionMatch) segments.condition = conditionMatch[1].trim();

    const labelsMatch = fullPrompt.match(/Label Guidance:\s*([\s\S]*?)\nOutput Instructions:/i);
    if (labelsMatch) segments.labels = labelsMatch[1].trim();

    const outputMatch = fullPrompt.match(/Output Instructions:\s*([\s\S]*)/i);
    if (outputMatch) segments.output = outputMatch[1].trim();

    return segments;
};

const initialOutputJsonFields = computed(() => {
    // Jika output_instructions_segment adalah JSON yang valid dari key-value
    // kita coba ubah kembali ke array objek. Ini butuh logika parsing yang lebih canggih
    // atau simpan struktur key-value mentah di database jika ingin diedit lagi dengan mudah.
    // Untuk sekarang, kita asumsikan output_instructions_segment adalah teks yang akan diedit di textarea.
    // Jika Anda menyimpan outputJsonFields di database, ambil dari sana.
    // Jika tidak, kita mulai dengan array kosong atau coba parse dari full_prompt.
    // Untuk kesederhanaan awal, kita mulai dengan array kosong jika diedit.
    // Nanti bisa disempurnakan untuk mem-parse full_prompt_text_generated->output_instructions_segment
    // atau lebih baik, simpan struktur outputJsonFields di database.
    // Untuk sekarang, kita biarkan user mengisi ulang jika mode manual atau UI key-value.
    return [{ key: '', value_description: '' }];
});


const form = useForm({
    _method: 'PUT', // Untuk update
    configured_prompt_name: props.configuredPrompt?.configured_prompt_name || '',
    prompt_template_id: props.configuredPrompt?.prompt_template_id || null,
    description: props.configuredPrompt?.description || '',
    full_prompt_text_generated: props.configuredPrompt?.full_prompt_text_generated || '',
    target_prompt_segment: '', // Akan diisi di onMounted jika mode manual
    condition_prompt_segment: '',
    label_guidance_segment: '',
    output_instructions_segment: props.configuredPrompt?.full_prompt_text_generated ?
        (parseFullPromptToSegments(props.configuredPrompt.full_prompt_text_generated).output || '{\n  "default_key": "default_value"\n}') :
        '{\n  "default_key": "default_value"\n}', // Akan diisi dari UI key-value atau textarea
    outputJsonFieldsManual: reactive(initialOutputJsonFields.value),

    // Inisialisasi field gen_* dari generation_config_final
    gen_temperature: initialGenerationConfig.value?.temperature || 0.4,
    gen_max_output_tokens: initialGenerationConfig.value?.maxOutputTokens || 1024,
    gen_top_k: initialGenerationConfig.value?.topK === 0 ? 0 : (initialGenerationConfig.value?.topK || 32),
    gen_top_p: initialGenerationConfig.value?.topP === 0 ? 0 : (initialGenerationConfig.value?.topP || 1.0),
    gen_stop_sequences_text: (initialGenerationConfig.value?.stopSequences || []).join(', '),

    mappings: [], // Akan diisi dari props.configuredPrompt.component_mappings
});

const selectedTemplateObject = ref(null);
const placeholderValues = reactive({});
const showAdvancedGenerationConfig = ref(false);

// --- Fungsi Perakitan (SAMA SEPERTI CREATE.VUE) ---
const assembleGenerationConfigJson = () => { const config = {
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

const assembleFullPrompt = () => { let finalOutputInstructions = '';
    if (selectedTemplateObject.value && selectedTemplateObject.value.template_string) {
        let assembled = selectedTemplateObject.value.template_string;
        for (const placeholder in placeholderValues) {
            const phData = placeholderValues[placeholder];
            let replacementText = `{{${placeholder}}}`;

            const isOutputPlaceholder = placeholder.toLowerCase().includes('output') || placeholder.toLowerCase().includes('format');

            if (isOutputPlaceholder) {
                if (phData.type === 'component' && phData.value) {
                    const component = props.promptComponents.find(c => c.id === phData.value);
                    replacementText = component ? component.content : `{{${placeholder}}}`;
                } else if (phData.type === 'manual_keyvalue') {
                    replacementText = assembleOutputInstructionsSegmentFromKeyValue(phData.keyValueFields);
                } else if (phData.type === 'manual_textarea' && phData.manualText.trim() !== '') {
                    replacementText = phData.manualText;
                }
                finalOutputInstructions = replacementText; // Simpan untuk nanti jika perlu
            } else { // Placeholder biasa
                if (phData.type === 'component' && phData.value) {
                    const component = props.promptComponents.find(c => c.id === phData.value);
                    replacementText = component ? component.content : `{{${placeholder}}}`;
                } else if (phData.type === 'manual' && phData.manualText.trim() !== '') { // Asumsi type 'manual' adalah textarea biasa
                    replacementText = phData.manualText;
                }
            }
            assembled = assembled.replace(new RegExp(`{{${placeholder}}}`, 'g'), replacementText);
        }
        form.full_prompt_text_generated = assembled;
        // Jika template punya placeholder output, output_instructions_segment di form utama tidak relevan
        form.output_instructions_segment = finalOutputInstructions; // Isi dengan hasil rakitan output
    } else {
        // Mode manual global
        form.output_instructions_segment = assembleOutputInstructionsSegmentFromKeyValue(form.outputJsonFieldsManual);
        form.full_prompt_text_generated = `Target: ${form.target_prompt_segment}\nCondition: ${form.condition_prompt_segment}\nLabel Guidance: ${form.label_guidance_segment}\nOutput Instructions: ${form.output_instructions_segment}`;
    }
};

// --- Inisialisasi State Form saat Komponen Dimuat ---
onMounted(() => {
    // Inisialisasi pilihan template
    if (form.prompt_template_id) {
        selectedTemplateObject.value = props.promptTemplates.find(t => t.id === form.prompt_template_id);
    }

    // Inisialisasi placeholderValues dari component_mappings yang ada
    if (selectedTemplateObject.value && selectedTemplateObject.value.placeholders_defined) {
        const mappings = props.configuredPrompt?.component_mappings || [];
        selectedTemplateObject.value.placeholders_defined.forEach(ph => {
            const existingMapping = mappings.find(m => m.placeholder_in_template === ph);
            let phValue = { type: 'manual_textarea', value: null, manualText: '', keyValueFields: reactive([{key:'', value_description:''}]) };

            if (existingMapping) {
                phValue.type = 'component';
                phValue.value = existingMapping.prompt_component_id;
            } else {
                // Jika tidak ada mapping komponen, coba ekstrak teks manual dari full_prompt
                // Ini bagian yang kompleks: mem-parse ulang full_prompt untuk mendapatkan teks manual per placeholder.
                // Untuk sekarang, kita set manualText kosong atau default.
                // Idealnya, jika tidak ada mapping, berarti teksnya adalah bagian dari full_prompt_text_generated
                // yang tidak berasal dari komponen.
                // Untuk kesederhanaan, kita biarkan user mengisi ulang jika tidak ada mapping komponen.
                // Atau, jika placeholder output, kita bisa coba parse dari full_prompt_text_generated
                if (ph.toLowerCase().includes('output') || ph.toLowerCase().includes('format')) {
                    const outputJsonStr = parseFullPromptToSegments(form.full_prompt_text_generated).output;
                    const parsedJson = safeJsonParse(outputJsonStr, {});
                    const fields = [];
                    for (const key in parsedJson) {
                        fields.push({ key: key, value_description: String(parsedJson[key]) });
                    }
                    if (fields.length > 0) {
                        phValue.type = 'manual_textarea'; // Atau 'manual_keyvalue' jika Anda bisa mem-parse balik ke fields
                        // Coba isi manualText dari full_prompt jika mungkin (ini akan rumit)
                        // Untuk sekarang, kita biarkan kosong jika tidak ada mapping
                        phValue.keyValueFields = reactive(fields);
                    } else {
                        phValue.type = 'manual_textarea';
                        phValue.manualText = outputJsonStr; // Tampilkan JSON string di textarea
                    }
                } else {
                    // Untuk placeholder lain, mungkin lebih sulit mengekstrak teks manualnya
                    // dari full_prompt jika tidak ada delimiter yang jelas.
                    // Biarkan manualText kosong untuk diisi ulang.
                    phValue.manualText = `{{${ph}}}`; // Default ke placeholder itu sendiri
                }
            }
            placeholderValues[ph] = phValue;
        });
    } else if (!form.prompt_template_id && form.full_prompt_text_generated) {
        // Mode manual global, isi segmen dari full_prompt
        const segments = parseFullPromptToSegments(form.full_prompt_text_generated);
        form.target_prompt_segment = segments.target;
        form.condition_prompt_segment = segments.condition;
        form.label_guidance_segment = segments.labels;
        // Untuk output_instructions_segment, isi outputJsonFieldsManual
        const parsedOutput = safeJsonParse(segments.output, {});
        const fields = [];
        for (const key in parsedOutput) {
            fields.push({ key: key, value_description: String(parsedOutput[key]) });
        }
        if (fields.length > 0) {
            form.outputJsonFieldsManual = reactive(fields);
        } else if (segments.output) { // Jika tidak bisa parse tapi ada teks
             form.output_instructions_segment = segments.output; // fallback ke textarea jika parsing gagal
             // Mungkin perlu state tambahan untuk mode input outputJsonFieldsManual
        }
    }
    assembleFullPrompt(); // Panggil untuk memastikan preview awal benar setelah semua inisialisasi
});

//     // Jika tidak ada template dipilih (mode manual), isi segmen dari full_prompt (perlu parsing)
//     // atau dari field segmen jika Anda menyimpannya terpisah di backend (saat ini tidak)
//     if (!form.prompt_template_id && form.full_prompt_text_generated) {
//         // Ini juga kompleks: mem-parse full_prompt_text_generated kembali ke segmen-segmen.
//         // Untuk sekarang, kita biarkan field segmen diisi manual oleh user jika mereka mau mengubah.
//         // Atau, Anda bisa menampilkan full_prompt_text_generated di satu textarea besar untuk diedit.
//         // Untuk UI yang konsisten, kita perlu cara mengisi form.xxx_prompt_segment
//         // dari props.configuredPrompt.full_prompt_text_generated jika tidak ada template.
//         // Untuk awal, kita biarkan kosong dan admin bisa copy-paste dari preview full_prompt
//         // atau kita bisa coba parsing sederhana:
//         const lines = form.full_prompt_text_generated.split('\n');
//         lines.forEach(line => {
//             if (line.startsWith('Target:')) form.target_prompt_segment = line.substring(8).trim();
//             else if (line.startsWith('Condition:')) form.condition_prompt_segment = line.substring(11).trim();
//             else if (line.startsWith('Label Guidance:')) form.label_guidance_segment = line.substring(16).trim();
//             else if (line.startsWith('Output Instructions:')) {
//                 // Ini adalah JSON string, coba isi UI Key-Value jika bisa di-parse
//                 const outputJsonStr = line.substring(20).trim();
//                 const parsedOutput = safeJsonParse(outputJsonStr, {});
//                 const fields = [];
//                 for (const key in parsedOutput) {
//                     fields.push({ key: key, value_description: String(parsedOutput[key]) });
//                 }
//                 if (fields.length > 0) {
//                     form.outputJsonFieldsManual = reactive(fields);
//                 }
//                  form.output_instructions_segment = outputJsonStr; // Simpan juga string aslinya
//             }
//         });
//     }
//     assembleFullPrompt(); // Panggil untuk memastikan preview awal benar
// });


// --- Watchers (SAMA SEPERTI CREATE.VUE, pastikan memanggil assembleFullPrompt) ---
watch(() => form.prompt_template_id, (newTemplateId) => { Object.keys(placeholderValues).forEach(key => delete placeholderValues[key]);
    if (newTemplateId) {
        selectedTemplateObject.value = props.promptTemplates.find(t => t.id === newTemplateId);
        if (selectedTemplateObject.value && selectedTemplateObject.value.placeholders_defined) {
            selectedTemplateObject.value.placeholders_defined.forEach(ph => {
                let defaultPhValue = { type: 'component', value: null, manualText: '' };
                if (ph.toLowerCase().includes('output') || ph.toLowerCase().includes('format')) {
                    defaultPhValue = {
                        type: 'component', // Default ke komponen
                        value: null,
                        manualText: '{\n  "default_key": "default_value"\n}',
                        keyValueFields: reactive([{ key: '', value_description: '' }]),
                    };
                }
                placeholderValues[ph] = defaultPhValue;
            });
        }
    } else {
        selectedTemplateObject.value = null;
    }
    assembleFullPrompt();
}, { immediate: true });

watch(placeholderValues, assembleFullPrompt, { deep: true });
watch(() => [form.target_prompt_segment, form.condition_prompt_segment, form.label_guidance_segment], () => {
    if (!form.prompt_template_id) { assembleFullPrompt(); }
}, { deep: true });
watch(form.outputJsonFieldsManual, () => {
    if (!form.prompt_template_id) { assembleFullPrompt(); }
}, { deep: true });


const relevantComponents = (placeholderName) => { let typeToFilter = placeholderName.toLowerCase();
    if (typeToFilter.includes('target')) typeToFilter = 'target_description';
    else if (typeToFilter.includes('condition')) typeToFilter = 'condition_details';
    else if (typeToFilter.includes('label')) typeToFilter = 'label_options';
    else if (typeToFilter.includes('output') || typeToFilter.includes('format')) typeToFilter = 'output_format_definition';
    return props.promptComponents.filter(c => c.component_type === typeToFilter);
};

const addFieldTo = (fieldsArray) => { fieldsArray.push({ key: '', value_description: '' }); assembleFullPrompt(); };
const removeFieldFrom = (fieldsArray, index) => { fieldsArray.splice(index, 1); assembleFullPrompt(); };

// --- Submit Update ---
const submitUpdateConfiguredPrompt = () => {
    assembleFullPrompt();
    const finalGenerationConfigJson = assembleGenerationConfigJson();
    const mappingsForBackend = [];
    if (selectedTemplateObject.value && selectedTemplateObject.value.placeholders_defined) {
        for (const placeholder in placeholderValues) {
            const phData = placeholderValues[placeholder];
            if (phData.type === 'component' && phData.value) {
                mappingsForBackend.push({
                    placeholder_in_template: placeholder,
                    prompt_component_id: phData.value,
                });
            }
        }
    }

    router.put(route('admin.configured-prompts.update', props.configuredPrompt.id), {
        configured_prompt_name: form.configured_prompt_name,
        prompt_template_id: form.prompt_template_id,
        description: form.description,
        full_prompt_text_generated: form.full_prompt_text_generated,
        generation_config_final_json: finalGenerationConfigJson,
        mappings: mappingsForBackend,
        // Kirim juga field segmen manual jika tidak ada template,
        // atau jika Anda ingin backend merakit ulang full_prompt_text_generated
        target_prompt_segment: form.target_prompt_segment,
        condition_prompt_segment: form.condition_prompt_segment,
        label_guidance_segment: form.label_guidance_segment,
        output_instructions_segment: form.output_instructions_segment, // Hasil rakitan dari UI JSON Dinamis
    }, {
        preserveScroll: true,
        onError: (errors) => { console.error("Error updating configured prompt:", errors); },
    });
};

// --- Logika Test Prompt Cepat (SAMA SEPERTI CREATE.VUE) ---
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
</script>

<template>
    <Head :title="'Edit Konfigurasi Prompt - ' + form.configured_prompt_name" />
    <AdminLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Edit Konfigurasi Prompt: {{ form.configured_prompt_name }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <!-- Form akan menggunakan submitUpdateConfiguredPrompt -->
                    <form @submit.prevent="submitUpdateConfiguredPrompt" class="p-6 space-y-6">
                        <!-- Semua input fields (Nama, Deskripsi, Template, Placeholders/Segmen Manual, Preview, Generation Config UI, Test Prompt UI) -->
                        <!-- akan SAMA PERSIS dengan template di Create.vue -->
                        <!-- Perbedaannya hanya pada inisialisasi nilai di `useForm` dan fungsi submit. -->

                        <!-- Contoh bagian yang sama: -->
                        <div>
                            <InputLabel for="configured_prompt_name" value="Nama Konfigurasi (Unik)" />
                            <TextInput id="configured_prompt_name" type="text" class="mt-1 block w-full" v-model="form.configured_prompt_name" required />
                            <InputError class="mt-2" :message="form.errors.configured_prompt_name || props.errors.configured_prompt_name" />
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
                                Simpan Perubahan
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>