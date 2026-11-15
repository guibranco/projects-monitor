<?php

namespace GuiBranco\ProjectsMonitor\Library\Attention\Evaluators;

use GuiBranco\ProjectsMonitor\Library\Attention\AttentionEvaluation;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionEvaluator;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionItem;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionThresholds;
use GuiBranco\ProjectsMonitor\Library\Attention\Fingerprint;
use GuiBranco\ProjectsMonitor\Library\Ip2WhoIs;

/**
 * Domains that expire within domains.expiry.warningDays (warning) or
 * domains.expiry.criticalDays (critical), or have already expired.
 */
class DomainsEvaluator implements AttentionEvaluator
{
    private const SECONDS_PER_DAY = 86400;


    /**
     * The source name.
     *
     * @return string
     */
    public function getSource(): string
    {
        return "domains";

    }//end getSource()


    /**
     * The domain (of the status strip).
     *
     * @return string
     */
    public function getDomain(): string
    {
        return "domains";

    }//end getDomain()


    /**
     * Reads the expiry date of each domain.
     *
     * @return mixed
     */
    public function fetch(): mixed
    {
        return (new Ip2WhoIs())->getExpirations();

    }//end fetch()


    /**
     * Decides which domains need attention.
     *
     * @param mixed               $data       Rows of domain and expiresAt (Unix time or null).
     * @param AttentionThresholds $thresholds The domains.expiry thresholds, in days.
     * @param integer             $now        The current Unix time.
     *
     * @return AttentionEvaluation
     */
    public function evaluate(mixed $data, AttentionThresholds $thresholds, int $now): AttentionEvaluation
    {
        $evaluation  = new AttentionEvaluation();
        $fingerprint = new Fingerprint();
        foreach ((array) $data as $row) {
            $expiresAt = ($row["expiresAt"] ?? null);
            if ($expiresAt === null) {
                $evaluation->addHealthy();
                continue;
            }

            $daysLeft = (int) floor((((int) $expiresAt) - $now) / self::SECONDS_PER_DAY);
            $severity = $this->getSeverity($daysLeft, $thresholds);
            if ($severity === null) {
                $evaluation->addHealthy();
                continue;
            }

            $domain = (string) ($row["domain"] ?? "");
            $evaluation->addItem(new AttentionItem($this->getSource(), $this->getDomain(), $severity, $this->getTitle($domain, $daysLeft), null, "index.php#domains", $fingerprint->make("domain", $domain)));
        }

        return $evaluation;

    }//end evaluate()


    /**
     * The severity for the days left before expiry.
     *
     * @param integer             $daysLeft   Days left, negative once expired.
     * @param AttentionThresholds $thresholds The domains.expiry thresholds.
     *
     * @return string|null
     */
    private function getSeverity(int $daysLeft, AttentionThresholds $thresholds): ?string
    {
        if ($daysLeft <= $thresholds->getValue("domains.expiry.criticalDays")) {
            return AttentionItem::CRITICAL;
        }

        if ($daysLeft <= $thresholds->getValue("domains.expiry.warningDays")) {
            return AttentionItem::WARNING;
        }

        return null;

    }//end getSeverity()


    /**
     * The title for a domain about to expire.
     *
     * @param string  $domain   The domain.
     * @param integer $daysLeft Days left, negative once expired.
     *
     * @return string
     */
    private function getTitle(string $domain, int $daysLeft): string
    {
        if ($daysLeft < 0) {
            return sprintf("Domain %s expired %d days ago", $domain, abs($daysLeft));
        }

        return sprintf("Domain %s expires in %d days", $domain, $daysLeft);

    }//end getTitle()


}//end class
