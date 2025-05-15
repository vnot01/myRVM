<script setup>
import { Link as InertiaLink } from '@inertiajs/vue3';

defineProps({
  links: Array,
});
</script>

<template>
  <div v-if="links && links.length > 3" class="flex flex-wrap -mb-1">
    <template v-for="(link, key) in links" :key="key">
      <div
        v-if="link.url === null"
        class="mr-1 mb-1 px-3 py-2 text-sm leading-4 text-gray-400 dark:text-gray-500 border rounded"
        v-html="link.label"
      />
      <InertiaLink
        v-else
        class="mr-1 mb-1 px-3 py-2 text-sm leading-4 border rounded hover:bg-white dark:hover:bg-gray-700 focus:border-indigo-500 dark:focus:border-indigo-700 focus:text-indigo-500 dark:focus:text-indigo-300"
        :class="{ 'bg-white dark:bg-gray-600 text-indigo-700 dark:text-indigo-300': link.active }"
        :href="link.url"
        v-html="link.label"
      />
    </template>
  </div>
</template>