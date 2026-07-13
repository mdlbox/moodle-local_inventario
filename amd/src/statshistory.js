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
 * Expand/collapse of extra reservation history rows on the stats page.
 *
 * @module     local_inventario/statshistory
 * @copyright  2026 mdlbox - https://mdlbox.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    return {
        init: function() {
            document.querySelectorAll('.inventario-history-toggle').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    var target = document.getElementById(btn.dataset.target);
                    if (!target) {
                        return;
                    }
                    var extras = target.querySelectorAll('.inventario-history-extra');
                    if (!extras.length) {
                        return;
                    }
                    var hidden = extras[0].classList.contains('d-none');
                    extras.forEach(function(el) {
                        el.classList.toggle('d-none', !hidden);
                    });
                    btn.textContent = hidden ? btn.dataset.less : btn.dataset.more;
                });
            });
        }
    };
});
