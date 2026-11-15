<?php

namespace GuiBranco\ProjectsMonitor\Library\Attention;

/**
 * Builds the stable identity of an attention item, so a snooze or an
 * acknowledgement keeps applying to it across refreshes.
 */
class Fingerprint
{


    /**
     * The fingerprint "<prefix>:<slug of name>", e.g. "hc:vinhedo2-upgrades".
     *
     * @param string $prefix The source prefix.
     * @param string $name   The checked thing's name.
     *
     * @return string
     */
    public function make(string $prefix, string $name): string
    {
        $slug = strtolower(trim((string) preg_replace("/[^A-Za-z0-9_.\/]+/", "-", $name), "-"));
        return sprintf("%s:%s", $prefix, $slug);

    }//end make()


}//end class
