/**
 * Reservations page interactions: filter objects by site and warn on unreturned objects.
 *
 * @module local_inventario/reservations
 */
define([], function() {
    const init = (baseurl, recordid, unreturnedIds) => {
        const siteSelect = document.querySelector('select[name="siteid"]');
        if (!siteSelect) {
            return;
        }
        const objectSelect = document.querySelector('select[name="objectid"]');
        const warning = document.querySelector('.local-inventario-unreturned-warning');

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
        if (objectSelect) {
            objectSelect.addEventListener('change', toggleWarning);
            toggleWarning();
        }
    };
    return {init};
});
