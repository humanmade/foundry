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
	public function rewind() : void {
		$this->position = 0;
	}

	/**
	 * Get the current item.
	 *
	 * Instantiates the model just-in-time to minimise memory usage.
	 *
	 * @return Model
	 */
	public function current() : Model {
		$model = $this->config['model'];
		return new $model( (array) $this->results[ $this->position ] );
	}

	/**
	 * Check if there is an object at the given offset.
	 *
	 * @param int $offset
	 * @return bool
	 */
	public function offsetExists( $offset ) : bool {
		return isset( $this->results[ $offset ] );
	}

	/**
	 * Get an object at the given offset.
	 *
	 * @param int $offset
	 * @return Model|null
	 */
	public function offsetGet( mixed $offset ) : ?Model {
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
	public function offsetSet( mixed $offset, mixed $value ) : void {
	}

	/**
	 * No op, the results set is immutable.
	 *
	 * @param mixed $offset
	 * @return void
	 */
	public function offsetUnset( mixed $offset ) : void {
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
	public function next() : void {
		++$this->position;
	}

	/**
	 * Check if the iterator's pointer is valid.
	 *
	 * @return boolean
	 */
	public function valid() : bool {
		return isset( $this->results[ $this->position ] );
	}

	/**
	 * Get the number of queried objects.
	 *
	 * This is the number of objects in the current results, that can be iterated over.
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
	 * @return Model[]
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
