<?php

namespace GuiBranco\ProjectsMonitor\Library\Attention;

/**
 * Decides which attention items deserve a push notification: only critical
 * ones that are active (not snoozed, not acknowledged), once per raise. An
 * item is raised again when its since value changes, or after it went away
 * and came back. Past notify.maxPushesPerRun new items, one summary push
 * replaces the individual ones. No I/O.
 */
class AttentionNotifier
{

    /**
     * The most individual pushes in one run.
     *
     * @var integer
     */
    private int $maxPushes;


    /**
     * Creates the notifier.
     *
     * @param integer $maxPushes The most individual pushes in one run (at least 1).
     */
    public function __construct(int $maxPushes)
    {
        $this->maxPushes = max(1, $maxPushes);

    }//end __construct()


    /**
     * The items to notify about now.
     *
     * @param array $items    The attention API's items (with their state).
     * @param array $notified What was already notified: since value ("" for none), by fingerprint.
     *
     * @return array
     */
    public function select(array $items, array $notified): array
    {
        $selected = [];
        foreach ($items as $item) {
            if (($item["severity"] ?? null) !== AttentionItem::CRITICAL || ($item["state"] ?? "active") !== "active") {
                continue;
            }

            $fingerprint = (string) $item["fingerprint"];
            $since       = (string) ($item["since"] ?? "");
            if (array_key_exists($fingerprint, $notified) === true && $notified[$fingerprint] === $since) {
                continue;
            }

            $selected[] = $item;
        }

        return $selected;

    }//end select()


    /**
     * The pushes for the selected items: one each, or a single summary when
     * there are more than the limit.
     *
     * @param array $selected What select() returned.
     *
     * @return array Pushes of heading, content, link and key (an idempotency UUID).
     */
    public function buildPushes(array $selected): array
    {
        if (count($selected) === 0) {
            return [];
        }

        if (count($selected) > $this->maxPushes) {
            $titles = array_map(fn ($item) => (string) $item["title"], $selected);
            $keys   = array_map(fn ($item) => $this->raiseKey($item), $selected);
            sort($keys);

            $push = [];

            $push["heading"] = sprintf("%d critical items need attention", count($selected));
            $push["content"] = implode(" · ", array_slice($titles, 0, 5)).(count($titles) > 5 ? " …" : "");
            $push["link"]    = "index.php";
            $push["key"]     = $this->uuid(implode("|", $keys));

            return [$push];
        }

        $pushes = [];
        foreach ($selected as $item) {
            $push = [];

            $push["heading"] = sprintf("Critical · %s", ucfirst((string) ($item["source"] ?? "attention")));
            $push["content"] = (string) $item["title"];
            $push["link"]    = (string) ($item["link"] ?? "index.php");
            $push["key"]     = $this->uuid($this->raiseKey($item));

            $pushes[] = $push;
        }

        return $pushes;

    }//end buildPushes()


    /**
     * What identifies one raise of an item: its fingerprint and since value.
     *
     * @param array $item An item.
     *
     * @return string
     */
    private function raiseKey(array $item): string
    {
        return sprintf("%s@%s", (string) $item["fingerprint"], (string) ($item["since"] ?? ""));

    }//end raiseKey()


    /**
     * A UUID (version 5 layout) derived from a name, so a push resent for the
     * same raise carries the same idempotency key.
     *
     * @param string $name The name.
     *
     * @return string
     */
    public function uuid(string $name): string
    {
        $hash = sha1("projects-monitor:attention:".$name);

        $timeHi = ((hexdec(substr($hash, 12, 4)) & 0x0fff) | 0x5000);
        $clock  = ((hexdec(substr($hash, 16, 4)) & 0x3fff) | 0x8000);

        return sprintf("%s-%s-%04x-%04x-%s", substr($hash, 0, 8), substr($hash, 8, 4), $timeHi, $clock, substr($hash, 20, 12));

    }//end uuid()


}//end class
