<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    title: String,
    value: [String, Number],
    icon: String, // Nama ikon dari library (misal FontAwesome) atau path SVG
    colorClass: { // Kelas Tailwind untuk background atau border
        type: String,
        default: 'bg-blue-500',
    },
    description: String, // Teks tambahan di bawah nilai
    link: String, // URL jika kartu bisa diklik
    linkText: String, // Teks untuk link
});

const cardClasses = computed(() => {
    return `p-6 rounded-lg shadow-lg text-white ${props.colorClass}`;
});
</script>

<template>
    <div :class="cardClasses">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium uppercase tracking-wider">
                    {{ title }}
                </p>
                <p class="text-3xl font-semibold">
                    {{ value }}
                </p>
                <!-- Menggunakan slot 'description' jika ada, atau prop 'description' jika slot tidak diisi -->
                <div v-if="$slots.description || description" class="text-xs mt-1 opacity-90">
                    <slot name="description">
                        {{ description }} <!-- Fallback ke prop description -->
                    </slot>
                </div>
            </div>
            <div v-if="icon" class="text-4xl opacity-70">
                <!-- Anda bisa menggunakan library ikon atau SVG inline di sini -->
                <!-- Contoh placeholder ikon -->
                <span v-html="icon"></span>
            </div>
        </div>
        <div v-if="link && linkText" class="mt-4">
            <Link :href="link" class="text-sm font-medium hover:underline">
                {{ linkText }} →
            </Link>
        </div>
    </div>
</template>