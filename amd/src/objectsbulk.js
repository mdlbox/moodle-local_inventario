// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Bulk actions (select-all and confirmation) on the objects management page.
 *
 * @module     local_inventario/objectsbulk
 * @copyright  2026 mdlbox - https://mdlbox.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    return {
        /**
         * @param {Object} strings Localised messages: noselection, deleteconfirm.
         */
        init: function(strings) {
            var all = document.getElementById('inventario-selectall');
            if (all) {
                all.addEventListener('change', function() {
                    document.querySelectorAll('.inventario-bulkcb').forEach(function(cb) {
                        cb.checked = all.checked;
                    });
                });
            }
            var form = document.getElementById('inventario-bulk-form');
            if (!form) {
                return;
            }
            form.addEventListener('submit', function(e) {
                var action = form.querySelector('[name=bulkaction]').value;
                var any = form.querySelector('.inventario-bulkcb:checked');
                if (!action) {
                    e.preventDefault();
                    return;
                }
                if (!any) {
                    e.preventDefault();
                    window.alert(strings.noselection);
                    return;
                }
                if (action === 'delete' && !window.confirm(strings.deleteconfirm)) {
                    e.preventDefault();
                }
            });
        }
    };
});
