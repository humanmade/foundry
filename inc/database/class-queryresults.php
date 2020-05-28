<?php

namespace Foundry\Database;

use Countable;
use ArrayAccess;
use Iterator;

class QueryResults implements ArrayAccess, Countable, Iterator {
	protected $config;

	protected $results;

	protected $total_available;

	protected $position = 0;

	/**
	 * Constructor.
	 *
	 * @param array $config
	 * @param array $results
	 * @param integer $total_available
	 */
	public function __construct( array $config, array $results, int $total_available ) {
		$this->config = $config;
		$this->results = $results;
		$this->total_available = $total_available;
	}

	/**
	 * Reset the iterator's pointer.
	 *
	 * @return void
	 */
	public function rewind() {
		$this->position = 0;
	}

	/**
	 * Get the current item.
	 *
	 * Instantiates the model just-in-time to minimise memory usage.
	 *
	 * @return \Foundry\Database\Model
	 */
	public function current() {
		$model = $this->config['model'];
		return new $model( (array) $this->results[ $this->position ] );
	}

	public function offsetExists( $offset ) : bool {
		return isset( $this->results[ $offset ] );
	}

	public function offsetGet( $offset ) {
		if ( ! isset( $this->results[ $offset ] ) ) {
			return null;
		}
		$model = $this->config['model'];
		return new $model( (array) $this->results[ $offset ] );
	}

	/**
	 * No op, the results set is immutable.
	 *
	 * @param mixed $offset
	 * @param mixed $value
	 * @return void
	 */
	public function offsetSet( $offset, $value ) {
	}

	/**
	 * No op, the results set is immutable.
	 *
	 * @param mixed $offset
	 * @return void
	 */
	public function offsetUnset( $offset ) {
	}

	/**
	 * Get the key for the current item.
	 *
	 * @return int
	 */
	public function key() : int {
		return $this->position;
	}

	/**
	 * Increment the iterator's pointer.
	 *
	 * @return void
	 */
	public function next() {
		++$this->position;
	}

	/**
	 * Check if the iterator's pointer is valid.
	 *
	 * @return boolean
	 */
	public function valid() {
		return isset( $this->results[ $this->position ] );
	}

	/**
	 * Get the number of results.
	 *
	 * @return int
	 */
	public function count() : int {
		return count( $this->results );
	}

	/**
	 * Cast the iterator to an array.
	 *
	 * Generally, the iterator can be iterated directly without needing to be
	 * cast to an array. For some array operations however, it's useful to use
	 * an array.
	 *
	 * Note that this will instantiate all objects in the array, and may lead
	 * to high memory usage.
	 *
	 * @return \Foundry\Database\Model[]
	 */
	public function as_array() {
		return iterator_to_array( $this );
	}

	/**
	 * Get the total available results.
	 *
	 * @return integer
	 */
	public function get_total_available() : int {
		return $this->total_available;
	}
}
