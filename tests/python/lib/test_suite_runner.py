import json
import os
import sys


class AssertionFailed(Exception):
    pass


class TestSuiteRunner:
    def __init__(self, suite_name):
        self.suite_name = suite_name
        self.tests_passed = 0
        self.tests_skipped = 0
        self.tests_failed = 0
        self.quiet = '-q' in sys.argv

    def test(self, description, operation=None):
        if operation is None:
            def register(test_operation):
                self.test(description, test_operation)
                return test_operation

            return register

        self._output('→ {}\n'.format(description))

        try:
            operation()
            self.tests_passed += 1
            self._output('✅ {}\n'.format(description))
        except AssertionFailed as error:
            self.tests_failed += 1
            sys.stdout.write('❌ {}\n'.format(description))
            sys.stdout.write('{}\n'.format(error))

    def skip(self, description, reason, operation=None):
        if operation is None:
            def register(test_operation):
                self.skip(
                    description,
                    reason,
                    test_operation,
                )
                return test_operation

            return register

        if not callable(operation):
            raise ValueError(
                'Skipped test must be callable: {}'.format(
                    description,
                )
            )
        
        self.tests_skipped += 1
        
        sys.stdout.write(
            '⚠️ {} — {}\n'.format(
                description,
                reason,
            )
        )

    def _output(self, message):
        if not self.quiet:
            sys.stdout.write(message)

    def finish(self):
        total = self.tests_passed + self.tests_skipped + self.tests_failed

        statistics_file = os.environ.get('TEST_STATISTICS_FILE')

        if statistics_file:
            statistics = {
                'suite': os.environ.get('TEST_SUITE_ID'),
                'status': 'failed' if self.tests_failed else 'passed',
                'tests': {
                    'run': total,
                    'skipped': self.tests_skipped,
                    'passed': self.tests_passed,
                    'failed': self.tests_failed,
                },
            }

            temporary_file = statistics_file + '.tmp'

            with open(temporary_file, 'w') as file:
                json.dump(
                    statistics,
                    file,
                    separators=(',', ':'),
                )

            os.replace(temporary_file, statistics_file)
        sys.stdout.write(
            '{}: {} tests run, {} passed, {} skipped, {} failed\n'.format(
                self.suite_name,
                total,
                self.tests_passed,
                self.tests_skipped,
                self.tests_failed,
            )
        )


        if self.tests_failed:
            sys.exit(1)
