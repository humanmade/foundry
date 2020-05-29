<?php

namespace Foundry;

use Foundry\Database;
use WP_CLI;
use WP_Error;

/**
 * @template TModel of Database\Model
 * @template TItem
 */
abstract class Importer {
	/**
	 * Get the model for the command.
	 *
	 * @internal This is implemented by Command.
	 *
	 * @return string Class name of a model.
	 * @psalm-return class-string<TModel>
	 */
	abstract protected static function get_model() : string;

	/**
	 * Prepare an item for the database.
	 *
	 * @param TModel $model Model object.
	 * @param TItem $item Row from the import data to prepare.
	 * @return TModel|\WP_Error Modified model on success, or WP_Error object on failure.
	 */
	abstract protected function prepare_import_item_for_database( Database\Model $model, $item );

	/**
	 * Import items.
	 *
	 * @param iterable $items
	 * @psalm-param iterable<int, TItem> $items
	 * @param boolean $dry_run True to skip committing changes to the database.
	 * @return WP_Error|true
	 */
	protected function import_items( iterable $items, bool $dry_run = false ) {
		$model_class = static::get_model();
		$models = [];
		foreach ( $items as $item ) {
			$model = new $model_class();
			$model = $this->prepare_import_item_for_database( $model, $item );
			if ( is_wp_error( $model ) ) {
				return $model;
			}

			$models[] = $model;
		}

		$result = Database\save_many( $models, (bool) $dry_run );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return true;
	}
}
