<?php

declare(strict_types=1);

namespace Tests;

use flight\debug\tracy\ExtensionBase;
use PHPUnit\Framework\TestCase;

class TestableExtensionBase extends ExtensionBase
{
	public function callHandleLongStrings($value): string
	{
		return $this->handleLongStrings($value);
	}

	public function callEllipsis(string $text, int $character_limit = 30): string
	{
		return $this->ellipsis($text, $character_limit);
	}

	public function getCurrentValueWidth(): int
	{
		return $this->value_width;
	}
}

class ExtensionBaseTest extends TestCase
{
	public function testDefaultValueWidthIs300(): void
	{
		$ext = new TestableExtensionBase();

		$this->assertSame(300, $ext->getCurrentValueWidth());
	}

	public function testSetValueWidthUpdatesWidth(): void
	{
		$ext = new TestableExtensionBase();
		$ext->setValueWidth(800);

		$this->assertSame(800, $ext->getCurrentValueWidth());
	}

	public function testEllipsisShortStringReturnsUnchanged(): void
	{
		$ext = new TestableExtensionBase();

		$this->assertSame('hello', $ext->callEllipsis('hello', 10));
		$this->assertSame('short', $ext->callEllipsis('short', 30));
	}

	public function testEllipsisLongStringTruncatesWithEllipsis(): void
	{
		$ext = new TestableExtensionBase();
		$text = 'this is a string that is definitely longer than the limit we will set';

		$result = $ext->callEllipsis($text, 20);

		$this->assertSame('this is a string tha...', $result);
		$this->assertLessThanOrEqual(23, mb_strlen($result)); // 20 + ...
	}

	public function testHandleLongStringsPrimitives(): void
	{
		$ext = new TestableExtensionBase();

		$this->assertSame('42', $ext->callHandleLongStrings(42));
		$this->assertSame('true', $ext->callHandleLongStrings(true));
		$this->assertSame('false', $ext->callHandleLongStrings(false));
		$this->assertSame('hello &amp; world', $ext->callHandleLongStrings('hello & world'));
	}

	public function testHandleLongStringsArrayUsesPrintR(): void
	{
		$ext = new TestableExtensionBase();
		$arr = ['a' => 1, 'b' => 'two'];

		$result = $ext->callHandleLongStrings($arr);

		$this->assertStringContainsString('Array', $result);
		$this->assertStringContainsString('1', $result);
		$this->assertStringContainsString('two', $result);
	}

	public function testHandleLongStringsObjectUsesPrintR(): void
	{
		$ext = new TestableExtensionBase();
		$obj = (object)['x' => 99];

		$result = $ext->callHandleLongStrings($obj);

		$this->assertStringContainsString('stdClass', $result);
		$this->assertStringContainsString('99', $result);
	}

	public function testHandleLongStringsShortStringNoToggle(): void
	{
		$ext = new TestableExtensionBase();
		$short = 'this is under sixty characters for sure';

		$result = $ext->callHandleLongStrings($short);

		$this->assertStringNotContainsString('tracy-toggle', $result);
		$this->assertStringNotContainsString('<pre', $result);
		$this->assertSame(htmlspecialchars($short), $result);
	}

	public function testHandleLongStringsLongStringAddsToggleAndPre(): void
	{
		$ext = new TestableExtensionBase();
		$long = str_repeat('x', 100);

		$result = $ext->callHandleLongStrings($long);

		$this->assertStringContainsString('tracy-toggle', $result);
		$this->assertStringContainsString('tracy-collapsed', $result);
		$this->assertStringContainsString('<pre id="tracy-request-panel-', $result);
		$this->assertStringContainsString('max-width: 300px', $result); // default width
		$this->assertStringContainsString('xxx...', $result); // the visible part
	}

	public function testHandleLongStringsRespectsCustomValueWidth(): void
	{
		$ext = new TestableExtensionBase();
		$ext->setValueWidth(500);
		$long = str_repeat('y', 80);

		$result = $ext->callHandleLongStrings($long);

		$this->assertStringContainsString('max-width: 500px', $result);
	}

	public function testHandleLongStringsTrimsNewlines(): void
	{
		$ext = new TestableExtensionBase();
		$multiline = "  line one  \n  line two  \n";

		$result = $ext->callHandleLongStrings($multiline);

		$this->assertStringContainsString("line one\nline two", $result);
		$this->assertStringNotContainsString('  line one  ', $result);
	}
}
