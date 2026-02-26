<?php

namespace Tests\Traits;

trait OverridesMailConfig
{
    protected function setUpMailConfig(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.port' => 1025,
            'mail.mailers.smtp.encryption' => null,
            'mail.mailers.smtp.username' => null,
            'mail.mailers.smtp.password' => null,
            'mail.from.address' => 'test@propmanager.local',
            'mail.from.name' => config('app.name'),
        ]);
    }
}
