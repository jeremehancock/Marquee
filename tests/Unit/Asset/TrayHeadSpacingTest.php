<?php

declare(strict_types=1);

namespace App\Tests\Unit\Asset;

use PHPUnit\Framework\TestCase;

/**
 * A shape tripwire, in the family of {@see TraySurfaceTest} and
 * {@see AlertGlyphClearanceTest}.
 *
 * Every tray in Marquee slides up from the bottom edge wearing the same grab
 * handle, so a user reads them as one component appearing in one way. They are
 * built from two lineages that share nothing else. `.sheet__panel` is a tray at
 * every width — Sort, Import from Plex, Orphaned posters, Settings, Plex
 * Connection, Actions, Poster actions — and heads itself with
 * `.sheet__head` and a `.sheet__title`
 * span. `.modal__panel` is a centred dialog above 640px and a tray below it —
 * Change poster, the confirmations, Support development — and heads itself with
 * `.modal__head` and an `<h2>`. The two head rules are 400 lines apart and
 * nothing in the stylesheet connects them.
 *
 * So they drifted. The dialog head padded 2px above its title where the tray
 * head padded 14px, and the titles landed 9px apart below an identical handle.
 * Nothing failed, nothing looked broken in isolation, and the difference is only
 * visible when the trays are opened one after another — which is why it survived
 * a year and why a tripwire is the cheap way to hold it.
 *
 * **Equal padding is not equal spacing, and that is the first assertion worth
 * having.** The distance the eye measures runs from the handle to the title's
 * glyphs, not to the top of its line box, so the title's half-leading is part of
 * it: 2.4px at the old 16px/1.3, 5.28px at 1.1rem/1.6. Two heads padded
 * identically but setting their titles differently still hold them 3px apart.
 * Matching the type is therefore half the contract, and it is the half a future
 * edit is most likely to undo, because restoring a `line-height` override
 * changes nothing about the padding anyone would think to check.
 *
 * **The second is that a head must be sized by its title.** `.support-ask__head`
 * is a `1fr auto 1fr` grid holding a 40px heart tile, and a grid row sizes to its
 * tallest occupant — so the tile, not the title, decided how tall that head was,
 * and the title centred 5.9px below where every other tray's title sits. That
 * fault cannot be seen from inside the head that causes it: the tile and the
 * heading look correctly balanced against each other, because they are. Only the
 * next tray opened reveals it. Any future head that adds an icon or a badge does
 * the same thing, which is why the row is pinned to one line of the title's type
 * rather than left to whatever the head happens to hold.
 *
 * The assertions below are about relationships, not coordinates — the same
 * discipline the sibling files keep. There is no rendered pixel here to check
 * against; whether the result looks right is verified by hand against the :dev
 * image. This pins the arrangement that lets it.
 */
final class TrayHeadSpacingTest extends TestCase
{
    /**
     * The stylesheet sets no `html` font-size, so a `rem` is the browser default.
     * {@see remToPixels} asserts that stays true rather than trusting it.
     */
    private const ROOT_FONT_SIZE = 16.0;

    private const PHONE_BLOCK = '@media (max-width: 640px) {';

    /**
     * Both tray families must pad their heads the same distance above the title,
     * or an identical grab handle sits an identical distance above two titles
     * that are not in the same place.
     */
    public function testBothTrayFamiliesPadTheirHeadsAlike(): void
    {
        self::assertSame(
            $this->paddingTop($this->rule($this->baseBlock(), '.sheet__head')),
            $this->paddingTop($this->rule($this->phoneBlock(), '.modal__head')),
            'A dialog docked to the bottom edge is a tray and wears the tray grab handle, '
            . 'so it must sit the same distance below it as .sheet__head does. These two '
            . 'rules are 400 lines apart and nothing else keeps them in step.',
        );
    }

    /**
     * The half of the contract that looks like a typography preference and is not.
     */
    public function testBothTrayFamiliesSetTheirTitlesAlike(): void
    {
        $sheetTitle = $this->rule($this->baseBlock(), '.sheet__title');

        self::assertSame(
            $this->declaration($this->rule($this->baseBlock(), '.modal__head h2'), 'font-size'),
            $this->declaration($sheetTitle, 'font-size'),
            'A tray title and a dialog title must be set at the same size, or the leading '
            . 'above their glyphs differs and the handle-to-title distance differs with it.',
        );

        self::assertDoesNotMatchRegularExpression(
            '/(?:^|[;\s])line-height\s*:/',
            $sheetTitle,
            '.sheet__title must inherit the body line-height, as .modal__head h2 does. '
            . 'An override here re-opens the gap by changing the title\'s half-leading, '
            . 'while leaving every padding value anyone would check untouched.',
        );
    }

    /**
     * The row pin, asserted as a derivation rather than as a number: `1lh` follows
     * the type, a length would have to be re-tuned alongside it and silently would
     * not be.
     */
    public function testTheSupportHeadIsSizedByItsTitleRatherThanItsMark(): void
    {
        $head = $this->rule($this->phoneBlock(), '.support-ask__head');

        self::assertMatchesRegularExpression(
            '/grid-template-rows:\s*[\d.]+lh\s*;/',
            $head,
            'The head must state its row height in `lh` so it stays one line of the '
            . 'title\'s type. Left to size itself, the row takes the height of the 40px '
            . 'heart tile and carries the title 5.9px below every other tray\'s.',
        );

        self::assertNotSame(
            '',
            $this->declaration($head, 'font-size'),
            '`1lh` resolves against the element it is written on, not against the grid '
            . 'item holding the title. Without a font-size here the head keeps the '
            . 'inherited 16px, the row computes 25.6px instead of 28.2px, and the title '
            . 'is misaligned by a margin small enough to look deliberate.',
        );
    }

    /**
     * The rotation of {@see AlertGlyphClearanceTest}: there, a head must pad
     * further from the left than its glyph reaches; here, further from the top
     * than its adornment overhangs.
     */
    public function testTheSupportHeadPadsFurtherThanItsMarkOverhangs(): void
    {
        $head = $this->rule($this->phoneBlock(), '.support-ask__head');

        $lineBox = $this->remToPixels($this->declaration($head, 'font-size'))
            * $this->lineHeightOfBody();
        $mark = $this->pixels($this->declaration($this->rule($this->baseBlock(), '.support-ask__mark'), 'height'));

        self::assertGreaterThan(
            $lineBox,
            $mark,
            'The mark is meant to be taller than the line it sits on — that is why the '
            . 'row has to be pinned. If it ever fits inside the row, this file is '
            . 'guarding a problem that no longer exists and should be revisited.',
        );

        self::assertGreaterThan(
            ($mark - $lineBox) / 2,
            $this->paddingTop($this->rule($this->phoneBlock(), '.modal__head')),
            'The mark overflows its row equally above and below. The head must pad '
            . 'further than that overhang or the tile crowds the grab handle above it — '
            . 'which is what 2px of padding did, close enough to read as a mistake.',
        );
    }

    /** The first value of a `padding` shorthand: the side facing the grab handle. */
    private function paddingTop(string $rule): float
    {
        $sides = preg_split('/\s+/', trim($this->declaration($rule, 'padding')));
        self::assertIsArray($sides);
        self::assertNotSame('', $sides[0] ?? '', 'Expected a padding shorthand stating a top value.');

        return $this->pixels($sides[0]);
    }

    /**
     * One declaration's value, or an empty string when the rule does not state it.
     *
     * Anchored so `height` is never read out of `line-height`, and `font-size`
     * never out of a custom property that ends in the same word.
     */
    private function declaration(string $rule, string $property): string
    {
        if (preg_match('/(?:^|[;{\s])' . preg_quote($property, '/') . '\s*:\s*([^;]+);/', $rule, $m) !== 1) {
            return '';
        }

        return trim($m[1]);
    }

    private function pixels(string $value): float
    {
        return (float) rtrim(trim($value), 'px');
    }

    private function remToPixels(string $value): float
    {
        self::assertDoesNotMatchRegularExpression(
            '/^[ \t]*html\s*(?:,[^{}]*)?\{[^{}]*font-size\s*:/m',
            $this->withoutComments($this->stylesheet()),
            'The rem-to-pixel arithmetic below assumes the browser default root size. '
            . 'A declared html font-size makes that assumption wrong.',
        );

        return (float) rtrim(trim($value), 'rem') * self::ROOT_FONT_SIZE;
    }

    /** What every tray head and title inherits, and therefore what `1lh` multiplies. */
    private function lineHeightOfBody(): float
    {
        $value = $this->declaration($this->rule($this->baseBlock(), 'body'), 'line-height');
        self::assertNotSame('', $value, 'body must declare the line-height that tray titles inherit.');

        return (float) $value;
    }

    /** Everything above the phone block: the rules the phone block overrides. */
    private function baseBlock(): string
    {
        $css = $this->withoutComments($this->stylesheet());
        $start = strpos($css, self::PHONE_BLOCK);
        self::assertIsInt($start, 'The phone block must remain an @media (max-width: 640px) section.');

        return substr($css, 0, $start);
    }

    /**
     * Inside the phone block, and this is the point of the helper.
     *
     * `.modal__head` and `.support-ask__head` each have a rule in both scopes with
     * an identical selector. A match against the whole stylesheet finds the base
     * rule first — which for `.modal__head` states no padding at all — so a test
     * written without this slice passes green against the wrong rule.
     */
    private function phoneBlock(): string
    {
        $css = $this->withoutComments($this->stylesheet());
        $start = strpos($css, self::PHONE_BLOCK);
        self::assertIsInt($start, 'The phone block must remain an @media (max-width: 640px) section.');

        $open = $start + strlen(self::PHONE_BLOCK) - 1;
        $depth = 0;

        for ($i = $open, $length = strlen($css); $i < $length; $i++) {
            if ($css[$i] === '{') {
                $depth++;

                continue;
            }

            if ($css[$i] === '}' && --$depth === 0) {
                return substr($css, $open + 1, $i - $open - 1);
            }
        }

        self::fail('The phone block is never closed.');
    }

    /**
     * The declarations of one rule, found by a selector at the head of a selector
     * list. Anchored to the start of a line so a descendant selector is never
     * mistaken for the rule it hangs off.
     */
    private function rule(string $css, string $selector): string
    {
        $pattern = '/^[ \t]*' . preg_quote($selector, '/') . '\s*(?:,[^{}]*)?\{([^{}]*)\}/m';
        self::assertSame(1, preg_match($pattern, $css, $m), sprintf('Expected a rule for "%s".', $selector));

        return $m[1];
    }

    /**
     * Comments are stripped everywhere. This stylesheet explains itself heavily,
     * and a property or selector named in prose would otherwise be matched as
     * though it were a declaration.
     */
    private function withoutComments(string $css): string
    {
        return (string) preg_replace('#/\*.*?\*/#s', '', $css);
    }

    private function stylesheet(): string
    {
        $path = dirname(__DIR__, 3) . '/public/assets/app.css';
        $source = file_get_contents($path);
        self::assertIsString($source, 'app.css must be readable at ' . $path);

        return $source;
    }
}
