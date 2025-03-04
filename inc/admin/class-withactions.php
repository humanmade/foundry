<?php

namespace Foundry\Admin;

use Foundry\Database\Model;

trait WithActions {
	/**
	 * Get the actions for the model.
	 *
	 * @return array Action specification.
	 */
	abstract protected function get_actions() : array;

	/*
	protected function get_actions() : array {
		return [
			// Specify a unique action ID as the key.
			'delete' => [
				// You can pass both a callback and a permission callback.
				'callback' => [ $this, 'delete_item' ],

				// The permission callback is used to determine if the action is
				// available for a specific item.
				// (For bulk actions, $item will be passed as null.)
				'permission_callback' => function ( ?Model $item ) {
					return current_user_can( 'manage_options' );
				},

				// The action labels can be set statically, or specified as a
				// callback. The callback receives the item as its only argument.
				//
				// (Label is expected to be short and is used inline, while
				// full_label should provide more context for places like
				// accessibility text.)
				'label' => 'Delete',
				'full_label' => function ( Model $item ){
					return sprintf( __( 'Delete item "%s"' ), $item->get_title() );
				},

				// For actions which support bulk processing, you can specify the
				// multiple flag.
				'multiple' => true,
				'multiple_label' => 'Delete items',

				// By default, multiple items will be processed by calling the
				// callback for each item. For efficiency, you can specify a
				// bulk callback to process all items at once.
				'multiple_callback' => [ $this, 'delete_items' ],
			],
		];
	}
	*/

	/**
	 * Renders row actions in the primary column.
	 *
	 * If you're overriding this method in your custom class, you can either
	 * use PHP's `use` resolution, or call
	 * `prepare_automatic_actions_for_output()` directly.
	 *
	 * @param Model $model
	 * @return array
	 */
	protected function prepare_actions_for_output( $model ) {
		$actions = parent::prepare_actions_for_output( $model );
		$automatic = $this->prepare_automatic_actions_for_output( $model );
		return array_merge( $actions, $automatic );
	}

	protected function prepare_automatic_actions_for_output( $model ) {
		$prepared = [];
		$actions = $this->get_actions();
		foreach ( $actions as $id => $opts ) {
			$permission_callback = $opts['permission_callback'] ?? '__return_false';
			if ( ! $permission_callback( $model ) ) {
				continue;
			}

			if ( is_callable( $opts['label'] ) ) {
				$label = $opts['label']( $model );
			} else {
				$label = $opts['label'];
			}

			if ( $opts['full_label'] && is_callable( $opts['full_label'] ) ) {
				$full_label = $opts['full_label']( $model );
			} else {
				$full_label = $opts['full_label'] ?? $label;
			}

			$action_url = add_query_arg( [
				'item_action' => $id,
				'item[]' => $model->get_id(),
				'_wpnonce' => wp_create_nonce( sprintf( '%s-%s', $model::class, $id ) ),
			] );

			$prepared[ $id ] = sprintf(
				'<a href="%s" aria-label="%s">%s</a>',
				esc_url( $action_url ),
				esc_attr( $full_label ),
				esc_html( $label )
			);
		}
		return $prepared;
	}

	/**
	 * Get the bulk actions for the model.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		$actions = [];
		$available = $this->get_actions();
		foreach ( $available as $id => $opts ) {
			$permission_callback = $opts['permission_callback'] ?? '__return_false';
			if ( ! $permission_callback( null ) ) {
				continue;
			}

			$actions[ $id ] = $opts['multiple_label'] ?? $opts['label'];
		}
		return $actions;
	}

	public function handle_action( $item ) {
		// Handle actions.
		if ( ! isset( $_REQUEST['action'] ) ) {
			return;
		}

		$action = sanitize_text_field( $_REQUEST['action'] );
		if ( ! isset( $_REQUEST['item'] ) ) {
			return;
		}

		$ids = array_map( 'intval', $_REQUEST['item'] );

		switch ( $action ) {
			case 'delete':
				foreach ( $ids as $id ) {
					$item = static::get_model()::get( $id );
					if ( ! $item ) {
						continue;
					}

					$item->delete();
				}
				break;
		}
	}
}
