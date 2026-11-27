<?php

namespace GuiBranco\ProjectsMonitor\Library;

/**
 * A request about an application that cannot be done as given: missing or
 * duplicate name, invalid ID or target, malformed JSON. Answered with 400.
 */
class InvalidApplicationException extends \InvalidArgumentException
{
}//end class
