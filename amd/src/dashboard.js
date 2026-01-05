/**
 * Dashboard interactions for local_inventario.
 *
 * @module     local_inventario/dashboard
 * @copyright 2025 mdlbox - https://app.mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/notification', 'core/toast', 'core/str'], function(Ajax, Notification, Toast, str) {
    let unknownError = '';

    /**
     * Execute AJAX toggle request.
     *
     * @param {HTMLElement} trigger
     */
    const doToggle = (trigger) => {
        const id = parseInt(trigger.dataset.id, 10);
        const visible = trigger.dataset.visible === '1' ? 0 : 1;

        Ajax.call([{
            methodname: 'local_inventario_toggle_visibility',
            args: {
                id: id,
                visible: !!visible,
            },
        }])[0]
            .then(() => {
                trigger.dataset.visible = visible.toString();
                trigger.innerText = visible ? trigger.dataset.hideLabel : trigger.dataset.showLabel;
                Toast.add({message: trigger.dataset.notice});
            })
            .catch(Notification.exception);
    };

    /**
     * Toggle visibility via modal confirmation + AJAX.
     * @param {HTMLElement} trigger
     */
    const handleToggle = (trigger) => {
        Notification.confirm(
            trigger.dataset.confirmTitle,
            trigger.dataset.confirmMessage,
            trigger.dataset.confirmYes,
            trigger.dataset.confirmNo,
            () => doToggle(trigger)
        );
    };

    /**
     * Init module.
     */
    const init = () => {
        str.get_string('unknownerror', 'local_inventario')
            .then(message => {
                unknownError = message;
                document.querySelectorAll('[data-inventario-toggle]').forEach(trigger => {
                    trigger.addEventListener('click', e => {
                        e.preventDefault();
                        handleToggle(trigger);
                    });
                });
            })
            .catch(Notification.exception);
    };

    return {init};
});
