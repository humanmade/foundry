<?php

namespace Foundry\Cli;

use Foundry\Database;
use WP_CLI;

trait WithImport {
	/**
	 * Get the model for the command.
	 *
	 * @internal This is implemented by Command.
	 *
	 * @return string Class name of a model.
	 */
	abstract protected static function get_model() : string;

	/**
	 * Prepare an item for the database.
	 *
	 * @param Database\Model $model Model object.
	 * @param mixed $item Row from the import data to prepare.
	 * @return Database\Model|\WP_Error Modified model on success, or WP_Error object on failure.
	 */
	abstract protected function prepare_import_item_for_database( $model, $item );

	/**
	 * Import items.
	 *
	 * @param mixed $items List of entries from the input data to import.
	 * @param boolean $dry_run True to skip committing changes to the database.
	 */
	protected function run_import( $items, bool $dry_run = false ) {
		$model_class = static::get_model();
		$models = [];
		foreach ( $items as $item ) {
			$model = new $model_class();
			$model = $this->prepare_import_item_for_database( $model, $item );
			if ( is_wp_error( $model ) ) {
				WP_CLI::error( $model->get_error_message() );
			}

			$models[] = $model;
		}

		$result = Database\save_many( $models, (bool) $dry_run );
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		$message = $dry_run ? 'Will create %d item(s)' : 'Created %d item(s)';
		WP_CLI::success( sprintf( $message, count( $models ) ) );
	}
}
