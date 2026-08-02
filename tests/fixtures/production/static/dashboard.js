document.addEventListener('DOMContentLoaded', function () {
    initialiseDrawers();
    initialisePullToRefresh();
    initialiseDesktopAutoRefresh();
});

//Constants
var DESKTOP_AUTO_REFRESH_MS = 60 * 1000;

// Drawers are associated with layout rows rather than cards so the
// same mechanism works for both single-column (mobile) and multi-column
// (desktop) layouts.
function initialiseDrawers() {
    var handles = document.querySelectorAll('[data-telemetry-toggle]');
    var openCardId = null;
    var drawer = document.createElement('div');

    drawer.className = 'telemetry-panel';
    drawer.classList.remove('open');

    handles.forEach(function (handle) {
        handle.addEventListener('click', function () {
            var cardId = handle.getAttribute('data-telemetry-toggle');
            var template = document.getElementById(cardId + '-telemetry-template');
            var cardSlot = handle.closest('.card-slot');

            if (!template || !cardSlot) {
                return;
            }

            if (openCardId === cardId && drawer.classList.contains('open')) {
                drawer.classList.remove('open');
                openCardId = null;
                return;
            }

            drawer.classList.remove('open');
            drawer.innerHTML = '';
            drawer.appendChild(template.content.cloneNode(true));

            if (window.matchMedia('(min-width: 800px)').matches) {
                var lastInRow = findLastCardSlotInSameRow(cardSlot);
                var row = lastInRow.parentNode;
                var dashboard = row.parentNode;

                dashboard.insertBefore(drawer, row.nextSibling);
            } else {
                cardSlot.parentNode.insertBefore(drawer, cardSlot.nextSibling);
            }

            if (window.matchMedia('(min-width: 800px)').matches) {
                var rect = handle.getBoundingClientRect();
                var drawerRect = drawer.getBoundingClientRect();

                drawer.style.setProperty(
                    '--pointer-x',
                    (rect.left + rect.width / 2 - drawerRect.left) + 'px'
                );
            } else {
                drawer.style.removeProperty('--pointer-x');
            }

            // Let the browser render the closed state before opening,
            // so the transition actually runs.
            window.requestAnimationFrame(function () {
            drawer.classList.add('open');
            });

            openCardId = cardId;
        });
    });
}

//
// Given the supplied card slot, find the last currently displayed card slot in the same row
// Note that in the case of a PWA this will be the same object as the supplied argument, since
// every row only contains a single card slot.
//
function findLastCardSlotInSameRow(cardSlot) {
    var slots = Array.prototype.slice.call(document.querySelectorAll('.card-slot'));
    var rowTop = Math.round(cardSlot.getBoundingClientRect().top);
    var lastInRow = cardSlot;

    for (var i = 0; i < slots.length; i++) {
        var slotTop = Math.round(slots[i].getBoundingClientRect().top);

        if (slotTop === rowTop) {
            lastInRow = slots[i];
        }
    }

    return lastInRow;
}

//
// Add a "pull to refresh" gesture to the desktop assuming it's running as a PWA on iPhone
//
function initialisePullToRefresh() {
    var startY = 0;
    var pulling = false;
    var shouldRefresh = false;
    var indicator = document.getElementById('refresh-indicator');

    document.addEventListener('touchstart', function (event) {
        if (window.scrollY <= 0) {
            startY = event.touches[0].clientY;
            pulling = true;
            shouldRefresh = false;
        }
    }, { passive: true });

    document.addEventListener('touchmove', function (event) {
        if (!pulling) {
            return;
        }

        var deltaY = event.touches[0].clientY - startY;

        var offset = Math.min(deltaY * 0.4, 40);

        if (deltaY > 80) {
            shouldRefresh = true;

            if (indicator) {
                indicator.style.setProperty(
                    '--pull-offset',
                    offset + 'px'
                );
                indicator.classList.add('pulling');
                indicator.classList.add('visible');
            }
        } else {
            shouldRefresh = false;

            if (indicator) {
                indicator.classList.remove('visible');
            }
        }
    }, { passive: true });

    document.addEventListener('touchend', function () {
        if (pulling && shouldRefresh) {
            if (indicator) {
                indicator.style.removeProperty('--pull-offset');
                indicator.classList.remove('pulling');
                indicator.classList.remove('visible');
            }

            window.setTimeout(function () {
                location.reload();
            }, 180);
        }
        shouldRefresh = false;
        pulling = false;
    });
}
//
// If the dashboard is in desktop mode, cause it to refresh every DESKTOP_AUTO_REFRESH_MS milliseconds
//
function initialiseDesktopAutoRefresh() {
    var desktopQuery = window.matchMedia('(min-width: 800px)');

    if (!desktopQuery.matches) {
        return;
    }

    window.setTimeout(function () {
        location.reload();
    }, DESKTOP_AUTO_REFRESH_MS);
}