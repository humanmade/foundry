<?php

namespace Foundry\Database\Relations;

use Foundry\Database;

trait WithRelationships {
	protected $relation_handlers = [];

	abstract protected static function get_relationships() : array;
		// return [
		// 	'users' => [
		// 		'type' => 'has_many',
		// 		'model' => WP_User::class,
		// 	],
		// ];

	/**
	 * @return HasManyAssociation|null
	 */
	protected function get_relation( string $type ) {
		if ( ! empty( $this->relation_handlers[ $type ] ) ) {
			return $this->relation_handlers[ $type ];
		}

		$relation = static::get_relationships()[ $type ] ?? null;
		if ( ! $relation ) {
			return null;
		}

		switch ( $relation['type'] ) {
			case 'has_many':
				$relationship = new HasManyAssociation( static::get_table_name()  . '_relationships', $this, $type, $relation['model'] );
				break;

			// case 'has_one':
			// case 'belongs_to':
			// default:
		}
		$this->relation_handlers[ $type ] = $relationship;
		return $this->relation_handlers[ $type ];
	}

	public static function ensure_table() {
		$res = parent::ensure_table();
		if ( $res !== true ) {
			return $res;
		}

		$relationships = static::get_relationships();
		foreach ( $relationships as $key => $relation ) {
			$res = static::ensure_relationship_table( $key );
			if ( $res !== true ) {
				return $res;
			}
		}

		return true;
	}

	protected static function ensure_relationship_table( string $key ) {
		$relationships = static::get_relationships();
		$relation = $relationships[ $key ];
		switch ( $relation['type'] ) {
			case 'has_many':
				$table_name = static::get_table_name()  . '_relationships';
				$schema = [
					'fields' => [
						'relationship' => 'varchar(255) NOT NULL',
						'left_id' => 'bigint(20) unsigned NOT NULL',
						'right_id' => 'bigint(20) unsigned NOT NULL',
					],
					'indexes' => [
						'PRIMARY KEY (relationship, left_id, right_id)',
						'KEY (relationship, right_id)',
					],
				];

				return Database\ensure_table( $table_name, $schema );

			case 'belongs_to':
				// Owned by other model, so not needed here.
				return true;

			case 'has_one':
				// Handled by fields or something?
				return true;

			default:
				break;
		}
	}
}
