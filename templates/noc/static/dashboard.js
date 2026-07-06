document.addEventListener('DOMContentLoaded', function () {
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
});
