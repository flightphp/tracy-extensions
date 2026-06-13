<?php

declare(strict_types=1);

namespace Tests;

use flight\debug\tracy\SessionExtension;
use PHPUnit\Framework\TestCase;

class SessionExtensionTest extends TestCase
{
	protected function setUp(): void
	{
		// Start with clean session superglobal for tests that rely on fallback
		$_SESSION = [];
	}

	public function testGetTabAlwaysShowsSessionLabel(): void
	{
		$ext = new SessionExtension([]);
		$tab = $ext->getTab();

		$this->assertStringContainsString('Session', $tab);
		$this->assertStringContainsString('bi-archive-fill', $tab);
	}

	public function testGetPanelWithEmptyDataRendersEmptyTable(): void
	{
		$ext = new SessionExtension([]);
		$html = $ext->getPanel();

		$this->assertStringContainsString('SESSION Data', $html);
		// No data rows expected
		$this->assertStringNotContainsString('<tr><td>', $html); // would have data rows if present
	}

	public function testGetPanelRendersProvidedSessionData(): void
	{
		$data = [
			'user_id' => 42,
			'name' => 'Alice',
			'roles' => ['admin', 'user'],
		];

		$ext = new SessionExtension($data);
		$html = $ext->getPanel();

		$this->assertStringContainsString('user_id', $html);
		$this->assertStringContainsString('42', $html);
		$this->assertStringContainsString('Alice', $html);
		$this->assertStringContainsString('Array', $html); // roles array rendered via print_r in handleLongStrings
	}

	public function testGetPanelSortsKeysNaturally(): void
	{
		$data = ['b' => 2, 'a' => 1, 'c10' => 3, 'c2' => 4];

		$ext = new SessionExtension($data);
		$html = $ext->getPanel();

		// Natural sort means c2 before c10
		$posC2 = strpos($html, '>c2<');
		$posC10 = strpos($html, '>c10<');
		$this->assertNotFalse($posC2);
		$this->assertNotFalse($posC10);
		$this->assertLessThan($posC10, $posC2);
	}

	public function testGetPanelExtractsGhostffSessionFormat(): void
	{
		// Ghostff/Session stores under special key
		$data = [
			':' => [
				0 => [
					'real_key' => 'real_value',
					'count' => 5,
				],
			],
			'other' => 'ignored',
		];

		$ext = new SessionExtension($data);
		$html = $ext->getPanel();

		$this->assertStringContainsString('real_key', $html);
		$this->assertStringContainsString('real_value', $html);
		$this->assertStringContainsString('count', $html);
		$this->assertStringNotContainsString('other', $html);
	}

	public function testConstructorFallsBackToSessionSuperglobalWhenEmpty(): void
	{
		$_SESSION = ['from_global' => 'yes'];

		$ext = new SessionExtension(); // no arg -> uses $_SESSION
		$html = $ext->getPanel();

		$this->assertStringContainsString('from_global', $html);
		$this->assertStringContainsString('yes', $html);
	}

	public function testLongValuesAreHandledWithToggle(): void
	{
		$data = ['big' => str_repeat('z', 100)];

		$ext = new SessionExtension($data);
		$html = $ext->getPanel();

		$this->assertStringContainsString('tracy-toggle', $html);
		$this->assertStringContainsString('zzz...', $html);
	}
}
