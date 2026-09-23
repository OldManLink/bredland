const test = require('node:test');
const assert = require('node:assert/strict');
const trusted_testlib = require('./lib/trusted_testlib');

var trusted_script = trusted_testlib.trusted_script;
var create_notification = trusted_testlib.create_notification;

test('trusted action button posts only after confirmation', async function () {
    var click_handler = null;
    var requests = [];

    var notification = create_notification();
    notification.appendChild = function () {};
    notification.querySelector = function () {
        return null;
    };

    global.window = {
        TRUSTED_BASE_URL: 'https://bredland.example:8081',

        TRUSTED_CAPABILITIES: {
            'test-resolution': 'test-token'
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

test('trusted action confirmation shows time until next heartbeat', async function () {
    var click_handler = null;
    var requests = [];
    var bredland_time = Date.parse('2026-09-02T08:02:42Z');
    var original_date_now = Date.now;

    Date.now = function () {
        return bredland_time - 123456;
    };

    var notification = create_notification();
    notification.appendChild = function () {};
    notification.querySelector = function () {
        return null;
    };

    global.window = {
        TRUSTED_BASE_URL: 'https://bredland.example:8081',
        TRUSTED_SERVER_TIME: bredland_time,

        TRUSTED_CAPABILITIES: {
            'test-resolution': 'test-token'
        },

        confirm: function (message) {
            assert.equal(
                message,
                'Perform the test action?\n\n' +
                'Next heartbeat expected in ~2m 18s.'
            );

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
        },

        getElementById: function (id) {
            var heartbeats = {
                'mikrotik-telemetry-template': {
                    ts: '2026-09-02T08:02:12Z',
                    ttl: 300
                },

                'bredland-telemetry-template': {
                    ts: '2026-09-02T08:00:00Z',
                    ttl: 300
                }
            };

            var heartbeat = heartbeats[id];

            if (! heartbeat) {
                return null;
            }

            return {
                content: {
                    querySelector: function (selector) {
                        assert.equal(
                            selector,
                            '.telemetry'
                        );

                        return {
                            textContent: JSON.stringify(
                                heartbeat
                            )
                        };
                    }
                }
            };
        },
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

    Date.now = original_date_now;
    delete global.window;
    delete global.fetch;
    delete global.document;
});

test('trusted action confirmation ignores overdue heartbeat', async function () {
    var click_handler = null;
    var requests = [];
    var bredland_time = Date.parse('2026-09-02T08:02:42Z');
    var original_date_now = Date.now;

    Date.now = function () {
        return bredland_time - 123456;
    };

    var notification = create_notification();
    notification.appendChild = function () {};
    notification.querySelector = function () {
        return null;
    };

    global.window = {
        TRUSTED_BASE_URL: 'https://bredland.example:8081',
        TRUSTED_SERVER_TIME: bredland_time,

        TRUSTED_CAPABILITIES: {
            'test-resolution': 'test-token'
        },

        confirm: function (message) {
            assert.equal(
                message,
                'Perform the test action?\n\n' +
                'Next heartbeat expected in ~2m 18s.'
            );

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
        },

        getElementById: function (id) {
            var heartbeats = {
                'mikrotik-telemetry-template': {
                    ts: '2026-09-02T07:57:00Z',
                    ttl: 300
                },

                'bredland-telemetry-template': {
                    ts: '2026-09-02T08:00:00Z',
                    ttl: 300
                }
            };

            var heartbeat = heartbeats[id];

            if (! heartbeat) {
                return null;
            }

            return {
                content: {
                    querySelector: function (selector) {
                        assert.equal(
                            selector,
                            '.telemetry'
                        );

                        return {
                            textContent: JSON.stringify(
                                heartbeat
                            )
                        };
                    }
                }
            };
        },
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

    Date.now = original_date_now;
    delete global.window;
    delete global.fetch;
    delete global.document;
});

test('trusted action confirmation falls back when all heartbeats are overdue', async function () {
    var click_handler = null;
    var requests = [];
    var bredland_time = Date.parse('2026-09-02T08:02:42Z');
    var original_date_now = Date.now;

    Date.now = function () {
        return bredland_time - 123456;
    };

    var notification = create_notification();
    notification.appendChild = function () {};
    notification.querySelector = function () {
        return null;
    };

    global.window = {
        TRUSTED_BASE_URL: 'https://bredland.example:8081',
        TRUSTED_SERVER_TIME: bredland_time,

        TRUSTED_CAPABILITIES: {
            'test-resolution': 'test-token'
        },

        confirm: function (message) {
            assert.equal(
                message,
                'Perform the test action?'
            );

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
        },

        getElementById: function (id) {
            var heartbeats = {
                'mikrotik-telemetry-template': {
                    ts: '2026-09-02T07:57:00Z',
                    ttl: 300
                },

                'bredland-telemetry-template': {
                    ts: '2026-09-02T07:37:00Z',
                    ttl: 300
                }
            };

            var heartbeat = heartbeats[id];

            if (! heartbeat) {
                return null;
            }

            return {
                content: {
                    querySelector: function (selector) {
                        assert.equal(
                            selector,
                            '.telemetry'
                        );

                        return {
                            textContent: JSON.stringify(
                                heartbeat
                            )
                        };
                    }
                }
            };
        },
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

    Date.now = original_date_now;
    delete global.window;
    delete global.fetch;
    delete global.document;
});

test('trusted action confirmation ignores malformed heartbeat', async function () {
    var click_handler = null;
    var requests = [];
    var bredland_time = Date.parse('2026-09-02T08:02:42Z');
    var original_date_now = Date.now;

    Date.now = function () {
        return bredland_time - 123456;
    };

    var notification = create_notification();
    notification.appendChild = function () {};
    notification.querySelector = function () {
        return null;
    };

    global.window = {
        TRUSTED_BASE_URL: 'https://bredland.example:8081',
        TRUSTED_SERVER_TIME: bredland_time,

        TRUSTED_CAPABILITIES: {
            'test-resolution': 'test-token'
        },

        confirm: function (message) {
            assert.equal(
                message,
                'Perform the test action?\n\n' +
                'Next heartbeat expected in ~2m 18s.'
            );

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
        },

        getElementById: function (id) {
            var heartbeats = {
                'mikrotik-telemetry-template': {
                    ts: 'not-a-timestamp',
                    ttl: 300
                },

                'bredland-telemetry-template': {
                    ts: '2026-09-02T08:00:00Z',
                    ttl: 300
                }
            };

            var heartbeat = heartbeats[id];

            if (! heartbeat) {
                return null;
            }

            return {
                content: {
                    querySelector: function (selector) {
                        assert.equal(
                            selector,
                            '.telemetry'
                        );

                        return {
                            textContent: JSON.stringify(
                                heartbeat
                            )
                        };
                    }
                }
            };
        },
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

    Date.now = original_date_now;
    delete global.window;
    delete global.fetch;
    delete global.document;
});

test('trusted action confirmation falls back without usable heartbeat', async function () {
    var click_handler = null;
    var requests = [];
    var bredland_time = Date.parse('2026-09-02T08:02:42Z');
    var original_date_now = Date.now;

    Date.now = function () {
        return bredland_time - 123456;
    };

    var notification = create_notification();
    notification.appendChild = function () {};
    notification.querySelector = function () {
        return null;
    };

    global.window = {
        TRUSTED_BASE_URL: 'https://bredland.example:8081',
        TRUSTED_SERVER_TIME: bredland_time,

        TRUSTED_CAPABILITIES: {
            'test-resolution': 'test-token'
        },

        confirm: function (message) {
            assert.equal(
                message,
                'Perform the test action?'
            );

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
        },

        getElementById: function (id) {
            var heartbeats = {
                'mikrotik-telemetry-template': {
                    ts: 'not-a-timestamp',
                    ttl: 300
                },

                'bredland-telemetry-template': {
                    ts: 'also-not-a-timestamp',
                    ttl: 300
                }
            };

            var heartbeat = heartbeats[id];

            if (! heartbeat) {
                return null;
            }

            return {
                content: {
                    querySelector: function (selector) {
                        assert.equal(
                            selector,
                            '.telemetry'
                        );

                        return {
                            textContent: JSON.stringify(
                                heartbeat
                            )
                        };
                    }
                }
            };
        },
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

    Date.now = original_date_now;
    delete global.window;
    delete global.fetch;
    delete global.document;
});

test('trusted action confirmation ignores malformed heartbeat JSON', async function () {
    var click_handler = null;
    var requests = [];
    var bredland_time = Date.parse('2026-09-02T08:02:42Z');
    var original_date_now = Date.now;

    Date.now = function () {
        return bredland_time - 123456;
    };

    var notification = create_notification();
    notification.appendChild = function () {};
    notification.querySelector = function () {
        return null;
    };

    global.window = {
        TRUSTED_BASE_URL: 'https://bredland.example:8081',
        TRUSTED_SERVER_TIME: bredland_time,

        TRUSTED_CAPABILITIES: {
            'test-resolution': 'test-token'
        },

        confirm: function (message) {
            assert.equal(
                message,
                'Perform the test action?\n\n' +
                'Next heartbeat expected in ~2m 18s.'
            );

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
        },

        getElementById: function (id) {
            if (id === 'mikrotik-telemetry-template') {
                return {
                    content: {
                        querySelector: function (selector) {
                            assert.equal(
                                selector,
                                '.telemetry'
                            );

                            return {
                                textContent: '{"ts":'
                            };
                        }
                    }
                };
            }

            if (id === 'bredland-telemetry-template') {
                return {
                    content: {
                        querySelector: function (selector) {
                            assert.equal(
                                selector,
                                '.telemetry'
                            );

                            return {
                                textContent:
                                    '{"ts":"2026-09-02T08:00:00Z","ttl":300}'
                            };
                        }
                    }
                };
            }

            return null;
        },
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

    Date.now = original_date_now;
    delete global.window;
    delete global.fetch;
    delete global.document;
});
