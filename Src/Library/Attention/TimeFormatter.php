<?php

namespace GuiBranco\ProjectsMonitor\Library\Attention;

/**
 * Normalises the timestamps sources report into ISO 8601 UTC.
 */
class TimeFormatter
{


    /**
     * A date and time string as ISO 8601 UTC, or null when empty or unparsable.
     *
     * @param mixed $value A string strtotime() understands, in the configured time zone unless it says otherwise.
     *
     * @return string|null
     */
    public function toIso(mixed $value): ?string
    {
        if (is_string($value) === false || trim($value) === "") {
            return null;
        }

        $time = strtotime($value);
        if ($time === false) {
            return null;
        }

        return $this->fromTimestamp($time);

    }//end toIso()


    /**
     * A Unix time as ISO 8601 UTC.
     *
     * @param integer $time The Unix time.
     *
     * @return string
     */
    public function fromTimestamp(int $time): string
    {
        return gmdate("Y-m-d\TH:i:s\Z", $time);

    }//end fromTimestamp()


    /**
     * A "H:i:s d/m/Y" date (the cPanel file times) as ISO 8601 UTC, or null when unparsable.
     *
     * @param mixed $value The date in the configured time zone.
     *
     * @return string|null
     */
    public function fromDashboardFormat(mixed $value): ?string
    {
        $date = date_create_from_format("H:i:s d/m/Y", (string) $value);
        if ($date === false) {
            return null;
        }

        return $this->fromTimestamp($date->getTimestamp());

    }//end fromDashboardFormat()


}//end class
