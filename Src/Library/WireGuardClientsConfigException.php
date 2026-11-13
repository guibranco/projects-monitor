<?php

namespace GuiBranco\ProjectsMonitor\Library;

use Exception;

/**
 * Thrown by WireGuardClientsConfig when wireguard-clients.json is malformed
 * or fails schema/business-rule validation.
 */
class WireGuardClientsConfigException extends Exception
{
}
