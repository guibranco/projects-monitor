<?php

use GuiBranco\Pancake\Request;
use GuiBranco\Pancake\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\Configuration;
use GuiBranco\ProjectsMonitor\Library\CPanel;
use GuiBranco\ProjectsMonitor\Library\CPanelFileOperation;

/**
 * Tests how Fileman::fileop responses are read, including the object-shaped
 * data cPanel returns on failure, directly and through the CPanel methods
 * that delete and rename files (HTTP client mocked, built without the
 * constructor, which reads secrets).
 */
class CPanelFileOperationTest extends TestCase
{


    /**
     * Response bodies and whether each one reports success.
     *
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function responses(): array
    {
        $cases = [];
        $cases["success list"]         = ['{"cpanelresult":{"data":[{"result":1}]}}', true];
        $cases["success as string"]    = ['{"cpanelresult":{"data":[{"result":"1"}]}}', true];
        $cases["success object"]       = ['{"cpanelresult":{"data":{"result":1}}}', true];
        $cases["failure list"]         = ['{"cpanelresult":{"data":[{"result":0,"reason":"No such file"}]}}', false];
        $cases["failure object"]       = ['{"cpanelresult":{"data":{"result":0,"reason":"No such file"}}}', false];
        $cases["empty object"]         = ['{"cpanelresult":{"data":{}}}', false];
        $cases["empty list"]           = ['{"cpanelresult":{"data":[]}}', false];
        $cases["scalar entry"]         = ['{"cpanelresult":{"data":[1]}}', false];
        $cases["missing data"]         = ['{"cpanelresult":{"error":"Access denied"}}', false];
        $cases["missing cpanelresult"] = ['{}', false];
        $cases["null"]                 = ['null', false];

        return $cases;

    }//end responses()


    /**
     * Each response shape is read without throwing.
     *
     * @param string $body     The JSON body.
     * @param bool   $expected Whether it reports success.
     *
     * @return void
     */
    #[DataProvider('responses')]
    public function testIsSuccessful(string $body, bool $expected): void
    {
        $this->assertSame($expected, (new CPanelFileOperation())->isSuccessful(json_decode($body)));

    }//end testIsSuccessful()


    /**
     * The CPanel file methods report success and failure, and an object-shaped
     * failure no longer throws.
     *
     * @return void
     */
    public function testCPanelFileMethods(): void
    {
        $success = $this->cPanelWithBody('{"cpanelresult":{"data":[{"result":1}]}}');
        $this->assertTrue($success->deleteErrorLogFile("public_html/api"));
        $this->assertTrue($success->renameFile("/home/user/a", "/home/user/b"));
        $this->assertTrue($success->deleteFile("/home/user/a"));

        $failure = $this->cPanelWithBody('{"cpanelresult":{"data":{"result":0,"reason":"No such file"}}}');
        $this->assertFalse($failure->deleteErrorLogFile(".softaculous/logs"));
        $this->assertFalse($failure->renameFile("/home/user/a", "/home/user/b"));
        $this->assertFalse($failure->deleteFile("/home/user/a"));

    }//end testCPanelFileMethods()


    /**
     * A CPanel whose HTTP client answers every request with the given body.
     *
     * @param string $body The JSON body.
     *
     * @return CPanel
     */
    private function cPanelWithBody(string $body): CPanel
    {
        (new Configuration())->init();

        $request = $this->createStub(Request::class);
        $request->method("get")->willReturn(new Response(true, $body, "", 200, "", []));

        $cPanel = (new ReflectionClass(CPanel::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty($cPanel, "request"))->setValue($cPanel, $request);
        (new ReflectionProperty($cPanel, "username"))->setValue($cPanel, "user");
        (new ReflectionProperty($cPanel, "baseUrl"))->setValue($cPanel, "https://cpanel.example.com");
        (new ReflectionProperty($cPanel, "apiToken"))->setValue($cPanel, "token");

        return $cPanel;

    }//end cPanelWithBody()


}//end class
