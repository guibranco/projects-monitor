<?php

namespace GuiBranco\ProjectsMonitor\Library;

class TimeZone
{
    private $timeZone;
    private $offset;

    public function __construct()
    {
        $timeZone = "Europe/Dublin";
        $offset = "+00:00";

        if (isset($_COOKIE["timezone"])) {
            $timeZone = strtolower($_COOKIE["timezone"]) === "europe/london"
                ? "Europe/Dublin"
                : $_COOKIE["timezone"];
        }

        if (isset($_COOKIE["offset"])) {
            $offset = $_COOKIE["offset"];
        } else {
            $dateTimeZone = new \DateTimeZone($timeZone);
            $dateTime = new \DateTime("now", $dateTimeZone);
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
