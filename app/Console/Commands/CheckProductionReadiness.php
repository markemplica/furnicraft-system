<?php

namespace App\Console\Commands;

use App\Services\ProductionReadiness;
use Illuminate\Console\Command;

class CheckProductionReadiness extends Command
{
    protected $signature = 'system:readiness';

    protected $description = 'Check production configuration and operational prerequisites';

    public function handle(ProductionReadiness $readiness): int
    {
        $result = $readiness->inspect();
        foreach ($result['checks'] as $check) {
            $this->line(($check['ok'] ? '<fg=green>PASS</>' : '<fg=red>BLOCKED</>').' '.$check['name'].' — '.$check['detail']);
        }

        if (! $result['ready']) {
            $this->error('Production readiness checks are not all passing.');

            return self::FAILURE;
        }

        $this->info('Production configuration checks passed. Hosting, restore drills, and live email delivery still require external verification.');

        return self::SUCCESS;
    }
}
