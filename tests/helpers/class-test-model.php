<?php

namespace Foundry\Tests;

use Foundry\Database\Model;

class Test_Model extends Model {
	public static function get_table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'foundry_test';
	}

	public static function get_table_schema() : array {
		return [
			'fields' => [
				'id'         => 'BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT',
				'name'       => 'VARCHAR(255) NOT NULL',
				'status'     => "VARCHAR(50) NOT NULL DEFAULT 'draft'",
				'value'      => 'INT NOT NULL DEFAULT 0',
				'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
			],
			'indexes' => [
				'PRIMARY KEY (id)',
				'KEY status (status)',
			],
		];
	}

	public function get_name() {
		return $this->get_field( 'name' );
	}

	public function set_name( $value ) {
		$this->set_field( 'name', $value );
	}

	public function get_status() {
		return $this->get_field( 'status' );
	}

	public function set_status( $value ) {
		$this->set_field( 'status', $value );
	}

	public function get_value() {
		return $this->get_field( 'value' );
	}

	public function set_value( $value ) {
		$this->set_field( 'value', $value );
	}

	public function get_created_at() {
		return $this->get_field( 'created_at' );
	}

	public function set_created_at( $value ) {
		$this->set_field( 'created_at', $value );
	}

	public function set_arbitrary_field( $key, $value ) {
		$this->set_field( $key, $value );
	}
}

/**
 * A model whose save() always fails, for testing rollback behavior.
 */
class Failing_Test_Model extends Test_Model {
	public function save() {
		return new \WP_Error( 'test_error', 'Intentional failure' );
	}
}
