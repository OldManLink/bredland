import contextlib
import io
import os
import tempfile
import time
import urllib.error

from test_suite_runner import AssertionFailed
from builtins import (Exception, getattr, isinstance, setattr, str, type)

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

def assert_string_not_contains(unexpected_substring, actual, message=''):
    assert_true(
        isinstance(actual, str),
        'Actual value must be a string',
    )

    if unexpected_substring in actual:
        detail = (
                'String-not-contains assertion failed'
                + (': ' + message if message else '')
                + '\nUnexpected substring: {!r}\nActual:               {!r}'.format(
            unexpected_substring,
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


def assert_http_error(expected_status, operation, message=''):
    try:
        operation()
    except urllib.error.HTTPError as error:
        assert_same(
            expected_status,
            error.code,
            message,
        )

        return error
    except Exception as error:
        raise AssertionFailed(
            'Expected HTTP {}\nActual exception: {}: {}'.format(
                expected_status,
                type(error).__name__,
                error,
            )
        )

    raise AssertionFailed(
        'Expected HTTP {}'.format(
            expected_status,
        )
    )

def wait_for_stderr(stderr, expected):
    deadline = time.time() + 1

    while (
            expected not in stderr.getvalue()
            and time.time() < deadline
    ):
        time.sleep(0.01)

@contextlib.contextmanager
def capture_stderr():
    stream = io.StringIO()

    with contextlib.redirect_stderr(
        stream
    ):
        yield stream


@contextlib.contextmanager
def suppress_stderr():
    with capture_stderr():
        yield


@contextlib.contextmanager
def temporary_text_file(
        contents='',
        suffix='',
        directory=None,
):
    handle = tempfile.NamedTemporaryFile(
        mode='w',
        suffix=suffix,
        dir=directory,
        delete=False,
        encoding='utf-8',
    )

    path = handle.name

    try:
        with handle:
            handle.write(
                contents
            )

        yield path
    finally:
        if os.path.exists(path):
            os.remove(
                path
            )

@contextlib.contextmanager
def patched_attribute(
        target,
        name,
        value,
):
    original = getattr(
        target,
        name,
    )

    setattr(
        target,
        name,
        value,
    )

    try:
        yield
    finally:
        setattr(
            target,
            name,
            original,
        )