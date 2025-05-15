<script setup>
import { onMounted, ref } from 'vue';

defineProps({
    modelValue: String, // Untuk v-model
    rows: {
        type: [String, Number],
        default: 3,
    }
});

// Emit event untuk v-model
const emit = defineEmits(['update:modelValue']);

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

// Fungsi untuk mengupdate modelValue saat input berubah
const onInput = (event) => {
    emit('update:modelValue', event.target.value);
};

// expose focus method
defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <textarea
        ref="input"
        class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm w-full"
        :value="modelValue"
        @input="onInput"
        :rows="rows"
    ></textarea>
</template>