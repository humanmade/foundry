<?php

namespace Foundry\Tests;

use Foundry\Database\Model;
use Foundry\Importer;

class Test_Importer extends Importer {
	protected static function get_model() : string {
		return Test_Model::class;
	}

	protected function prepare_import_item_for_database( Model $model, $item ) {
		$model->set_name( $item['name'] );
		if ( isset( $item['status'] ) ) {
			$model->set_status( $item['status'] );
		}
		if ( isset( $item['value'] ) ) {
			$model->set_value( $item['value'] );
		}
		return $model;
	}

	protected function get_model_for_item( $item ) : ?Model {
		if ( empty( $item['id'] ) ) {
			return null;
		}
		return Test_Model::get( (int) $item['id'] );
	}
}
