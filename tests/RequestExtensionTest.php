<?php

declare(strict_types=1);

namespace Tests;

use flight\debug\tracy\RequestExtension;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/TestEngineStub.php';

class RequestExtensionTest extends TestCase
{
	private array $originalServer;
	private array $originalGet;
	private array $originalPost;
	private array $originalFiles;

	protected function setUp(): void
	{
		$this->originalServer = $_SERVER;
		$this->originalGet = $_GET;
		$this->originalPost = $_POST;
		$this->originalFiles = $_FILES;

		// Provide minimal safe defaults so getTab() etc don't blow up on missing keys
		$_SERVER = [
			'REQUEST_METHOD' => 'GET',
			'REQUEST_URI' => '/test/path?foo=1',
			'REMOTE_ADDR' => '127.0.0.1',
			'HTTP_HOST' => 'example.test',
			'PHP_SELF' => '/index.php',
			'USER' => 'www-data',
		];
		$_GET = [];
		$_POST = [];
		$_FILES = [];
	}

	protected function tearDown(): void
	{
		$_SERVER = $this->originalServer;
		$_GET = $this->originalGet;
		$_POST = $this->originalPost;
		$_FILES = $this->originalFiles;
	}

	public function testGetTabUsesRequestUri(): void
	{
		$engine = new TestEngineStub();
		$panel = new RequestExtension($engine);

		$tab = $panel->getTab();

		$this->assertStringContainsString('/test/path', $tab);
		$this->assertStringContainsString('bi-caret-right-square-fill', $tab);
	}

	public function testGetPanelRendersCommonFieldsAndPayloads(): void
	{
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI'] = '/submit';
		$_GET = ['q' => 'search'];
		$_POST = ['name' => 'Bob'];
		$_FILES = ['upload' => ['name' => 'file.txt']];

		$engine = new TestEngineStub();
		$panel = new RequestExtension($engine);
		$html = $panel->getPanel();

		$this->assertStringContainsString('Request', $html);
		$this->assertStringContainsString('REQUEST_METHOD', $html);
		$this->assertStringContainsString('POST', $html);
		$this->assertStringContainsString('REQUEST_URI', $html);
		$this->assertStringContainsString('/submit', $html);
		$this->assertStringContainsString('GET', $html);
		$this->assertStringContainsString('search', $html);
		$this->assertStringContainsString('POST', $html);
		$this->assertStringContainsString('Bob', $html);
		$this->assertStringContainsString('FILES', $html);
	}

	public function testGetPanelIncludesProxyIpAndAllServerVars(): void
	{
		$engine = new TestEngineStub();
		$engine->testRequest = new class {
			public function getProxyIpAddress(): string
			{
				return '10.0.0.5';
			}
		};

		$_SERVER['X-FORWARDED-FOR'] = '1.2.3.4'; // extra server var

		$panel = new RequestExtension($engine);
		$html = $panel->getPanel();

		$this->assertStringContainsString('PROXY_IP_ADDRESS', $html);
		$this->assertStringContainsString('10.0.0.5', $html);
		$this->assertStringContainsString('X-FORWARDED-FOR', $html);
	}

	public function testLongPayloadsGetToggleHandling(): void
	{
		$_GET = ['data' => str_repeat('a', 100)];

		$engine = new TestEngineStub();
		$panel = new RequestExtension($engine);
		$html = $panel->getPanel();

		$this->assertStringContainsString('tracy-toggle', $html);
	}
}
