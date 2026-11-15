<?php

namespace GuiBranco\ProjectsMonitor\Library\Attention;

/**
 * A snooze, acknowledge or clear request for one attention item, as POST
 * /api/v1/attention/state receives it.
 */
class AttentionStateRequest
{
    public const MAX_SNOOZE_MINUTES = 43200;

    private const MAX_FINGERPRINT_LENGTH = 191;

    private const MAX_SINCE_LENGTH = 25;

    private const ACTIONS = [
                             "snooze",
                             "acknowledge",
                             "clear",
                            ];

    /**
     * The decoded request body.
     *
     * @var array
     */
    private array $input;


    /**
     * Wraps the decoded request body.
     *
     * @param mixed $input The decoded JSON body.
     */
    public function __construct(mixed $input)
    {
        $this->input = [];
        if (is_array($input) === true) {
            $this->input = $input;
        }

    }//end __construct()


    /**
     * What is wrong with the request, null when it is valid.
     *
     * @return string|null
     */
    public function validate(): ?string
    {
        $fingerprint = ($this->input["fingerprint"] ?? null);
        if (is_string($fingerprint) === false || $fingerprint === "" || strlen($fingerprint) > self::MAX_FINGERPRINT_LENGTH) {
            return "A valid 'fingerprint' is required";
        }

        $action = ($this->input["action"] ?? null);
        if (in_array($action, self::ACTIONS, true) === false) {
            return "'action' must be snooze, acknowledge or clear";
        }

        if ($action === "snooze" && $this->getMinutes() === null) {
            return sprintf("'minutes' must be between 1 and %d", self::MAX_SNOOZE_MINUTES);
        }

        return $this->validateSince();

    }//end validate()


    /**
     * What is wrong with the since value, null when it is valid.
     *
     * @return string|null
     */
    private function validateSince(): ?string
    {
        $since = ($this->input["since"] ?? null);
        if ($since !== null && (is_string($since) === false || strlen($since) > self::MAX_SINCE_LENGTH)) {
            return "'since' must be the item's since value or null";
        }

        return null;

    }//end validateSince()


    /**
     * Saves the request to the store. Call validate() first.
     *
     * @param AttentionStore $store  The store.
     * @param integer        $userId Who asked.
     * @param integer        $now    The current Unix time.
     *
     * @return boolean
     */
    public function apply(AttentionStore $store, int $userId, int $now): bool
    {
        $fingerprint = (string) $this->input["fingerprint"];
        return match ($this->input["action"]) {
            "snooze" => $store->snooze($fingerprint, ($now + ((int) $this->getMinutes() * 60)), $userId),
            "acknowledge" => $store->acknowledge($fingerprint, ($this->input["since"] ?? null), $userId),
            default => $store->clear($fingerprint),
        };

    }//end apply()


    /**
     * The response body.
     *
     * @return array
     */
    public function toArray(): array
    {
        $response = [];

        $response["fingerprint"] = ($this->input["fingerprint"] ?? null);
        $response["action"]      = ($this->input["action"] ?? null);

        return $response;

    }//end toArray()


    /**
     * The snooze length in minutes, null when missing or out of range.
     *
     * @return integer|null
     */
    public function getMinutes(): ?int
    {
        $range = [];

        $range["min_range"] = 1;
        $range["max_range"] = self::MAX_SNOOZE_MINUTES;

        $minutes = filter_var(($this->input["minutes"] ?? null), FILTER_VALIDATE_INT, ["options" => $range]);
        if ($minutes === false) {
            return null;
        }

        return $minutes;

    }//end getMinutes()


}//end class
