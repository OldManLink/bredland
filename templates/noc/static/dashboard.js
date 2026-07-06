document.addEventListener('DOMContentLoaded', function () {
    initialiseDrawers();
    initialisePullToRefresh();
});

function initialiseDrawers() {
    var handles = document.querySelectorAll('[data-drawer-target]');

    handles.forEach(function (handle) {
        handle.addEventListener('click', function () {
            var targetId = handle.getAttribute('data-drawer-target');
            var panel = document.getElementById(targetId);

            if (panel) {
                panel.hidden = !panel.hidden;
            }
        });
    });
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