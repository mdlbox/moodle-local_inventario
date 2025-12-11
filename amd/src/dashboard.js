/**
 * Dashboard interactions for local_inventario.
 *
 * @module     local_inventario/dashboard
 * @copyright 2025 mdlbox - https://app.mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/notification', 'core/toast', 'core/str'], function(Notification, Toast, str) {
    let unknownError = '';

    /**
     * Execute AJAX toggle request.
     *
     * @param {HTMLElement} trigger
     * @param {Object} config
     */
    const doToggle = (trigger, config) => {
        const id = trigger.dataset.id;
        const visible = trigger.dataset.visible === '1' ? 0 : 1;

        const body = new URLSearchParams();
        body.append('action', 'togglevisibility');
        body.append('id', id);
        body.append('visible', visible);
        body.append('sesskey', config.sesskey);

        fetch(config.toggleurl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body,
        })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.error || unknownError);
                }
                trigger.dataset.visible = visible.toString();
                trigger.innerText = visible ? trigger.dataset.hideLabel : trigger.dataset.showLabel;
                Toast.add({message: trigger.dataset.notice});
            })
            .catch(Notification.exception);
    };

    /**
     * Toggle visibility via modal confirmation + AJAX.
     * @param {HTMLElement} trigger
     * @param {Object} config
     */
    const handleToggle = (trigger, config) => {
        Notification.confirm(
            trigger.dataset.confirmTitle,
            trigger.dataset.confirmMessage,
            trigger.dataset.confirmYes,
            trigger.dataset.confirmNo,
            () => doToggle(trigger, config)
        );
    };

    /**
     * Init module.
     * @param {Object} config
     */
    const init = config => {
        str.get_string('unknownerror', 'local_inventario')
            .then(message => {
                unknownError = message;
                document.querySelectorAll('[data-inventario-toggle]').forEach(trigger => {
                    trigger.addEventListener('click', e => {
                        e.preventDefault();
                        handleToggle(trigger, config);
                    });
                });
            })
            .catch(Notification.exception);
    };

    return {init};
});
