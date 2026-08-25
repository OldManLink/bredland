const test = require('node:test');
const assert = require('node:assert/strict');

test('bootstrap starts exactly one discovery probe', async function () {
    var requests = [];

    global.fetch = function (url) {
        requests.push(url);

        return Promise.resolve({
            ok: false
        });
    };

    global.NOC_TRUSTED_PROBE_URL = 'https://bredland.example/probe';

    require('../../templates/noc/static/bootstrap.js');

    assert.equal(requests.length, 1);
    assert.equal(
        requests[0],
        'https://bredland.example/probe'
    );

    delete global.fetch;
    delete global.NOC_TRUSTED_PROBE_URL;
});

test('bootstrap aborts a discovery probe that takes too long', function () {
    var aborted = false;
    var timeout_callback = null;

    global.setTimeout = function (callback) {
        timeout_callback = callback;
        return 1;
    };

    global.AbortController = function () {
        this.signal = {};
        this.abort = function () {
            aborted = true;
        };
    };

    global.fetch = function () {
        return new Promise(function () {});
    };

    global.NOC_TRUSTED_PROBE_URL = 'https://bredland.example/probe';

    delete require.cache[require.resolve('../../templates/noc/static/bootstrap.js')];
    require('../../templates/noc/static/bootstrap.js');

    assert.notEqual(timeout_callback, null);
    timeout_callback();
    assert.equal(aborted, true);

    delete global.setTimeout;
    delete global.AbortController;
    delete global.fetch;
    delete global.NOC_TRUSTED_PROBE_URL;
});

test('bootstrap clears the discovery timeout when the probe completes', async function () {
    var timeout_id = 17;
    var cleared_timeout_id = null;

    global.setTimeout = function () {
        return timeout_id;
    };

    global.clearTimeout = function (id) {
        cleared_timeout_id = id;
    };

    global.AbortController = function () {
        this.signal = {};
        this.abort = function () {};
    };

    global.fetch = function () {
        return Promise.resolve({
            ok: false
        });
    };

    global.NOC_TRUSTED_PROBE_URL = 'https://bredland.example/probe';

    delete require.cache[require.resolve('../../templates/noc/static/bootstrap.js')];
    require('../../templates/noc/static/bootstrap.js');

    await Promise.resolve();

    assert.equal(cleared_timeout_id, timeout_id);

    delete global.setTimeout;
    delete global.clearTimeout;
    delete global.AbortController;
    delete global.fetch;
    delete global.NOC_TRUSTED_PROBE_URL;
});

test('bootstrap loads trusted assets after successful discovery', async function () {
    var appended = [];

    global.setTimeout = function () {
        return 17;
    };

    global.clearTimeout = function () {};

    global.AbortController = function () {
        this.signal = {};
        this.abort = function () {};
    };

    global.fetch = function () {
        return Promise.resolve({
            ok: true,
            json: function () {
                return Promise.resolve({
                    assets: {
                        script: 'https://bredland.example/opaque-script',
                        stylesheet: 'https://bredland.example/opaque-style'
                    }
                });
            }
        });
    };

    global.document = {
        createElement: function (tag_name) {
            return {
                tagName: tag_name
            };
        },

        head: {
            appendChild: function (element) {
                appended.push(element);
            }
        }
    };

    global.NOC_TRUSTED_PROBE_URL = 'https://bredland.example/probe';

    delete require.cache[require.resolve('../../templates/noc/static/bootstrap.js')];
    require('../../templates/noc/static/bootstrap.js');

    await new Promise(function (resolve) {
        setImmediate(resolve);
    });

    assert.equal(appended.length, 2);

    assert.equal(appended[0].tagName, 'script');
    assert.equal(
        appended[0].src,
        'https://bredland.example/opaque-script'
    );

    assert.equal(appended[1].tagName, 'link');
    assert.equal(appended[1].rel, 'stylesheet');
    assert.equal(
        appended[1].href,
        'https://bredland.example/opaque-style'
    );

    delete global.setTimeout;
    delete global.clearTimeout;
    delete global.AbortController;
    delete global.fetch;
    delete global.document;
    delete global.NOC_TRUSTED_PROBE_URL;
});

test('bootstrap ignores incomplete discovery data', async function () {
    var appended = [];

    global.setTimeout = function () {
        return 17;
    };

    global.clearTimeout = function () {};

    global.AbortController = function () {
        this.signal = {};
        this.abort = function () {};
    };

    global.fetch = function () {
        return Promise.resolve({
            ok: true,
            json: function () {
                return Promise.resolve({
                    assets: {
                        script: 'https://bredland.example/opaque-script'
                    }
                });
            }
        });
    };

    global.document = {
        createElement: function (tag_name) {
            return {
                tagName: tag_name
            };
        },

        head: {
            appendChild: function (element) {
                appended.push(element);
            }
        }
    };

    global.NOC_TRUSTED_PROBE_URL = 'https://bredland.example/probe';

    delete require.cache[require.resolve('../../templates/noc/static/bootstrap.js')];
    require('../../templates/noc/static/bootstrap.js');

    await new Promise(function (resolve) {
        setImmediate(resolve);
    });

    assert.equal(appended.length, 0);

    delete global.setTimeout;
    delete global.clearTimeout;
    delete global.AbortController;
    delete global.fetch;
    delete global.document;
    delete global.NOC_TRUSTED_PROBE_URL;
});

test('bootstrap ignores malformed discovery response', async function () {
    var appended = [];

    global.setTimeout = function () {
        return 17;
    };

    global.clearTimeout = function () {};

    global.AbortController = function () {
        this.signal = {};
        this.abort = function () {};
    };

    global.fetch = function () {
        return Promise.resolve({
            ok: true,
            json: function () {
                return Promise.reject(new Error('invalid json'));
            }
        });
    };

    global.document = {
        createElement: function (tag_name) {
            return {
                tagName: tag_name
            };
        },

        head: {
            appendChild: function (element) {
                appended.push(element);
            }
        }
    };

    global.NOC_TRUSTED_PROBE_URL = 'https://bredland.example/probe';

    delete require.cache[require.resolve('../../templates/noc/static/bootstrap.js')];
    require('../../templates/noc/static/bootstrap.js');

    await new Promise(function (resolve) {
        setImmediate(resolve);
    });

    assert.equal(appended.length, 0);

    delete global.setTimeout;
    delete global.clearTimeout;
    delete global.AbortController;
    delete global.fetch;
    delete global.document;
    delete global.NOC_TRUSTED_PROBE_URL;
});
