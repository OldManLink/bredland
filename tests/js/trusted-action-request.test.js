const test = require('node:test');
const assert = require('node:assert/strict');
const trusted_testlib = require('./lib/trusted_testlib');

var trusted_script = trusted_testlib.trusted_script;
var create_notification = trusted_testlib.create_notification;

test('trusted action button posts resolution and token', async function () {
    var appended = [];
    var click_handler = null;
    var requests = [];

    var notification = create_notification();

    notification.appendChild = function (element) {
        appended.push(element);
    }
    notification.querySelector = function () {
        return null;
    }

    global.window = {
        TRUSTED_BASE_URL: 'https://bredland.example:8081',
        TRUSTED_CAPABILITIES: {
            'test-resolution': 'test-token'
        },
        confirm: function () {
            return true;
        }
    };

    global.fetch = function (url, options) {
        requests.push(
            {
                url: url,
                options: options
            }
        );

        return Promise.resolve({
            ok: true
        });
    };

    global.setTimeout = function () {
        return 1;
    };

    global.document = {
        querySelectorAll: function () {
            return [
                notification
            ];
        },

        createElement: function (tag_name) {
            return {
                tagName: tag_name,

                addEventListener: function (
                    event_name,
                    handler
                ) {
                    if (event_name === 'click') {
                        click_handler = handler;
                    }
                },
                remove: function () {}
            };
        },

        body: {
            appendChild: function () {}
        }
    };

    delete require.cache[
        require.resolve(trusted_script)
        ];

    require(trusted_script);

    click_handler();

    await Promise.resolve();

    assert.equal(
        requests.length,
        1
    );

    assert.equal(
        requests[0].url,
        'https://bredland.example:8081/action'
    );

    assert.equal(
        requests[0].options.method,
        'POST'
    );

    assert.equal(
        requests[0].options.headers[
            'Content-Type'
            ],
        'application/json'
    );

    assert.deepEqual(
        JSON.parse(
            requests[0].options.body
        ),
        {
            resolution: 'test-resolution',
            token: 'test-token'
        }
    );

    delete global.window;
    delete global.fetch;
    delete global.document;
    delete global.setTimeout;
});

test('trusted action button disables while request is pending', function () {
    var click_handler = null;
    var button = null;

    var notification = create_notification();
    notification.appendChild = function (element) {
        button = element;
    };
    notification.querySelector = function () {
        return null;
    };

    global.window = {
        TRUSTED_BASE_URL: 'https://bredland.example:8081',

        TRUSTED_CAPABILITIES: {
            'test-resolution': 'test-token'
        },

        confirm: function () {
            return true;
        }
    };

    global.fetch = function () {
        return new Promise(function () {});
    };

    global.document = {
        querySelectorAll: function () {
            return [
                notification
            ];
        },

        createElement: function () {
            return {
                disabled: false,

                addEventListener: function (
                    event_name,
                    handler
                ) {
                    if (event_name === 'click') {
                        click_handler = handler;
                    }
                }
            };
        }
    };

    delete require.cache[
        require.resolve(trusted_script)
        ];

    require(trusted_script);

    click_handler();

    assert.equal(
        button.disabled,
        true
    );

    delete global.window;
    delete global.fetch;
    delete global.document;
});

test('trusted action shows failure message when update already in progress', async function () {
    var click_handler = null;
    var appended_to_notification = [];

    var notification = create_notification();
    notification.appendChild = function (element) {
        appended_to_notification.push(element);
    };
    notification.querySelector = function () {
        return null;
    };

    global.window = {
        TRUSTED_BASE_URL: 'https://bredland.example:8081',

        TRUSTED_CAPABILITIES: {
            'test-resolution': 'test-token'
        },

        confirm: function () {
            return true;
        }
    };

    global.fetch = function () {
        return Promise.resolve({
            ok: false,
            status: 423
        });
    };

    global.document = {
        querySelectorAll: function () {
            return [
                notification
            ];
        },

        createElement: function (tag_name) {
            return {
                tagName: tag_name,

                addEventListener: function (
                    event_name,
                    handler
                ) {
                    if (event_name === 'click') {
                        click_handler = handler;
                    }
                }
            };
        }
    };

    delete require.cache[
        require.resolve(trusted_script)
        ];

    require(trusted_script);

    click_handler();

    await new Promise(function (resolve) {
        setImmediate(resolve);
    });

    assert.equal(
        appended_to_notification.length,
        2
    );

    assert.equal(
        appended_to_notification[1].textContent,
        'Update request already in progress.'
    );

    assert.equal(
        appended_to_notification[1].className,
        'trusted-action-failure'
    );

    delete global.window;
    delete global.fetch;
    delete global.document;
});

test('trusted action shows failure message when update no longer available', async function () {
    var click_handler = null;
    var appended_to_notification = [];

    var notification = create_notification();
    notification.appendChild = function (element) {
        appended_to_notification.push(element);
    };
    notification.querySelector = function () {
        return null;
    };

    global.window = {
        TRUSTED_BASE_URL: 'https://bredland.example:8081',

        TRUSTED_CAPABILITIES: {
            'test-resolution': 'test-token'
        },

        confirm: function () {
            return true;
        }
    };

    global.fetch = function () {
        return Promise.resolve({
            ok: false,
            status: 409
        });
    };

    global.document = {
        querySelectorAll: function () {
            return [
                notification
            ];
        },

        createElement: function (tag_name) {
            return {
                tagName: tag_name,

                addEventListener: function (
                    event_name,
                    handler
                ) {
                    if (event_name === 'click') {
                        click_handler = handler;
                    }
                }
            };
        }
    };

    delete require.cache[
        require.resolve(trusted_script)
        ];

    require(trusted_script);

    click_handler();

    await new Promise(function (resolve) {
        setImmediate(resolve);
    });

    assert.equal(
        appended_to_notification.length,
        2
    );

    assert.equal(
        appended_to_notification[1].textContent,
        'The update is no longer available.'
    );

    assert.equal(
        appended_to_notification[1].className,
        'trusted-action-failure'
    );

    delete global.window;
    delete global.fetch;
    delete global.document;
});

test('trusted action shows failure message when request expires', async function () {
    var click_handler = null;
    var appended_to_notification = [];

    var notification = create_notification();
    notification.appendChild = function (element) {
        appended_to_notification.push(element);
    };
    notification.querySelector = function () {
        return null;
    };

    global.window = {
        TRUSTED_BASE_URL: 'https://bredland.example:8081',

        TRUSTED_CAPABILITIES: {
            'test-resolution': 'test-token'
        },

        confirm: function () {
            return true;
        }
    };

    global.fetch = function () {
        return Promise.resolve({
            ok: false,
            status: 400
        });
    };

    global.document = {
        querySelectorAll: function () {
            return [
                notification
            ];
        },

        createElement: function (tag_name) {
            return {
                tagName: tag_name,

                addEventListener: function (
                    event_name,
                    handler
                ) {
                    if (event_name === 'click') {
                        click_handler = handler;
                    }
                }
            };
        }
    };

    delete require.cache[
        require.resolve(trusted_script)
        ];

    require(trusted_script);

    click_handler();

    await new Promise(function (resolve) {
        setImmediate(resolve);
    });

    assert.equal(
        appended_to_notification.length,
        2
    );

    assert.equal(
        appended_to_notification[1].textContent,
        'Request expired. Reload the page and try again.'
    );

    assert.equal(
        appended_to_notification[1].className,
        'trusted-action-failure'
    );

    delete global.window;
    delete global.fetch;
    delete global.document;
});

test('trusted action shows failure message when RouterOS could not be reached', async function () {
    var click_handler = null;
    var appended_to_notification = [];

    var notification = create_notification();
    notification.appendChild = function (element) {
        appended_to_notification.push(element);
    };
    notification.querySelector = function () {
        return null;
    };

    global.window = {
        TRUSTED_BASE_URL: 'https://bredland.example:8081',

        TRUSTED_CAPABILITIES: {
            'test-resolution': 'test-token'
        },

        confirm: function () {
            return true;
        }
    };

    global.fetch = function () {
        return Promise.resolve({
            ok: false,
            status: 503
        });
    };

    global.document = {
        querySelectorAll: function () {
            return [
                notification
            ];
        },

        createElement: function (tag_name) {
            return {
                tagName: tag_name,

                addEventListener: function (
                    event_name,
                    handler
                ) {
                    if (event_name === 'click') {
                        click_handler = handler;
                    }
                }
            };
        }
    };

    delete require.cache[
        require.resolve(trusted_script)
        ];

    require(trusted_script);

    click_handler();

    await new Promise(function (resolve) {
        setImmediate(resolve);
    });

    assert.equal(
        appended_to_notification.length,
        2
    );

    assert.equal(
        appended_to_notification[1].textContent,
        'RouterOS could not be reached. Try again shortly.'
    );

    assert.equal(
        appended_to_notification[1].className,
        'trusted-action-failure'
    );

    delete global.window;
    delete global.fetch;
    delete global.document;
});

test('trusted action shows failure message when update request fails', async function () {
    var click_handler = null;
    var appended_to_notification = [];

    var notification = create_notification();
    notification.appendChild = function (element) {
        appended_to_notification.push(element);
    };
    notification.querySelector = function () {
        return null;
    };

    global.window = {
        TRUSTED_BASE_URL: 'https://bredland.example:8081',

        TRUSTED_CAPABILITIES: {
            'test-resolution': 'test-token'
        },

        confirm: function () {
            return true;
        }
    };

    global.fetch = function () {
        return Promise.resolve({
            ok: false,
            status: 500
        });
    };

    global.document = {
        querySelectorAll: function () {
            return [
                notification
            ];
        },

        createElement: function (tag_name) {
            return {
                tagName: tag_name,

                addEventListener: function (
                    event_name,
                    handler
                ) {
                    if (event_name === 'click') {
                        click_handler = handler;
                    }
                }
            };
        }
    };

    delete require.cache[
        require.resolve(trusted_script)
        ];

    require(trusted_script);

    click_handler();

    await new Promise(function (resolve) {
        setImmediate(resolve);
    });

    assert.equal(
        appended_to_notification.length,
        2
    );

    assert.equal(
        appended_to_notification[1].textContent,
        'The update request failed.'
    );

    assert.equal(
        appended_to_notification[1].className,
        'trusted-action-failure'
    );

    delete global.window;
    delete global.fetch;
    delete global.document;
});

test('trusted action shows failure message when fetch rejects', async function () {
    var click_handler = null;
    var appended_to_notification = [];

    var notification = create_notification();
    notification.appendChild = function (element) {
        appended_to_notification.push(element);
    };
    notification.querySelector = function () {
        return null;
    };

    global.window = {
        TRUSTED_BASE_URL: 'https://bredland.example:8081',

        TRUSTED_CAPABILITIES: {
            'test-resolution': 'test-token'
        },

        confirm: function () {
            return true;
        }
    };

    global.fetch = function () {
        return Promise.reject(
            new Error('Network unavailable')
        );
    };

    global.document = {
        querySelectorAll: function () {
            return [
                notification
            ];
        },

        createElement: function (tag_name) {
            return {
                tagName: tag_name,

                addEventListener: function (
                    event_name,
                    handler
                ) {
                    if (event_name === 'click') {
                        click_handler = handler;
                    }
                }
            };
        }
    };

    delete require.cache[
        require.resolve(trusted_script)
        ];

    require(trusted_script);

    click_handler();

    await new Promise(function (resolve) {
        setImmediate(resolve);
    });

    assert.equal(
        appended_to_notification.length,
        2
    );

    assert.equal(
        appended_to_notification[1].textContent,
        'Connection lost while requesting the update.'
    );

    assert.equal(
        appended_to_notification[1].className,
        'trusted-action-failure'
    );

    delete global.window;
    delete global.fetch;
    delete global.document;
});

test('trusted action success toast disappears', async function () {
    var click_handler = null;
    var animation_end_handler = null;
    var toast = null;

    var notification = create_notification();
    notification.appendChild = function (element) {
        toast = element;
    };
    notification.querySelector = function () {
        return null;
    };

    global.window = {
        TRUSTED_BASE_URL: 'https://bredland.example:8081',

        TRUSTED_CAPABILITIES: {
            'test-resolution': 'test-token'
        },

        confirm: function () {
            return true;
        }
    };

    global.fetch = function () {
        return Promise.resolve({
            ok: true,
            status: 202,

            json: function () {
                return Promise.resolve({
                    request_id: 'request-123'
                });
            }
        });
    };

    global.setTimeout = function () {
        return 1;
    };

    global.document = {
        querySelectorAll: function () {
            return [
                notification
            ];
        },

        createElement: function (tag_name) {
            return {
                tagName: tag_name,

                addEventListener: function (
                    event_name,
                    handler
                ) {
                    if (event_name === 'click') {
                        click_handler = handler;
                    }

                    if (event_name === 'animationend') {
                        animation_end_handler = handler;
                    }
                },

                remove: function () {
                    toast = null;
                }
            };
        },

        body: {
            appendChild: function () {}
        }
    };

    try {
        delete require.cache[
            require.resolve(trusted_script)
        ];

        require(trusted_script);

        click_handler();

        await new Promise(function (resolve) {
            setImmediate(resolve);
        });

        assert.notEqual(
            toast,
            null
        );

        assert.notEqual(
            animation_end_handler,
            null
        );

        animation_end_handler();

        assert.equal(
            toast,
            null
        );
    } finally {
        delete global.window;
        delete global.fetch;
        delete global.setTimeout;
        delete global.document;
    }
});
