<?php

namespace Foundry\Database\Relations;

use Foundry\Database;
use Foundry\Database\RelationalQuery;

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

		$res = static::ensure_relationship_table();
		if ( $res !== true ) {
			return $res;
		}

		return true;
	}

	public static function query( array $where, array $args = [] ) : Database\Query {
		$config = [
			'model' => get_called_class(),
			'table' => static::get_table_name(),
			'schema' => static::get_table_schema(),
			'relationships' => static::get_relationships(),
		];
		return new RelationalQuery( $config, $where, $args );
	}

	protected static function ensure_relationship_table() {
		$created_relationships_table = false;
		$relationships = static::get_relationships();
		foreach ( $relationships as $key => $relation ) {
			switch ( $relation['type'] ) {
				case 'has_many':
					if ( $created_relationships_table ) {
						break;
					}

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

					$res = Database\ensure_table( $table_name, $schema );
					if ( $res !== true ) {
						return $res;
					}

					$created_relationships_table = true;
					break;

				case 'belongs_to':
					// Handled by fields or something?
					break;

				case 'has_one':
					// Owned by other model, so not needed here.
					break;

				default:
					break;
			}
		}
	}
}
