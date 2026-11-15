<?php

namespace GuiBranco\ProjectsMonitor\Library\Attention;

use GuiBranco\ProjectsMonitor\Library\Attention\Evaluators\AppMessagesEvaluator;
use GuiBranco\ProjectsMonitor\Library\Attention\Evaluators\DatabaseErrorsEvaluator;
use GuiBranco\ProjectsMonitor\Library\Attention\Evaluators\DomainsEvaluator;
use GuiBranco\ProjectsMonitor\Library\Attention\Evaluators\ErrorLogFilesEvaluator;
use GuiBranco\ProjectsMonitor\Library\Attention\Evaluators\GitHubQuotaEvaluator;
use GuiBranco\ProjectsMonitor\Library\Attention\Evaluators\HealthChecksEvaluator;
use GuiBranco\ProjectsMonitor\Library\Attention\Evaluators\QueuesEvaluator;
use GuiBranco\ProjectsMonitor\Library\Attention\Evaluators\ServerHealthEvaluator;
use GuiBranco\ProjectsMonitor\Library\Attention\Evaluators\UpTimeRobotEvaluator;
use GuiBranco\ProjectsMonitor\Library\Attention\Evaluators\WebhooksBacklogEvaluator;
use GuiBranco\ProjectsMonitor\Library\Attention\Evaluators\WorkflowRunsEvaluator;

/**
 * The evaluators of every source the attention feed reads. A new source is
 * one evaluator class added here.
 */
class AttentionSources
{


    /**
     * One evaluator per source.
     *
     * @return AttentionEvaluator[]
     */
    public function getEvaluators(): array
    {
        return [
                new HealthChecksEvaluator(),
                new UpTimeRobotEvaluator(),
                new ServerHealthEvaluator(),
                new QueuesEvaluator(),
                new WebhooksBacklogEvaluator(),
                new AppMessagesEvaluator(),
                new DatabaseErrorsEvaluator(),
                new ErrorLogFilesEvaluator(),
                new WorkflowRunsEvaluator(),
                new GitHubQuotaEvaluator(),
                new DomainsEvaluator(),
               ];

    }//end getEvaluators()


}//end class
