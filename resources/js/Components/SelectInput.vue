<script setup>
import { onMounted, ref } from 'vue';

defineProps({
    modelValue: [String, Number], // Bisa string atau number tergantung value option
    options: { // Array objek, misal: [{value: 'active', label: 'Active'}, {value: 'inactive', label: 'Inactive'}]
        type: Array,
        required: true,
    },
    valueKey: { // Key untuk value di objek options
        type: String,
        default: 'value',
    },
    labelKey: { // Key untuk label di objek options
        type: String,
        default: 'label',
    },
    placeholder: String, // Opsional placeholder/option default
});

const emit = defineEmits(['update:modelValue']);

const select = ref(null);

onMounted(() => {
    if (select.value.hasAttribute('autofocus')) {
        select.value.focus();
    }
});

const onChange = (event) => {
    emit('update:modelValue', event.target.value);
};

defineExpose({ focus: () => select.value.focus() });
</script>

<template>
    <select
        ref="select"
        class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm w-full"
        :value="modelValue"
        @change="onChange"
    >
        <option v-if="placeholder" value="" disabled selected>{{ placeholder }}</option>
        <option v-for="option in options" :key="option[valueKey]" :value="option[valueKey]">
            {{ option[labelKey] }}
        </option>
    </select>
</template>