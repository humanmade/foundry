<?php

namespace Foundry\Database;

trait Table {
	/**
	 * Ensure the table for the model exists.
	 *
	 * @return void
	 */
	public static function ensure_table() {
		$name = static::get_table_name();
		$schema = static::get_table_schema();

		return ensure_table( $name, $schema );
	}

	/**
	 * Get the table name for the model.
	 *
	 * @return string
	 */
	abstract protected static function get_table_name() : string;

	/**
	 * Get the table schema for the model.
	 *
	 * @return array
	 */
	abstract protected static function get_table_schema() : array;
}
