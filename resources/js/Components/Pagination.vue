<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
  links: Array,
});
</script>

<template>
    <div v-if="links.length > 3" class="flex flex-wrap -mb-1">
        <template v-for="(link, key) in links" :key="key">
            <div
                v-if="link.url === null"
                class="mr-1 mb-1 px-4 py-3 text-sm leading-4 text-gray-400 dark:text-gray-600 border rounded dark:border-slate-600"
                v-html="link.label"/>
            <Link
                v-else
                class="mr-1 mb-1 px-4 py-3 text-sm leading-4 border rounded hover:bg-white dark:border-slate-600 dark:hover:bg-slate-700 focus:border-indigo-500 focus:text-indigo-500 dark:focus:border-indigo-400 dark:focus:text-indigo-400"
                :class="{ 'bg-white dark:bg-slate-700': link.active }"
                :href="link.url"
                v-html="link.label"/>
        </template>
    </div>
</template>