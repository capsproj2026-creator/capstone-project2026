<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class DarkModeCssTest extends TestCase
{
    private function css(): string
    {
        $path = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'css'.DIRECTORY_SEPARATOR.'app.css';
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function test_dark_mode_covers_tinted_surfaces_and_nested_panels(): void
    {
        $css = $this->css();

        $this->assertStringContainsString('html.dark .portal-shell .bg-white\\/95', $css);
        $this->assertStringContainsString('html.dark .bg-amber-50', $css);
        $this->assertStringContainsString('html.dark .bg-emerald-50', $css);
        $this->assertStringContainsString('html.dark .bg-rose-50', $css);
        $this->assertStringContainsString('html.dark .bg-purple-50', $css);
        $this->assertStringContainsString('html.dark .bg-violet-50', $css);
        $this->assertStringContainsString('html.dark .peer:checked ~ .peer-checked\\:bg-indigo-600', $css);
        $this->assertStringContainsString('html.dark span.absolute.rounded-full.bg-white', $css);
        $this->assertStringContainsString('.settings-subnav__tab--active', $css);
        $this->assertStringContainsString('.zone-role-panel', $css);
        $this->assertStringContainsString('.zone-access-savebar', $css);
        $this->assertStringContainsString('.account-overview-hero', $css);
        $this->assertStringContainsString('html.dark .focus\\:bg-white:focus', $css);
        $this->assertMatchesRegularExpression('/\.parking-zone-snapshot img\s*\{[^}]*object-fit:\s*contain/s', $css);
        $this->assertMatchesRegularExpression('/\.parking-zone-thumb\s*\{[^}]*object-fit:\s*contain/s', $css);
    }
}
