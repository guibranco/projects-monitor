<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\GitHubAccounts;

/**
 * Tests the dashboard's own GitHub accounts: the user's account and the
 * organizations it follows, matched case-insensitively like GitHub logins.
 */
class GitHubAccountsTest extends TestCase
{


    /**
     * The user's account comes first, then the organizations, with no gaps.
     *
     * @return void
     */
    public function testListsTheUserThenTheOrganizations(): void
    {
        $accounts = new GitHubAccounts();
        $all      = $accounts->getAll();

        $this->assertSame(GitHubAccounts::USER, $all[0]);
        $this->assertSame(GitHubAccounts::ORGANIZATIONS, array_slice($all, 1));
        $this->assertContains('ApiBR', $all);
        $this->assertContains('GuilhermeStracini', $all);

    }//end testListsTheUserThenTheOrganizations()


    /**
     * Logins match in any case; other accounts, blanks and non-strings do not.
     *
     * @return void
     */
    public function testRecognisesOwnAccountsCaseInsensitively(): void
    {
        $accounts = new GitHubAccounts();

        $this->assertTrue($accounts->isOwn('guibranco'));
        $this->assertTrue($accounts->isOwn('GuiBranco'));
        $this->assertTrue($accounts->isOwn('apibr'));
        $this->assertTrue($accounts->isOwn('GuilhermeStracini'));
        $this->assertFalse($accounts->isOwn('octocat'));
        $this->assertFalse($accounts->isOwn('guibranco-forks'));
        $this->assertFalse($accounts->isOwn(''));
        $this->assertFalse($accounts->isOwn(null));
        $this->assertFalse($accounts->isOwn(42));

    }//end testRecognisesOwnAccountsCaseInsensitively()


}//end class
