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
	 * Get an existing model for an import item
	 *
	 * @param TItem $item Row from the import data.
	 * @return ?TModel
	 */
	abstract protected function get_model_for_item( $item ) : ?Database\Model;

	/**
	 * Import items.
	 *
	 * @param iterable $items
	 * @psalm-param iterable<int, TItem> $items
	 * @param boolean $dry_run True to skip committing changes to the database.
	 * @return WP_Error|array
	 * @psalm-return WP_Error|array{ total: int, inserted: int, updated: int }
	 */
	public function import_items( iterable $items, bool $dry_run = false ) {
		$model_class = static::get_model();
		$models = [];
		$result = [
			'total' => 0,
			'inserted' => 0,
			'updated' => 0,
		];

		foreach ( $items as $item ) {
			$result['total']++;
			$model = $this->get_model_for_item( $item );
			if ( $model ) {
				$result['updated']++;
			} else {
				$result['inserted']++;
				$model = new $model_class();
			}
			$model = $this->prepare_import_item_for_database( $model, $item );
			if ( is_wp_error( $model ) ) {
				return $model;
			}

			$models[] = $model;
		}

		$db_update = Database\save_many( $models, (bool) $dry_run );
		if ( is_wp_error( $db_update ) ) {
			return $db_update;
		}
		return $result;
	}
}
