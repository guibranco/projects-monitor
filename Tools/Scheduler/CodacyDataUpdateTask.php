<?php

namespace Tools\Scheduler;

use Src\Services\CodacyDataService;

class CodacyDataUpdateTask
{
    private $codacyDataService;

    public function __construct(CodacyDataService $codacyDataService)
    {
        $this->codacyDataService = $codacyDataService;
    }

    public function run()
    {
        $this->codacyDataService->updateCodacyData();
    }
}

// This task can be scheduled using a cron job or any scheduler component to run at desired intervals.