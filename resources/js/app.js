/*
 * Vite entry point for future bundled modules.
 * Runtime UI behavior currently lives in public/js/buildino-*.js so existing
 * Blade layouts continue to work before and after a Vite build.
 */

window.dispatchEvent(new CustomEvent('buildino:vite-ready'));
