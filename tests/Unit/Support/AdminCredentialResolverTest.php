<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\AdminCredentialResolver;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class AdminCredentialResolverTest extends TestCase
{
    private AdminCredentialResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = app(AdminCredentialResolver::class);
    }

    #[Test]
    public function email_returns_configured_value_or_fallback(): void
    {
        config(['admin.email' => 'ops@example.test']);

        $this->assertSame('ops@example.test', $this->resolver->email());

        config(['admin.email' => '']);

        $this->assertSame('admin@local', $this->resolver->email());
    }

    #[Test]
    public function password_uses_env_value_in_testing(): void
    {
        config(['admin.password' => 'configured-secret']);

        $this->assertSame('configured-secret', $this->resolver->password());
    }

    #[Test]
    public function password_falls_back_to_default_in_local_and_testing(): void
    {
        config(['admin.password' => '', 'app.env' => 'testing']);

        $this->assertSame(AdminCredentialResolver::FORBIDDEN_PRODUCTION_PASSWORD, $this->resolver->password());
    }

    #[Test]
    public function password_throws_when_missing_outside_local_testing(): void
    {
        config(['admin.password' => '', 'app.env' => 'staging']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ADMIN_PASSWORD is not set');

        $this->resolver->password();
    }

    #[Test]
    public function assert_password_allowed_rejects_default_in_production(): void
    {
        config(['app.env' => 'production']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Refusing to use the default admin password in production');

        $this->resolver->assertPasswordAllowed(AdminCredentialResolver::FORBIDDEN_PRODUCTION_PASSWORD);
    }

    #[Test]
    public function assert_password_allowed_permits_strong_password_in_production(): void
    {
        config(['app.env' => 'production']);

        $this->resolver->assertPasswordAllowed('strong-random-password-9');

        $this->addToAssertionCount(1);
    }
}
