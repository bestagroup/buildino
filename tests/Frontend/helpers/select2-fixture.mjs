import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { join } from 'node:path';

const project = fileURLToPath(new URL('../../../', import.meta.url));
const read = path => readFileSync(join(project, path), 'utf8');

// Real shared adapter and plugin for Blade fixtures that do not load Vite.
export const select2Fixture = () => `<style>${read('node_modules/select2/dist/css/select2.css')}\n${read('resources/css/buildino-select2.css')}</style>
<script>window.requestAnimationFrame = callback => setTimeout(() => callback(performance.now()), 16); window.cancelAnimationFrame = clearTimeout;</script>
<script>${read('node_modules/jquery/dist/jquery.js')}</script>
<script>${read('node_modules/select2/dist/js/select2.full.js')}</script>
<script>(()=>{const $=window.jQuery;${read('resources/js/buildino-select2.js').replace(/^import[^\n]*\n/gm, '')}})();</script>`;
