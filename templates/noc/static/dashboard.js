document.addEventListener('DOMContentLoaded', function () {
    initialiseDrawers();
    initialisePullToRefresh();
});

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

function initialisePullToRefresh() {
    var startY = 0;
    var pulling = false;
    var shouldRefresh = false;

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

        if (deltaY > 80) {
            shouldRefresh = true;
        }
    }, { passive: true });

    document.addEventListener('touchend', function () {
        if (pulling && shouldRefresh) {
            location.reload();
        }

        pulling = false;
        shouldRefresh = false;
    });
}