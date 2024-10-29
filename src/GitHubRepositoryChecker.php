<?php

class GitHubRepositoryChecker
{
    private $githubApi;

    public function __construct($githubApi)
    {
        $this->githubApi = $githubApi;
    }

    public function checkSizeLabelFile($repository)
    {
        $path = '.github/workflows/size-label.yml';
        try {
            $this->githubApi->getFile($repository, $path);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

}
