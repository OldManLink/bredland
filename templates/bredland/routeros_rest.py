import base64
import json
import ssl
import urllib.request

def execute_routeros_script(
        base_url,
        script_name,
        post,
):
    return post(
        base_url + '/rest/system/script/run',
        {
            '.id': script_name,
        },
        )

def post_json(
        url,
        body,
        headers,
        context,
        open_request,
):
    request_headers = dict(headers)
    request_headers['Content-Type'] = 'application/json'

    request = urllib.request.Request(
        url,
        data=json.dumps(
            body,
            separators=(',', ':'),
        ).encode('utf-8'),
        headers=request_headers,
        method='POST',
    )

    response = open_request(
        request,
        context=context,
    )

    return response.status == 200

def load_routeros_rest_credentials(
        credentials_file,
):
    values = {}

    with open(credentials_file, 'r') as file:
        for line in file:
            line = line.strip()

            if not line:
                continue

            key, value = line.split('=', 1)
            values[key] = value

    return {
        'username': values[
            'MIKROTIK_REST_USER'
        ],
        'password': values[
            'MIKROTIK_REST_PASSWORD'
        ],
    }

def routeros_rest_authorization(
        username,
        password,
):
    credentials = '{}:{}'.format(
        username,
        password,
    ).encode('utf-8')

    encoded = base64.b64encode(
        credentials
    ).decode('ascii')

    return 'Basic {}'.format(
        encoded
    )

def create_routeros_rest_tls_context(
        ca_file,
):
    return ssl.create_default_context(
        cafile=ca_file,
    )

def create_routeros_rest_poster(
        credentials,
        context,
        open_request,
        post_json_function,
):
    authorization = routeros_rest_authorization(
        credentials['username'],
        credentials['password'],
    )

    def post(
            url,
            body,
    ):
        return post_json_function(
            url,
            body,
            {
                'Authorization': authorization,
            },
            context,
            open_request,
        )

    return post

def create_routeros_rest_getter(
        credentials,
        context,
        open_request,
        get_json_function,
):
    authorization = routeros_rest_authorization(
        credentials['username'],
        credentials['password'],
    )

    def get(url):
        return get_json_function(
            url,
            {
                'Authorization': authorization,
            },
            context,
            open_request,
        )

    return get

def create_routeros_action_executor(
        base_url,
        post,
):
    def execute(script_name):
        return execute_routeros_script(
            base_url,
            script_name,
            post,
        )

    return execute

def routeros_update_available(
        base_url,
        get,
):
    update = get(
        base_url + '/rest/system/package/update'
    )

    installed_version = update.get(
        'installed-version'
    )
    latest_version = update.get(
        'latest-version'
    )
    status = update.get(
        'status'
    )

    return (
            isinstance(installed_version, str)
            and installed_version != ''
            and isinstance(latest_version, str)
            and latest_version != ''
            and installed_version != latest_version
            and status == 'New version is available'
    )

def get_json(
        url,
        headers,
        context,
        open_request,
):
    request = urllib.request.Request(
        url,
        headers=headers,
        method='GET',
    )

    response = open_request(
        request,
        context=context,
    )

    return json.loads(
        response.read().decode('utf-8')
    )
