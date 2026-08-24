<script setup>
import MyProjectInfo from './MyProjectInfo.vue';

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['back', 'delete', 'update']);
</script>

<template>
    <MyProjectInfo
        :project="project"
        @back="emit('back')"
        @delete="emit('delete', $event)"
        @update="emit('update', $event)"
    />
</template>
