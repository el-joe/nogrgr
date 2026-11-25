/**
 * jQuery-based libraries used by the panels (Select2, DataTables).
 * jQuery + Select2 come from core/select2-lib.js (shared with the central website).
 */
import { $ } from '../core/select2-lib.js';
import DataTable from 'datatables.net-dt';
import 'datatables.net-dt/css/dataTables.dataTables.css';
import 'datatables.net-responsive-dt';
import 'datatables.net-responsive-dt/css/responsive.dataTables.css';

export { $, DataTable };
