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
