<?php

declare(strict_types=1);

namespace Tests;

use flight\debug\database\PdoQueryCapture;
use flight\debug\tracy\DatabaseExtension;
use PHPUnit\Framework\TestCase;

class DatabaseExtensionTest extends TestCase
{
	protected function setUp(): void
	{
		// Ensure clean static state for every test (pure data driven)
		PdoQueryCapture::$query_data = [];
	}

	public function testGetTabWithNoQueriesShowsZeroCount(): void
	{
		$panel = new DatabaseExtension();

		$tab = $panel->getTab();

		$this->assertStringContainsString('0 / 0', $tab);
		$this->assertStringNotContainsString('long queries', $tab);
		$this->assertStringContainsString('bi-server', $tab);
	}

	public function testGetTabWithQueriesCalculatesTotalTimeAndCount(): void
	{
		PdoQueryCapture::$query_data = [
			uniqid('', true) => ['execution_time' => 0.0123, 'prepare_time' => 0.002, 'rows' => 5],
			uniqid('', true) => ['execution_time' => 0.045, 'rows' => 1],
		];

		$panel = new DatabaseExtension();
		$tab = $panel->getTab();

		// total ~ 0.0593 rounded to 4 decimals -> 0.0593
		$this->assertStringContainsString('0.0593 / 2', $tab);
	}

	public function testGetTabShowsLongQueryWarningWhenOverThreshold(): void
	{
		PdoQueryCapture::$query_data = [
			uniqid('', true) => ['execution_time' => 0.6, 'rows' => 10], // > 0.5
			uniqid('', true) => ['execution_time' => 0.1, 'rows' => 3],
		];

		$panel = new DatabaseExtension();
		$tab = $panel->getTab();

		$this->assertStringContainsString('1 long queries!', $tab);
		$this->assertStringContainsString('text-danger', $tab);
	}

	public function testGetPanelWithNoQueriesShowsHelpfulMessage(): void
	{
		$panel = new DatabaseExtension();
		$html = $panel->getPanel();

		$this->assertStringContainsString('No queries were run', $html);
		$this->assertStringContainsString('Database Queries', $html);
	}

	public function testGetPanelRendersQueryRowsWithEscapedContent(): void
	{
		$key = uniqid('', true);
		PdoQueryCapture::$query_data[$key] = [
			'query' => "SELECT * FROM users WHERE name = 'O''Reilly' <script>",
			'execution_time' => 0.0042,
			'prepare_time' => 0,
			'params' => [],
			'backtrace' => [['file' => '/app/test.php', 'line' => 42]],
			'rows' => 7,
		];

		$panel = new DatabaseExtension();
		$html = $panel->getPanel();

		$this->assertStringContainsString('0.0042', $html);
		$this->assertStringContainsString('7', $html); // rows
		// Content should be handled (long string logic or escaped). Note: SQL '' for literal ' becomes &#039;&#039;
		$this->assertStringContainsString('O&#039;&#039;Reilly', $html);
		$this->assertStringContainsString('&lt;script&gt;', $html);
	}

	public function testGetPanelHighlightsLongQueryRow(): void
	{
		$key = uniqid('', true);
		PdoQueryCapture::$query_data[$key] = [
			'query' => 'SELECT SLEEP(1)',
			'execution_time' => 1.23,
			'prepare_time' => 0,
			'params' => [],
			'backtrace' => [],
			'rows' => 0,
		];

		$panel = new DatabaseExtension();
		$html = $panel->getPanel();

		$this->assertStringContainsString('background-color: coral', $html);
	}

	public function testGetPanelIncludesPrepareTimeInDisplayedTime(): void
	{
		$key = uniqid('', true);
		PdoQueryCapture::$query_data[$key] = [
			'query' => 'SELECT 1',
			'execution_time' => 0.001,
			'prepare_time' => 0.009,
			'params' => [],
			'backtrace' => [],
			'rows' => 1,
		];

		$panel = new DatabaseExtension();
		$html = $panel->getPanel();

		// 0.01 total
		$this->assertStringContainsString('0.01', $html);
	}
}
