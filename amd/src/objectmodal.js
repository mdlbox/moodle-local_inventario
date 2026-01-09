/**
 * Object details modal handler for local_inventario.
 *
 * @module     local_inventario/objectmodal
 * @copyright  2025 mdlbox - https://app.mdlbox.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/modal_factory', 'core/modal_events', 'core/notification'], function($, ModalFactory, ModalEvents, Notification) {
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
