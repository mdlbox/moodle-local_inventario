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
 * Object details modal handler for local_inventario.
 *
 * @module     local_inventario/objectmodal
 * @copyright  2025 mdlbox - https://app.mdlbox.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'jquery',
    'core/modal_factory',
    'core/modal_events',
    'core/notification',
], function($, ModalFactory, ModalEvents, Notification) {
    /**
     * Init modal binding using Moodle ModalFactory.
     */
    const init = () => {
        let modalPromise = null;

        $(document).on('click', '.inventario-object-info', function(e) {
            e.preventDefault();
            const targetId = $(this).data('detail-id');
            const title = $(this).data('detail-title') || '';
            const content = $('#' + targetId).html() || '';

            if (!modalPromise) {
                modalPromise = ModalFactory.create({
                    type: ModalFactory.types.DEFAULT,
                    title: title || '',
                    body: content || '',
                });
            }

            modalPromise.then(modal => {
                modal.setTitle(title || '');
                modal.setBody(content || '');
                modal.getRoot().on(ModalEvents.hidden, () => {
                    modal.setBody('');
                });
                modal.show();
                return modal;
            }).catch(Notification.exception);
        });
    };

    return {init};
});
