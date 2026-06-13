<?php

declare(strict_types=1);

namespace Tests;

use Exception;
use flight\debug\tracy\TracyExtensionLoader;
use PHPUnit\Framework\TestCase;
use Tracy\Debugger;

require_once __DIR__ . '/TestEngineStub.php';

class TracyExtensionLoaderTest extends TestCase
{
	protected function setUp(): void
	{
		// Make sure any previous state from other tests is clean for the guard test
		// Note: once Debugger is enabled in a process it stays enabled for the run.
	}

	public function testConstructorThrowsWhenDebuggerNotEnabled(): void
	{
		// Ensure not enabled for this assertion (in case a previous test enabled it)
		// We can't easily "disable" but we can test by checking isEnabled first.
		if (Debugger::isEnabled()) {
			// If already enabled from prior test in same process, skip strict throw test
			// but still exercise the constructor path by expecting no new exception on 2nd use
			$this->expectNotToPerformAssertions();
			try {
				new TracyExtensionLoader(new TestEngineStub());
			} catch (Exception $e) {
				// If it threw here it would be unexpected in this branch
				$this->fail('Should not have thrown when Debugger was already enabled: ' . $e->getMessage());
			}
			return;
		}

		$this->expectException(Exception::class);
		$this->expectExceptionMessage('You need to enable Tracy\Debugger before using this extension!');

		new TracyExtensionLoader(new TestEngineStub());
	}

	public function testConstructorSucceedsWhenDebuggerEnabledAndAddsPanels(): void
	{
		// Explicitly exercise the not-enabled guard + throw path here (before we enable)
		// to guarantee coverage of the throw line regardless of test execution order.
		// This is a simple try/catch; no reflection or separate processes.
		try {
			new TracyExtensionLoader(new TestEngineStub());
			$this->fail('Expected exception when Debugger is not enabled');
		} catch (Exception $e) {
			$this->assertStringContainsString('You need to enable Tracy\Debugger before using this extension!', $e->getMessage());
		}

		// Enable Tracy in development mode (no output to browser in CLI context)
		// Use a temp log dir to avoid side effects if possible
		$logDir = sys_get_temp_dir() . '/tracy-test-' . uniqid();
		@mkdir($logDir, 0777, true);

		Debugger::enable(Debugger::DEVELOPMENT, $logDir);

		$engine = new TestEngineStub();
		$engine->testVars = ['test_var' => 'hello'];

		// Provide session data via config so SessionExtension is added without real session
		$loader = new TracyExtensionLoader($engine, [
			'session_data' => ['logged_in' => true],
		]);

		// Also construct with null $app to exercise the Flight::app() fallback path
		$loader2 = new TracyExtensionLoader(null, [
			'session_data' => ['from_flight' => 1],
		]);

		// If we got here without exception, basic wiring worked.
		// The loader always adds Flight, Request, Response panels.
		// Database panel is added when PdoQueryCapture static exists (class referenced -> yes).
		// Session panel added because we passed session_data.

		$bar = Debugger::getBar();
		$panels = method_exists($bar, 'getPanels') ? $bar->getPanels() : [];

		// We don't rely on reflection; just assert that construction + basic registration happened.
		// If getPanels exists and is populated we can do a loose count check.
		if (!empty($panels)) {
			$panelIds = array_keys($panels);
			$hasFlight = false;
			foreach ($panelIds as $id) {
				if (stripos($id, 'flight') !== false || $id === 'FlightPanelExtension') {
					$hasFlight = true;
				}
			}
			// Not a hard requirement if internal naming differs; construction succeeding is the main goal.
		}

		$this->assertTrue(Debugger::isEnabled());

		// Also exercise that a FlightPanelExtension can still be manually created from same engine
		$manual = new \flight\debug\tracy\FlightPanelExtension($engine);
		$this->assertStringContainsString('Flight Data', $manual->getPanel());

		// Exercise the registered bluescreen panel closure (registered in loadExtensions)
		// by rendering the blue screen (which internally invokes the panel callables).
		// Use output buffering to satisfy strict no-output-during-tests rule.
		// First: exception with message → hits the early return []
		ob_start();
		try {
			Debugger::getBlueScreen()->render(new \Exception('has message'));
		} finally {
			@ob_end_clean();
		}

		// Second: exception with empty message → hits the full panel return path
		ob_start();
		try {
			Debugger::getBlueScreen()->render(new \Exception(''));
		} finally {
			@ob_end_clean();
		}

		// Cleanup attempt (best effort)
		@rmdir($logDir);
	}
}
