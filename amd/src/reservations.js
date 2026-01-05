/**
 * Reservations page interactions: filter objects by site, warn on unreturned objects,
 * and hide/show location based on type settings.
 *
 * @module     local_inventario/reservations
 * @copyright  2025 mdlbox - https://app.mdlbox.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
const init = (baseurl, recordid, unreturnedIds, objectMeta = {}) => {
    const siteSelect = document.querySelector('select[name="siteid"]');
    if (!siteSelect) {
        return;
    }
    const objectSelect = document.querySelector('select[name="objectid"]');
    const warning = document.querySelector('.local-inventario-unreturned-warning');
    const locationInput = document.querySelector('input[name="location"]');
    const locationWrapper = locationInput ? locationInput.closest('.fitem') : null;

        siteSelect.addEventListener('change', e => {
            e.preventDefault();
            const params = new URLSearchParams();
            if (recordid) {
                params.set('id', recordid);
            }
            params.set('siteid', siteSelect.value);
            window.location = baseurl.split('?')[0] + '?' + params.toString();
        });

        const toggleWarning = () => {
            if (!objectSelect || !warning) {
                return;
            }
            const val = parseInt(objectSelect.value, 10);
            if (unreturnedIds && unreturnedIds.includes(val)) {
                warning.classList.remove('hidden');
            } else {
                warning.classList.add('hidden');
            }
        };
        const toggleLocation = () => {
            if (!objectSelect || !locationInput || !locationWrapper) {
                return;
            }
            const val = parseInt(objectSelect.value, 10);
            const meta = objectMeta ? objectMeta[val] : null;
            const requiresLocation = meta && Object.prototype.hasOwnProperty.call(meta, 'requireslocation')
                ? !!meta.requireslocation
                : true;
            if (requiresLocation) {
                locationWrapper.classList.remove('d-none');
                locationWrapper.classList.remove('hidden');
                locationInput.required = true;
                locationInput.setAttribute('aria-required', 'true');
            } else {
                locationWrapper.classList.add('d-none');
                locationInput.required = false;
                locationInput.removeAttribute('aria-required');
                locationInput.value = '';
            }
        };
        if (objectSelect) {
            objectSelect.addEventListener('change', () => {
                toggleWarning();
                toggleLocation();
            });
            toggleWarning();
            toggleLocation();
        }
    };
    return {init};
});
