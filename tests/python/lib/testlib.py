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

def fail(message):
    raise AssertionFailed(message)

def assert_different(expected, actual, message=''):
    if expected == actual:
        detail = (
                'Different assertion failed'
                + (': ' + message if message else '')
                + '\nExpected: {!r}\nActual:   {!r}'.format(
            expected,
            actual,
        )
        )

        raise AssertionFailed(detail)


def assert_true(actual, message=''):
    assert_same(
        True,
        actual,
        message,
    )


def assert_false(actual, message=''):
    assert_same(
        False,
        actual,
        message,
    )


def assert_string_starts_with(expected_prefix, actual, message=''):
    assert_true(
        isinstance(actual, str),
        'Actual value must be a string',
    )

    if not actual.startswith(expected_prefix):
        detail = (
                'String-starts-with assertion failed'
                + (': ' + message if message else '')
                + '\nExpected prefix: {!r}\nActual:          {!r}'.format(
            expected_prefix,
            actual,
        )
        )

        raise AssertionFailed(detail)


def assert_string_contains(expected_substring, actual, message=''):
    assert_true(
        isinstance(actual, str),
        'Actual value must be a string',
    )

    if expected_substring not in actual:
        detail = (
                'String-contains assertion failed'
                + (': ' + message if message else '')
                + '\nExpected substring: {!r}\nActual:             {!r}'.format(
            expected_substring,
            actual,
        )
        )

        raise AssertionFailed(detail)

def assert_string_ends_with(expected_suffix, actual, message=''):
    assert_true(
        isinstance(actual, str),
        'Actual value must be a string',
    )

    if not actual.endswith(expected_suffix):
        detail = (
                'String-ends-with assertion failed'
                + (': ' + message if message else '')
                + '\nExpected suffix: {!r}\nActual:          {!r}'.format(
            expected_suffix,
            actual,
        )
        )

        raise AssertionFailed(detail)

def assert_throws(exception_class, expected_message, operation):
    try:
        operation()
    except Exception as error:
        assert_same(
            exception_class,
            type(error),
            'Unexpected exception type',
        )
        assert_same(
            expected_message,
            str(error),
        )
        return

    raise AssertionFailed(
        'Expected exception: {}\nExpected message: {!r}'.format(
            exception_class.__name__,
            expected_message,
        )
    )
