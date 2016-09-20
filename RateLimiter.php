<?php

/**
 * Class RateLimiter
 *
 * usage:
 *
 * $limit = new \Utils\RateLimiter(300,10);
 *
 * if ($limit->check('event')){
 *      //do something
 * }
 *
 */

class RateLimiter {

	private $redis = false;
	private $connected = false;
	private $time_interval = 0;
	private $attempts = 0;

	/**
	 * RateLimiter constructor.
	 *
	 * @param int    $time_interval time frame
	 * @param int    $attempts      number of allowed atempts
	 * @param string $redis_server  ip of redis server
	 */
	public function __construct( $time_interval = 300, $attempts = 10, $redis_server = '127.0.0.1' ) {
		$this->time_interval = $time_interval;
		$this->attempts      = $attempts;
		if ( class_exists( "\\Redis" ) ) {
			$this->redis = new \Redis();
			$this->connected = $this->redis->connect( $redis_server, 6379, .5 );
		}
	}

	/**
	 * check if this attempt is within permited limits
	 *
	 * @param mixed $conditions
	 *
	 * @return bool true if limit is not reached
	 */
	public function check( $conditions ) {
		if ( $this->connected ) {
			$key    = 'RL:' . sha1( serialize( $conditions ) );
			$mtime  = microtime( true );
			$result = (array) $this->redis->multi()
			                              ->zRemRangeByScore( $key, 0, $mtime - $this->time_interval )
			                              ->zAdd( $key, $mtime, $mtime )
			                              ->zRange( $key, 0, - 1 )
			                              ->expire( $key, $this->time_interval )
			                              ->exec();

			return $this->attempts >= count( $result['2'] );
		}
		return true;
	}
}
