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
     * Response bodies that report success.
     *
     * @return array<string, array<int, string>>
     */
    public static function successfulResponses(): array
    {
        $cases = [];
        $cases["list"]          = ['{"cpanelresult":{"data":[{"result":1}]}}'];
        $cases["result string"] = ['{"cpanelresult":{"data":[{"result":"1"}]}}'];
        $cases["object"]        = ['{"cpanelresult":{"data":{"result":1}}}'];

        return $cases;

    }//end successfulResponses()


    /**
     * Response bodies that report failure or have an unexpected shape.
     *
     * @return array<string, array<int, string>>
     */
    public static function failedResponses(): array
    {
        $cases = [];
        $cases["failure list"]         = ['{"cpanelresult":{"data":[{"result":0,"reason":"No such file"}]}}'];
        $cases["failure object"]       = ['{"cpanelresult":{"data":{"result":0,"reason":"No such file"}}}'];
        $cases["empty object"]         = ['{"cpanelresult":{"data":{}}}'];
        $cases["empty list"]           = ['{"cpanelresult":{"data":[]}}'];
        $cases["scalar entry"]         = ['{"cpanelresult":{"data":[1]}}'];
        $cases["missing data"]         = ['{"cpanelresult":{"error":"Access denied"}}'];
        $cases["missing cpanelresult"] = ['{}'];
        $cases["null"]                 = ['null'];

        return $cases;

    }//end failedResponses()


    /**
     * A successful response is read as success.
     *
     * @param string $body The JSON body.
     *
     * @return void
     */
    #[DataProvider('successfulResponses')]
    public function testIsSuccessful(string $body): void
    {
        $this->assertTrue((new CPanelFileOperation())->isSuccessful(json_decode($body)));

    }//end testIsSuccessful()


    /**
     * A failed or unexpected response is read as failure without throwing.
     *
     * @param string $body The JSON body.
     *
     * @return void
     */
    #[DataProvider('failedResponses')]
    public function testIsNotSuccessful(string $body): void
    {
        $this->assertFalse((new CPanelFileOperation())->isSuccessful(json_decode($body)));

    }//end testIsNotSuccessful()


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
