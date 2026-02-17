<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

class HashidTest extends CIUnitTestCase
{
    /**
     * Test hashid_encode function
     */
    public function testHashidEncode()
    {
        $id = 123;
        $encoded = hashid_encode($id);

        $this->assertIsString($encoded);
        $this->assertNotEmpty($encoded);
        $this->assertGreaterThanOrEqual(8, strlen($encoded)); // Default min length is 8
    }

    /**
     * Test hashid_decode function
     */
    public function testHashidDecode()
    {
        $id = 456;
        $encoded = hashid_encode($id);
        $decoded = hashid_decode($encoded);

        $this->assertIsInt($decoded);
        $this->assertEquals($id, $decoded);
    }

    /**
     * Test encoding and decoding round trip
     */
    public function testHashidRoundTrip()
    {
        $testIds = [1, 10, 100, 999, 12345];

        foreach ($testIds as $id) {
            $encoded = hashid_encode($id);
            $decoded = hashid_decode($encoded);
            $this->assertEquals($id, $decoded, "Round trip failed for ID: $id");
        }
    }

    /**
     * Test that different IDs produce different hashes
     */
    public function testHashidUniqueness()
    {
        $encoded1 = hashid_encode(1);
        $encoded2 = hashid_encode(2);
        $encoded3 = hashid_encode(3);

        $this->assertNotEquals($encoded1, $encoded2);
        $this->assertNotEquals($encoded2, $encoded3);
        $this->assertNotEquals($encoded1, $encoded3);
    }

    /**
     * Test hashid_decode with invalid hash
     */
    public function testHashidDecodeInvalid()
    {
        $invalidHash = 'invalid_hash_xyz123';
        $decoded = hashid_decode($invalidHash);

        $this->assertNull($decoded);
    }

    /**
     * Test hashid_decode with empty string
     */
    public function testHashidDecodeEmpty()
    {
        $decoded = hashid_decode('');

        $this->assertNull($decoded);
    }

    /**
     * Test that the salt is configurable
     */
    public function testHashidsStaticInstance()
    {
        $hashids1 = hashids();
        $hashids2 = hashids();

        $this->assertSame($hashids1, $hashids2, 'Hashids should return same instance');
    }

    /**
     * Test encode produces consistent results
     */
    public function testHashidConsistency()
    {
        $id = 789;
        $encoded1 = hashid_encode($id);
        $encoded2 = hashid_encode($id);

        $this->assertEquals($encoded1, $encoded2);
    }

    /**
     * Test with large IDs
     */
    public function testHashidWithLargeId()
    {
        $largeId = 999999;
        $encoded = hashid_encode($largeId);
        $decoded = hashid_decode($encoded);

        $this->assertEquals($largeId, $decoded);
    }
}
