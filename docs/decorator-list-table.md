# List Table

## Schema

Just like other decorators, JSON Schema is used for a description of your model. Your subclass should declare the `get_item_schema()` method, which should return an associative array of column ID => column options.

The available column options are:

* `title`
* `description`
* `foundry:render` - Callable, defaults to `Model->get_field( $id )`
* `foundry:sortable` - Boolean, defaults to true if the data type is sortable.
