<?php

declare(strict_types=1);

namespace App\Tests\Unit\Meta;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Keeps the instructions Claude Code loads at session start small. Every byte in CLAUDE.md is re-read on every
 * request; detail belongs in .claude/*.md, which agents read on demand.
 */
#[CoversNothing]
final class InstructionBudgetTest extends TestCase
{
    private const int MAX_CLAUDE_MD_BYTES = 12 * 1024;

    private const int MAX_SESSION_START_BYTES = 16 * 1024;

    #[Test]
    public function claudeMdStaysWithinBudget(): void
    {
        $size = filesize($this->root() . '/CLAUDE.md');

        self::assertNotFalse($size);
        self::assertLessThanOrEqual(
            self::MAX_CLAUDE_MD_BYTES,
            $size,
            'CLAUDE.md is too large; move detail into .claude/*.md and link it.',
        );
    }

    #[Test]
    public function sessionStartLoadStaysWithinBudget(): void
    {
        $claudeMd = (string) file_get_contents($this->root() . '/CLAUDE.md');
        $total = \strlen($claudeMd);

        // "@path" lines are imported into the session start context together with CLAUDE.md
        preg_match_all('/^@(\S+)/m', $claudeMd, $matches);
        foreach ($matches[1] as $import) {
            $path = $this->root() . '/' . $import;
            self::assertFileExists($path, \sprintf('Imported file %s is missing', $import));
            $total += (int) filesize($path);
        }

        self::assertLessThanOrEqual(self::MAX_SESSION_START_BYTES, $total);
    }

    #[Test]
    public function hookScriptsReferencedInSettingsExist(): void
    {
        $settings = (string) file_get_contents($this->root() . '/.claude/settings.json');

        preg_match_all('#\.claude/hooks/[\w.-]+#', $settings, $matches);
        self::assertNotEmpty($matches[0], 'settings.json references no hook scripts');
        foreach ($matches[0] as $hook) {
            self::assertFileExists($this->root() . '/' . $hook);
        }
    }

    private function root(): string
    {
        return \dirname(__DIR__, 3);
    }
}
