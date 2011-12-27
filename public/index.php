<?php
/**
 * Laravel - A PHP Framework For Web Artisans
 *
 * @package  Laravel
 * @version  2.0.6
 * @author   Taylor Otwell <taylorotwell@gmail.com>
 * @link     http://laravel.com
 */

// --------------------------------------------------------------
// Tick... Tock... Tick... Tock...
// --------------------------------------------------------------
define('LARAVEL_START', microtime(true));

// --------------------------------------------------------------
// The path to the application directory.
// --------------------------------------------------------------
$application = '../application';

// --------------------------------------------------------------
// The path to the bundles directory.
// --------------------------------------------------------------
$bundles = '../widgets';

// --------------------------------------------------------------
// The path to the storage directory.
// --------------------------------------------------------------
$storage = '../storage';

// --------------------------------------------------------------
// The path to the Laravel directory.
// --------------------------------------------------------------
$laravel = '../laravel';

// --------------------------------------------------------------
// The path to the public directory.
// --------------------------------------------------------------
$public = __DIR__;

// --------------------------------------------------------------
// Launch Laravel.
// --------------------------------------------------------------
require $laravel.'/laravel.php';

// echo number_format((microtime(true) - LARAVEL_START) * 1000, 2);
