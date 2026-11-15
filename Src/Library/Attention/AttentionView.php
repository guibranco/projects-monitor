<?php

namespace GuiBranco\ProjectsMonitor\Library\Attention;

/**
 * Turns a snapshot into what the dashboard shows: snoozed items hidden,
 * acknowledged ones kept but not counted, the rest sorted by severity then
 * age, one status tile per domain, and how fresh the data is. No I/O.
 */
class AttentionView
{
    public const DOMAINS = [
                            "ops"      => "Ops",
                            "pipeline" => "Pipeline",
                            "errors"   => "Errors",
                            "ci"       => "CI",
                            "github"   => "GitHub",
                            "domains"  => "Domains",
                           ];

    private const STATE_ACTIVE = "active";

    /**
     * The current Unix time.
     *
     * @var integer
     */
    private int $now;


    /**
     * Creates the view.
     *
     * @param integer $now The current Unix time.
     */
    public function __construct(int $now)
    {
        $this->now = $now;

    }//end __construct()


    /**
     * The attention API response.
     *
     * @param array      $snapshot  What AttentionCollector::collect() returned.
     * @param array      $states    Stored snoozes and acknowledgements, by fingerprint.
     * @param array|null $freshness The public stats cache: updatedAt (Unix time or null) and staleSeconds.
     *
     * @return array
     */
    public function build(array $snapshot, array $states, ?array $freshness): array
    {
        $items   = $this->applyStates((array) ($snapshot["items"] ?? []), $states);
        $visible = array_values(array_filter($items, fn ($item) => $item["state"] !== AttentionStore::SNOOZED));
        usort($visible, [$this, "compareItems"]);

        $response = [];

        $response["generatedAt"] = ($snapshot["generatedAt"] ?? null);
        $response["domains"]     = $this->summarizeDomains($visible);
        $response["items"]       = $visible;
        $response["summary"]     = $this->summarize($items, (int) ($snapshot["checked"] ?? 0));
        $response["freshness"]   = $this->describeFreshness($snapshot, $freshness);
        $response["sources"]     = ($snapshot["sources"] ?? []);

        return $response;

    }//end build()


    /**
     * Marks each item active, snoozed or acknowledged. A snooze holds until
     * it ends; an acknowledgement holds while the item's since value is the
     * one it was acknowledged at.
     *
     * @param array $items  The items.
     * @param array $states Stored states, by fingerprint.
     *
     * @return array
     */
    public function applyStates(array $items, array $states): array
    {
        $result = [];
        foreach ($items as $item) {
            $item["state"]        = self::STATE_ACTIVE;
            $item["snoozedUntil"] = null;
            $state = ($states[$item["fingerprint"]] ?? null);
            if (is_array($state) === true && $state["state"] === AttentionStore::SNOOZED && (int) $state["snoozedUntil"] > $this->now) {
                $item["state"]        = AttentionStore::SNOOZED;
                $item["snoozedUntil"] = gmdate("Y-m-d\TH:i:s\Z", (int) $state["snoozedUntil"]);
            }

            if (is_array($state) === true && $state["state"] === AttentionStore::ACKNOWLEDGED && $state["acknowledgedSince"] === $item["since"]) {
                $item["state"] = AttentionStore::ACKNOWLEDGED;
            }

            $result[] = $item;
        }

        return $result;

    }//end applyStates()


    /**
     * Orders items: active before acknowledged, critical before warning, oldest first, then by title.
     *
     * @param array $left  An item.
     * @param array $right Another item.
     *
     * @return integer
     */
    public function compareItems(array $left, array $right): int
    {
        $keyLeft  = [
                     ($left["state"] !== self::STATE_ACTIVE),
                     ($left["severity"] !== AttentionItem::CRITICAL),
                     ($left["since"] === null),
                     (string) $left["since"],
                     $left["title"],
                    ];
        $keyRight = [
                     ($right["state"] !== self::STATE_ACTIVE),
                     ($right["severity"] !== AttentionItem::CRITICAL),
                     ($right["since"] === null),
                     (string) $right["since"],
                     $right["title"],
                    ];

        $order = ($keyLeft <=> $keyRight);
        return $order;

    }//end compareItems()


    /**
     * One tile per domain: the worst severity of its active items and how many there are.
     *
     * @param array $items The visible items.
     *
     * @return array
     */
    private function summarizeDomains(array $items): array
    {
        $tiles = [];
        foreach (self::DOMAINS as $domain => $label) {
            $tile = [];

            $tile["domain"]   = $domain;
            $tile["label"]    = $label;
            $tile["severity"] = "ok";
            $tile["count"]    = 0;

            $tiles[$domain] = $tile;
        }

        foreach ($items as $item) {
            $domain = $item["domain"];
            if ($item["state"] !== self::STATE_ACTIVE || isset($tiles[$domain]) === false) {
                continue;
            }

            $tiles[$domain]["count"]++;
            if ($item["severity"] === AttentionItem::CRITICAL || $tiles[$domain]["severity"] === "ok") {
                $tiles[$domain]["severity"] = $item["severity"];
            }
        }

        return array_values($tiles);

    }//end summarizeDomains()


    /**
     * The counts behind the "N other checks healthy · M snoozed" line.
     *
     * @param array   $items   Every item, snoozed ones included.
     * @param integer $checked How many checks ran.
     *
     * @return array
     */
    private function summarize(array $items, int $checked): array
    {
        $counts = [];

        $counts["active"]       = 0;
        $counts["critical"]     = 0;
        $counts["warning"]      = 0;
        $counts["snoozed"]      = 0;
        $counts["acknowledged"] = 0;

        foreach ($items as $item) {
            $state = $item["state"];
            if ($state === self::STATE_ACTIVE) {
                $counts[$item["severity"]]++;
            }

            $counts[$state]++;
        }

        $counts["healthy"] = max(0, ($checked - count($items)));

        return $counts;

    }//end summarize()


    /**
     * How old the attention snapshot and the public stats cache are.
     *
     * @param array      $snapshot  The snapshot.
     * @param array|null $freshness The public stats cache: updatedAt (Unix time or null) and staleSeconds.
     *
     * @return array
     */
    private function describeFreshness(array $snapshot, ?array $freshness): array
    {
        $result    = [];
        $generated = strtotime((string) ($snapshot["generatedAt"] ?? ""));

        $result["ageSeconds"]        = null;
        $result["cacheUpdatedAt"]    = null;
        $result["cacheAgeSeconds"]   = null;
        $result["cacheStale"]        = true;
        $result["cacheStaleSeconds"] = (int) ($freshness["staleSeconds"] ?? 0);

        if ($generated !== false) {
            $result["ageSeconds"] = max(0, ($this->now - $generated));
        }

        $updatedAt = ($freshness["updatedAt"] ?? null);
        if (is_int($updatedAt) === true) {
            $result["cacheUpdatedAt"]  = gmdate("Y-m-d\TH:i:s\Z", $updatedAt);
            $result["cacheAgeSeconds"] = max(0, ($this->now - $updatedAt));
            $result["cacheStale"]      = $result["cacheAgeSeconds"] > $result["cacheStaleSeconds"];
        }

        return $result;

    }//end describeFreshness()


}//end class
