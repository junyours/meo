<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tighten\Ziggy\BladeRouteGenerator;

class ZiggyRouteManifestTest extends TestCase
{
    public function test_ziggy_manifest_only_includes_frontend_routes(): void
    {
        $generator = new BladeRouteGenerator();
        $output = $generator->generate();

        $this->assertStringContainsString('"dashboard"', $output);
        $this->assertStringContainsString('"admin.dashboard"', $output);
        $this->assertStringContainsString('"superadmin.dashboard"', $output);
        $this->assertStringNotContainsString('sanctum.csrf-cookie', $output);
        $this->assertStringNotContainsString('ignition.updateConfig', $output);
    }
}
