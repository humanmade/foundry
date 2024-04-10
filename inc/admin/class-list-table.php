<?php

namespace Foundry\Admin;

use WP_List_Table;

abstract class List_Table extends WP_List_Table {
	/**
	 * Get the model for the command.
	 *
	 * @return string Class name of a model.
	 */
	abstract protected static function get_model() : string;

	/**
	 * Get the schema for the model.
	 *
	 * The expected return
	 */
	abstract protected function get_item_schema() : array;

	/**
	 * Prepare the model for output.
	 *
	 * @param Model $model Model object.
	 * @return array|WP_Error Data array on success, or WP_Error object on failure.
	 */
	abstract protected function prepare_model_for_output( $model );

	/**
	 * Prepares the list of items for displaying.
	 *
	 * @uses WP_List_Table::set_pagination_args()
	 *
	 * @since 3.1.0
	 * @abstract
	 */
	public function prepare_items() {
		$model = static::get_model();
		$this->items = $model::query( [] );
	}

	/**
	 * Get all columns to display in the list table.
	 *
	 * Derived from 
	 */
	public function get_columns() {
		$schema = static::get_item_schema();

		return array_map( function ( $column, $id ) {
			return $column['title'] ?? $id;
		}, $schema, array_keys( $schema ) );
	}

	/**
	 * Generates content for a single row of the table.
	 *
	 * @since 3.1.0
	 *
	 * @param object|array $item The current item
	 */
	public function single_row( $item ) {
		$formatted = $this->prepare_model_for_output( $item );
		$formatted['_model'] = $item;

		echo '<tr>';
		$this->single_row_columns( $formatted );
		echo '</tr>';
	}

	/**
	 * @param \Foundry\Database\Model $item
	 */
	public function column_default( $item, $column ) {
		$schema = static::get_item_schema();

		if ( ! isset( $schema[ $column ] ) || empty( $item[ $column ] ) ) {
			return '';
		}

		if ( isset( $schema[ $column ]['foundry:render'] ) ) {
			return call_user_func( $schema[ $column ]['foundry:render'], $item );
		}

		return $item[ $column ];
	}
}
