# List Table

## Schema

Just like other decorators, JSON Schema is used for a description of your model. Your subclass should declare the `get_item_schema()` method, which should return an associative array of column ID => column options.

The available column options are:

* `title`
* `description`
* `foundry:render` - Callable, defaults to `Model->get_field( $id )`
* `foundry:sortable` - Boolean, defaults to true if the data type is sortable.

You'll also need to provide `prepare_model_for_output()`, which should return an associative array mapping from column ID to output.


## Column rendering

Each registered column will directly output the data returned from the `prepare_model_for_output()` method.

For more advanced rendering, column rendering can be overridden through two different mechanisms:

* **Schema** - In your schema, you can declare a `foundry:render` callback, which will be passed the data from `prepare_model_for_output()`.
* **Methods** - You can also use the traditional WordPress-style for list tables, and declare a method which matches the column name, prefixed with `column_`. For example, an `id` column would use a `column_id()` method if declared.

Each type of callback receives the array prepared from `prepare_model_for_output()`. The underlying model is available as the `_model` key if needed.

For example:

```php
// Callback style.
protected function get_item_schema() : array {
	return [
		'name' => [
			'title' => 'Name',
			'foundry:render' => function ( $data ) {
				// Use the data directly (and don't forget to escape!)
				return esc_html( 'The name of the model is:' . $data['name'] );
			}
		],
	];
}

// Method style.
protected function column_name( $data ) {
	// Grab the model.
	/** @var MyModel */
	$model = $data['_model'];

	// Do something more complex.
	$name = lookup_name_for_model( $model );

	return esc_html( $name );
}
```


## Basic Actions

Actions can be added through the standard list table APIs, including implementing `handle_row_actions()`.

However, Foundry provides a higher-level abstraction with a `prepare_actions_for_output()` method. This method is called once per row and receives the model.

By default, the item's internal ID is added as the first action to provide users with context on items; this can be retained by calling `parent::prepare_actions_for_output( $model )`, or overridden by removing it from the array.

```php
protected function prepare_actions_for_output( $model ) {
	$actions = parent::prepare_actions_for_output( $model );

	$actions['view'] = sprintf(
		'<a href="%s">View item</a>',
		$model->get_permalink(),
	);

	return $actions;
}
```


## Advanced Actions

Foundry also includes a powerful helper trait, `Foundry\Admin\WithActions`, which can be used to easily add single and bulk actions to any list table. This trait provides the power to easily register actions and handle user interaction with them without needing to write all the glue code yourself.

To implement this trait, `use` it, and implement the abstract `get_actions()` method:

```php
use Foundry\Admin\WithActions;

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
```

### Checkbox column

If bulk actions are registed, a special `cb` column will be inserted as the first column with default handling.

A special `foundry:accessibility-text` option may be explicitly specified for this column, which will provide the screen reader text for the label associated with this item (for example, "Select My Post").
