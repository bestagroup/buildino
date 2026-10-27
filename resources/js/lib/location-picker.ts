export interface MapPoint { latitude: number; longitude: number }
export interface MapConfiguration {
    tileUrl: string;
    attribution: string;
    center: [number, number];
    zoom: number;
    maxZoom: number;
}

export const asMapPoint = (latitude: unknown, longitude: unknown): MapPoint | null => {
    if ([latitude, longitude].some(value => !['string', 'number'].includes(typeof value) || (typeof value === 'string' && !value.trim()))) return null;
    const lat = Number(latitude), lng = Number(longitude);
    return Number.isFinite(lat) && Number.isFinite(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180
        ? { latitude: lat, longitude: lng } : null;
};

export const mapConfiguration = (): MapConfiguration => {
    const defaults: MapConfiguration = {
        tileUrl: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors',
        center: [32.4, 53.7], zoom: 5, maxZoom: 19,
    };
    try {
        const config = JSON.parse(document.getElementById('buildino-map-config')?.textContent || '{}');
        const center = Array.isArray(config.center) ? asMapPoint(config.center[0], config.center[1]) : null;
        return {
            ...defaults,
            tileUrl: typeof config.tileUrl === 'string' && config.tileUrl ? config.tileUrl : defaults.tileUrl,
            attribution: typeof config.attribution === 'string' ? config.attribution : defaults.attribution,
            center: center ? [center.latitude, center.longitude] : defaults.center,
            zoom: Number.isInteger(config.zoom) && config.zoom >= 2 && config.zoom <= 19 ? config.zoom : defaults.zoom,
            maxZoom: Number.isInteger(config.maxZoom) && config.maxZoom >= 2 && config.maxZoom <= 22 ? config.maxZoom : defaults.maxZoom,
        };
    } catch { return defaults; }
};

export const loadMapLibrary = async () => {
    const [library] = await Promise.all([import('leaflet'), import('leaflet/dist/leaflet.css')]);
    return library;
};
