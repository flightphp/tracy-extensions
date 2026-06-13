<?php

declare(strict_types=1);

namespace Tests;

use flight\debug\tracy\ResponseExtension;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/TestEngineStub.php';

class ResponseExtensionTest extends TestCase
{
	public function testGetTabShowsStatusCode(): void
	{
		$engine = new TestEngineStub();
		$engine->testResponse = new class {
			public function getBody(): string { return 'ok'; }
			public function getHeaders(): array { return []; }
			public function status($code = null) { return 201; }
		};

		$panel = new ResponseExtension($engine);
		$tab = $panel->getTab();

		$this->assertStringContainsString('201', $tab);
		$this->assertStringContainsString('bi-box-seam-fill', $tab);
	}

	public function testGetPanelRendersBodyHeadersAndStatus(): void
	{
		$engine = new TestEngineStub();
		$engine->testResponse = new class {
			public function getBody(): string { return '<html>Hello</html>'; }
			public function getHeaders(): array { return ['Content-Type' => 'text/html', 'X-Custom' => 'yes']; }
			public function status($code = null) { return 200; }
		};

		$panel = new ResponseExtension($engine);
		$html = $panel->getPanel();

		$this->assertStringContainsString('Response', $html);
		$this->assertStringContainsString('Body', $html);
		$this->assertStringContainsString('&lt;html&gt;Hello&lt;/html&gt;', $html); // escaped via handleLongStrings
		$this->assertStringContainsString('Headers', $html);
		$this->assertStringContainsString('Content-Type', $html);
		$this->assertStringContainsString('Status Code', $html);
		$this->assertStringContainsString('200', $html);
	}

	public function testGetPanelSortsResponseDataKeys(): void
	{
		$engine = new TestEngineStub();
		$engine->testResponse = new class {
			public function getBody(): string { return ''; }
			public function getHeaders(): array { return []; }
			public function status($code = null) { return 404; }
		};

		$panel = new ResponseExtension($engine);
		$html = $panel->getPanel();

		// Keys are Body, Headers, Status Code - natural sort order
		$posBody = strpos($html, '>Body<');
		$posHeaders = strpos($html, '>Headers<');
		$posStatus = strpos($html, '>Status Code<');
		$this->assertNotFalse($posBody);
		$this->assertNotFalse($posHeaders);
		$this->assertNotFalse($posStatus);
		$this->assertLessThan($posHeaders, $posBody);
		$this->assertLessThan($posStatus, $posHeaders);
	}
}
