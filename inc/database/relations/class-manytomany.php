<?php

namespace Foundry\Database\Relations;

use Foundry\Database\Model;

trait ManyToMany {
	abstract protected function get_relationship_table_name() : string;
	abstract protected function get_relationship_id() : string;
	abstract protected function get_left_model() : string;
	abstract protected function get_right_model() : string;

	protected function get_left_ids( Model $right ) : array {
		global $wpdb;
		$right_model = $this->get_right_model();
		if ( ! ( $right instanceof $right_model ) ) {
			return null;
		}

		$table = $this->get_relationship_table_name();
		return $wpdb->get_col( $wpdb->prepare(
			"SELECT `left_id` FROM `$table` WHERE relationship = %s AND right_id = %d",
			$this->get_relationship_id(),
			$right->get_id()
		) );
	}

	protected function get_left( Model $right ) : array {
		$ids = $this->get_left_ids( $right );
		return array_map( function ( int $id ) {
			return $this->get_left_model()::get( $id );
		}, $ids );
	}

	protected function get_right_ids( Model $left ) : array {
		global $wpdb;
		$left_model = $this->get_left_model();
		if ( ! ( $left instanceof $left_model ) ) {
			return null;
		}

		$table = $this->get_relationship_table_name();
		return $wpdb->get_col( $wpdb->prepare(
			"SELECT `right_id` FROM `$table` WHERE relationship = %s AND left_id = %d",
			$this->get_relationship_id(),
			$left->get_id()
		) );
	}

	protected function get_right( Model $left ) : array {
		$ids = $this->get_right_ids( $left );
		return array_map( function ( int $id ) {
			return $this->get_right_model()::get( $id );
		}, $ids );
	}

	protected function create_relation( Model $left, Model $right ) {
		global $wpdb;
		$result = $wpdb->insert(
			$this->get_relationship_table_name(),
			[
				'relationship' => $this->get_relationship_id(),
				'left_id' => $left->get_id(),
				'right_id' => $right->get_id(),
			],
			[
				'%s',
				'%d',
				'%d',
			]
		);
	}

	protected function remove_relation( Model $left, Model $right ) {
		global $wpdb;
		$result = $wpdb->delete(
			$this->get_relationship_table_name(),
			[
				'relationship' => $this->get_relationship_id(),
				'left_id' => $left->get_id(),
				'right_id' => $right->get_id(),
			],
			[
				'%s',
				'%d',
				'%d'
			]
		);
	}
}
