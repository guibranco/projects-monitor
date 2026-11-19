<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\ErrorText;

/**
 * Tests how ErrorText renders templates and summarises failures.
 */
class ErrorTextTest extends TestCase
{
    /**
     * The 503 page a busy server answers with.
     */
    private const HTML_503 = "<!DOCTYPE html>\n<html><head><title> 503 Service Unavailable\n</title><style>body{color:#444}</style></head><body><h1>503</h1>\n<h2>Service Unavailable</h2><p>The server is temporarily busy, try again later!</p></body></html>";

    /**
     * The class under test.
     *
     * @var ErrorText
     */
    private $errorText;


    /**
     * Creates the class under test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->errorText = new ErrorText();

    }//end setUp()


    /**
     * An HTML error page becomes its visible text on one line.
     *
     * @return void
     */
    public function testSummarizeTurnsHtmlErrorPageIntoText(): void
    {
        $expected = "503 Service Unavailable The server is temporarily busy, try again later!";

        $this->assertSame($expected, $this->errorText->summarize(self::HTML_503));
        $this->assertSame("{\"error\": \"forbidden\"}", $this->errorText->summarize("  {\"error\":\n \"forbidden\"}"));

    }//end testSummarizeTurnsHtmlErrorPageIntoText()


    /**
     * Placeholders reach nested arrays; unknown ones are kept.
     *
     * @return void
     */
    public function testRenderReplacesNestedAndKeepsUnknownPlaceholders(): void
    {
        $context            = [];
        $context["service"] = "Webhooks API";
        $context["request"] = ["method" => "GET"];
        $context["flag"]    = false;
        $context["nothing"] = null;

        $rendered = $this->errorText->render("{service} {request.method} {missing} {flag} {nothing}", $context);

        $this->assertSame("Webhooks API GET {missing} false null", $rendered);

    }//end testRenderReplacesNestedAndKeepsUnknownPlaceholders()


    /**
     * Throwables, arrays and objects are rendered as text.
     *
     * @return void
     */
    public function testRenderStringifiesComplexValues(): void
    {
        $context              = [];
        $context["exception"] = new RuntimeException("boom");
        $context["list"]      = range(1, 2);
        $context["object"]    = new ArrayObject();

        $rendered = $this->errorText->render("{exception} {list} {object}", $context);

        $this->assertSame("RuntimeException: boom [1,2] \"[object ArrayObject]\"", $rendered);

    }//end testRenderStringifiesComplexValues()


    /**
     * Long values are cut.
     *
     * @return void
     */
    public function testRenderTruncatesLongValues(): void
    {
        $context          = [];
        $context["value"] = str_repeat("a", 400);

        $rendered = $this->errorText->render("{value}", $context);

        $this->assertSame(sprintf("%s…", str_repeat("a", 300)), $rendered);

    }//end testRenderTruncatesLongValues()


    /**
     * Throwables keep their chain; deep arrays and objects are cut short.
     *
     * @return void
     */
    public function testNormalizeDescribesThrowablesAndLimitsDepth(): void
    {
        $previous  = new LogicException("inner");
        $exception = new RuntimeException("outer", 7, $previous);
        $deep      = [[[[[["six"]]]]]];

        $context              = [];
        $context["exception"] = $exception;
        $context["deep"]      = $deep;
        $context["object"]    = new ArrayObject();

        $normalized = $this->errorText->normalize($context, 0);

        $this->assertSame(RuntimeException::class, $normalized["exception"]["class"]);
        $this->assertSame(7, $normalized["exception"]["code"]);
        $this->assertSame("inner", $normalized["exception"]["previous"]["message"]);
        $this->assertSame("[array]", $normalized["deep"][0][0][0][0]);
        $this->assertSame("[object ArrayObject]", $normalized["object"]);

    }//end testNormalizeDescribesThrowablesAndLimitsDepth()


}//end class
