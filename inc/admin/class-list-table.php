<?php

namespace Foundry\Admin;

use Foundry\Database\Model;
use Foundry\Database\Query;
use WP_List_Table;

abstract class List_Table extends WP_List_Table {
	protected Query $query;

	/**
	 * Queried items.
	 *
	 * @internal Override type.
	 * @var \Foundry\Database\QueryResults
	 */
	public $items;

	/**
	 * Get the model for the command.
	 *
	 * @return string Class name of a model.
	 */
	abstract protected static function get_model() : string;

	/**
	 * Get the schema for the model.
	 *
	 * The expected return
	 */
	abstract protected function get_item_schema() : array;

	/**
	 * Prepare the model for output.
	 *
	 * Data returned from this method is output directly to the browser, and so
	 * must be escaped by your method (using esc_html() or similar).
	 *
	 * @param Model $model Model object.
	 * @return array|WP_Error Data array on success, or WP_Error object on failure.
	 */
	abstract protected function prepare_model_for_output( $model );

	/**
	 * Prepare actions for output.
	 *
	 * This method should return an array of actions that can be taken on each
	 * model. The key should be a unique ID, and the value should be the HTML
	 * (typically a link) for the action.
	 *
	 * By default, the item's internal ID is displayed.
	 *
	 * @param Model $model Model object.
	 */
	protected function prepare_actions_for_output( $model ) {
		$actions = [];

		$actions['id'] = sprintf(
			'Item #%d',
			$model->get_id(),
		);

		return $actions;
	}

	/**
	 * Prepares the list of items for displaying.
	 *
	 * @uses WP_List_Table::set_pagination_args()
	 *
	 * @since 3.1.0
	 * @abstract
	 */
	public function prepare_items() {
		$model = static::get_model();
		$per_page = $this->get_items_per_page( 'edit_' . $model . '_per_page' );
		$this->query = $model::query( [], [
			'page' => $this->get_pagenum(),
			'per_page' => $per_page,
		] );
		$this->items = $this->query->get_results();

		$this->set_pagination_args( [
			'total_items' => $this->items->get_total_available(),
			'per_page' => $per_page,
		] );
	}

	/**
	 * Get all columns to display in the list table.
	 *
	 * Derived from 
	 */
	public function get_columns() {
		$schema = static::get_item_schema();

		$columns = [];

		// Register the checkbox column if needed for bulk actions.
		$bulk_actions = $this->get_bulk_actions();
		if ( ! empty( $bulk_actions ) || isset( $schema['cb'] ) ) {
			$columns['cb'] = '<input type="checkbox" />';
		}

		foreach ( $schema as $id => $column ) {
			// Skip checkbox, as it's handled earlier.
			if ( $id === 'cb' ) {
				continue;
			}

			$columns[ $id ] = $column['title'] ?? $id;
		}
		return $columns;
	}

	/**
	 * Generates content for a single row of the table.
	 *
	 * @since 3.1.0
	 *
	 * @param object|array $item The current item
	 */
	public function single_row( $item ) {
		$formatted = $this->prepare_model_for_output( $item );
		$formatted['_model'] = $item;

		echo '<tr>';
		$this->single_row_columns( $formatted );
		echo '</tr>';
	}

	/**
	 * Render an arbitrary column.
	 *
	 * Data is pre-prepared by prepare_model_for_output(), so this simply
	 * outputs the result of that directly.
	 *
	 * @param array $item Data prepared by prepare_model_for_output(). (Includes model under _model key.)
	 * @param string $column Column ID.
	 * @return string Raw HTML to be output to the browser.
	 */
	public function column_default( $item, $column ) {
		$schema = static::get_item_schema();

		if ( ! isset( $schema[ $column ] ) || empty( $item[ $column ] ) ) {
			return '';
		}

		if ( isset( $schema[ $column ]['foundry:render'] ) ) {
			return call_user_func( $schema[ $column ]['foundry:render'], $item );
		}

		return $item[ $column ];
	}

	/**
	 * Handles the checkbox column output.
	 *
	 * @since 4.3.0
	 * @since 5.9.0 Renamed `$post` to `$item` to match parent class for PHP 8 named parameter support.
	 *
	 * @param array $item Data prepared by prepare_model_for_output(). (Includes model under _model key.)
	 */
	public function column_cb( $item ) {
		$schema = static::get_item_schema();
		/** @var \Foundry\Database\Model */
		$model = $item['_model'];
		?>
		<label class="screen-reader-text" for="cb-select-<?php echo esc_attr( $model->get_id() ) ?>">
			<?php
			if ( isset( $schema['cb']['foundry:accessibility-text'] ) ) {
				/* translators: %s: Post title. */
				echo esc_html(
					call_user_func( $schema['cb']['foundry:accessibility-text'], $item )
				);
			} else {
				echo esc_html(
					'Select item #%d',
					$model->get_id()
				);
			}
			?>
		</label>
		<input
			id="cb-select-<?php echo esc_attr( $model->get_id() ) ?>"
			type="checkbox"
			name="item[]"
			value="<?php echo esc_attr( $model->get_id() ) ?>"
		/>
		<?php
	}

	/**
	 * Renders row actions in the primary column.
	 *
	 * @param array $item Data prepared by prepare_model_for_output(). (Includes model under _model key.)
	 * @param string $column_name Current column name.
	 * @param string $primary Primary column name.
	 * @return string Actions output for the column.
	 */
	protected function handle_row_actions( $item, $column_name, $primary ) {
		if ( $primary !== $column_name ) {
			return '';
		}

		$actions = $this->prepare_actions_for_output( $item['_model'] );
		if ( empty( $actions ) ) {
			return '';
		}

		return $this->row_actions( $actions );
	}
}
