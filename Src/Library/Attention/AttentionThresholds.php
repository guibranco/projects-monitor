<?php

namespace GuiBranco\ProjectsMonitor\Library\Attention;

/**
 * The thresholds the attention evaluators judge against: the defaults in
 * attention-thresholds.json, overridden by the rows of the attention_thresholds
 * table so a threshold can change without a deploy.
 */
class AttentionThresholds
{
    private const DEFAULTS_FILE = "/../attention-thresholds.json";

    /**
     * Threshold values by name.
     *
     * @var array
     */
    private array $values = [];


    /**
     * Loads the defaults and applies the overrides.
     *
     * @param array $overrides Threshold values by name, winning over the defaults.
     */
    public function __construct(array $overrides)
    {
        $defaults = json_decode((string) file_get_contents(sprintf("%s%s", __DIR__, self::DEFAULTS_FILE)), true);
        foreach ($defaults as $name => $value) {
            if (is_numeric($value) === true) {
                $this->values[$name] = (float) $value;
            }
        }

        foreach ($overrides as $name => $value) {
            if (is_numeric($value) === true) {
                $this->values[$name] = (float) $value;
            }
        }

    }//end __construct()


    /**
     * The value of a threshold.
     *
     * @param string $name The threshold name, e.g. "queue.messages.warning".
     *
     * @return float
     *
     * @throws \InvalidArgumentException When the threshold is not defined.
     */
    public function getValue(string $name): float
    {
        if (isset($this->values[$name]) === false) {
            throw new \InvalidArgumentException(sprintf("Unknown attention threshold: %s", $name));
        }

        return $this->values[$name];

    }//end getValue()


    /**
     * The severity of a value against the "<prefix>.warning" and "<prefix>.critical" thresholds.
     *
     * @param string $prefix The threshold name without the level, e.g. "queue.messages".
     * @param float  $value  The measured value.
     *
     * @return string|null AttentionItem::CRITICAL, AttentionItem::WARNING, or null when below both.
     */
    public function getSeverity(string $prefix, float $value): ?string
    {
        if ($value >= $this->getValue(sprintf("%s.critical", $prefix))) {
            return AttentionItem::CRITICAL;
        }

        if ($value >= $this->getValue(sprintf("%s.warning", $prefix))) {
            return AttentionItem::WARNING;
        }

        return null;

    }//end getSeverity()


    /**
     * All thresholds, by name.
     *
     * @return array
     */
    public function getAll(): array
    {
        return $this->values;

    }//end getAll()


}//end class
