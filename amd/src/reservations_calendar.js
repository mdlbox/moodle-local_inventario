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
 * Week/day calendar grid with drag-to-select and multi-object reservation.
 *
 * @module     local_inventario/reservations_calendar
 * @copyright  2026 mdlbox - https://mdlbox.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/notification'], function(Ajax, Notification) {

    var SLOT_PX = 30;

    var pad = function(n) {
        return (n < 10 ? '0' : '') + n;
    };

    var minToLabel = function(min) {
        return pad(Math.floor(min / 60)) + ':' + pad(min % 60);
    };

    var tsToTime = function(ts) {
        var d = new Date(ts * 1000);
        return pad(d.getHours()) + ':' + pad(d.getMinutes());
    };

    var el = function(tag, cls, text) {
        var node = document.createElement(tag);
        if (cls) {
            node.className = cls;
        }
        if (text !== undefined && text !== null) {
            node.textContent = text;
        }
        return node;
    };

    /**
     * Calendar controller.
     *
     * @param {HTMLElement} root
     * @param {Object} cfg
     */
    var Calendar = function(root, cfg) {
        this.root = root;
        this.cfg = cfg;
        this.gridStartMin = cfg.daystart * 60;
        this.gridEndMin = cfg.dayend * 60;
        this.slot = cfg.slot;
        this.slotCount = Math.ceil((this.gridEndMin - this.gridStartMin) / this.slot);
        this.dragging = false;
        this.dragDay = null;
        this.dragFrom = null;
        this.dragTo = null;
        this.dayCols = [];
    };

    Calendar.prototype.render = function() {
        var self = this;
        this.root.innerHTML = '';
        var wrap = el('div', 'inventario-cal inventario-cal-' + this.cfg.view);

        // Header row: empty time corner + one cell per day.
        var head = el('div', 'inventario-cal-head');
        head.appendChild(el('div', 'inventario-cal-timecol-head'));
        this.cfg.days.forEach(function(day, idx) {
            var h = el('div', 'inventario-cal-dayhead' + (day.istoday ? ' today' : ''), day.label);
            h.setAttribute('data-day', idx);
            head.appendChild(h);
        });
        wrap.appendChild(head);

        // Body: time labels column + day columns with slots.
        var body = el('div', 'inventario-cal-body');
        var timecol = el('div', 'inventario-cal-timecol');
        for (var s = 0; s < this.slotCount; s++) {
            var min = this.gridStartMin + s * this.slot;
            var lbl = el('div', 'inventario-cal-timelabel', minToLabel(min));
            lbl.style.height = SLOT_PX + 'px';
            timecol.appendChild(lbl);
        }
        body.appendChild(timecol);

        this.dayCols = [];
        this.cfg.days.forEach(function(day, idx) {
            var col = el('div', 'inventario-cal-daycol');
            col.setAttribute('data-day', idx);
            col.style.height = (self.slotCount * SLOT_PX) + 'px';
            for (var s2 = 0; s2 < self.slotCount; s2++) {
                var min2 = self.gridStartMin + s2 * self.slot;
                var cell = el('div', 'inventario-cal-slot');
                cell.style.height = SLOT_PX + 'px';
                cell.setAttribute('data-day', idx);
                cell.setAttribute('data-slot', s2);
                cell.setAttribute('data-min', min2);
                col.appendChild(cell);
            }
            body.appendChild(col);
            self.dayCols.push(col);
        });
        wrap.appendChild(body);
        this.root.appendChild(wrap);

        this.renderReservations();
        this.attach();
    };

    Calendar.prototype.renderReservations = function() {
        var self = this;
        this.cfg.reservations.forEach(function(res) {
            self.cfg.days.forEach(function(day, idx) {
                var dayGridStart = day.ts + self.gridStartMin * 60;
                var dayGridEnd = day.ts + self.gridEndMin * 60;
                var start = Math.max(res.start, dayGridStart);
                var end = Math.min(res.end, dayGridEnd);
                if (end <= start) {
                    return;
                }
                var topMin = (start - dayGridStart) / 60;
                var durMin = (end - start) / 60;
                var block = el('div', 'inventario-cal-resblock');
                block.style.top = (topMin / self.slot * SLOT_PX) + 'px';
                block.style.height = Math.max(SLOT_PX - 2, durMin / self.slot * SLOT_PX) + 'px';
                block.style.background = res.color;
                block.appendChild(el('div', 'inventario-cal-resblock-title', res.name));
                block.appendChild(el('div', 'inventario-cal-resblock-meta',
                    tsToTime(res.start) + '-' + tsToTime(res.end) + ' · ' + res.user));
                block.addEventListener('mousedown', function(e) {
                    e.stopPropagation();
                });
                block.addEventListener('click', function() {
                    window.location = self.cfg.reserveurl + '?id=' + res.id;
                });
                self.dayCols[idx].appendChild(block);
            });
        });
    };

    Calendar.prototype.attach = function() {
        var self = this;

        this.root.addEventListener('mousedown', function(e) {
            var slot = e.target.closest('.inventario-cal-slot');
            if (!slot) {
                return;
            }
            e.preventDefault();
            self.dragging = true;
            self.dragDay = parseInt(slot.getAttribute('data-day'), 10);
            self.dragFrom = parseInt(slot.getAttribute('data-slot'), 10);
            self.dragTo = self.dragFrom;
            self.paint();
        });

        this.root.addEventListener('mouseover', function(e) {
            if (!self.dragging) {
                return;
            }
            var slot = e.target.closest('.inventario-cal-slot');
            if (!slot) {
                return;
            }
            if (parseInt(slot.getAttribute('data-day'), 10) !== self.dragDay) {
                return;
            }
            self.dragTo = parseInt(slot.getAttribute('data-slot'), 10);
            self.paint();
        });

        document.addEventListener('mouseup', function() {
            if (!self.dragging) {
                return;
            }
            self.dragging = false;
            self.finishSelection();
        });
    };

    Calendar.prototype.paint = function() {
        var lo = Math.min(this.dragFrom, this.dragTo);
        var hi = Math.max(this.dragFrom, this.dragTo);
        var slots = this.root.querySelectorAll('.inventario-cal-slot');
        slots.forEach(function(s) {
            var day = parseInt(s.getAttribute('data-day'), 10);
            var idx = parseInt(s.getAttribute('data-slot'), 10);
            if (day === this.dragDay && idx >= lo && idx <= hi) {
                s.classList.add('selecting');
            } else {
                s.classList.remove('selecting');
            }
        }, this);
    };

    Calendar.prototype.clearPaint = function() {
        this.root.querySelectorAll('.inventario-cal-slot.selecting').forEach(function(s) {
            s.classList.remove('selecting');
        });
    };

    Calendar.prototype.finishSelection = function() {
        var lo = Math.min(this.dragFrom, this.dragTo);
        var hi = Math.max(this.dragFrom, this.dragTo);
        var day = this.cfg.days[this.dragDay];
        var startMin = this.gridStartMin + lo * this.slot;
        var endMin = this.gridStartMin + (hi + 1) * this.slot;
        var startTs = day.ts + startMin * 60;
        var endTs = day.ts + endMin * 60;
        this.clearPaint();
        this.openModal(startTs, endTs, startMin, endMin);
    };

    Calendar.prototype.parseSlots = function(raw) {
        var slots = [];
        if (!raw) {
            return slots;
        }
        raw.split(/\r\n|\r|\n/).forEach(function(line) {
            var m = line.trim().match(/^([0-2][0-9]):([0-5][0-9])-([0-2][0-9]):([0-5][0-9])$/);
            if (m) {
                slots.push({
                    start: parseInt(m[1], 10) * 60 + parseInt(m[2], 10),
                    end: parseInt(m[3], 10) * 60 + parseInt(m[4], 10)
                });
            }
        });
        return slots;
    };

    /**
     * Mirror of the server availability check so unavailable objects are hidden.
     *
     * @param {Object} obj
     * @param {Number} startTs
     * @param {Number} endTs
     * @param {Number} startMin Minutes from midnight of the selection start.
     * @param {Number} endMin Minutes from midnight of the selection end.
     * @return {Boolean}
     */
    Calendar.prototype.objAvailable = function(obj, startTs, endTs, startMin, endMin) {
        if (!obj.availenabled) {
            return true;
        }
        if (obj.availfrom > 0 && startTs < obj.availfrom) {
            return false;
        }
        if (obj.availto > 0 && endTs > obj.availto) {
            return false;
        }
        var slots = this.parseSlots(obj.availtimes);
        if (slots.length) {
            var inside = slots.some(function(sl) {
                return startMin >= sl.start && endMin <= sl.end;
            });
            if (!inside) {
                return false;
            }
        }
        return true;
    };

    Calendar.prototype.hasOverlap = function(objectid, startTs, endTs) {
        return this.cfg.reservations.some(function(res) {
            return res.objectid === objectid && res.start < endTs && res.end > startTs;
        });
    };

    Calendar.prototype.openModal = function(startTs, endTs, startMin, endMin) {
        var self = this;
        var cfg = this.cfg;
        var s = cfg.strings;

        // Only objects actually reservable in the selected range are offered.
        var available = cfg.objects.filter(function(obj) {
            return self.objAvailable(obj, startTs, endTs, startMin, endMin) &&
                !self.hasOverlap(obj.id, startTs, endTs);
        });

        var overlay = el('div', 'inventario-cal-modal-overlay');
        var modal = el('div', 'inventario-cal-modal');
        modal.appendChild(el('h4', 'inventario-cal-modal-title', s.newreservation));

        var period = tsToTime(startTs) + ' - ' + tsToTime(endTs);
        modal.appendChild(el('div', 'inventario-cal-modal-period', s.period + ': ' + period));

        var errBox = el('div', 'inventario-cal-modal-error');
        errBox.style.display = 'none';
        modal.appendChild(errBox);

        // Object search + checkbox list.
        modal.appendChild(el('label', 'inventario-cal-modal-label', s.selectobjects));
        var search = el('input', 'form-control mb-2');
        search.type = 'text';
        search.placeholder = s.searchobjects;
        modal.appendChild(search);

        var list = el('div', 'inventario-cal-objlist');
        if (!available.length) {
            list.appendChild(el('div', 'text-muted small', s.noobjects));
        }
        available.forEach(function(obj) {
            var item = el('label', 'inventario-cal-objitem');
            item.setAttribute('data-name', obj.name.toLowerCase());
            var cb = el('input');
            cb.type = 'checkbox';
            cb.value = obj.id;
            cb.setAttribute('data-requireslocation', obj.requireslocation);
            item.appendChild(cb);
            item.appendChild(el('span', '', ' ' + obj.name));
            list.appendChild(item);
        });
        modal.appendChild(list);

        search.addEventListener('input', function() {
            var q = search.value.toLowerCase();
            list.querySelectorAll('.inventario-cal-objitem').forEach(function(it) {
                it.style.display = it.getAttribute('data-name').indexOf(q) !== -1 ? '' : 'none';
            });
        });

        // Location field.
        modal.appendChild(el('label', 'inventario-cal-modal-label', s.location));
        var locInput = el('input', 'form-control mb-1');
        locInput.type = 'text';
        modal.appendChild(locInput);
        var locNote = el('div', 'text-muted small mb-2', s.locationrequired);
        locNote.style.display = 'none';
        modal.appendChild(locNote);

        list.addEventListener('change', function() {
            var needs = false;
            list.querySelectorAll('input[type=checkbox]:checked').forEach(function(cb) {
                if (parseInt(cb.getAttribute('data-requireslocation'), 10) === 1) {
                    needs = true;
                }
            });
            locNote.style.display = needs ? '' : 'none';
        });

        // User select (managers only).
        var userSelect = null;
        if (cfg.canmanageall && cfg.users.length) {
            modal.appendChild(el('label', 'inventario-cal-modal-label', s.user));
            userSelect = el('select', 'form-control mb-2');
            var optSelf = el('option', '', '—');
            optSelf.value = '0';
            userSelect.appendChild(optSelf);
            cfg.users.forEach(function(u) {
                var o = el('option', '', u.name);
                o.value = u.id;
                userSelect.appendChild(o);
            });
            modal.appendChild(userSelect);
        }

        // Buttons.
        var actions = el('div', 'inventario-cal-modal-actions');
        var cancelBtn = el('button', 'btn btn-secondary', s.cancel);
        cancelBtn.type = 'button';
        var createBtn = el('button', 'btn btn-primary', s.create);
        createBtn.type = 'button';
        actions.appendChild(cancelBtn);
        actions.appendChild(createBtn);
        modal.appendChild(actions);

        overlay.appendChild(modal);
        document.body.appendChild(overlay);

        var needsReload = false;
        var close = function() {
            if (needsReload) {
                window.location.reload();
                return;
            }
            if (overlay.parentNode) {
                overlay.parentNode.removeChild(overlay);
            }
        };
        cancelBtn.addEventListener('click', close);
        overlay.addEventListener('mousedown', function(e) {
            if (e.target === overlay) {
                close();
            }
        });

        var showError = function(nodes) {
            errBox.innerHTML = '';
            nodes.forEach(function(n) {
                errBox.appendChild(n);
            });
            errBox.style.display = '';
        };

        createBtn.addEventListener('click', function() {
            var ids = [];
            list.querySelectorAll('input[type=checkbox]:checked').forEach(function(cb) {
                ids.push(parseInt(cb.value, 10));
            });
            if (!ids.length) {
                showError([el('div', '', s.nothingselected)]);
                return;
            }
            createBtn.disabled = true;
            errBox.style.display = 'none';
            var args = {
                objectids: ids,
                timestart: startTs,
                timeend: endTs,
                location: locInput.value,
                siteid: 0,
                userid: userSelect ? parseInt(userSelect.value, 10) : 0
            };
            Ajax.call([{
                methodname: 'local_inventario_create_reservations',
                args: args
            }])[0].then(function(result) {
                if (!result.errors || !result.errors.length) {
                    window.location.reload();
                    return null;
                }
                // Keep the dialog open and explain, per object, why it failed.
                needsReload = result.created > 0;
                var nodes = [];
                if (result.created > 0) {
                    nodes.push(el('div', 'inventario-cal-modal-error-ok',
                        s.created.replace('{$a}', result.created)));
                }
                nodes.push(el('div', 'fw-bold', s.conflicts));
                var ul = el('ul', 'mb-0');
                result.errors.forEach(function(err) {
                    ul.appendChild(el('li', '', self.objectName(err.objectid) + ': ' + err.message));
                });
                nodes.push(ul);
                showError(nodes);
                createBtn.disabled = false;
                return null;
            }).catch(function(error) {
                createBtn.disabled = false;
                Notification.exception(error);
            });
        });
    };

    Calendar.prototype.objectName = function(id) {
        var match = this.cfg.objects.filter(function(o) {
            return o.id === id;
        });
        return match.length ? match[0].name : ('#' + id);
    };

    return {
        /**
         * Initialise the calendar grid.
         *
         * @param {Object} config
         */
        init: function(config) {
            var root = document.getElementById('inventario-cal-grid');
            if (!root) {
                return;
            }
            var cal = new Calendar(root, config);
            cal.render();
        }
    };
});
