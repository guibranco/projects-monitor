<?php

namespace GuiBranco\ProjectsMonitor\Library\Attention;

/**
 * One thing that needs attention right now, normalised across every source.
 */
class AttentionItem
{
    public const CRITICAL = "critical";

    public const WARNING = "warning";

    /**
     * The source that raised it, e.g. "healthchecks".
     *
     * @var string
     */
    public readonly string $source;

    /**
     * The status strip domain it counts towards (ops, pipeline, errors, ci, github, domains).
     *
     * @var string
     */
    public readonly string $domain;

    /**
     * Either critical (broken) or warning (degraded).
     *
     * @var string
     */
    public readonly string $severity;

    /**
     * What is wrong, in one line.
     *
     * @var string
     */
    public readonly string $title;

    /**
     * Since when, as ISO 8601 UTC, or null when the source does not say.
     *
     * @var string|null
     */
    public readonly ?string $since;

    /**
     * Where to look at it.
     *
     * @var string
     */
    public readonly string $link;

    /**
     * Stable identity across refreshes, used to snooze and acknowledge it.
     *
     * @var string
     */
    public readonly string $fingerprint;

    /**
     * Actions the UI offers inline, besides snooze and acknowledge.
     *
     * @var array
     */
    private array $actions = [];

    /**
     * Extra data the actions need, e.g. the workflow run to re-run.
     *
     * @var array
     */
    private array $meta = [];


    /**
     * Creates an item.
     *
     * @param string      $source      The source that raised it.
     * @param string      $domain      The status strip domain.
     * @param string      $severity    AttentionItem::CRITICAL or AttentionItem::WARNING.
     * @param string      $title       What is wrong.
     * @param string|null $since       Since when (ISO 8601 UTC), null when unknown.
     * @param string      $link        Where to look at it.
     * @param string      $fingerprint Stable identity.
     */
    public function __construct(
        string $source,
        string $domain,
        string $severity,
        string $title,
        ?string $since,
        string $link,
        string $fingerprint
    ) {
        $this->source      = $source;
        $this->domain      = $domain;
        $this->severity    = $severity;
        $this->title       = $title;
        $this->since       = $since;
        $this->link        = $link;
        $this->fingerprint = $fingerprint;

    }//end __construct()


    /**
     * Returns a copy that offers an extra inline action.
     *
     * @param string $action The action, e.g. "rerun".
     * @param array  $meta   Data the action needs.
     *
     * @return AttentionItem
     */
    public function withAction(string $action, array $meta): AttentionItem
    {
        $copy            = clone $this;
        $copy->actions[] = $action;
        $copy->meta      = array_merge($copy->meta, $meta);
        return $copy;

    }//end withAction()


    /**
     * Whether it is critical.
     *
     * @return boolean
     */
    public function isCritical(): bool
    {
        return $this->severity === self::CRITICAL;

    }//end isCritical()


    /**
     * The item as the attention API returns it.
     *
     * @return array
     */
    public function toArray(): array
    {
        $item = [];

        $item["source"]      = $this->source;
        $item["domain"]      = $this->domain;
        $item["severity"]    = $this->severity;
        $item["title"]       = $this->title;
        $item["since"]       = $this->since;
        $item["link"]        = $this->link;
        $item["actions"]     = array_merge(["open"], $this->actions, ["snooze", "acknowledge"]);
        $item["fingerprint"] = $this->fingerprint;
        $item["meta"]        = (object) $this->meta;

        return $item;

    }//end toArray()


}//end class
