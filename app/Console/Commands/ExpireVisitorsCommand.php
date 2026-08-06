<?php

namespace App\Console\Commands;

use App\Services\VisitorService;
use Illuminate\Console\Command;

class ExpireVisitorsCommand extends Command
{
    protected $signature = 'visitors:expire';

    protected $description = 'Expire visitors and temporary RFID cards past expected exit time';

    public function handle(VisitorService $visitors): int
    {
        $count = $visitors->expireOverdue();
        $this->info("Expired {$count} visitor visit(s).");

        return self::SUCCESS;
    }
}
