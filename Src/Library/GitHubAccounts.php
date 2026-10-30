<?php

namespace GuiBranco\ProjectsMonitor\Library;

/**
 * The GitHub accounts this dashboard is about: the user's own account and
 * the organizations it follows. Anything the webhooks API reports for other
 * accounts (GStraccini-bot is installed on repositories that are not these)
 * is somebody else's and stays off the dashboards, while the API itself keeps
 * returning everything for the bot's own use.
 */
class GitHubAccounts
{

    /**
     * The user's own GitHub account.
     */
    public const USER = 'guibranco';

    /**
     * The organizations the dashboards follow, besides the user's account:
     * the same set the GitHub pull requests section queries.
     */
    public const ORGANIZATIONS = [
                                  'ApiBR',
                                  'GuilhermeStracini',
                                  'InovacaoMediaBrasil',
                                  'rustdevbr',
                                  'pythondevbr',
                                  'pydevbr',
                                  'dotnetdevbr',
                                  'nodejsdevbr',
                                  'rubydevbr',
                                  'frontend-ao',
                                  'frontend-pt',
                                  'backend-ao',
                                  'backend-pt',
                                  'developersRJ',
                                 ];


    /**
     * Every followed account: the user's own first, then the organizations.
     *
     * @return array
     */
    public function getAll(): array
    {
        return array_merge([self::USER], self::ORGANIZATIONS);

    }//end getAll()


    /**
     * Whether $owner is one of the followed accounts. GitHub logins are
     * case-insensitive, so the comparison is too.
     *
     * @param mixed $owner A repository owner's login as the webhooks API reports it.
     *
     * @return boolean
     */
    public function isOwn($owner): bool
    {
        if (is_string($owner) === false || $owner === '') {
            return false;
        }

        $followed = array_map('strtolower', $this->getAll());

        return in_array(strtolower($owner), $followed, true);

    }//end isOwn()


}//end class
