const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const child_process = require('node:child_process');

var tmpdir = fs.mkdtempSync(
    path.join(os.tmpdir(), 'bredland-bootstrap-')
);

var secrets_file = path.join(
    tmpdir,
    'secrets.env'
);

var rendered_bootstrap = path.join(
    tmpdir,
    'bootstrap.js'
);

fs.writeFileSync(
    secrets_file,
    'BREDLAND_TRUSTED_BASE_URL=https://bredland.example:8081\n'
);

child_process.execFileSync(
    'scripts/render-template.sh',
    [
        'templates/noc/static/bootstrap.template.js',
        rendered_bootstrap
    ],
    {
        env: Object.assign(
            {},
            process.env,
            {
                BREDLAND_SECRETS_FILE: secrets_file
            }
        )
    }
);

test('bootstrap starts exactly one discovery probe', async function () {
    var requests = [];

    global.fetch = function (url) {
        requests.push(url);

        return Promise.resolve({
            ok: false
        });
    };

    require(rendered_bootstrap);

    assert.equal(requests.length, 1);
    assert.equal(
        requests[0],
        'https://bredland.example:8081/probe'
    );

    delete global.fetch;
});

test('bootstrap aborts a discovery probe that takes too long', function () {
    var aborted = false;
    var timeout_callback = null;
    var timeout_delay = null;

    global.setTimeout = function (callback, delay) {
        timeout_callback = callback;
        timeout_delay = delay;
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

    delete require.cache[require.resolve(rendered_bootstrap)];
    require(rendered_bootstrap);

    assert.notEqual(timeout_callback, null);
    assert.equal(timeout_delay, 20000);

    timeout_callback();

    assert.equal(aborted, true);

    delete global.setTimeout;
    delete global.AbortController;
    delete global.fetch;
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

    delete require.cache[require.resolve(rendered_bootstrap)];
    require(rendered_bootstrap);

    await Promise.resolve();

    assert.equal(cleared_timeout_id, timeout_id);

    delete global.setTimeout;
    delete global.clearTimeout;
    delete global.AbortController;
    delete global.fetch;
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

    delete require.cache[require.resolve(rendered_bootstrap)];
    require(rendered_bootstrap);

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

    delete require.cache[require.resolve(rendered_bootstrap)];
    require(rendered_bootstrap);

    await new Promise(function (resolve) {
        setImmediate(resolve);
    });

    assert.equal(appended.length, 0);

    delete global.setTimeout;
    delete global.clearTimeout;
    delete global.AbortController;
    delete global.fetch;
    delete global.document;
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

    delete require.cache[require.resolve(rendered_bootstrap)];
    require(rendered_bootstrap);

    await new Promise(function (resolve) {
        setImmediate(resolve);
    });

    assert.equal(appended.length, 0);

    delete global.setTimeout;
    delete global.clearTimeout;
    delete global.AbortController;
    delete global.fetch;
    delete global.document;
});
