<?php

namespace Foundry\Database;

use WP_Date_Query;
use WP_Error;

/**
 * @template TModel of class-string<Model>
 *
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
 * @psalm-type TWhereScalarFieldQuery = array {
 *    compare: '<'|'<='|'>'|'>='|'='|'!='|'LIKE',
 *    value: scalar
 * }
 *
 * @psalm-type TWhereFieldQuery = scalar|TWhereDateFieldQuery|TWhereScalarFieldQuery
 *
 * @psalm-type TWhereClause = array {
 *     relation: 'OR'|'AND',
 *     fields: array<array-key, TWhereFieldQuery>
 * }
 *
 * @psalm-type TLooseWhereClause = TWhereClause|array<array-key, TWhereFieldQuery>
 *
 * @psalm-type TSchema = array {
 *     fields: array<array-key, string>,
 *     indexes?: array<array-key, string>
 * }
 *
 * @psalm-type TQueryArgs = array {
 *     page?: int,
 *     per_page?: int,
 * }
 *
 * @psalm-type TConfig = array {
 *      model: string,
 *      table: string,
 *      schema: TSchema,
 * }
 */
class Query {
	/**
	 * @psalm-var TConfig
	 * @var array
	 */
	protected $config;

	/**
	 * @var TQueryArgs
	 */
	protected $args;

	/**
	 * @var TLooseWhereClause
	 */
	protected $where;

	/**
	 * @var bool
	 */
	protected $executed = false;

	/**
	 * @var ?int
	 */
	protected $total = null;

	/**
	 * @psalm-param TConfig $config
	 *
	 * @psalm-param TLooseWhereClause $where
	 * @psalm-param TQueryArgs $args
	 *
	 * @param array $config
	 * @param array $where
	 * @param array $args
	 */
	public function __construct( array $config, array $where, array $args ) {
		$this->config = $config;
		$this->args = $args;
		$this->where = $where;
	}

	public function get_args() : array {
		return $this->args;
	}

	protected function repeat_placeholders( array $values ) : string {
		$count = count( $values );
		return trim( str_repeat( '%s, ', $count ), ' ,' );
	}

	/**
	 * Build the WHERE query from WHERE clauses
	 *
	 * @psalm-param TLooseWhereClause $args
	 * @psalm-return array{ 0: string, 1: list<scalar> }|WP_Error
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

		/** @var TWhereClause */
		$args = $args;

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
			} elseif ( is_string( $key ) ) {
				$where = [];
				if ( isset( $args[ $key ] ) ) {
					$query_where = $this->build_where_for_field_where_clause( $key, $args[ $key ] );
					if ( is_wp_error( $query_where ) ) {
						return $query_where;
					}

					array_push( $where, $query_where[0] );
					$where_values = array_merge( $where_values, $query_where[1] );
				}

				$where_string .= ' ' . $relation . ' ' . implode( ' ' . $relation . ' ', $where );
			}
		}

		$where_string = substr( $where_string, strlen( $relation ) + 1 );
		if ( $where_string === false ) {
			return [ '', [] ];
		}
		return [ $where_string, $where_values ];
	}

	/**
	 * Get the WHERE SQL clause for a single column and value.
	 *
	 * @psalm-param TWhereFieldQuery $clause
	 * @psalm-return array{ 0: string, 1: list<scalar> }|WP_Error
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
			/** @var TWhereDateFieldQuery $clause */
			$clause = $clause;
			$date_query = new WP_Date_Query( $clause, $this->config['table'] . '.' . $field );
			$sql = $date_query->get_sql();
			// Remove the WP_Date_Query "AND" top level relation.
			$where = substr( $sql, 5 );
			if ( $where === false ) {
				return new WP_Error( 'date-query-sql-error', 'SQL error in Date Query' );
			}
		} else {
			/** @var TWhereScalarFieldQuery */
			$where_item = $where_item;
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

	/**
	 * @psalm-param TLooseWhereClause $extra_args
	 *
	 * @param array $extra_args
	 * @return string|WP_Error
	 */
	protected function build_query() {
		/** @var \wpdb $wpdb */
		global $wpdb;

		$args = $this->args;

		$where = $this->build_where( $this->where );
		if ( is_wp_error( $where ) ) {
			return $where;
		}

		list( $where_string, $where_values ) = $where;
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
		if ( ! $prepared ) {
			return new WP_Error( 'prepare-failed', 'Preparing values failed.' );
		}
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
		if ( is_wp_error( $query ) ) {
			return $query;
		}
		/** @var list<object> */
		$results = $wpdb->get_results( $query );
		$this->executed = true;

		if ( $wpdb->last_error ) {
			return new WP_Error( 'foundry.database.query.could_not_execute', $wpdb->last_error );
		}

		$total = (int) $wpdb->get_var( 'SELECT FOUND_ROWS()' );
		return new QueryResults( $this->config, $results, $total );
	}
}
