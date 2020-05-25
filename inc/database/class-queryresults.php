<?php

namespace Foundry\Database;

use Iterator;

class QueryResults implements Iterator {
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
