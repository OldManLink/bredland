#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/lib/testlib.php';

assertSame("42", "4" . "2");
assertNotSame("73", "3" . "7");
