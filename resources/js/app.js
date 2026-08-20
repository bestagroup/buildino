import DataTable from 'datatables.net-bs5';
import 'datatables.net-responsive-bs5';
import 'datatables.net-bs5/css/dataTables.bootstrap5.css';
import 'datatables.net-responsive-bs5/css/responsive.bootstrap5.css';
import './buildino-select2';
import './buildino-jalali-datepicker';

/*
 * Keep the DataTables runtime local to the application bundle. Management and
 * portal pages must not stay in a permanent loading state when a third-party
 * CDN is unavailable in the browser.
 */
window.DataTable = DataTable;

window.dispatchEvent(new CustomEvent('buildino:vite-ready'));
window.dispatchEvent(new CustomEvent('buildino:datatables-ready'));
