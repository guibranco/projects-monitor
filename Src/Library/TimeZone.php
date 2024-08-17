<?php

namespace GuiBranco\ProjectsMonitor\Library;

class TimeZone
{
    private $timeZone;
    private $offset;

    public function __construct()
    {
        $timezone = "Europe/Dublin";
        $offset = "+00:00";

        if (isset($_COOKIE["timezone"])) {
            $timezone = strtolower($_COOKIE["timezone"]) === "europe/london"
                ? "Europe/Dublin"
                : $_COOKIE["timezone"];
        }

        if (isset($_COOKIE["offset"])) {
            $offset = $_COOKIE["offset"];
        } else {
            $datetimezone = new \DateTimeZone($timezone);
            $dateTime = new \DateTime("now", $datetimezone);
            $offset = $dateTime->getOffset() === 3600 ? "+01:00" : "+00:00";
        }

        $this->timeZone = $timeZone;
        $this->offset = $offset;
    }

    public function getTimeZone()
    {
        return $this->timeZone;
    }

    public function getOffset()
    {
        return $this->offset;
    }
}
