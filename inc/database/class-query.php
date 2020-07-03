<?php

namespace Foundry\Database;

use WP_Date_Query;
use WP_Error;

/**
 * @psalm-type TWhereDateFieldQuery = array {
 *    compare: '<'|'<='|'>'|'>='|'='|'!='|'IN'|'NOT IN'|'BETWEEN'|'NOT BETWEEN',
 *    year: int|list<int>,
 *    month: int|list<int>,
 *    week: int|list<int>,
 *    dayofyear: int|list<int>,
 *    day: int|list<int>,
 *    dayofweek: int|list<int>,
 *    dayofweek_iso: int|list<int>,
 *    hour: int|list<int>,
 *    minute: int|list<int>,
 *    second: int|list<int>,
 *    inclusive: bool
 * }
 *
 * @psalm-type TWhereFieldQuery = array {
 *    compare: '<'|'<='|'>'|'>='|'='|'!='|'LIKE',
 *    value: int|float|string|bool
 * }|scalar|TWhereDateFieldQuery
 *
 * @psalm-type TWhereClause = array {
 *     relation?: 'OR'|'AND',
 *     fields: array<array-key, TWhereFieldQuery>
 * }|array<array-key, TWhereFieldQuery>
 */
class Query {
	protected $config;
	protected $args;

	protected $executed = false;
	protected $total = null;

	protected $query;

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

	/**
	 * Build the WHERE query from WHERE clauses
	 *
	 * @psalm-param TWhereClause $args
	 * @psalm-return array{ 0: string, 1: list<scalar> }
	 *
	 * @param array $args
	 * @return array|WP_Error
	 */
	protected function build_where( array $args ) {
		$where = [];
		$where_values = [];

		// The full args should be defined per TWhereClause, but we support $args
		// just being the `fields`, which is an implicit AND relation.
		if ( ! isset( $args['relation'] ) ) {
			$args = [
				'relation' => 'AND',
				'fields' => $args,
			];
		}

		$fields = $this->config['schema']['fields'];
		$relation = $args['relation'];

		// Build up the WHERE query in a string. We use a single string rather than an array of
		// where conditions, because each WHERE condition can have a different relations (AND / OR / nesting)
		// that makes the array of clauses approach impractical.
		$where_string = '';
		$args = $args['fields'];
		foreach ( $args as $key => $query ) {
			// Any "field" that is actually just an array with a numeric key is a
			// sub-clause with it's own nested relation.
			if ( is_int( $key ) && is_array( $query ) ) {
				$sub_query = $this->build_where( $query );
				if ( is_wp_error( $sub_query ) ) {
					return $sub_query;
				}
				$where_string .= sprintf( ' %s ( %s )', $relation, $sub_query[0] );
				$where_values = array_merge( $where_values, $sub_query[1] );
			} else {
				$where = [];
				// TODO: this is not supported since we switched to loop over the fields in the query, rather
				// than all known fields. We'll need to instead reverse $field*__in` etc to $field, and create
				// the query accordingly.
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
					$query_where = $this->build_where_for_field_where_clause( $key, $args[ $key ] );
					if ( is_wp_error( $query_where ) ) {
						return $query_where;
					}

					$where = array_merge( $where, $query_where[0] );
					$where_values = array_merge( $where_values, $query_where[1] );
				}

				$where_string .= ' ' . $relation . ' ' . implode( ' ' . $relation . ' ', $where );
			}
		}

		$where_string = substr( $where_string, strlen( $relation ) + 1 );
		return [ $where_string, $where_values ];
	}

	/**
	 * Get the WHERE SQL clause for a single column and value.
	 *
	 * @psalm-param TWhereFieldQuery $clause
	 * @psalm-return array{ 0: string, 1: list<mixed> }|WP_Error
	 *
	 * @param string $field The field/column name
	 * @param mixed $clause
	 * @return array|WP_Error
	 */
	protected function build_where_for_field_where_clause( string $field, $clause ) {
		$fields = $this->config['schema']['fields'];

		$where = [];
		$where_values = [];

		$where_item = is_array( $clause ) ? $clause : [ 'compare' => '=', 'value' => $clause ];

		// Use WP_Date_Query for date columns.
		if ( $fields[ $field ] === 'date' ) {
			$date_query = new WP_Date_Query( $clause, $this->config['table'] . '.' . $field );
			$sql = $date_query->get_sql();
			// Remove the WP_Date_Query "AND" top level relation.
			$where = substr( $sql, 5 );
		} else {
			switch ( $where_item['compare'] ) {
				case '>':
				case '>=':
				case '<':
				case '<=':
				case '=':
				case '!=':
				case 'LIKE':
					$where = sprintf(
						'`%s` %s %%s',
						$field,
						$where_item['compare']
					);
					$where_values[] = $where_item['value'];
				break;
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

		list( $where_string, $where_values ) = $this->build_where( $args );
		$where_statement = empty( $where_string ) ? '' : 'WHERE ' . $where_string;

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
