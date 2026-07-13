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
 * "Show more/less" toggle for day cells in the month calendar view.
 *
 * @module     local_inventario/calendarmonth
 * @copyright  2026 mdlbox - https://mdlbox.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    return {
        /**
         * @param {String} showlessLabel Localised "show less" label.
         */
        init: function(showlessLabel) {
            document.addEventListener('click', function(e) {
                var btn = e.target.closest('.inventario-calendar-showmore');
                if (!btn) {
                    return;
                }
                e.preventDefault();
                var cell = btn.closest('.inventario-calendar-cell');
                if (!cell) {
                    return;
                }
                var expanded = cell.classList.toggle('expanded');
                if (!btn.dataset.label) {
                    btn.dataset.label = btn.textContent;
                }
                btn.textContent = expanded ? showlessLabel : btn.dataset.label;
            });
        }
    };
});
