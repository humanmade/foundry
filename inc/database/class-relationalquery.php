<?php

namespace Foundry\Database;

use WP_Error;

class RelationalQuery extends Query {
	/**
	 * @psalm-param TLooseWhereClause $extra_args
	 *
	 * @param array $extra_args
	 * @return string|WP_Error
	 */
	protected function build_query() {
		/** @var \wpdb $wpdb */
		global $wpdb;

		// If there's no relationship query, just use the parent's method.
		if ( empty( $this->where['relationships'] ) ) {
			return parent::build_query();
		}

		// Remove the "relationships" key from the where clause.
		$relationships = $this->where['relationships'];
		$real_where = $this->where;
		unset( $real_where['relationships'] );

		// Build our regular where query first.
		$where = $this->build_where( $real_where );
		if ( is_wp_error( $where ) ) {
			return $where;
		}

		list( $where_string, $where_values ) = $where;

		// Now, build the relationship query.
		$join_where = $this->build_join_where( $relationships );
		if ( is_wp_error( $join_where ) ) {
			return $join_where;
		}

		list( $joins, $join_where_string, $join_where_values ) = $join_where;

		$args = $this->args;
		$page = $args['page'] ?? 1;
		$per_page = $args['per_page'] ?? 10;

		$offset = ( $page - 1 ) * $per_page;
		$limit = $per_page;

		$full_where = '';
		if ( ! empty( $where_string ) && ! empty( $join_where_string ) ) {
			$full_where = '( ' . $where_string . ' ) AND ( ' . $join_where_string . ' )';
		} else {
			$full_where = $where_string . $join_where_string;
		}
		$where_statement = empty( $full_where ) ? '' : 'WHERE ' . $full_where;

		$query = sprintf(
			'SELECT SQL_CALC_FOUND_ROWS `%1$s`.* FROM `%1$s` %2$s %3$s LIMIT %4$d, %5$d',
			$this->config['table'],
			implode( ' ', $joins ),
			$where_statement,
			$offset,
			$limit
		);
		$values = array_merge(
			$where_values,
			$join_where_values
		);

		$prepared = empty( $values ) ? $query : $wpdb->prepare( $query, $values );
		if ( ! $prepared ) {
			return new WP_Error( 'prepare-failed', 'Preparing values failed.' );
		}
		return $prepared;
	}

	protected function build_join_where( array $args ) {
		/** @var \wpdb $wpdb */
		global $wpdb;

		// The full args should be defined per TWhereClause, but we support $args
		// just being the `fields`, which is an implicit AND relation.
		if ( ! isset( $args['relation'] ) ) {
			$args = [
				'relation' => 'AND',
				'fields' => $args,
			];
		}

		$primary = get_primary_column( $this->config['schema'] );
		$relationships = $this->config['relationships'];
		$relation = $args['relation'];

		$added_rel_table = false;
		$joins = [];
		$where_string = '';
		$where_values = [];

		foreach ( $args['fields'] as $key => $query ) {
			if ( empty( $relationships[ $key ] ) ) {
				return new WP_Error( 'foundry.database.relationalquery.invalid-relation', 'Invalid relation key: ' . $key );
			}

			$model = $relationships[ $key ]['model'];
			switch ( $relationships[ $key ]['type'] ) {
				case 'has_many':
					// Has-many relationships are stored in a dedicated table.
					// Add it once, then reuse the join.
					$relations_table = $this->config['table'] . '_relationships';
					if ( ! $added_rel_table ) {
						$joins[] = sprintf(
							'LEFT JOIN `%s` ON `%s`.`left_id` = `%s`.`%s`',
							$relations_table,
							$relations_table,
							$this->config['table'],
							get_primary_column( $this->config['schema'] )
						);
						$added_rel_table = true;
					}

					// Normalize the value, if it's an object.
					if ( is_object( $query ) ) {
						$query = $query->get_id();
					}

					$where = sprintf(
						'`%1$s`.`relationship` = %2$s AND `%1$s`.`right_id` = %2$s',
						$relations_table,
						'%s',
						'%d'
					);
					$where_string .= ' ' . $relation . ' ' . $where;
					$where_values[] = $key;
					$where_values[] = $query;
					break;
				default:
					return new WP_Error( 'foundry.database.relationalquery.invalid-relation-type', 'Invalid relation type: ' . $relationships[ $key ]['type'] );
			}
		}

		$where_string = substr( $where_string, strlen( $relation ) + 1 );
		if ( $where_string === false ) {
			//          oWo
			return [ [], '', [] ];
		}
		return [ $joins, $where_string, $where_values ];
	}
}
