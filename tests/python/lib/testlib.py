from test_suite_runner import AssertionFailed


def assert_same(expected, actual, message=''):
    if expected != actual:
        detail = (
                'Same assertion failed'
                + (': ' + message if message else '')
                + '\nExpected: {!r}\nActual:   {!r}'.format(
            expected,
            actual,
        )
        )

        raise AssertionFailed(detail)
