<?php

namespace Foundry\Tests;

use Foundry\Api\Controller;
use Foundry\Database\Model;
use WP_REST_Request;

class Test_Controller extends Controller {
	protected function get_namespace() : string {
		return 'foundry-test/v1';
	}

	protected function get_model() : string {
		return Test_Model::class;
	}

	protected function get_rest_base() : string {
		return 'items';
	}

	protected function prepare_changes_for_database( $item, WP_REST_Request $request ) {
		$params = $request->get_params();
		if ( isset( $params['name'] ) ) {
			$item->set_name( $params['name'] );
		}
		if ( isset( $params['status'] ) ) {
			$item->set_status( $params['status'] );
		}
		if ( isset( $params['value'] ) ) {
			$item->set_value( $params['value'] );
		}
		return $item;
	}

	protected function prepare_model_for_response( $model, WP_REST_Request $request ) {
		return [
			'id'     => (int) $model->get_id(),
			'name'   => $model->get_name(),
			'status' => $model->get_status(),
			'value'  => $model->get_value(),
		];
	}

	public function get_item_schema() {
		return [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'test-item',
			'type'       => 'object',
			'properties' => [
				'id'     => [ 'type' => 'integer' ],
				'name'   => [ 'type' => 'string' ],
				'status' => [ 'type' => 'string' ],
				'value'  => [ 'type' => 'integer' ],
			],
		];
	}
}
