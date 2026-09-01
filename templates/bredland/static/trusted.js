// Bredland trusted-mode JavaScript
// Served only from the trusted network
// BRD-030 trusted discovery asset
var resolution = 'install-routeros-update';

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
                        'Install the available RouterOS update?'
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