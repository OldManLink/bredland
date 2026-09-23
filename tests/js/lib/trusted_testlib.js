const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

var trusted_template = path.join(
    process.cwd(),
    'templates/bredland/static/trusted.js'
);

var trusted_script = path.join(
    os.tmpdir(),
    'bredland-trusted-test.js'
);

var trusted_source = fs
    .readFileSync(
        trusted_template,
        'utf8'
    )
    .replace(
        '__TRUSTED_ACTIONS__',
        [
            'render_trusted_action(',
            "    'test-resolution',",
            "    'Perform the test action?'",
            ');'
        ].join('\n')
    );

fs.writeFileSync(
    trusted_script,
    trusted_source
);

function create_notification() {
    return {
        querySelector: function () {
            return null;
        },

        appendChild: function () {}
    };
}

module.exports = {
    trusted_script: trusted_script,
    create_notification: create_notification
};
