var controller = new AbortController();

var timeout_id = setTimeout(function () {
    controller.abort();
}, 3000);

fetch(
    globalThis.NOC_TRUSTED_PROBE_URL,
    {
        signal: controller.signal
    }
).then(
    function (response) {
        clearTimeout(timeout_id);

        if (!response.ok) {
            return null;
        }

        return response.json().then(
            function (discovery) {
                return discovery;
            },
            function () {
                return null;
            }
        );
    },
    function () {
        clearTimeout(timeout_id);
        return null;
    }
).then(function (discovery) {
    if (
        discovery === null ||
        !discovery.assets ||
        !discovery.assets.script ||
        !discovery.assets.stylesheet
    ) {
        return;
    }

    var script = document.createElement('script');
    script.src = discovery.assets.script;

    var stylesheet = document.createElement('link');
    stylesheet.rel = 'stylesheet';
    stylesheet.href = discovery.assets.stylesheet;

    document.head.appendChild(script);
    document.head.appendChild(stylesheet);
});