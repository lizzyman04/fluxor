<?php

/**
 * PHPUnit bootstrap.
 *
 * Defines PHPUNIT_RUNNING so framework internals (e.g. Flow state reset) know
 * they run under the test harness. Route-matching tests construct the Router
 * with a null cache dir, so no on-disk cache artifacts are written.
 */

\define('PHPUNIT_RUNNING', true);

require __DIR__ . '/../vendor/autoload.php';
