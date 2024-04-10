<?php

namespace Foundry\Cli;

use Foundry\Database\Model;
use WP_CLI;
use WP_CLI\Formatter;
use WP_CLI\Iterators\Transform;
use WP_Error;

abstract class Command {
	/**
	 * Get command.
	 *
	 * @return string
	 */
	abstract protected static function get_command() : string;

	/**
	 * Get the model for the command.
	 *
	 * @return string Class name of a model.
	 */
	abstract protected static function get_model() : string;

	/**
	 * Prepare the model for output.
	 *
	 * @param Model $model Model object.
	 * @param array $assoc_args Arguments passed from the command line.
	 * @return Model|WP_Error Modified model on success, or WP_Error object on failure.
	 */
	abstract protected function prepare_changes_for_database( $model, array $assoc_args );

	/**
	 * Prepare the model for output.
	 *
	 * @param Model $model Model object.
	 * @return array|WP_Error Data array on success, or WP_Error object on failure.
	 */
	abstract protected function prepare_model_for_output( $model );

	abstract protected function get_item_schema() : array;

	/**
	 * Register the CLI command.
	 */
	public static function register() {
		if ( ! is_subclass_of( static::get_model(), Model::class ) ) {
			trigger_error(
				'Model classes must extend Model',
				E_USER_ERROR
			);
		}

		WP_CLI::add_command( static::get_command(), get_called_class() );
	}

	/**
	 * Create a new item.
	 *
	 * ## OPTIONS
	 *
	 * [--<field>=<value>]
	 * : Associative args for the new item.
	 *
	 * [--porcelain]
	 * : Output just the new item's ID.
	 */
	public function create( $args, $assoc_args ) {
		// Check all required/missing args first.
		$schema = $this->get_item_schema();
		$required = [];
		foreach ( $schema as $key => $arg_opts ) {
			if ( isset( $arg_opts['default'] ) && empty( $assoc_args[ $key ] ) ) {
				$assoc_args[ $key ] = $arg_opts['default'];
			}

			if ( isset( $arg_opts['required'] ) && $arg_opts['required'] === true && empty( $assoc_args[ $key ] ) ) {
				$required[] = $key;
			}
		}
		if ( ! empty( $required ) ) {
			WP_CLI::error(
				sprintf(
					__( 'Missing args(s): %s' ),
					implode( ', ', $required )
				)
			);
		}

		// Sanitize our inputs.
		$props = $this->sanitize_assoc_args( $assoc_args );
		if ( is_wp_error( $props ) ) {
			print_all_errors( $props );
			return;
		}

		$model = static::get_model();

		/** @var Model|null $item */
		$item = new $model();

		// Prepare the changes.
		$new_item = $this->prepare_changes_for_database( $item, $props );
		if ( is_wp_error( $new_item ) ) {
			WP_CLI::error( $new_item->get_error_message() );
		}

		if ( ! $new_item->is_new() ) {
			WP_CLI::error( 'Cannot create item with ID; use `update` command instead.' );
			return;
		}

		if ( ! $new_item->is_modified() ) {
			WP_CLI::error( 'Need some fields to create.' );
		}

		$did_create = $new_item->save();
		if ( is_wp_error( $did_create ) ) {
			WP_CLI::error( $did_create->get_error_message() );
		}

		if ( isset( $assoc_args['porcelain'] ) ) {
			echo (int) $new_item->get_id();
		} elseif ( ! $did_create ) {
			WP_CLI::success( 'No updates required.' );
		} else {
			WP_CLI::success( sprintf( 'Updated item %d.', $new_item->get_id() ) );
		}
	}

	/**
	 * Get details about a post.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The ID of the post to get.
	 *
	 * [--field=<field>]
	 * : Instead of returning the whole post, returns the value of a single field.
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific fields. Defaults to all fields.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * @param array $args Positional arguments
	 * @param array $assoc_args Associative (option) arguments
	 */
	public function get( $args, $assoc_args ) {
		if ( ! is_numeric( $args[0] ) ) {
			WP_CLI::error( 'Invalid ID (non-numeric)' );
		}

		$model = static::get_model();

		/** @var Model|null $item */
		$item = $model::get( (int) $args[0] );
		if ( empty( $item ) ) {
			WP_CLI::error( sprintf( 'Could not find item with ID %d.', (int) $args[0] ) );
		}

		$data = $this->prepare_model_for_output( $item );

		$formatter = get_formatter( $assoc_args, $this->get_default_fields() );
		$formatter->display_item( $data );
	}

	/**
	 * Update one or more existing items.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : One or more IDs of items to update.
	 *
	 * --<field>=<value>
	 * : One or more fields to update.
	 *
	 * [--porcelain]
	 * : Output just the new item's ID.
	 */
	public function update( $args, $assoc_args ) {
		if ( ! is_numeric( $args[0] ) ) {
			WP_CLI::error( 'Invalid ID (non-numeric)' );
		}

		// Sanitize our inputs.
		$props = $this->sanitize_assoc_args( $assoc_args );
		if ( is_wp_error( $props ) ) {
			print_all_errors( $props );
			return;
		}

		if ( empty( $props ) ) {
			WP_CLI::error( 'Need some fields to update.' );
		}

		$model = static::get_model();

		/** @var Model|null $item */
		$item = $model::get( (int) $args[0] );
		if ( empty( $item ) ) {
			WP_CLI::error( sprintf( 'Could not find item with ID %d.', (int) $args[0] ) );
		}

		// Prepare the changes.
		$updated_item = $this->prepare_changes_for_database( $item, $props );
		if ( is_wp_error( $updated_item ) ) {
			WP_CLI::error( $updated_item->get_error_message() );
		}

		if ( ! $updated_item->is_modified() ) {
			WP_CLI::success( 'No updates required.' );
			return;
		}

		$did_update = $updated_item->save();
		if ( is_wp_error( $did_update ) ) {
			WP_CLI::error( $did_update->get_error_message() );
		}

		if ( isset( $assoc_args['porcelain'] ) ) {
			echo (int) $updated_item->get_id();
		} elseif ( ! $did_update ) {
			WP_CLI::success( 'No updates required.' );
		} else {
			WP_CLI::success( sprintf( 'Updated item %d.', $updated_item->get_id() ) );
		}
	}

	/**
	 * Delete an item.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : One or more IDs of items to update.
	 *
	 * --<field>=<value>
	 * : One or more fields to update.
	 */
	public function delete( $args, $assoc_args ) {
		if ( ! is_numeric( $args[0] ) ) {
			WP_CLI::error( 'Invalid ID (non-numeric)' );
		}

		$model = static::get_model();

		/** @var Model|null $item */
		$item = $model::get( (int) $args[0] );
		if ( empty( $item ) ) {
			WP_CLI::error( sprintf( 'Could not find item with ID %d.', (int) $args[0] ) );
		}

		$success = $item->delete();
		if ( is_wp_error( $success ) ) {
			WP_CLI::error( $success->get_error_message() );
		}

		WP_CLI::success( sprintf( 'Deleted item %d.', (int) $args[0] ) );
	}

	/**
	 * Get a list of items.
	 *
	 * ## OPTIONS
	 *
	 * [--<field>=<value>]
	 * : One or more args to pass to the query.
	 *
	 * [--field=<field>]
	 * : Prints the value of a single field for each post.
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific object fields.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 *
	 * @subcommand list
	 *
	 * @param array $args Positional arguments
	 * @param array $assoc_args Associative (option) arguments
	 */
	public function list_( array $args, array $assoc_args ) {
		$defaults = [
			'per_page' => 100,
			'page' => 1,
		];

		$opts = array_merge( $defaults, $assoc_args );

		$model = static::get_model();

		/** @var \Foundry\Database\Query|WP_Error $query */
		$query = $model::query( $assoc_args, $opts );
		if ( is_wp_error( $query ) ) {
			WP_CLI::error( sprintf( 'Could not query (%s)', $query->get_error_message() ) );
		}

		$results = $query->get_results();
		if ( is_wp_error( $results ) ) {
			WP_CLI::error( sprintf( 'Could not query (%s)', $results->get_error_message() ) );
		}

		// Set up transformer.
		$transformer = new Transform( $results );
		$transformer->add_transform( function ( $item ) {
			return $this->prepare_model_for_output( $item );
		} );

		// And, display.
		$formatter = get_formatter( $assoc_args, $this->get_default_fields() );
		$formatter->display_items( $transformer );
	}

	/**
	 * Get the default fields to display for an object.
	 *
	 * @return string[]
	 */
	protected function get_default_fields() : array {
		$schema = $this->get_item_schema();
		return array_keys( $schema );
	}

	/**
	 * Validate and sanitize associative args for item changes.
	 *
	 * @param array $args Associative args passed by the user.
	 * @return array|WP_Error Prepared associative args if valid, error otherwise.
	 */
	protected function sanitize_assoc_args( array $args ) {
		$schema = $this->get_item_schema();

		$invalid_params = [];
		$prepared = [];
		foreach ( $args as $key => $value ) {
			if ( ! isset( $schema[ $key ] ) ) {
				continue;
			}
			$param_args = $schema[ $key ];

			// If the arg has a type but no sanitize_callback attribute, default to rest_parse_request_arg.
			if ( ! array_key_exists( 'sanitize_callback', $param_args ) && ! empty( $param_args['type'] ) ) {
				$param_args['sanitize_callback'] = __NAMESPACE__ . '\\parse_assoc_arg';
			}
			// If there's still no sanitize_callback, nothing to do here.
			if ( empty( $param_args['sanitize_callback'] ) ) {
				continue;
			}

			$sanitized_value = call_user_func( $param_args['sanitize_callback'], $value, $schema, $key );

			if ( is_wp_error( $sanitized_value ) ) {
				$invalid_params[ $key ] = $sanitized_value->get_error_message();
			} else {
				$prepared[ $key ] = $sanitized_value;
			}
		}

		if ( $invalid_params ) {
			$error = new WP_Error(
				'foundry.cli.invalid_params',
				/* translators: %s: List of invalid parameters. */
				sprintf( __( 'Invalid args(s): %s', 'foundry' ), implode( ', ', array_keys( $invalid_params ) ) )
			);

			/** @var WP_Error $error */
			foreach ( $invalid_params as $key => $param_error ) {
				$error->add(
					'foundry.cli.invalid_params',
					sprintf(
						'%s: %s',
						$key,
						$param_error
					)
				);
			}

			return $error;
		}

		return $prepared;
	}
}
