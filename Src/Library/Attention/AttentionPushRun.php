<?php

namespace GuiBranco\ProjectsMonitor\Library\Attention;

use GuiBranco\ProjectsMonitor\Library\OneSignal;

/**
 * One run of the push notifications (Worker/NotifyAttention.php): selects
 * the critical items to push from an attention API response, sends them,
 * remembers them once every push went out, and forgets the pushes of items
 * no longer raised when every source answered.
 */
class AttentionPushRun
{

    /**
     * Remembers what was pushed.
     *
     * @var AttentionStore
     */
    private AttentionStore $store;

    /**
     * Sends the pushes.
     *
     * @var OneSignal
     */
    private OneSignal $oneSignal;

    /**
     * Decides what to push.
     *
     * @var AttentionNotifier
     */
    private AttentionNotifier $notifier;


    /**
     * Creates the run.
     *
     * @param AttentionStore    $store     Remembers what was pushed.
     * @param OneSignal         $oneSignal Sends the pushes.
     * @param AttentionNotifier $notifier  Decides what to push.
     */
    public function __construct(AttentionStore $store, OneSignal $oneSignal, AttentionNotifier $notifier)
    {
        $this->store     = $store;
        $this->oneSignal = $oneSignal;
        $this->notifier  = $notifier;

    }//end __construct()


    /**
     * Runs once.
     *
     * @param array $response The attention API response (items with their state, and sources).
     *
     * @return array selected, sent and failed counts, and errors (the failed pushes' reasons).
     */
    public function run(array $response): array
    {
        $items    = (array) ($response["items"] ?? []);
        $selected = $this->notifier->select($items, $this->store->getNotified());

        $result = [];

        $result["selected"] = count($selected);
        $result["sent"]     = 0;
        $result["failed"]   = 0;
        $result["errors"]   = [];

        foreach ($this->notifier->buildPushes($selected) as $push) {
            try {
                $this->oneSignal->send($push["heading"], $push["content"], $push["link"], $push["key"]);
                $result["sent"]++;
            } catch (\Throwable $exception) {
                $result["failed"]++;
                $result["errors"][] = $exception->getMessage();
            }
        }

        // A failed push is retried on the next run, with the same
        // idempotency key, so OneSignal drops it if it did get through.
        if ($result["failed"] === 0) {
            foreach ($selected as $item) {
                $this->store->markNotified((string) $item["fingerprint"], ($item["since"] ?? null));
            }
        }

        // An unreadable source would otherwise look like its items went away and re-push them.
        $unreadable = array_filter((array) ($response["sources"] ?? []), fn ($source) => ($source["status"] ?? "") === "error");
        if (empty($unreadable) === true) {
            $this->store->pruneNotified(array_column($items, "fingerprint"));
        }

        return $result;

    }//end run()


}//end class
