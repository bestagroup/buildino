<script setup lang="ts">
import UiNumberInput from './UiNumberInput.vue';
import { money, type InvoiceItem } from '../../lib/invoice-workspace';
const rows = defineModel<InvoiceItem[]>({ required: true });
defineProps<{ currency: string; readonly?: boolean }>();
</script>
<template>
    <section class="finance-detail-section" aria-label="ردیف‌های صورتحساب">
        <h4>ردیف‌های صورتحساب</h4>
        <div v-for="(row, index) in rows" :key="index" class="finance-line">
            <div class="ownership-form-grid">
                <label class="ownership-field"><span>عنوان ردیف {{ index + 1 }}</span><input v-model="row.title" :readonly="readonly" maxlength="255" :data-bill-title="index"></label>
                <label class="ownership-field"><span>تعداد</span><UiNumberInput v-model="row.quantity" :readonly="readonly" min="1" max="100000" step="1" :data-bill-quantity="index" /></label>
                <label class="ownership-field"><span>مبلغ هر مورد ({{ currency }})</span><UiNumberInput v-model="row.unit_amount" :readonly="readonly" min="-1000000000000" max="1000000000000" step="1" :data-bill-amount="index" /></label>
                <label class="ownership-field"><span>توضیحات ردیف</span><textarea v-model="row.description" :readonly="readonly" maxlength="2000"></textarea></label>
            </div>
            <p>مبلغ ردیف: {{ money(Number(row.quantity || 0) * Number(row.unit_amount || 0)) }} {{ currency }}</p>
            <button v-if="!readonly && rows.length > 1" type="button" class="ownership-button" @click="rows.splice(index, 1)">حذف ردیف {{ index + 1 }}</button>
        </div>
        <button v-if="!readonly && rows.length < 100" type="button" class="ownership-button" data-bill-add-line @click="rows.push({ title: '', quantity: 1, unit_amount: '' })">افزودن ردیف</button>
    </section>
</template>
