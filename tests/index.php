<?php

use flight\debug\tracy\TracyExtensionLoader;
use Ghostff\Session\Session;
use Tracy\Debugger;

require(__DIR__ . '/../vendor/autoload.php');

Flight::register('session', Session::class);

Flight::route('/', function() {
	Flight::session()->set('test', 'test');
	Flight::session()->commit();
	Flight::response()->header('X-Test', 'test');
	echo "<h1>I work</h1>";
});

Debugger::enable(Debugger::Development);
Flight::set('flight.content_length', false);
new TracyExtensionLoader(Flight::app(), [ 'session_data' => Flight::session()->getAll() ]);

Flight::start();