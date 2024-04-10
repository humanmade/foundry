<?php

namespace Foundry\Database;

use WP_Error;

abstract class Model {
	use Table;

	/**
	 * Model data.
	 *
	 * @var array
	 */
	protected $data;

	/**
	 * Updated data to be saved.
	 *
	 * @var array
	 */
	protected $updated = [];

	/**
	 * Whether the model has been deleted from the database.
	 *
	 * @var boolean
	 */
	protected $deleted = false;

	public function __construct( array $data = [] ) {
		$this->data = $data;
	}

	public static function query( array $where, array $args = [] ) : Query {
		$config = [
			'model' => get_called_class(),
			'table' => static::get_table_name(),
			'schema' => static::get_table_schema(),
		];
		return new Query( $config, $where, $args );
	}

	/**
	 * Get the unique ID for this model.
	 *
	 * All models must have a unique ID. In most cases, you can use the WithId
	 * trait for this.
	 *
	 * @return int Unique ID for the model.
	 */
	public function get_id() : ?int {
		$schema = static::get_table_schema();
		$primary = get_primary_column( $schema );
		return $this->get_field( $primary );
	}

	/**
	 * Get a field value.
	 *
	 * Retrieves the value from a pending update if set, otherwise from the
	 * database value.
	 *
	 * @param string $key
	 * @return mixed|null Value if set, null otherwise.
	 */
	protected function get_field( string $key ) {
		if ( isset( $this->updated[ $key ] ) ) {
			return $this->updated[ $key ];
		}

		return $this->data[ $key ] ?? null;
	}

	/**
	 * Set a field value.
	 *
	 * Sets the field value to save on the next update.
	 *
	 * @param string $key
	 * @param mixed $value
	 * @return void
	 */
	protected function set_field( string $key, $value ) {
		$this->updated[ $key ] = $value;
	}

	/**
	 * Is the model new?
	 *
	 * Determines whether the model exists in the database.
	 *
	 * @return boolean True if the model does not exist in the database, false otherwise.
	 */
	public function is_new() : bool {
		return empty( $this->data );
	}

	/**
	 * Is the model modified?
	 *
	 * Determines whether this instance of the model has pending changes to be
	 * saved to the database.
	 *
	 * @return boolean True if the model has been modified, false otherwise.
	 */
	public function is_modified() : bool {
		return ! empty( $this->updated );
	}

	/**
	 * Has the model been deleted?
	 *
	 * Determines whether the model has been deleted from the database.
	 *
	 * @return boolean True if the model has been deleted, false otherwise.
	 */
	public function is_deleted() : bool {
		return $this->deleted;
	}

	/**
	 * Reload data from the database.
	 *
	 * Discards any local modifications, and reloads all data from the
	 * database.
	 *
	 * Deleted and new records cannot be reloaded.
	 *
	 * @return bool
	 */
	public function reload() {
		if ( $this->is_deleted() ) {
			return new WP_Error(
				'foundry.database.model.reload.cannot_reload_deleted',
				'Deleted models cannot be reloaded'
			);
		}

		if ( $this->is_new() ) {
			return new WP_Error(
				'foundry.database.model.reload.cannot_reload_new',
				'New models cannot be reloaded'
			);
		}

		$schema = static::get_table_schema();
		$primary = get_primary_column( $schema );

		$id = $this->data[ $primary ];
		$data = static::fetch_record( $id );

		$this->data = $data;
		$this->updated = [];
		return true;
	}

	/**
	 * Save the pending changes to the database.
	 *
	 * @return bool|WP_Error True if saved successfully, false if no changes were made, error otherwise.
	 */
	public function save() {
		/** @var \wpdb $wpdb */
		global $wpdb;

		if ( $this->is_deleted() ) {
			return new WP_Error(
				'foundry.database.model.save.cannot_save_deleted',
				'Deleted model cannot be saved'
			);
		}

		if ( empty( $this->updated ) ) {
			return false;
		}

		$table = static::get_table_name();
		$schema = static::get_table_schema();
		$primary = get_primary_column( $schema );

		$updated = $this->updated;

		if ( $this->is_new() ) {
			$res = $wpdb->insert(
				$table,
				$updated
			);

			if ( $res !== 1 ) {
				// If there's no error, then we just didn't need to update
				// the database.
				if ( ! $wpdb->last_error ) {
					return false;
				}

				return new WP_Error(
					'foundry.database.model.save.could_not_update',
					$wpdb->last_error,
					compact( 'table', 'updated' )
				);
			}

			// Reload the object.
			$this->data = [
				$primary => (int) $wpdb->insert_id,
			];
			$this->reload();
		} else {
			$where = [
				$primary => $this->data[ $primary ],
			];

			$res = $wpdb->update(
				$table,
				$updated,
				$where
			);

			if ( $res !== 1 ) {
				// If there's no error, then we just didn't need to update
				// the database.
				if ( ! $wpdb->last_error ) {
					return false;
				}

				return new WP_Error(
					'foundry.database.model.save.could_not_update',
					$wpdb->last_error,
					compact( 'table', 'updated', 'where' )
				);
			}

			// Update internal reference.
			$this->data = array_merge( $this->data, $this->updated );
			$this->updated = [];
		}

		return true;
	}

	public function delete() {
		/** @var \wpdb $wpdb */
		global $wpdb;

		$table = static::get_table_name();
		$schema = static::get_table_schema();
		$primary = get_primary_column( $schema );
		$where = [
			$primary => $this->data[ $primary ],
		];

		$res = $wpdb->delete(
			$table,
			$where
		);
		if ( $res !== 1 ) {
			return new WP_Error(
				'foundry.database.model.delete.could_not_delete',
				$wpdb->last_error,
				compact( 'table', 'where' )
			);
		}

		$this->deleted = true;
		return true;
	}

	/**
	 * Get an instance of the model
	 *
	 * @param integer $id ID of the model.
	 * @return static|null Model instance if available, null if not found.
	 */
	public static function get( int $id ) : ?self {
		$data = static::fetch_record( $id );
		if ( empty( $data ) ) {
			return null;
		}

		return new static( $data );
	}

	/**
	 * Fetch a record from the database.
	 *
	 * @param integer $id ID of the model.
	 * @return array|null Raw database data if available, null if not found.
	 */
	protected static function fetch_record( int $id ) {
		/** @var \wpdb $wpdb */
		global $wpdb;

		$table = static::get_table_name();
		$schema = static::get_table_schema();
		$primary = get_primary_column( $schema );

		$query = "SELECT * FROM `$table` WHERE `$primary` = %d LIMIT 1";
		$prepared = $wpdb->prepare( $query, $id );
		return $wpdb->get_row( $prepared, ARRAY_A );
	}
}
