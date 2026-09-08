// Bredland trusted-mode JavaScript
// Served only from the trusted network
// BRD-030 trusted discovery asset
var resolution = 'install-routeros-update';

if (
    typeof window.TRUSTED_SERVER_TIME === 'number'
) {
    window.TRUSTED_CLOCK_DELTA = (
        window.TRUSTED_SERVER_TIME
        - Date.now()
    );
}

function heartbeat_confirmation_message() {
    var message = 'Install the available RouterOS update?';

    if (
        typeof document.getElementById !== 'function'
    ) {
        return message;
    }

    var template_ids = [
        'mikrotik-telemetry-template',
        'bredland-telemetry-template'
    ];

    var bredland_now = (
        Date.now()
        + window.TRUSTED_CLOCK_DELTA
    );

    var remaining_seconds = null;

    template_ids.forEach(function (template_id) {
        var template = document.getElementById(
            template_id
        );

        if (! template) {
            return;
        }

        var heartbeat;

        try {
            var telemetry = template.content.querySelector('.telemetry');
            heartbeat = JSON.parse(telemetry.textContent);
        } catch (error) {
            return;
        }

        var heartbeat_time = Date.parse(
            heartbeat.ts
        );

        var expected_time = (
            heartbeat_time
            + (heartbeat.ttl * 1000)
        );

        var candidate = Math.floor(
            (expected_time - bredland_now) / 1000
        );

        if (! Number.isFinite(candidate)) {
            return;
        }

        if (candidate <= 0) {
            return;
        }

        if (
            remaining_seconds === null ||
            candidate < remaining_seconds
        ) {
            remaining_seconds = candidate;
        }
    });

    if (remaining_seconds === null) {
        return message;
    }

    var minutes = Math.floor(
        remaining_seconds / 60
    );

    var seconds = remaining_seconds % 60;

    return (
        message +
        '\n\nNext heartbeat expected in ~' +
        minutes +
        'm ' +
        seconds +
        's.'
    );
}

document
    .querySelectorAll(
        '[data-resolution="' + resolution + '"]'
    )
    .forEach(function (notification) {
        if (
            !window.TRUSTED_CAPABILITIES ||
            !window.TRUSTED_CAPABILITIES[resolution]
        ) {
            return;
        }

        var panel = notification.closest(
            '.notification-panel'
        );

        if (panel === null) {
            return;
        }

        if (
            panel.querySelector(
                '.trusted-action-button'
            ) !== null
        ) {
            return;
        }

        var button = document.createElement(
            'button'
        );

        button.type = 'button';
        button.textContent = 'Update';
        button.className = 'trusted-action-button';

        button.addEventListener(
            'click',
            function () {
                if (
                    !window.confirm(
                        heartbeat_confirmation_message()
                    )
                ) {
                    return;
                }

                button.disabled = true;

                function showFailure(message) {
                    var failure = document.createElement(
                        'div'
                    );

                    failure.textContent = message;
                    failure.className = 'trusted-action-failure';

                    panel.appendChild(
                        failure
                    );
                }

                fetch(
                    window.TRUSTED_BASE_URL + '/action',
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(
                            {
                                resolution: resolution,
                                token: window.TRUSTED_CAPABILITIES[
                                    resolution
                                    ]
                            }
                        )
                    }
                ).then(function (response) {
                    if (!response.ok) {
                        var failureMessages = {
                            400: 'Request expired. Reload the page and try again.',
                            409: 'The update is no longer available.',
                            423: 'Update request already in progress.',
                            500: 'The update request failed.',
                            503: 'RouterOS could not be reached. Try again shortly.'
                        };

                        var message = failureMessages[
                            response.status
                            ];

                        showFailure(
                            message ||
                            'Update failed. Reload the page to try again.'
                        );
                        return;
                    }

                    var toast = document.createElement(
                        'div'
                    );

                    toast.textContent = 'Update requested';
                    toast.className = 'trusted-action-success';

                    panel.appendChild(
                        toast
                    );

                    toast.addEventListener(
                        'animationend',
                        function () {
                            toast.remove();
                        }
                    );
                }).catch(function () {
                    showFailure(
                        'Connection lost while requesting the update.'
                    );
                });
            }
        );

        panel.appendChild(
            button
        );
    });