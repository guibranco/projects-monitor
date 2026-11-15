<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionStateRequest;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionStore;

/**
 * Tests for AttentionStateRequest.
 */
class AttentionStateRequestTest extends TestCase
{
    private const NOW = 1763221200;


    /**
     * Invalid bodies are rejected with a reason, valid ones pass.
     *
     * @return void
     */
    public function testValidate(): void
    {
        $this->assertNotNull((new AttentionStateRequest("not an array"))->validate());
        $this->assertNotNull((new AttentionStateRequest(array_merge(["fingerprint" => "", "action" => "clear"])))->validate());
        $this->assertNotNull((new AttentionStateRequest(array_merge(["fingerprint" => str_repeat("x", 192), "action" => "clear"])))->validate());
        $this->assertNotNull((new AttentionStateRequest(array_merge(["fingerprint" => "hc:a", "action" => "delete"])))->validate());
        $this->assertNotNull((new AttentionStateRequest(array_merge(["fingerprint" => "hc:a", "action" => "snooze"])))->validate());
        $this->assertNotNull((new AttentionStateRequest(array_merge(["fingerprint" => "hc:a", "action" => "snooze", "minutes" => 0])))->validate());
        $this->assertNotNull((new AttentionStateRequest(array_merge(["fingerprint" => "hc:a", "action" => "snooze", "minutes" => 43201])))->validate());
        $this->assertNotNull((new AttentionStateRequest(array_merge(["fingerprint" => "hc:a", "action" => "acknowledge", "since" => 5])))->validate());

        $this->assertNull((new AttentionStateRequest(array_merge(["fingerprint" => "hc:a", "action" => "snooze", "minutes" => "60"])))->validate());
        $this->assertNull((new AttentionStateRequest(array_merge(["fingerprint" => "hc:a", "action" => "acknowledge", "since" => null])))->validate());
        $this->assertNull((new AttentionStateRequest(array_merge(["fingerprint" => "hc:a", "action" => "clear"])))->validate());

    }//end testValidate()


    /**
     * Each action calls the matching store method.
     *
     * @return void
     */
    public function testApply(): void
    {
        $store = $this->createMock(AttentionStore::class);
        $store->expects($this->once())->method("snooze")->with("hc:a", (self::NOW + 3600), 7)->willReturn(true);
        $store->expects($this->once())->method("acknowledge")->with("hc:b", "2026-11-15T15:40:00Z", 7)->willReturn(true);
        $store->expects($this->once())->method("clear")->with("hc:c")->willReturn(true);

        $snooze = new AttentionStateRequest(array_merge(["fingerprint" => "hc:a", "action" => "snooze", "minutes" => 60]));
        $this->assertTrue($snooze->apply($store, 7, self::NOW));
        $this->assertSame("snooze", $snooze->toArray()["action"]);
        $this->assertTrue((new AttentionStateRequest(array_merge(["fingerprint" => "hc:b", "action" => "acknowledge", "since" => "2026-11-15T15:40:00Z"])))->apply($store, 7, self::NOW));
        $this->assertTrue((new AttentionStateRequest(array_merge(["fingerprint" => "hc:c", "action" => "clear"])))->apply($store, 7, self::NOW));

    }//end testApply()


}//end class
