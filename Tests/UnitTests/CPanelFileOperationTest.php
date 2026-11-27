<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\CPanelFileOperation;

/**
 * Tests how CPanelFileOperation reads Fileman::fileop responses, including
 * the object-shaped data cPanel returns on failure.
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
        return [
            "success list"          => ['{"cpanelresult":{"data":[{"result":1}]}}', true],
            "success as string"     => ['{"cpanelresult":{"data":[{"result":"1"}]}}', true],
            "success object"        => ['{"cpanelresult":{"data":{"result":1}}}', true],
            "failure list"          => ['{"cpanelresult":{"data":[{"result":0,"reason":"No such file"}]}}', false],
            "failure object"        => ['{"cpanelresult":{"data":{"result":0,"reason":"No such file"}}}', false],
            "empty object"          => ['{"cpanelresult":{"data":{}}}', false],
            "empty list"            => ['{"cpanelresult":{"data":[]}}', false],
            "scalar entry"          => ['{"cpanelresult":{"data":[1]}}', false],
            "missing data"          => ['{"cpanelresult":{"error":"Access denied"}}', false],
            "missing cpanelresult"  => ['{}', false],
            "null"                  => ['null', false],
        ];

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
        $this->assertSame($expected, CPanelFileOperation::isSuccessful(json_decode($body)));

    }//end testIsSuccessful()


}//end class
