<?php

namespace GuiBranco\ProjectsMonitor\Library;

use Exception;

/**
 * Thrown by WireGuardClientsConfig when wireguard-clients.json is unreadable,
 * malformed or fails validation.
 */
class WireGuardClientsConfigException extends Exception
{
}//end class
