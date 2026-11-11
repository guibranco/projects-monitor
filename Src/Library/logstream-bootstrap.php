<?php
/*
 * Loaded by Composer's autoloader (autoload.files): registers the server
 * variables LogStream adds to every entry, so that no class reads $_SERVER.
 */

use GuiBranco\ProjectsMonitor\Library\LogStream;

(new LogStream())->setServer($_SERVER);
