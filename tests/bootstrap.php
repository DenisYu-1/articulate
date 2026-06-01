<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Eager-initialize DB connections once for the entire test run so failures
// surface here as a clear startup error, not buried in individual test output.
Articulate\Tests\ConnectionPool::getInstance();
