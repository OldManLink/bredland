const test = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');

var trusted_script = path.join(
    process.cwd(),
    'templates/bredland/static/trusted.js'
);

test('trusted action button is added to notification panel', function () {
    var appended = [];

    var panel = {
        appendChild: function (element) {
            appended.push(element);
        }
    };

    var notification = {
        closest: function (selector) {
            assert.equal(
                selector,
                '.notification-panel'
            );

            return panel;
        }
    };

    global.window = {
        TRUSTED_BASE_URL: 'https://bredland.example:8081',
        TRUSTED_CAPABILITIES: {
            'install-routeros-update': 'test-token'
        }
    };

    global.document = {
        querySelectorAll: function (selector) {
            assert.equal(
                selector,
                '[data-resolution="install-routeros-update"]'
            );

            return [
                notification
            ];
        },

        createElement: function (tag_name) {
            return {
                tagName: tag_name,

                addEventListener: function () {}
            };
        }
    };

    delete require.cache[
        require.resolve(trusted_script)
        ];

    require(trusted_script);

    assert.equal(
        appended.length,
        1
    );

    assert.equal(
        appended[0].tagName,
        'button'
    );

    assert.equal(
        appended[0].textContent,
        'Update'
    );

    assert.equal(
        appended[0].className,
        'trusted-action-button'
    );

    delete global.window;
    delete global.document;
});

test('trusted action button is not added without capability', function () {
    var appended = [];

    var panel = {
        appendChild: function (element) {
            appended.push(element);
        }
    };

    var notification = {
        closest: function () {
            return panel;
        }
    };

    global.window = {
        TRUSTED_BASE_URL: 'https://bredland.example:8081',
        TRUSTED_CAPABILITIES: {}
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

                addEventListener: function () {}
            };
        }
    };

    delete require.cache[
        require.resolve(trusted_script)
        ];

    require(trusted_script);

    assert.equal(
        appended.length,
        0
    );

    delete global.window;
    delete global.document;
});

test('trusted action button posts resolution and token', async function () {
    var appended = [];
    var click_handler = null;
    var requests = [];

    var panel = {
        appendChild: function (element) {
            appended.push(element);
        }
    };

    var notification = {
        closest: function () {
            return panel;
        }
    };

    global.window = {
        TRUSTED_BASE_URL: 'https://bredland.example:8081',
        TRUSTED_CAPABILITIES: {
            'install-routeros-update': 'test-token'
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
            resolution: 'install-routeros-update',
            token: 'test-token'
        }
    );

    delete global.window;
    delete global.fetch;
    delete global.document;
    delete global.setTimeout;
});

test('trusted action button posts only after confirmation', async function () {
    var click_handler = null;
    var requests = [];

    var panel = {
        appendChild: function () {}
    };

    var notification = {
        closest: function () {
            return panel;
        }
    };

    global.window = {
        TRUSTED_BASE_URL: 'https://bredland.example:8081',

        TRUSTED_CAPABILITIES: {
            'install-routeros-update': 'test-token'
        },

        confirm: function () {
            return false;
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

    global.document = {
        querySelectorAll: function () {
            return [
                notification
            ];
        },

        createElement: function () {
            return {
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

    await Promise.resolve();

    assert.equal(
        requests.length,
        0
    );

    delete global.window;
    delete global.fetch;
    delete global.document;
});

test('trusted action button disables while request is pending', function () {
    var click_handler = null;
    var button = null;

    var panel = {
        appendChild: function (element) {
            button = element;
        }
    };

    var notification = {
        closest: function () {
            return panel;
        }
    };

    global.window = {
        TRUSTED_BASE_URL: 'https://bredland.example:8081',

        TRUSTED_CAPABILITIES: {
            'install-routeros-update': 'test-token'
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

test('trusted action shows success toast', async function () {
    var click_handler = null;
    var appended_to_panel = [];

    var panel = {
        appendChild: function (element) {
            appended_to_panel.push(element);
        }
    };

    var notification = {
        closest: function () {
            return panel;
        }
    };

    global.window = {
        TRUSTED_BASE_URL: 'https://bredland.example:8081',

        TRUSTED_CAPABILITIES: {
            'install-routeros-update': 'test-token'
        },

        confirm: function () {
            return true;
        }
    };

    global.fetch = function () {
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

    await new Promise(function (resolve) {
        setImmediate(resolve);
    });

    assert.equal(
        appended_to_panel.length,
        2
    );

    assert.equal(
        appended_to_panel[1].textContent,
        'Update requested'
    );

    assert.equal(
        appended_to_panel[1].className,
        'trusted-action-success'
    );

    delete global.window;
    delete global.fetch;
    delete global.document;
    delete global.setTimeout;
});

test('trusted action shows failure message', async function () {
    var click_handler = null;
    var appended_to_body = [];

    var panel = {
        appendChild: function () {}
    };

    var notification = {
        closest: function () {
            return panel;
        }
    };

    global.window = {
        TRUSTED_BASE_URL: 'https://bredland.example:8081',

        TRUSTED_CAPABILITIES: {
            'install-routeros-update': 'test-token'
        },

        confirm: function () {
            return true;
        }
    };

    global.fetch = function () {
        return Promise.resolve({
            ok: false
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
        },

        body: {
            appendChild: function (element) {
                appended_to_body.push(element);
            }
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
        appended_to_body.length,
        1
    );

    assert.equal(
        appended_to_body[0].textContent,
        'Update failed. Reload the page to try again.'
    );

    assert.equal(
        appended_to_body[0].className,
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

    var panel = {
        appendChild: function (element) {
            toast = element;
        }
    };

    var notification = {
        closest: function () {
            return panel;
        }
    };

    global.window = {
        TRUSTED_BASE_URL: 'https://bredland.example:8081',

        TRUSTED_CAPABILITIES: {
            'install-routeros-update': 'test-token'
        },

        confirm: function () {
            return true;
        }
    };

    global.fetch = function () {
        return Promise.resolve({
            ok: true
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

    delete global.window;
    delete global.fetch;
    delete global.document;
});
