<?php

declare(strict_types=1);

namespace Tests;

/**
 * Minimal Engine stand-in for unit testing the Tracy panel extensions.
 * Extends the real Engine so it satisfies typed properties (Engine $app).
 * Only the methods/properties actually used by the panels are provided.
 * No reflection or mocking library used.
 */
class TestEngineStub extends \flight\Engine
{
	/** @var array */
	public array $testVars = [];

	/** @var object|null */
	public $testCurrentRoute = null;

	/** @var object|null */
	public $testRequest = null;

	/** @var object|null */
	public $testResponse = null;

	public function get($key = null)
	{
		if ($key === null) {
			return $this->testVars;
		}
		return $this->testVars[$key] ?? null;
	}

	public function router()
	{
		$route = $this->testCurrentRoute;
		return new class($route) {
			private $route;
			public function __construct($route)
			{
				$this->route = $route;
			}
			public function current()
			{
				return $this->route;
			}
		};
	}

	public function request()
	{
		if ($this->testRequest !== null) {
			return $this->testRequest;
		}
		return new class {
			public function getProxyIpAddress(): string
			{
				return '';
			}
		};
	}

	public function response()
	{
		if ($this->testResponse !== null) {
			return $this->testResponse;
		}
		return new class {
			public function getBody(): string
			{
				return '';
			}
			public function getHeaders(): array
			{
				return [];
			}
			public function status($code = null)
			{
				return $code ?? 200;
			}
		};
	}
}
