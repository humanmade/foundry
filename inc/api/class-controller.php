<?php

namespace Foundry\Api;

use Foundry\Database\Model;
use Foundry\Database\QueryResults;
use WP_Error;
use WP_Http;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

abstract class Controller extends WP_REST_Controller {
	/**
	 * The namespace for this controller.
	 *
	 * @var string
	 */
	protected $namespace;

	/**
	 * The base for this controller.
	 *
	 * @var string
	 */
	protected $rest_base;

	/**
	 * Model class.
	 *
	 * @var string
	 */
	protected $model_class;

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( ! is_subclass_of( $this->get_model(), Model::class ) ) {
			trigger_error(
				'Model classes must extend Model',
				E_USER_ERROR
			);
		}

		$this->namespace = $this->get_namespace();
		$this->rest_base = $this->get_rest_base();
	}

	/**
	 * Get the REST namespace.
	 *
	 * @return string REST namespace (in format `name/version`, eg `foo/v1`)
	 */
	abstract protected function get_namespace() : string;

	/**
	 * Get the model class name.
	 *
	 * @return string Class name, must extend {@see Model}
	 */
	abstract protected function get_model() : string;

	/**
	 * Get the REST route base.
	 *
	 * @return string REST base.
	 */
	abstract protected function get_rest_base() : string;

	/**
	 * Prepares an item for creating or updating.
	 *
	 * @param Model $item Item to prepare changes on. May be new.
	 * @param WP_REST_Request $request Request object.
	 * @return Model|WP_Error The prepared item, or WP_Error object on failure.
	 */
	abstract protected function prepare_changes_for_database( $item, WP_REST_Request $request );

	/**
	 * Prepare the model for the response.
	 *
	 * @param Model $model Model object.
	 * @param WP_REST_Request $request Request object.
	 * @return WP_Error|WP_REST_Response Response object on success, or WP_Error object on failure.
	 */
	abstract protected function prepare_model_for_response( $model, WP_REST_Request $request );

	/**
	 * Get routes to register.
	 *
	 * @return array Route specification, used in {@see register_routes}
	 */
	protected function get_routes() {
		$get_item_args = array(
			'context' => $this->get_context_param( [ 'default' => 'view' ] ),
		);

		return [
			$this->rest_base => [
				[
					'methods' => WP_REST_Server::READABLE,
					'callback' => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
					'args' => $this->get_collection_params(),
				],
				[
					'methods' => WP_REST_Server::CREATABLE,
					'callback' => [ $this, 'create_item' ],
					'permission_callback' => [ $this, 'create_item_permissions_check' ],
					'args' => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			],
			$this->rest_base . '/(?P<id>\d+)' => [
				'args' => array(
					'id' => array(
						'description' => __( 'Unique identifier for the object.' ),
						'type'        => 'integer',
					),
				),
				[
					'methods' => WP_REST_Server::READABLE,
					'callback' => [ $this, 'get_item' ],
					'permission_callback' => [ $this, 'get_item_permissions_check' ],
					'args' => $get_item_args,
				],
				[
					'methods' => WP_REST_Server::EDITABLE,
					'callback' => [ $this, 'update_item' ],
					'permission_callback' => [ $this, 'update_item_permissions_check' ],
					'args' => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				],
				[
					'methods' => WP_REST_Server::DELETABLE,
					'callback' => [ $this, 'delete_item' ],
					'permission_callback' => [ $this, 'delete_item_permissions_check' ],
				],
				'schema' => array( $this, 'get_public_item_schema' ),
			]
		];
	}

	/**
	 * Register routes for the controller.
	 */
	public function register_routes() {
		$routes = $this->get_routes();
		foreach ( $routes as $route => $args ) {
			register_rest_route( $this->namespace, $route, $args );
		}
	}

	/**
	 * Get model item by ID.
	 *
	 * @param integer $id
	 * @return Model|WP_Error
	 */
	protected function get_model_item( int $id ) {
		$model = $this->get_model();
		$item = $model::get( $id );
		if ( empty( $item ) ) {
			return new WP_Error( 'foundry.api.invalid_id', 'Invalid item ID.', [ 'status' => 404 ] );
		}

		return $item;
	}

	/**
	 * Checks if a given request has access to get items.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool True if the request has read access, WP_Error object otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		return true; // todo: implement
	}

	/**
	 * Retrieves a collection of items.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		$model = $this->get_model();
		$args = $request->get_params();

		$page = $request['page'];

		/** @var \Foundry\Database\Query $query */
		$query = $model::query( $args );

		$results = $query->get_results();
		if ( is_wp_error( $results ) ) {
			return new WP_Error(
				'foundry.api.invalid_query',
				'Invalid query parameters.',
				[ 'status' => WP_Http::BAD_REQUEST ]
			);
		}

		$data = [];
		foreach ( $results as $item ) {
			$formatted = $this->prepare_item_for_response( $item, $request );
			if ( is_wp_error( $formatted ) ) {
				continue; // todo: fail?
			}

			$data[] = $this->prepare_response_for_collection( $formatted );
		}

		$total = $results->get_total_available();
		$max_pages = ceil( $total / 10 );

		if ( $page > $max_pages && $total > 0 ) {
			return new WP_Error(
				'foundry.api.invalid_page_number',
				'The page number requested is larger than the number of pages available.',
				[ 'status' => WP_Http::BAD_REQUEST ]
			);
		}

		$response = new WP_REST_Response( $data );
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', $max_pages );
		return $response;
	}

	/**
	 * Checks if a given request has access to get a specific item.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool True if the request has read access for the item, WP_Error object otherwise.
	 */
	public function get_item_permissions_check( $request ) {
		return true; // todo: implement
	}

	/**
	 * Retrieves a single item.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_item( $request ) {
		$item = $this->get_model_item( $request['id'] );
		if ( is_wp_error( $item ) ) {
			return $item;
		}

		return $this->prepare_item_for_response( $item, $request );
	}

	/**
	 * Checks if a given request has access to create items.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool True if the request has access to create items, WP_Error object otherwise.
	 */
	public function create_item_permissions_check( $request ) {
		return true; // todo: implement
	}

	/**
	 * Creates an item.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response Response object on success, or WP_Error object on failure.
	 */
	public function create_item( $request ) {
		if ( ! empty( $request['id'] ) ) {
			return new WP_Error( 'foundry.api.cannot_create_existing', 'Cannot create existing item.', array( 'status' => 400 ) );
		}

		$model = $this->get_model();
		$item = $this->prepare_changes_for_database( new $model(), $request );
		if ( is_wp_error( $item ) ) {
			return $item;
		}

		if ( ! $item->is_new() ) {
			return new WP_Error( 'foundry.api.cannot_create_existing', 'Cannot create existing item.', array( 'status' => 400 ) );
		}

		$result = $item->save();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$request->set_param( 'context', 'edit' );

		$response = $this->prepare_item_for_response( $item, $request );
		$response = rest_ensure_response( $response );

		$response->set_status( 201 );
		$item_route = sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $item->get_id() );
		$response->header( 'Location', rest_url( $item_route ) );

		return $response;
	}

	/**
	 * Checks if a given request has access to update a specific item.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool True if the request has access to update the item, WP_Error object otherwise.
	 */
	public function update_item_permissions_check( $request ) {
		return true; // todo: implement
	}

	/**
	 * Updates a single item.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function update_item( $request ) {
		$existing = $this->get_model_item( $request['id'] );
		if ( is_wp_error( $existing ) ) {
			return $existing;
		}

		$item = $this->prepare_changes_for_database( $existing, $request );
		if ( is_wp_error( $item ) ) {
			return $item;
		}

		if ( $item->is_new() ) {
			return new WP_Error();
		}

		if ( ! $item->is_modified() ) {
			// No changes.
			return new WP_Error();
		}

		$result = $item->save();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$request->set_param( 'context', 'edit' );

		$response = $this->prepare_item_for_response( $item, $request );
		return rest_ensure_response( $response );
	}

	/**
	 * Checks if a given request has access to delete a post.
	 *
	 * @since 4.7.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has access to delete the item, WP_Error object otherwise.
	 */
	public function delete_item_permissions_check( $request ) {
		return true; // todo: implement
	}

	/**
	 * Deletes a single item.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function delete_item( $request ) {
		$item = $this->get_model_item( $request['id'] );
		if ( is_wp_error( $item ) ) {
			return $item;
		}

		// Prepare the data so we can return it.
		$request->set_param( 'context', 'edit' );
		$previous = rest_ensure_response( $this->prepare_item_for_response( $item, $request ) );
		if ( is_wp_error( $previous ) ) {
			return $previous;
		}
		$response = rest_ensure_response( [
			'deleted'  => true,
			'previous' => $previous->get_data(),
		] );

		// Execute the deletion!
		$result = $item->delete();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $response;
	}

	/**
	 * Retrieves the model's schema, conforming to JSON Schema.
	 *
	 * @return array Model schema data.
	 */
	// abstract public static function get_item_schema();

	/**
	 * Prepares the item for the REST response.
	 *
	 * @param Model $model Model object.
	 * @param WP_REST_Request $request Request object.
	 * @return WP_Error|WP_REST_Response Response object on success, or WP_Error object on failure.
	 */
	public function prepare_item_for_response( $model, $request ) {
		$response = rest_ensure_response( $this->prepare_model_for_response( $model, $request ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Link to self if we can.
		$links = $response->get_links();
		if ( empty( $links['self'] ) ) {
			$url = rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $model->get_id() ) );
			$response->add_link( 'self', $url );
		}

		return $response;
	}
}
