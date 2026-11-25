/**
 * jQuery + Select2 only (no DataTables), so the central website can use the same
 * Select2 setup as the panels. jQuery is exposed on window for Select2 plugins only;
 * new code should use vanilla JS / Alpine.
 */
import $ from 'jquery';
import select2Factory from 'select2';
import 'select2/dist/css/select2.css';

window.$ = window.jQuery = $;
select2Factory(window, $);

export { $ };
