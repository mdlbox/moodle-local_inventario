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
 * Reservation details modal on the reservations list page.
 *
 * @module     local_inventario/reslistmodal
 * @copyright  2026 mdlbox - https://mdlbox.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'], function($) {
    return {
        /**
         * @param {String} defaulttitle Fallback modal title.
         */
        init: function(defaulttitle) {
            $(document).on('click', '.inventario-res-info', function(e) {
                e.preventDefault();
                var targetId = $(this).data('detail-id');
                var title = $(this).data('detail-title') || '';
                var content = $('#' + targetId).html() || '';
                $('#inventario-res-modal-title').text(title || defaulttitle);
                $('#inventario-res-modal-body').html(content);
                $('#inventario-res-modal').modal('show');
            });
        }
    };
});
