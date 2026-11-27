<?php

namespace GuiBranco\ProjectsMonitor\Library;

/**
 * Reads the outcome of a cPanel API2 Fileman::fileop response.
 *
 * On failure cPanel may return `cpanelresult->data` as an object instead of
 * an array, so array access on it must not happen without a type check
 * (isset($object[0]) on a stdClass throws instead of returning false).
 */
class CPanelFileOperation
{


    /**
     * Whether the decoded response reports a successful file operation.
     *
     * @param mixed $response The decoded JSON response.
     *
     * @return bool True when the first result entry reports success.
     */
    public function isSuccessful(mixed $response): bool
    {
        $data = ($response->cpanelresult->data ?? null);

        if (is_object($data) === true) {
            $data = [$data];
        }

        if (is_array($data) === false || isset($data[0]) === false || is_object($data[0]) === false) {
            return false;
        }

        return isset($data[0]->result) === true && ((int) $data[0]->result) === 1;

    }//end isSuccessful()


}//end class
