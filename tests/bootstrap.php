<?php

$_tests_dir = dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit';

// Point to our test config.
putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-tests-config.php' );

// Load Composer autoloader.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Load the WP testing framework.
require_once $_tests_dir . '/includes/bootstrap.php';

// Load test helpers.
require_once __DIR__ . '/helpers/class-test-model.php';
require_once __DIR__ . '/helpers/class-test-child-model.php';
require_once __DIR__ . '/helpers/class-test-parent-model.php';
require_once __DIR__ . '/helpers/class-test-importer.php';
require_once __DIR__ . '/helpers/class-test-controller.php';
