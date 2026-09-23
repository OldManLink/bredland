const test = require('node:test');
const assert = require('node:assert/strict');
const trusted_testlib = require('./lib/trusted_testlib');

var trusted_script = trusted_testlib.trusted_script;
var create_notification = trusted_testlib.create_notification;

test('trusted action schedules first status poll', async function () {
    var click_handler = null;
    var scheduled = [];
    var original_date_now = Date.now;

    Date.now = function () {
        return 100000;
    };

    var notification = create_notification();
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

    global.setTimeout = function (handler, delay) {
        scheduled.push({
            handler: handler,
            delay: delay
        });

        return 1;
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
                },

                remove: function () {}
            };
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

        assert.equal(
            scheduled.length,
            1
        );

        assert.equal(
            scheduled[0].delay,
            1209
        );
    } finally {
        Date.now = original_date_now;

        delete global.window;
        delete global.fetch;
        delete global.setTimeout;
        delete global.document;
    }
});

test('trusted action polling starts from acceptance time', async function () {
    var click_handler = null;
    var scheduled = [];
    var now = 100000;
    var original_date_now = Date.now;

    Date.now = function () {
        return now;
    };

    var notification = create_notification();
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
                now += 200;

                return Promise.resolve({
                    request_id: 'request-123'
                });
            }
        });
    };

    global.setTimeout = function (handler, delay) {
        scheduled.push({
            handler: handler,
            delay: delay
        });

        return 1;
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
                },

                remove: function () {}
            };
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

        assert.equal(
            scheduled.length,
            1
        );

        assert.equal(
            scheduled[0].delay,
            1009
        );
    } finally {
        Date.now = original_date_now;

        delete global.window;
        delete global.fetch;
        delete global.setTimeout;
        delete global.document;
    }
});

test('trusted action polls accepted request status', async function () {
    var click_handler = null;
    var scheduled_handler = null;
    var requests = [];

    var notification = create_notification();
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

    global.fetch = function (url, options) {
        requests.push({
            url: url,
            options: options
        });

        if (requests.length === 1) {
            return Promise.resolve({
                ok: true,
                status: 202,

                json: function () {
                    return Promise.resolve({
                        request_id: 'request-123'
                    });
                }
            });
        }

        return Promise.resolve({
            ok: true
        });
    };

    global.setTimeout = function (handler) {
        scheduled_handler = handler;
        return 1;
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
                },

                remove: function () {}
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

    scheduled_handler();

    await Promise.resolve();

    assert.equal(
        requests.length,
        2
    );

    assert.equal(
        requests[1].url,
        'https://bredland.example:8081/action/request-123'
    );

    assert.equal(
        requests[1].options,
        undefined
    );

    delete global.window;
    delete global.fetch;
    delete global.setTimeout;
    delete global.document;
});

test('trusted action polling preserves absolute schedule', async function () {
    var click_handler = null;
    var scheduled = [];
    var requests = [];
    var now = 100000;
    var original_date_now = Date.now;

    Date.now = function () {
        return now;
    };

    var notification = create_notification();
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

    global.fetch = function (url, options) {
        requests.push({
            url: url,
            options: options
        });

        if (requests.length === 1) {
            return Promise.resolve({
                ok: true,
                status: 202,

                json: function () {
                    return Promise.resolve({
                        request_id: 'request-123'
                    });
                }
            });
        }

        now += 186;

        return Promise.resolve({
            ok: true,

            json: function () {
                return Promise.resolve({
                    status: 'pending'
                });
            }
        });
    };

    global.setTimeout = function (handler, delay) {
        scheduled.push({
            handler: handler,
            delay: delay
        });

        return scheduled.length;
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
                },

                remove: function () {}
            };
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

        assert.equal(
            scheduled[0].delay,
            1209
        );

        now += 1209;

        scheduled[0].handler();

        await new Promise(function (resolve) {
            setImmediate(resolve);
        });

        assert.equal(
            scheduled.length,
            2
        );

        assert.equal(
            scheduled[1].delay,
            3420
        );
    } finally {
        Date.now = original_date_now;

        delete global.window;
        delete global.fetch;
        delete global.setTimeout;
        delete global.document;
    }
});

test('trusted action polling catches up overdue poll immediately', async function () {
    var click_handler = null;
    var scheduled = [];
    var requests = [];
    var now = 100000;
    var original_date_now = Date.now;

    Date.now = function () {
        return now;
    };

    var notification = create_notification();
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

    global.fetch = function (url, options) {
        requests.push({
            url: url,
            options: options
        });

        if (requests.length === 1) {
            return Promise.resolve({
                ok: true,
                status: 202,

                json: function () {
                    return Promise.resolve({
                        request_id: 'request-123'
                    });
                }
            });
        }

        now += 4000;

        return Promise.resolve({
            ok: true,

            json: function () {
                return Promise.resolve({
                    status: 'pending'
                });
            }
        });
    };

    global.setTimeout = function (handler, delay) {
        scheduled.push({
            handler: handler,
            delay: delay
        });

        return scheduled.length;
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
                },

                remove: function () {}
            };
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

        now += 1209;

        scheduled[0].handler();

        await new Promise(function (resolve) {
            setImmediate(resolve);
        });

        assert.equal(
            scheduled.length,
            2
        );

        assert.equal(
            scheduled[1].delay,
            0
        );
    } finally {
        Date.now = original_date_now;

        delete global.window;
        delete global.fetch;
        delete global.setTimeout;
        delete global.document;
    }
});

test('trusted action shows requested toast when accepted', async function () {
    var click_handler = null;
    var appended = [];

    var notification = create_notification();
    notification.querySelector = function () {
        return null;
    };

    notification.appendChild = function (element) {
        appended.push(
            element
        );
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
                },

                remove: function () {}
            };
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

        assert.equal(
            appended.length,
            2
        );

        assert.equal(
            appended[1].textContent,
            'Update requested'
        );

        assert.equal(
            appended[1].className,
            'trusted-action-success'
        );
    } finally {
        delete global.window;
        delete global.fetch;
        delete global.setTimeout;
        delete global.document;
    }
});

test('trusted action shows completion toast after successful poll', async function () {
    var click_handler = null;
    var scheduled_handler = null;
    var appended = [];
    var requests = [];

    var notification = create_notification();
    notification.querySelector = function () {
        return null;
    };

    notification.appendChild = function (element) {
        appended.push(
            element
        );
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

    global.fetch = function (url, options) {
        requests.push({
            url: url,
            options: options
        });

        if (requests.length === 1) {
            return Promise.resolve({
                ok: true,
                status: 202,

                json: function () {
                    return Promise.resolve({
                        request_id: 'request-123'
                    });
                }
            });
        }

        return Promise.resolve({
            ok: true,

            json: function () {
                return Promise.resolve({
                    status: 'succeeded'
                });
            }
        });
    };

    global.setTimeout = function (handler) {
        scheduled_handler = handler;
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

        scheduled_handler();

        await new Promise(function (resolve) {
            setImmediate(resolve);
        });

        assert.equal(
            appended.length,
            3
        );

        assert.equal(
            appended[2].textContent,
            'Download complete'
        );

        assert.equal(
            appended[2].className,
            'trusted-action-success'
        );
    } finally {
        delete global.window;
        delete global.fetch;
        delete global.setTimeout;
        delete global.document;
    }
});

test('trusted action shows failure after failed poll', async function () {
    var click_handler = null;
    var scheduled_handler = null;
    var appended = [];
    var requests = [];

    var notification = create_notification();
    notification.querySelector = function () {
        return null;
    };

    notification.appendChild = function (element) {
        appended.push(
            element
        );
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

    global.fetch = function (url, options) {
        requests.push({
            url: url,
            options: options
        });

        if (requests.length === 1) {
            return Promise.resolve({
                ok: true,
                status: 202,

                json: function () {
                    return Promise.resolve({
                        request_id: 'request-123'
                    });
                }
            });
        }

        return Promise.resolve({
            ok: true,

            json: function () {
                return Promise.resolve({
                    status: 'failed'
                });
            }
        });
    };

    global.setTimeout = function (handler) {
        scheduled_handler = handler;
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

        scheduled_handler();

        await new Promise(function (resolve) {
            setImmediate(resolve);
        });

        assert.equal(
            appended.length,
            3
        );

        assert.equal(
            appended[2].textContent,
            'Download failed.'
        );

        assert.equal(
            appended[2].className,
            'trusted-action-failure'
        );
    } finally {
        delete global.window;
        delete global.fetch;
        delete global.setTimeout;
        delete global.document;
    }
});

test('trusted action times out after final pending poll', async function () {
    var click_handler = null;
    var scheduled = [];
    var appended = [];
    var requests = [];
    var now = 100000;
    var original_date_now = Date.now;

    Date.now = function () {
        return now;
    };

    var notification = create_notification();
    notification.querySelector = function () {
        return null;
    };

    notification.appendChild = function (element) {
        appended.push(
            element
        );
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

    global.fetch = function (url, options) {
        requests.push({
            url: url,
            options: options
        });

        if (requests.length === 1) {
            return Promise.resolve({
                ok: true,
                status: 202,

                json: function () {
                    return Promise.resolve({
                        request_id: 'request-123'
                    });
                }
            });
        }

        return Promise.resolve({
            ok: true,

            json: function () {
                return Promise.resolve({
                    status: 'pending'
                });
            }
        });
    };

    global.setTimeout = function (handler, delay) {
        scheduled.push({
            handler: handler,
            delay: delay
        });

        return scheduled.length;
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

        var poll_times = [
            1209,
            4815,
            10005,
            15000
        ];

        for (var index = 0; index < poll_times.length; index += 1) {
            now = 100000 + poll_times[index];

            scheduled[index].handler();

            await new Promise(function (resolve) {
                setImmediate(resolve);
            });
        }

        assert.equal(
            scheduled.length,
            4
        );

        assert.equal(
            appended.length,
            3
        );

        assert.equal(
            appended[2].textContent,
            'Download timed out.'
        );

        assert.equal(
            appended[2].className,
            'trusted-action-failure'
        );
    } finally {
        Date.now = original_date_now;

        delete global.window;
        delete global.fetch;
        delete global.setTimeout;
        delete global.document;
    }
});

test('trusted action shows failure when status poll loses connection', async function () {
    var click_handler = null;
    var scheduled_handler = null;
    var appended = [];
    var requests = [];

    var notification = create_notification();
    notification.querySelector = function () {
        return null;
    };

    notification.appendChild = function (element) {
        appended.push(
            element
        );
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

    global.fetch = function (url, options) {
        requests.push({
            url: url,
            options: options
        });

        if (requests.length === 1) {
            return Promise.resolve({
                ok: true,
                status: 202,

                json: function () {
                    return Promise.resolve({
                        request_id: 'request-123'
                    });
                }
            });
        }

        return Promise.reject(
            new Error('connection lost')
        );
    };

    global.setTimeout = function (handler) {
        scheduled_handler = handler;
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

        scheduled_handler();

        await new Promise(function (resolve) {
            setImmediate(resolve);
        });

        assert.equal(
            appended.length,
            3
        );

        assert.equal(
            appended[2].textContent,
            'Connection lost while checking status.'
        );

        assert.equal(
            appended[2].className,
            'trusted-action-failure'
        );
    } finally {
        delete global.window;
        delete global.fetch;
        delete global.setTimeout;
        delete global.document;
    }
});

test('trusted action shows failure when status poll fails', async function () {
    var click_handler = null;
    var scheduled_handler = null;
    var appended = [];
    var requests = [];

    var notification = create_notification();
    notification.querySelector = function () {
        return null;
    };

    notification.appendChild = function (element) {
        appended.push(
            element
        );
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

    global.fetch = function (url, options) {
        requests.push({
            url: url,
            options: options
        });

        if (requests.length === 1) {
            return Promise.resolve({
                ok: true,
                status: 202,

                json: function () {
                    return Promise.resolve({
                        request_id: 'request-123'
                    });
                }
            });
        }

        return Promise.resolve({
            ok: false,
            status: 500
        });
    };

    global.setTimeout = function (handler) {
        scheduled_handler = handler;
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

        scheduled_handler();

        await new Promise(function (resolve) {
            setImmediate(resolve);
        });

        assert.equal(
            appended.length,
            3
        );

        assert.equal(
            appended[2].textContent,
            'Status check failed.'
        );

        assert.equal(
            appended[2].className,
            'trusted-action-failure'
        );
    } finally {
        delete global.window;
        delete global.fetch;
        delete global.setTimeout;
        delete global.document;
    }
});

test('trusted action shows failure for malformed status response', async function () {
    var click_handler = null;
    var scheduled_handler = null;
    var appended = [];
    var requests = [];

    var notification = create_notification();
    notification.querySelector = function () {
        return null;
    };

    notification.appendChild = function (element) {
        appended.push(
            element
        );
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

    global.fetch = function (url, options) {
        requests.push({
            url: url,
            options: options
        });

        if (requests.length === 1) {
            return Promise.resolve({
                ok: true,
                status: 202,

                json: function () {
                    return Promise.resolve({
                        request_id: 'request-123'
                    });
                }
            });
        }

        return Promise.resolve({
            ok: true,

            json: function () {
                return Promise.resolve({
                    nonsense: 'wat'
                });
            }
        });
    };

    global.setTimeout = function (handler) {
        scheduled_handler = handler;
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

        scheduled_handler();

        await new Promise(function (resolve) {
            setImmediate(resolve);
        });

        assert.equal(
            appended.length,
            3
        );

        assert.equal(
            appended[2].textContent,
            'Invalid status response.'
        );

        assert.equal(
            appended[2].className,
            'trusted-action-failure'
        );
    } finally {
        delete global.window;
        delete global.fetch;
        delete global.setTimeout;
        delete global.document;
    }
});

test('trusted action shows failure when status response is malformed JSON', async function () {
    var click_handler = null;
    var scheduled_handler = null;
    var appended = [];
    var requests = [];

    var notification = create_notification();
    notification.querySelector = function () {
        return null;
    };

    notification.appendChild = function (element) {
        appended.push(
            element
        );
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

    global.fetch = function (url, options) {
        requests.push({
            url: url,
            options: options
        });

        if (requests.length === 1) {
            return Promise.resolve({
                ok: true,
                status: 202,

                json: function () {
                    return Promise.resolve({
                        request_id: 'request-123'
                    });
                }
            });
        }

        return Promise.resolve({
            ok: true,

            json: function () {
                return Promise.reject(
                    new Error('invalid JSON')
                );
            }
        });
    };

    global.setTimeout = function (handler) {
        scheduled_handler = handler;
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

        scheduled_handler();

        await new Promise(function (resolve) {
            setImmediate(resolve);
        });

        assert.equal(
            appended.length,
            3
        );

        assert.equal(
            appended[2].textContent,
            'Invalid status response.'
        );

        assert.equal(
            appended[2].className,
            'trusted-action-failure'
        );
    } finally {
        delete global.window;
        delete global.fetch;
        delete global.setTimeout;
        delete global.document;
    }
});

test('trusted action shows failure when status response has no JSON body', async function () {
    var click_handler = null;
    var scheduled_handler = null;
    var appended = [];
    var requests = [];

    var notification = create_notification();
    notification.querySelector = function () {
        return null;
    };

    notification.appendChild = function (element) {
        appended.push(
            element
        );
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

    global.fetch = function (url, options) {
        requests.push({
            url: url,
            options: options
        });

        if (requests.length === 1) {
            return Promise.resolve({
                ok: true,
                status: 202,

                json: function () {
                    return Promise.resolve({
                        request_id: 'request-123'
                    });
                }
            });
        }

        return Promise.resolve({
            ok: true
        });
    };

    global.setTimeout = function (handler) {
        scheduled_handler = handler;
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

        scheduled_handler();

        await new Promise(function (resolve) {
            setImmediate(resolve);
        });

        assert.equal(
            appended.length,
            3
        );

        assert.equal(
            appended[2].textContent,
            'Invalid status response.'
        );

        assert.equal(
            appended[2].className,
            'trusted-action-failure'
        );
    } finally {
        delete global.window;
        delete global.fetch;
        delete global.setTimeout;
        delete global.document;
    }
});
