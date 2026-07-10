<?php

namespace Foundry\Tests;

use Foundry\Database\Model;
use Foundry\Database\Relations\WithRelationships;

class Test_Parent_Model extends Model {
	use WithRelationships;

	public static function get_table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'foundry_test_parent';
	}

	public static function get_table_schema() : array {
		return [
			'fields' => [
				'id'    => 'BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT',
				'label' => 'VARCHAR(255) NOT NULL',
			],
			'indexes' => [
				'PRIMARY KEY (id)',
			],
		];
	}

	protected static function get_relationships() : array {
		return [
			'children' => [
				'type'  => 'has_many',
				'model' => Test_Child_Model::class,
			],
		];
	}

	public function get_label() {
		return $this->get_field( 'label' );
	}

	public function set_label( $value ) {
		$this->set_field( 'label', $value );
	}

	/**
	 * Public accessor for the children relationship.
	 */
	public function children() {
		return $this->get_relation( 'children' );
	}

	/**
	 * Public accessor for testing invalid relations.
	 */
	public function get_relation_public( string $type ) {
		return $this->get_relation( $type );
	}
}
