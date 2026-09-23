const test = require('node:test');
const assert = require('node:assert/strict');
const trusted_testlib = require('./lib/trusted_testlib');

var trusted_script = trusted_testlib.trusted_script;
var create_notification = trusted_testlib.create_notification;

test('trusted script calibrates browser clock to Bredland', function () {
    var original_date_now = Date.now;

    global.window = {
        TRUSTED_SERVER_TIME: 1788345803417
    };

    global.document = {
        querySelectorAll: function () {
            return [];
        }
    };

    Date.now = function () {
        return 1788345800000;
    };

    try {
        delete require.cache[
            require.resolve(trusted_script)
            ];

        require(trusted_script);

        assert.equal(
            window.TRUSTED_CLOCK_DELTA,
            3417
        );
    } finally {
        Date.now = original_date_now;

        delete global.window;
        delete global.document;
    }
});

test('trusted action button is added to notification', function () {
    var appended = [];

    var notification = create_notification();
    notification.appendChild = function (element) {
        appended.push(
            element
        );
    };

    global.window = {
        TRUSTED_BASE_URL: 'https://bredland.example:8081',
        TRUSTED_CAPABILITIES: {
            'test-resolution': 'test-token'
        }
    };

    global.document = {
        querySelectorAll: function (selector) {
            assert.equal(
                selector,
                '[data-resolution="test-resolution"]'
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

test('trusted action button is not duplicated', function () {
    var appended = [];

    var notification = create_notification();

    notification.appendChild = function (element) {
        appended.push(
            element
        );
    };

    notification.querySelector = function (selector) {
        assert.equal(
            selector,
            '.trusted-action-button'
        );

        return appended.find(function (element) {
            return element.className === 'trusted-action-button';
        }) || null;
    };

    global.window = {
        TRUSTED_BASE_URL: 'https://bredland.example:8081',
        TRUSTED_CAPABILITIES: {
            'test-resolution': 'test-token'
        }
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

    delete require.cache[
        require.resolve(trusted_script)
        ];

    require(trusted_script);

    assert.equal(
        appended.length,
        1
    );

    delete global.window;
    delete global.document;
});

test('trusted action button is not added without capability', function () {
    var appended = [];

    var notification = create_notification();

    notification.appendChild = function (element) {
        appended.push(element);
    }

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
