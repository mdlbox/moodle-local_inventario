/**
 * This file is part of Moodle - http://moodle.org/
 *
 * Moodle is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Moodle is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
 */
/**
 * Objects page interactions.
 *
 * @module     local_inventario/objects
 * @copyright  2025 mdlbox - https://app.mdlbox.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    const buildUrl = (baseurl, params) => {
        const hasQuery = baseurl.indexOf('?') !== -1;
        const prefix = hasQuery ? baseurl + '&' : baseurl + '?';
        return prefix + params.join('&');
    };

    const init = (baseurl, recordid) => {
        const select = document.querySelector('.inventario-type-select');
        if (!select) {
            return;
        }
        select.addEventListener('change', () => {
            const v = select.value;
            if (!v) {
                return;
            }
            const params = [`typeid=${encodeURIComponent(v)}`];
            if (recordid) {
                params.push(`id=${encodeURIComponent(recordid)}`);
            }
            window.location = buildUrl(baseurl, params);
        });
    };
    return {init};
});
