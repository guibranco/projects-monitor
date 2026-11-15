<?php

namespace GuiBranco\ProjectsMonitor\Library\Attention;

/**
 * Judges one source: fetch() reads its data, evaluate() decides, without I/O,
 * what in that data needs attention.
 */
interface AttentionEvaluator
{


    /**
     * The source name, e.g. "healthchecks".
     *
     * @return string
     */
    public function getSource(): string;


    /**
     * The status strip domain: ops, pipeline, errors, ci, github or domains.
     *
     * @return string
     */
    public function getDomain(): string;


    /**
     * Reads the source's data.
     *
     * @return mixed
     */
    public function fetch(): mixed;


    /**
     * Decides what in the data needs attention.
     *
     * @param mixed               $data       What fetch() returned.
     * @param AttentionThresholds $thresholds The thresholds to judge against.
     * @param integer             $now        The current Unix time.
     *
     * @return AttentionEvaluation
     */
    public function evaluate(mixed $data, AttentionThresholds $thresholds, int $now): AttentionEvaluation;


}//end interface
