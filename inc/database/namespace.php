<?php

namespace Foundry\Database;

use WP_Error;

/**
 * Ensure a table matches the scheme.
 *
 * Creates the table if it doesn't exist, or updates the table if necessary.
 *
 * @param string $name
 * @param array $schema
 * @return bool|WP_Error True on success, error otherwise.
 */
function ensure_table( string $name, array $schema ) {
	/** @var \wpdb $wpdb */
	global $wpdb;

	$res = $wpdb->query( $wpdb->prepare( 'SHOW TABLES LIKE %s;', $name ) );
	if ( ! $res ) {
		return create_table( $name, $schema );
	}

	return conform_table( $name, $schema );
}

/**
 * Create a table from the schema.
 *
 * @param string $name
 * @param array $schema
 * @return bool|WP_Error True on success, error otherwise.
 */
function create_table( string $name, array $schema ) {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();

	$fields = [];
	foreach ( $schema['fields'] as $key => $spec ) {
		$fields[] = sprintf( '`%s` %s', $key, $spec );
	}

	$indexes = implode( ",\n\t", $schema['indexes'] );

	$statement = sprintf(
		"CREATE TABLE %s (\n\t%s,\n\t%s\n) %s;",
		$name,
		implode( ",\n\t", $fields ),
		$indexes,
		$charset_collate
	);

	$prev = $wpdb->suppress_errors();
	$result = $wpdb->query( $statement );
	$wpdb->suppress_errors( $prev );

	if ( $result === true ) {
		return true;
	}

	$error = $wpdb->last_error;
	return new WP_Error( 'foundry.database.create_table.could_not_create', $error, compact( 'name', 'schema', 'statement' ) );
}

/**
 * Conform a table to a specification.
 *
 * Creates new columns and indexes if they are missing from a given table.
 *
 * Does not guarantee column or index order. Does not remove existing fields
 * or indexes.
 *
 * @param string $name
 * @param array $schema
 * @return bool|WP_Error True if table is updated, false if already up-to-date, WP_Error if table cannot be conformed.
 */
function conform_table( string $name, array $schema ) {
	/** @var \wpdb $wpdb */
	global $wpdb;

	// Find any missing columns.
	$missing_fields = $schema['fields'];
	$existing_fields = $wpdb->get_results( sprintf( 'DESCRIBE %s;', $name ) );
	foreach ( $existing_fields as $row ) {
		$key = $row->Field;
		unset( $missing_fields[ $key ] );
	}

	// Find any missing indexes.
	$missing_indexes = [];
	foreach ( $schema['indexes'] as $index ) {
		$parsed = parse_index( $index );
		if ( ! $parsed ) {
			return new WP_Error(
				'foundry.database.conform_table.invalid_index',
				sprintf(
					'Could not parse index: %s',
					$index
				)
			);
		}

		$missing_indexes[ $parsed['name'] ] = $index;
	}

	$existing_indexes = $wpdb->get_results( sprintf( 'SHOW INDEX FROM %s;', $name ) );

	foreach ( $existing_indexes as $index ) {
		$key = $index->Key_name;
		unset( $missing_indexes[ $key ] );
	}

	if ( empty( $missing_fields ) && empty( $missing_indexes ) ) {
		return false;
	}

	$alter_statements = [];
	foreach ( $missing_fields as $key => $spec ) {
		$alter_statements[] = sprintf(
			'ADD COLUMN `%s` %s',
			$key,
			$spec
		);
	}

	foreach ( $missing_indexes as $key => $spec ) {
		$alter_statements[] = sprintf(
			'ADD %s',
			$spec
		);
	}

	$statement = sprintf(
		"ALTER TABLE %s\n\t%s",
		$name,
		implode( ",\n\t", $alter_statements )
	);

	$prev = $wpdb->suppress_errors();
	$result = $wpdb->query( $statement );
	$wpdb->suppress_errors( $prev );

	if ( $result === true ) {
		return true;
	}

	$error = $wpdb->last_error;
	return new WP_Error( 'foundry.database.conform_table.could_not_update', $error, compact( 'name', 'schema', 'statement' ) );
}

/**
 * Parse an index.
 *
 * @param string $index Index specification string.
 * @return array|null Assoc array with type, name, and columns keys, or null if specification cannot be parsed.
 */
function parse_index( string $index ) : ?array {
	$trimmed = trim( $index );

	$res = preg_match(
		'/^'
		.   '(?P<index_type>'             // 1) Type of the index.
		.       'PRIMARY\s+KEY|(?:UNIQUE|FULLTEXT|SPATIAL)\s+(?:KEY|INDEX)|KEY|INDEX'
		.   ')'
		.   '\s+'                         // Followed by at least one white space character.
		.   '(?:'                         // Name of the index. Optional if type is PRIMARY KEY.
		.       '`?'                      // Name can be escaped with a backtick.
		.           '(?P<index_name>'     // 2) Name of the index.
		.               '(?:[0-9a-zA-Z$_-]|[\xC2-\xDF][\x80-\xBF])+'
		.           ')'
		.       '`?'                      // Name can be escaped with a backtick.
		.       '\s+'                     // Followed by at least one white space character.
		.   ')*'
		.   '\('                          // Opening bracket for the columns.
		.       '(?P<index_columns>'
		.           '.+?'                 // 3) Column names, index prefixes, and orders.
		.       ')'
		.   '\)'                          // Closing bracket for the columns.
		. '$/im',
		$trimmed,
		$parts
	);
	if ( ! $res ) {
		return null;
	}

	// Uppercase the index type and normalize space characters.
	$type = strtoupper( preg_replace( '/\s+/', ' ', trim( $parts['index_type'] ) ) );

	// 'INDEX' is a synonym for 'KEY', standardize on 'KEY'.
	$type = str_replace( 'INDEX', 'KEY', $type );

	$name = $parts['index_name'];
	if ( $type === 'PRIMARY KEY' ) {
		$name = 'PRIMARY';
	}

	// Use the columns if no name is explicitly set.
	if ( empty( $name ) ) {
		$name = $parts['index_columns'];
	}

	return [
		'type' => $type,
		'name' => $name,
		'columns' => $parts['index_columns']
	];
}

/**
 * Get the primary column from a schema.
 *
 * @param array $schema
 * @return string|null Column name if available.
 */
function get_primary_column( array $schema ) : ?string {
	foreach ( $schema['indexes'] as $index ) {
		$parsed = parse_index( $index );
		if ( ! $parsed ) {
			return new WP_Error(
				'foundry.database.conform_table.invalid_index',
				sprintf(
					'Could not parse index: %s',
					$index
				)
			);
		}

		if ( $parsed['name'] === 'PRIMARY' ) {
			return $parsed['columns'];
		}
	}

	return null;
}

/**
 * Atomically save many updates and inserts to the database.
 *
 * Uses a transaction to save the update of many models at once.
 *
 * The $dry_run parameter can be used to run all save operations before rolling
 * back, providing a way to check database constraints without changing the data.
 *
 * @param Model[] $models Models to save.
 * @param boolean $dry_run Set to true to "dry run" the update, which will not commit.
 * @return boolean|WP_Error True on success, error otherwise.
 */
function save_many( array $models, bool $dry_run = false ) {
	/** @var \wpdb $wpdb */
	global $wpdb;
	$result = $wpdb->query( 'START TRANSACTION' );
	if ( $result === false ) {
		return new WP_Error(
			'foundry.database.save_many.cannot_start_transaction',
			sprintf(
				'Could not start transaction: %s',
				$wpdb->last_error
			)
		);
	}

	foreach ( $models as $model ) {
		$result = $model->save();
		if ( ! is_wp_error( $result ) ) {
			continue;
		}

		// Found an error. Roll back.
		$rollback = $wpdb->query( 'ROLLBACK' );
		if ( $rollback === false ) {
			new WP_Error(
				'foundry.database.save_many.could_not_rollback',
				sprintf(
					'Could not rollback transaction: %s',
					$wpdb->last_error
				)
			);
		}

		return $result;
	}

	if ( $dry_run ) {
		// No errors, roll back.
		$rollback = $wpdb->query( 'ROLLBACK' );
		if ( $rollback === false ) {
			new WP_Error(
				'foundry.database.save_many.could_not_rollback',
				sprintf(
					'Could not rollback transaction: %s',
					$wpdb->last_error
				)
			);
		}

		return true;
	}

	$result = $wpdb->query( 'COMMIT' );
	if ( $result === false ) {
		return new WP_Error(
			'foundry.database.save_many.could_not_commit',
			'Could not commit changes'
		);
	}

	return true;
}
