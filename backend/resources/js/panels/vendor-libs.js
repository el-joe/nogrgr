/**
 * jQuery-based libraries used by the panels (Select2, DataTables).
 * jQuery is exposed on window for Select2/DataTables plugins only; new code
 * should use vanilla JS / Alpine.
 */
import $ from 'jquery';
import select2Factory from 'select2';
import 'select2/dist/css/select2.css';
import DataTable from 'datatables.net-dt';
import 'datatables.net-dt/css/dataTables.dataTables.css';
import 'datatables.net-responsive-dt';
import 'datatables.net-responsive-dt/css/responsive.dataTables.css';

window.$ = window.jQuery = $;
select2Factory(window, $);

export { $, DataTable };
