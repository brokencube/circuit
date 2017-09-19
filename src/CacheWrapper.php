<?php

namespace Circuit;

use Psr\SimpleCache\CacheInterface as Psr16;
use Psr\Cache\CacheItemPoolInterface as Psr6;

/**
 * CacheWrapper
 *
 * Wrap PSR 6/16 cache objects with simple interface
 *
 * @author Nik Barham <nik@brokencube.co.uk>
 */
class CacheWrapper
{
    /** @var Psr16|Psr6 PSR6/16 compatible cache item */
    protected $cache;
    
    /**
     * @param Psr16|Psr6 $cache A PSR-6 or PSR-16 compatible Cache object
     */
    public function __construct($cache = null)
    {
        $this->cache = $cache;
        
        // PSR-16 Cache
        if (!$this->cache instanceof Psr16 && !$this->cache instanceof Psr6) {
            throw new \InvalidArgumentException('Expect PSR 6/16 compatible cache object');
        }
    }
    
    /**
     * @param mixed $key Key to get from the cache
     */
    public function get($key)
    {
        // PSR-16 Cache
        if ($this->cache instanceof Psr16) {
            return $this->cache->get($key);
        }
        
        // PSR-6 Cache
        if ($this->cache instanceof Psr6) {
            $item = $this->cache->getItem($key);
            if ($item->isHit()) {
                return $item->get();
            }
        }
        
        return null;
    }
    
    /**
     * @param mixed $key Key to set in the cache
     * @param mixed $value Value to set in the cache
     * @param mixed $expiry Cache expiry in seconds
     */
    public function set($key, $value, $expiry = 3600)
    {
        // PSR-16 Cache
        if ($this->cache instanceof Psr16) {
            return $this->cache->set($key, $value, $expiry);
        }
        
        // PSR-6 Cache
        if ($this->cache instanceof Psr6) {
            $item = $this->cache->getItem($key);
            $item->set($value);
            $item->expiresAt(new \DateTime('now + ' . $expiry . 'seconds'));
            return $this->cache->save($item);
        }
    }
}
