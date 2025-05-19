<script setup>
import { computed } from 'vue';

const props = defineProps({
    title: String,
    value: [String, Number],
    icon: Object, // Komponen ikon Tabler atau SVG string
    colorClass: { // Kelas Tailwind untuk warna background/border
        type: String,
        default: 'bg-sky-500 dark:bg-sky-700'
    },
    change: String, // Misal: "+12%" atau "-5"
    changeType: String // 'positive', 'negative', atau 'neutral'
});

const valueFormatted = computed(() => {
    if (typeof props.value === 'number') {
        return props.value.toLocaleString('id-ID');
    }
    return props.value;
});

const changeColorClass = computed(() => {
    if (props.changeType === 'positive') return 'text-green-600 dark:text-green-400';
    if (props.changeType === 'negative') return 'text-red-600 dark:text-red-400';
    return 'text-gray-500 dark:text-gray-400';
});
</script>

<template>
    <div class="bg-white dark:bg-slate-800 p-6 rounded-xl shadow-lg flex items-start space-x-4">
        <div :class="colorClass" class="flex-shrink-0 w-12 h-12 flex items-center justify-center rounded-lg text-white">
            <component :is="icon" class="w-6 h-6" v-if="icon" />
            <span v-else class="text-2xl font-bold">?</span>
        </div>
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ title }}</p>
            <!-- Gunakan slot untuk value jika ada, jika tidak, tampilkan props.value -->
            <slot name="value">
                <p class="text-3xl font-semibold text-gray-900 dark:text-white">{{ valueFormatted }}</p>
            </slot>
            <div v-if="change || $slots.change" class="text-xs mt-1"> <!-- Cek juga $slots.change -->
                <!-- Gunakan slot untuk change jika ada, jika tidak, tampilkan props.change -->
                <slot name="change">
                    <span :class="changeColorClass" class="font-semibold">{{ change }}</span>
                </slot>
            </div>
        </div>
    </div>
</template>