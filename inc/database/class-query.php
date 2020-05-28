<?php

namespace Foundry\Database;

use WP_Error;

class Query {
	protected $config;
	protected $args;

	protected $executed = false;
	protected $total = null;

	public function __construct( array $config, array $args ) {
		$this->config = $config;
		$this->args = $args;
	}

	public function get_args() {
		return $this->args;
	}

	protected function repeat_placeholders( array $values ) : string {
		$count = count( $values );
		return trim( str_repeat( '%s, ', $count ), ' ,' );
	}

	protected function build_where( array $args ) : array {
		$where = [];
		$where_values = [];

		$fields = $this->config['schema']['fields'];
		foreach ( $fields as $key => $value ) {
			$key_in = sprintf( '%s__in', $key );
			$key_not_in = sprintf( '%s__not_in', $key );

			if ( isset( $args[ $key_in ] ) ) {
				$where[] = sprintf(
					'`%s` IN ( %s )',
					$key,
					$this->repeat_placeholders( (array) $args[ $key_in ] )
				);
				$where_values = array_merge( $where_values, array_values( (array) $args[ $key_in ] ) );
			}

			if ( isset( $args[ $key_not_in ] ) ) {
				$where[] = sprintf(
					'`%s` NOT IN ( %s )',
					$key,
					$this->repeat_placeholders( (array) $args[ $key_not_in ] )
				);
				$where_values = array_merge( $where_values, array_values( (array) $args[ $key_not_in ] ) );
			}

			if ( isset( $args[ $key ] ) ) {
				$where[] = sprintf(
					'`%s` = %%s',
					$key
				);
				$where_values[] = $args[ $key ];
			}
		}

		return [ $where, $where_values ];
	}

	protected function build_query( array $extra_args = [] ) : string {
		/** @var \wpdb $wpdb */
		global $wpdb;

		$args = array_merge( $this->args, $extra_args );

		$where = [];
		$where_values = [];

		list( $where, $where_values ) = $this->build_where( $args );
		$where_statement = empty( $where ) ? '' : 'WHERE ' . implode( ' AND ', $where );

		$page = $args['page'] ?? 1;
		$per_page = $args['per_page'] ?? 10;

		$offset = ( $page - 1 ) * $per_page;
		$limit = $per_page;

		$query = sprintf(
			'SELECT SQL_CALC_FOUND_ROWS * FROM `%s` %s LIMIT %d, %d',
			$this->config['table'],
			$where_statement,
			$offset,
			$limit
		);
		$values = array_merge(
			$where_values
		);

		$prepared = empty( $values ) ? $query : $wpdb->prepare( $query, $values );
		return $prepared;
	}

	/**
	 * Fetch the results for the query
	 *
	 * @return WP_Error|QueryResults
	 */
	public function get_results() {
		/** @var \wpdb $wpdb */
		global $wpdb;
		$query = $this->build_query();
		$results = $wpdb->get_results( $query );
		$this->executed = true;

		if ( $wpdb->last_error ) {
			return new WP_Error( 'foundry.database.query.could_not_execute', $wpdb->last_error );
		}

		$total = (int) $wpdb->get_var( 'SELECT FOUND_ROWS()' );
		return new QueryResults( $this->config, $results, $total );
	}
}
