<?php

namespace Tests\Architecture;

use Tests\TestCase;

/**
 * Arch §8/§26: Core MUST NOT depend on application modules.
 * PRD §39 scope boundary (v0.4): token APIs live in the dedicated API
 * repository — this web core MUST NOT expose them.
 */
class AuthModuleTest extends TestCase
{
    public function test_core_authentication_does_not_depend_on_app_modules(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path('Core/Authentication'), \FilesystemIterator::SKIP_DOTS)
        );

        $violations = [];
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $src = file_get_contents($file->getPathname());
            if (preg_match('/App\\\\Modules\\\\/', $src)) {
                $violations[] = $file->getPathname();
            }
        }

        $this->assertSame([], $violations, 'Core\\Authentication must not reference App\\Modules');
    }

    public function test_user_model_uses_uuid_soft_deletes_and_web_2fa(): void
    {
        $src = file_get_contents(app_path('Models/User.php'));
        foreach (['HasUuids', 'SoftDeletes', 'TwoFactorAuthenticatable', 'uuid7'] as $needle) {
            $this->assertStringContainsString($needle, $src, "User model must reference {$needle}");
        }

        $this->assertStringNotContainsString('HasApiTokens', $src, 'User model must not reference Sanctum tokens');
    }

    public function test_web_core_exposes_no_token_api_surface(): void
    {
        $this->assertFileDoesNotExist(base_path('routes/api.php'), 'routes/api.php must not exist in the web core');
        $this->assertFileDoesNotExist(config_path('sanctum.php'), 'config/sanctum.php must not exist in the web core');
        $this->assertDirectoryDoesNotExist(
            app_path('Core/Authentication/Http/Controllers/Api'),
            'API controllers must live in the dedicated API repository'
        );

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS)
        );

        $violations = [];
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $src = file_get_contents($file->getPathname());
            if (preg_match('/auth:sanctum|HasApiTokens|Laravel\\\\Sanctum|ApiResponse|\\/api\\/v1/', $src)) {
                $violations[] = $file->getPathname();
            }
        }

        $this->assertSame([], $violations, 'app/ must not reference the token API surface');
    }
}
