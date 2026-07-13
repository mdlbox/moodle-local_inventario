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
 * Expand/collapse of type rows and their object panels on the types page.
 *
 * @module     local_inventario/types
 * @copyright  2026 mdlbox - https://mdlbox.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    return {
        /**
         * Wire the type rows and object toggles.
         */
        init: function() {
            var typeRows = document.querySelectorAll('.inventario-type-row');
            var detailRows = document.querySelectorAll('.inventario-type-objects');

            typeRows.forEach(function(row) {
                row.addEventListener('click', function() {
                    var tid = row.dataset.typeid;
                    var target = document.querySelector('.inventario-type-objects[data-typeid="' + tid + '"]');
                    var isOpen = target && target.style.display === 'table-row';

                    detailRows.forEach(function(r) {
                        r.style.display = 'none';
                    });
                    typeRows.forEach(function(r) {
                        r.classList.remove('open');
                    });

                    if (!isOpen && target) {
                        target.style.display = 'table-row';
                        row.classList.add('open');
                    }
                });
            });

            document.querySelectorAll('.inventario-object-toggle').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    var panel = document.getElementById(btn.dataset.target);
                    if (!panel) {
                        return;
                    }
                    var isVisible = panel.style.display !== 'none';
                    panel.style.display = isVisible ? 'none' : 'block';
                });
            });
        }
    };
});
