<?php

declare(strict_types=1);

namespace Tests;

use flight\debug\tracy\FlightPanelExtension;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/TestEngineStub.php';

class FlightPanelExtensionTest extends TestCase
{
	public function testGetTabAlwaysContainsFlightLabel(): void
	{
		$engine = new TestEngineStub();
		$panel = new FlightPanelExtension($engine);

		$tab = $panel->getTab();

		$this->assertStringContainsString('Flight', $tab);
		$this->assertStringContainsString('bi-file-zip-fill', $tab);
	}

	public function testGetPanelRendersRegisteredVars(): void
	{
		$engine = new TestEngineStub();
		$engine->testVars = [
			'my_setting' => 'abc123',
			'debug_mode' => true,
			'count' => 7,
		];

		$panel = new FlightPanelExtension($engine);
		$html = $panel->getPanel();

		$this->assertStringContainsString('Flight Data', $html);
		$this->assertStringContainsString('my_setting', $html);
		$this->assertStringContainsString('abc123', $html);
		$this->assertStringContainsString('debug_mode', $html);
		$this->assertStringContainsString('true', $html);
		$this->assertStringContainsString('count', $html);
	}

	public function testGetPanelSortsKeysNaturally(): void
	{
		$engine = new TestEngineStub();
		$engine->testVars = ['z' => 1, 'a' => 2, 'm10' => 3, 'm2' => 4];

		$panel = new FlightPanelExtension($engine);
		$html = $panel->getPanel();

		$posM2 = strpos($html, '>m2<');
		$posM10 = strpos($html, '>m10<');
		$this->assertNotFalse($posM2);
		$this->assertNotFalse($posM10);
		$this->assertLessThan($posM10, $posM2);
	}

	public function testGetPanelShowsObjectsAsClassNameOnly(): void
	{
		$engine = new TestEngineStub();
		$engine->testVars = [
			'user_service' => new \stdClass(),
		];

		$panel = new FlightPanelExtension($engine);
		$html = $panel->getPanel();

		$this->assertStringContainsString('stdClass Class', $html);
	}

	public function testGetPanelIncludesCurrentRouteDetailsWhenPresent(): void
	{
		$route = new class {
			public array $methods = ['GET', 'POST'];
			public array $params = ['id' => 5];
			public string $pattern = '/user/@id';
			public ?string $alias = 'user.view';
			public string $regex = '';
			public string $splat = '';
		};

		$engine = new TestEngineStub();
		$engine->testCurrentRoute = $route;

		$panel = new FlightPanelExtension($engine);
		$html = $panel->getPanel();

		$this->assertStringContainsString('Current Route', $html);
		$this->assertStringContainsString('Pattern: /user/@id', $html);
		$this->assertStringContainsString('Methods: GET, POST', $html);
		$this->assertStringContainsString('Alias:   user.view', $html);
	}

	public function testGetPanelHandlesNoCurrentRouteGracefully(): void
	{
		$engine = new TestEngineStub();
		$engine->testCurrentRoute = null;

		$panel = new FlightPanelExtension($engine);
		$html = $panel->getPanel();

		$this->assertStringContainsString('Current Route', $html);
		$this->assertStringContainsString('Pattern:', $html);
	}
}
