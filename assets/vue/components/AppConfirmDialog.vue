<script setup>
import ConfirmDialog from 'primevue/confirmdialog';
import Button from 'primevue/button';

// The one confirmation dialog (pages.html → UI standards → Confirmation): title = action + object,
// body = consequence, safe button left (focused first), red verb button right. Esc / outside = safe.
</script>

<template>
    <ConfirmDialog group="app" :draggable="false" dismissable-mask :style="{ width: 'min(28rem, calc(100vw - 2rem))' }">
        <template #container="{ message, acceptCallback, rejectCallback }">
            <div class="overflow-hidden rounded-lg bg-white">
                <div class="px-5 pt-5 text-lg font-bold text-surface-900">{{ message.header }}</div>
                <div class="px-5 pb-5 pt-2 text-surface-700">{{ message.message }}</div>
                <div class="flex justify-end gap-2 bg-surface-100 px-5 py-3">
                    <Button :label="message.rejectLabel" severity="secondary" outlined autofocus @click="rejectCallback" />
                    <Button :label="message.acceptLabel" :severity="message.tone === 'primary' ? undefined : 'danger'" @click="acceptCallback" />
                </div>
            </div>
        </template>
    </ConfirmDialog>
</template>
