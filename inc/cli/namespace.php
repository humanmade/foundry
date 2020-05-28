<?php

namespace Foundry\Cli;

use WP_CLI;
use WP_CLI\Formatter;
use WP_Error;

function print_all_errors( WP_Error $error ) {
	$messages = $error->get_error_messages();
	WP_CLI::error( array_shift( $messages ), false );
	if ( ! empty( $messages ) ) {
		foreach ( $messages as $message ) {
			WP_CLI::error( "\t" . $message, false );
		}
	}
}

/**
 * Get Formatter object based on supplied parameters.
 *
 * @param array $assoc_args Parameters passed to command. Determines formatting.
 * @return Formatter
 */
function get_formatter( &$assoc_args, array $default_fields ) {
	$fields = $default_fields;

	if ( ! empty( $assoc_args['fields'] ) ) {
		if ( is_string( $assoc_args['fields'] ) ) {
			$fields = explode( ',', $assoc_args['fields'] );
		} else {
			$fields = $assoc_args['fields'];
		}
	}

	return new Formatter( $assoc_args, $fields );
}

/**
 * Read post content from file or STDIN
 *
 * @param string $arg Supplied argument
 * @return resource
 */
function read_from_file_or_stdin( $arg ) {
	if ( '-' !== $arg ) {
		$readfile = $arg;
		if ( ! file_exists( $readfile ) || ! is_file( $readfile ) ) {
			WP_CLI::error( "Unable to read content from '{$readfile}'." );
		}
	} else {
		$readfile = 'php://stdin';
	}
	return fopen( $readfile, 'r' );
}

function parse_assoc_arg( $value, $schema, $param ) {
	// If not declared in the schema, return.
	if ( ! isset( $schema[ $param ] ) || ! is_array( $schema[ $param ] ) ) {
		return $value;
	}

	$args = $schema[ $param ];
	$is_valid = rest_validate_value_from_schema( $value, $args, $param );
	if ( is_wp_error( $is_valid ) ) {
		return $is_valid;
	}

	return rest_sanitize_value_from_schema( $value, $args );
}
