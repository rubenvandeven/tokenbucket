<?php
/**
 * Copyright (c) Fatih Ustundag <fatih.ustundag@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TokenBucket;

use TokenBucket\Storage\Memcached as MemcachedStorage;
use TokenBucket\Storage\StorageInterface;

class TokenBucketTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var StorageInterface
     */
    private $storage;

    /**
     * @var TokenBucket
     */
    private $tokenBucket;

    /**
     * @var \Memcached
     */
    protected static $memcached;

    public static function setUpBeforeClass()
    {
        self::$memcached = new \Memcached();
        self::$memcached->addServer('127.0.0.1', 11211);
        self::$memcached->setOptions(array(
            \Memcached::OPT_TCP_NODELAY => true,
            \Memcached::OPT_NO_BLOCK => true,
            \Memcached::OPT_CONNECT_TIMEOUT => 100
        ));
    }

    public static function tearDownAfterClass()
    {
        self::$memcached = null;
    }

    public function setUp()
    {
        self::$memcached->flush();
        $this->storage     = new MemcachedStorage(self::$memcached);
        $this->tokenBucket = new TokenBucket('test', $this->storage);
    }

    public function tearDown()
    {
        $this->storage     = null;
        $this->tokenBucket = null;
    }

    public function testGetTtl()
    {
        $this->assertEquals(6.0, $this->tokenBucket->getTtl(), 'GetTttl failed');
    }

    public function testSetOptions()
    {
        $this->tokenBucket->setOptions(array('capacity' => 30, 'fillRate' => 10));
        $this->assertEquals(30, $this->tokenBucket->getCapacity(), 'New capacity option set failed');
        $this->assertEquals(10, $this->tokenBucket->getFillRate(), 'New fillRate option set failed');
    }

    public function testSetOptionsDefault()
    {
        $this->tokenBucket->setOptions(array('capacity' => -12, 'fillRate' => 'abc'));
        $this->assertEquals(20, $this->tokenBucket->getCapacity(), 'Default capacity option value failed');
        $this->assertEquals(5, $this->tokenBucket->getFillRate(), 'Default fillRate option value failed');
    }

    public function testFill()
    {
        $this->tokenBucket->setOptions(array('capacity' => 100, 'fillRate' => 10));
        $this->storage->set(
            $this->tokenBucket->getBucketKey(),
            array('count' => 50, 'time' => time()),
            $this->tokenBucket->getTtl()
        );
        sleep(1);
        $this->tokenBucket->fill();
        $this->assertEquals(60, $this->tokenBucket->getBucket()['count'], 'Fill does not work');

    }

    public function testConsume()
    {
        $this->assertTrue($this->tokenBucket->consume(), 'Consume failed');
        $this->assertEquals(19, $this->tokenBucket->getBucket()['count'], 'Token Count after consume failed');
        $this->assertNotEmpty($this->storage->get($this->tokenBucket->getBucketKey()), 'Key not found at storage');
        $this->assertArrayHasKey(
            'count',
            $this->storage->get($this->tokenBucket->getBucketKey()),
            '"count" index not found at storage key'
        );
        $this->assertEquals(
            19,
            $this->storage->get($this->tokenBucket->getBucketKey())['count'],
            'Token Count after consume failed'
        );

        sleep(1);
        $this->assertFalse($this->tokenBucket->consume(22), 'Not Consume failed');
        $this->assertEquals(20, $this->tokenBucket->getBucket()['count'], 'Token Count after not consumed failed');
    }
}