<?php

/**
 * PHPUnit bootstrap.
 *
 * Defines PHPUNIT_RUNNING so framework internals (e.g. Flow state reset) know
 * they run under the test harness, and disables the on-disk route cache so the
 * Matcher never writes cache artifacts during the suite.
 */

\define('PHPUNIT_RUNNING', true);

\putenv('DISABLE_FLUXOR_CACHE=true');
$_ENV['DISABLE_FLUXOR_CACHE'] = 'true';
$_SERVER['DISABLE_FLUXOR_CACHE'] = 'true';

require __DIR__ . '/../vendor/autoload.php';
