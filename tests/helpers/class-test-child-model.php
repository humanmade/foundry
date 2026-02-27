<?php

namespace Foundry\Tests;

use Foundry\Database\Model;

class Test_Child_Model extends Model {
	public static function get_table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'foundry_test_child';
	}

	public static function get_table_schema() : array {
		return [
			'fields' => [
				'id'    => 'BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT',
				'title' => 'VARCHAR(255) NOT NULL',
			],
			'indexes' => [
				'PRIMARY KEY (id)',
			],
		];
	}

	public function get_title() {
		return $this->get_field( 'title' );
	}

	public function set_title( $value ) {
		$this->set_field( 'title', $value );
	}
}
