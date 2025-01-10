<?php

namespace Foundry\Builtin;

use Foundry\Database\Model;
use WP_Error;
use WP_Term;

class Term extends Model {
	/**
	 * Save the pending changes to the database.
	 *
	 * @return bool|WP_Error True if saved successfully, false if no changes were made, error otherwise.
	 */
	public function save() {
		return new WP_Error(
			'foundry.builtin.model.save.cannot_save',
			'Built-in models cannot be saved'
		);
	}

	public function delete() {
		return new WP_Error(
			'foundry.builtin.model.delete.cannot_delete',
			'Built-in models cannot be deleted'
		);
	}

	/**
	 * Convert the model back to the term object.
	 *
	 * @return WP_Term
	 */
	public function as_term() : WP_Term {
		return get_term( $this->get_id() );
	}

	public static function get_table_name() : string {
		return $GLOBALS['wpdb']->prefix . 'terms';
	}

	/**
	 * Convert a term into a model.
	 *
	 * @param WP_Term $term
	 * @return static
	 */
	public static function from_term( WP_Term $term ) {
		$instance = new static( [
			'term_id' => $term->term_id,
			'name' => $term->name,
			'slug' => $term->slug,
			'term_group' => $term->term_group,
		] );
		return $instance;
	}

	/**
	 * Get a term model using the term ID.
	 *
	 * @param int $id
	 * @return static|WP_Error
	 */
	public static function from_id( int $id ) {
		$term = get_term( $id );
		if ( is_wp_error( $term ) ) {
			return $term;
		}
		return static::from_term( $term );
	}

	public static function get_table_schema() : array {
		return [
			'fields' => [
				'term_id' => 'bigint(20) unsigned NOT NULL auto_increment',
				'name' => 'varchar(200) NOT NULL default \'\'',
				'slug' => 'varchar(200) NOT NULL default \'\'',
				'term_group' => 'bigint(10) NOT NULL default 0',
			],
			'indexes' => [
				'PRIMARY KEY  (term_id)',
				'KEY slug (slug($max_index_length))',
				'KEY name (name($max_index_length))',
			],
		];
	}

	public static function ensure_table() {
		trigger_error( 'Terms table is a core WordPress table and cannot be created.', E_USER_ERROR );
		exit( 1 );
	}
}
