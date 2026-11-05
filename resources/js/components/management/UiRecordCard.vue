<script setup lang="ts">
defineProps<{ title: string; subtitle?: string }>();
</script>

<template>
    <article class="operation-record">
        <header class="operation-record__heading">
            <div class="operation-record__identity"><h4>{{ title }}</h4><p v-if="subtitle">{{ subtitle }}</p></div>
            <slot name="status" />
        </header>
        <dl v-if="$slots.fields" class="operation-record__fields"><slot name="fields" /></dl>
        <div class="operation-record__actions"><slot name="actions" /></div>
    </article>
</template>
