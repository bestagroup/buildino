<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, useId } from 'vue';
import type { Map as LeafletMap, Marker, LeafletMouseEvent } from 'leaflet';
import UiDrawer from './UiDrawer.vue';
import { asMapPoint, loadMapLibrary, mapConfiguration, type MapPoint, type MapConfiguration } from '../../lib/location-picker';
import '../../../css/location-picker.css';

const props = withDefaults(defineProps<{
    modelValue: MapPoint | null;
    label?: string;
    disabled?: boolean;
    clearable?: boolean;
    error?: string;
    mapOptions?: Partial<MapConfiguration>;
}>(), { label: 'موقعیت روی نقشه', disabled: false, clearable: true, error: '' });
const emit = defineEmits<{ 'update:modelValue': [value: MapPoint | null] }>();
const hintId = 'location-hint-' + useId();
const open = ref(false), draft = ref<MapPoint | null>(null), tilesReady = ref(false), loading = ref(false), locating = ref(false);
const mapError = ref(''), locationError = ref(''), container = ref<HTMLElement | null>(null);
const opener = ref<HTMLButtonElement | null>(null);
const value = computed(() => asMapPoint(props.modelValue?.latitude, props.modelValue?.longitude));
const coordinateText = (point: MapPoint) => `${point.latitude.toFixed(6)}, ${point.longitude.toFixed(6)}`;
let map: LeafletMap | null = null, marker: Marker | null = null;
let library: Awaited<ReturnType<typeof loadMapLibrary>> | null = null;
let generation = 0, geoRequest = 0, timeout: ReturnType<typeof setTimeout> | undefined, observer: ResizeObserver | undefined;

const disposeMap = () => {
    generation++; geoRequest++; clearTimeout(timeout); observer?.disconnect(); observer = undefined;
    map?.remove(); map = null; marker = null; locating.value = false;
};
const placeMarker = (point: MapPoint) => {
    if (!map || !library) return;
    if (!marker) {
        marker = library.marker([point.latitude, point.longitude], {
            draggable: true, keyboard: true, title: 'موقعیت انتخاب‌شده؛ برای تنظیم جابه‌جا کنید',
            icon: library.divIcon({ className: 'location-marker', html: '<span></span>', iconSize: [30, 38], iconAnchor: [15, 36] }),
        }).addTo(map);
        marker.on('dragend', () => {
            const latlng = marker?.getLatLng().wrap();
            if (latlng) setDraft(latlng.lat, latlng.lng);
        });
    } else marker.setLatLng([point.latitude, point.longitude]);
};
const setDraft = (latitude: number, longitude: number) => {
    const point = asMapPoint(Number(latitude.toFixed(6)), Number(longitude.toFixed(6)));
    if (!point) return;
    geoRequest++; locating.value = false;
    draft.value = point; locationError.value = ''; placeMarker(point);
};
const startMap = async () => {
    const previousCenter = map?.getCenter(), previousZoom = map?.getZoom();
    disposeMap(); const ticket = generation;
    tilesReady.value = false; loading.value = true; mapError.value = ''; locationError.value = '';
    try {
        library = await loadMapLibrary(); await nextTick();
        if (!open.value || ticket !== generation || !container.value) return;
        const config = { ...mapConfiguration(), ...props.mapOptions };
        map = library.map(container.value, { zoomControl: false, minZoom: 2, maxZoom: config.maxZoom, maxBounds: [[-85, -180], [85, 180]], maxBoundsViscosity: .8 });
        map.attributionControl.setPrefix(false);
        library.control.zoom({ position: 'topleft', zoomInTitle: 'بزرگ‌نمایی', zoomOutTitle: 'کوچک‌نمایی' }).addTo(map);
        const tiles = library.tileLayer(config.tileUrl, { attribution: config.attribution, maxZoom: config.maxZoom, noWrap: true });
        tiles.on('loading', () => { if (ticket === generation) { loading.value = true; tilesReady.value = false; } });
        tiles.on('tileload', () => { if (ticket === generation) { tilesReady.value = true; loading.value = false; mapError.value = ''; clearTimeout(timeout); } });
        tiles.on('tileerror', () => { if (ticket === generation && !tilesReady.value) { loading.value = false; mapError.value = 'نقشه دریافت نشد. اتصال اینترنت را بررسی کنید و دوباره تلاش کنید.'; } });
        tiles.addTo(map);
        const center = previousCenter || (draft.value ? [draft.value.latitude, draft.value.longitude] as [number, number] : config.center);
        map.setView(center, previousZoom ?? (draft.value ? Math.min(16, config.maxZoom) : config.zoom));
        map.on('click', (event: LeafletMouseEvent) => { if (tilesReady.value) { const point = event.latlng.wrap(); setDraft(point.lat, point.lng); } });
        if (draft.value) placeMarker(draft.value);
        observer = new ResizeObserver(() => map?.invalidateSize({ pan: false })); observer.observe(container.value);
        timeout = setTimeout(() => { if (ticket === generation && !tilesReady.value) { loading.value = false; mapError.value = 'بارگذاری نقشه طول کشید. دوباره تلاش کنید.'; } }, 15000);
    } catch {
        if (ticket === generation && open.value) { loading.value = false; mapError.value = 'نقشه باز نشد. دوباره تلاش کنید.'; }
    }
};
const showPicker = async () => {
    if (props.disabled) return;
    opener.value?.focus({ preventScroll: true });
    draft.value = value.value ? { ...value.value } : null;
    tilesReady.value = false; loading.value = true; mapError.value = ''; locationError.value = ''; open.value = true;
    await nextTick(); await startMap();
};
const closePicker = () => { open.value = false; disposeMap(); };
const clearPoint = async () => { if (props.disabled) return; emit('update:modelValue', null); await nextTick(); opener.value?.focus({ preventScroll: true }); };
const confirm = () => {
    if (!draft.value || !tilesReady.value || props.disabled || locating.value) return;
    emit('update:modelValue', { ...draft.value }); closePicker();
};
const selectCenter = () => { if (tilesReady.value && map) { const center = map.getCenter().wrap(); setDraft(center.lat, center.lng); } };
const locate = () => {
    if (!map || locating.value) return;
    locationError.value = '';
    if (!navigator.geolocation) { locationError.value = 'مرورگر شما امکان دریافت موقعیت فعلی را ندارد.'; return; }
    locating.value = true; const ticket = generation, geoTicket = ++geoRequest;
    navigator.geolocation.getCurrentPosition(position => {
        if (ticket !== generation || geoTicket !== geoRequest || !open.value || !map) return;
        locating.value = false; setDraft(position.coords.latitude, position.coords.longitude);
        map.setView([position.coords.latitude, position.coords.longitude], Math.min(17, map.getMaxZoom()));
    }, error => {
        if (ticket !== generation || geoTicket !== geoRequest || !open.value) return;
        locating.value = false; locationError.value = error.code === 1 ? 'دسترسی به موقعیت فعلی مجاز نیست. نقطه را روی نقشه انتخاب کنید.' : 'موقعیت فعلی دریافت نشد. دوباره تلاش کنید یا نقطه را روی نقشه انتخاب کنید.';
    }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 });
};
onBeforeUnmount(disposeMap);
</script>

<template>
    <div class="location-picker">
        <div class="location-picker__label">{{ label }}</div>
        <div class="location-picker__control" :class="{ 'has-error': error }">
            <button ref="opener" type="button" class="location-picker__open" :disabled="disabled" :aria-describedby="error ? hintId : undefined" :aria-invalid="Boolean(error)" data-location-open @click="showPicker">
                <svg width="25" height="25" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20 10c0 6-8 11-8 11S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                <span><strong>{{ value ? 'موقعیت انتخاب شده است' : 'انتخاب موقعیت روی نقشه' }}</strong><small v-if="value" dir="ltr">{{ coordinateText(value) }}</small><small v-else>نقطه موردنظر را روی نقشه مشخص کنید.</small></span>
                <span class="location-picker__action">{{ value ? 'تغییر' : 'انتخاب' }}</span>
            </button>
            <button v-if="value && clearable" type="button" class="location-picker__clear" :disabled="disabled" aria-label="حذف موقعیت انتخاب‌شده" @click="clearPoint">حذف</button>
        </div>
        <small v-if="error" :id="hintId" class="location-picker__error" role="alert">{{ error }}</small>
        <UiDrawer :open="open" :title="label" presentation="dialog" @close="closePicker">
            <div class="location-dialog">
                <p :id="hintId + '-instructions'" class="location-dialog__hint">روی نقشه کلیک کنید؛ برای تنظیم دقیق‌تر، نشانگر را جابه‌جا کنید.</p>
                <div class="location-dialog__tools"><button type="button" class="crud-button crud-button--soft" :disabled="locating" data-location-locate @click="locate">{{ locating ? 'در حال دریافت موقعیت…' : 'موقعیت فعلی من' }}</button><button type="button" class="crud-button crud-button--soft" :disabled="!tilesReady" data-location-center @click="selectCenter">انتخاب مرکز نقشه</button></div>
                <div class="location-dialog__map-wrap" :aria-busy="loading">
                    <div ref="container" class="location-dialog__map" role="region" aria-label="نقشه انتخاب موقعیت؛ جابه‌جایی با کلیدهای جهت و انتخاب با دکمه انتخاب مرکز نقشه" :aria-describedby="hintId + '-instructions'" tabindex="0" @keydown.enter.self.prevent="selectCenter"></div>
                    <div v-if="loading && !tilesReady" class="location-dialog__loading" role="status">در حال بارگذاری نقشه…</div>
                </div>
                <div v-if="mapError" class="location-dialog__error" role="alert"><span>{{ mapError }}</span><button type="button" class="crud-button crud-button--soft" data-location-retry @click="startMap">تلاش مجدد</button></div>
                <p v-if="locationError" class="location-picker__error" role="alert">{{ locationError }}</p>
                <p class="location-dialog__selection" role="status" aria-live="polite"><template v-if="draft">موقعیت انتخاب‌شده: <b dir="ltr">{{ coordinateText(draft) }}</b></template><template v-else>هنوز نقطه‌ای انتخاب نشده است.</template></p>
            </div>
            <template #footer><button type="button" class="crud-button crud-button--soft" data-location-cancel @click="closePicker">انصراف</button><button type="button" class="crud-button crud-button--primary" :disabled="!draft || !tilesReady || locating || disabled" data-location-confirm @click="confirm">تأیید موقعیت</button></template>
        </UiDrawer>
    </div>
</template>
