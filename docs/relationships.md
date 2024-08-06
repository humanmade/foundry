# Relationships

Foundry supports many-to-many relationships between any two Models (or with WordPress core objects).


## Declaring relationships

Models can declare support for relationships by using the `Foundry\Database\Relations\WithRelationships` trait.

WithRelationships requires defining a `get_relationships()` method, which is used to define the model's relationships. This method should return an associative array, with the keys as the relationship ID, and values as an associative array with options.

All relationship types share the following common options:

* `type` (string) - The type of the relationship: `has_many`, `belongs_to`, `has_one`
* `model` (string) - Class name for the model

Note: the ID you chose may be stored in the database, and is not automatically migrated if you change it, so select it carefully.

```php
use Foundry\Database\Model;
use Foundry\Database\Relations\WithRelationships;

class MyModel extends Model {
	use WithRelationships;

	public static function get_relationships() : array {
		return [
			'other' => [
				'type' => 'has_many',
				'model' => OtherModel::class,
			]
		]
	}
}
```

### Relationships with WordPress core objects

In many cases, you'll want to relate models to one of the four WordPress core objects: posts, comments, terms, or users.

Since WordPress manages the models for these objects rather than Foundry, they aren't immediately available as regular models. Instead, Foundry implements a `Foundry\Builtin` shim model to allow them to be used for relationships. These shim models can't be used for regular model manipulation, and are only designed for use in relationships.

You can use these built-ins directly in your `get_relationships()` declaration in place of a regular model:

```php
use Foundry\Builtin\Term;
use Foundry\Database\Model;
use Foundry\Database\Relations\WithRelationships;

class MyModel extends Model {
	use WithRelationships;

	public static function get_relationships() : array {
		return [
			'tag' => [
				'type' => 'has_many',
				'model' => Term::class,
			]
		]
	}
}
```


## Relationship types

### Belongs-to relationships

Belongs-to relationships are used for models which belong to a different model, such as the child in a parent-child relationship.

**Note:** Not yet implemented.

### Has-one relationships

Has-one relationships are used for models which have a single other model belonging to them, such as the parent in a parent-child relationship.

**Note:** Not yet implemented.

### Has-many relationships

Has-many relationships are used for models which have zero-or-more other models belonging to them.

The `type` should be set to `has_many` for these relationships. There are no other options.


## Using relations

The WithRelationships trait provides the `->get_relation( $id )` helper to use each relation you declare.


### Has-many

`$this->get_relation( $id )->get_items()` returns the items connected to the current model.

Models can be added via `$this->get_relation( $id )->add( Model $other )` and removed via `$this->get_relation( $id )->remove( Model $other )`


### Querying

Models using WithRelationships will automatically gain support for querying by related items, with `::query()` using the `Foundry\Database\RelationalQuery` builder.

This query builder works just like regular querying, but supports querying by related items:

```
MyModel::query( [
	'relationships' => [
		// Either pass the ID:
		'rel1' => 42,

		// Or a model instance itself:
		'rel2' => Other::get( 42 ),
	],

	// Relation queries can be combined with field queries:
	'fields' => [
		'my-value' => 2,
	],
])
```
