# Example Project using Foundry

Let's walk through creating a project with Foundry.

We'll be building a basic calendar tool. Calendar events are a classic data type which doesn't fit into the custom post type model, and where querying by metadata would be very slow.


## Adding to your project

...


## Creating a model

Let's now create a basic model to represent an event. Here's a basic setup with just a model with an ID:

```php
<?php

namespace Holocene;

use Foundry\Database\Model;

class Project extends Model {
	protected static function get_table_name(): string {
		return $GLOBALS['wpdb']->prefix . 'projects';
	}

	protected static function get_table_schema() : array {
		return [
			'fields' => [
				'id' => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT',
			],
			'indexes' => [
				'PRIMARY KEY (id)',
			],
		];
	}
}
```

Great! We've got our first model created.


### Adding properties

It's not very useful yet though, so let's add some properties to it. We'll start out by giving it a name:

```php
// ...
	public function get_name() : string {
		return $this->get_field( 'name' ) ?? '';
	}

	public function set_name( string $name ) {
		return $this->set_field( 'name', $name );
	}

	protected static function get_table_schema() : array {
		return [
			'fields' => [
				'id' => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT',
				'name' => 'varchar(255)',
			],
			'indexes' => [
				'PRIMARY KEY (id)',
			],
		];
	}
```

Here we add a getter and a setter for our new field, as well as adding it to our schema. These ones are just basic, but let's try something more complex, by adding a start date for our project:

```php
	public function get_start_date() : ?DateTime {
		$start = $this->get_field( 'start_date' );
		if ( empty( $start ) ) {
			return null;
		}

		return new DateTime( $start );
	}

	public function set_start_date( DateTime $date ) {
		$formatted = $date->format( 'Y-m-d' );
		return $this->set_field( 'start_date', $formatted );
	}

	protected static function get_table_schema() : array {
		return [
			'fields' => [
				'id' => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT',
				'name' => 'varchar(255)',
				'start_date' => 'date',
			],
			'indexes' => [
				'PRIMARY KEY (id)',
			],
		];
	}
```

Using getters and setters allows us to perform complex validation when we need to, as well as typecasting. And unlike custom post types, we can use rich schema types here to allow us to perform better operations directly on the database.


## Adding CLI access

Let's make our project actually useful by making it available through CLI commands. To do this, we'll create a CLI decorator by extending `Foundry\Cli\Command`:

```php
<?php

namespace Example\Cli;

use Foundry\Cli;
use Foundry\Cli\Command;
use Example\Project;
use WP_CLI;

class ProjectsCommand extends Command {
```

Now, let's walk through each of the abstract methods we need to implement.

First, we need to specify what our command will be, and which model it relates to.

```php
	protected static function get_command() : string {
		// i.e. wp example projects list
		return 'example projects';
	}

	protected static function get_model() : string {
		return Project::class;
	}
```

Next, we need to tell 


## Adding another model

Let's create our second model, the deliverable. We'll create it the same way, and give it a name and a due date:

```php
<?php

namespace Example;

use Foundry\Database\Model;

class Deliverable extends Model {
	protected static function get_table_name(): string {
		return $GLOBALS['wpdb']->prefix . 'deliverables';
	}

	public function get_name() : string {
		return $this->get_field( 'name' ) ?? '';
	}

	public function set_name( string $name ) {
		return $this->set_field( 'name', $name );
	}

	public function get_due_date() : ?DateTime {
		$due = $this->get_field( 'due_date' );
		if ( empty( $due ) ) {
			return null;
		}

		return new DateTime( $due );
	}

	public function set_due_date( DateTime $date ) {
		$formatted = $date->format( 'Y-m-d' );
		return $this->set_field( 'due_date', $formatted );
	}

	protected static function get_table_schema() : array {
		return [
			'fields' => [
				'id' => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT',
				'name' => 'varchar(255)',
				'due_date' => 'date',
			],
			'indexes' => [
				'PRIMARY KEY (id)',
			],
		];
	}
}
```

This looks suspiciously similar to our project model! (In fact, I copy and pasted from above.) Now's a good time to think carefully about our data design.

Unlike with custom post types, Foundry models provide less guardrails, and give you the decisions about how to model your data. These models look similar, so perhaps we could consider merging them into a broader model that gives us hierarchical "items" instead. But, maybe they'll diverge in the future, or we may have performance or business reasons not to merge them together.

Foundry encourages you to think about your design decisions from the start and consider how your system will look altogether. You might already be doing this, or you may need to plan more than you currently are. In most cases, think about it, but don't overthink; you can always migrate data later if you need to, or if needs change.

We can also achieve a middle ground: sharing code across multiple models with traits. Traits enable horizontal reuse for common behaviours, while still letting you design your model how you like. Let's try it out with the `name` field, which will be the same across many of our models.


## Extracting the name trait

Let's break out the name accessors into a new trait:

```php
<?php

namespace Example;

trait WithName {
	public function get_name() : string {
		return $this->get_field( 'name' ) ?? '';
	}

	public function set_name( string $name ) {
		return $this->set_field( 'name', $name );
	}
}
```

By convention, traits begin with `With`. We can now use this trait across in our project model:

```php
<?php

namespace Example;

use Foundry\Database\Model;

class Project extends Model {
	use WithName;

	protected static function get_table_name(): string {
		return $GLOBALS['wpdb']->prefix . 'projects';
	}

	public function get_due_date() : ?DateTime {
		$due = $this->get_field( 'due_date' );
		if ( empty( $due ) ) {
			return null;
		}

		return new DateTime( $due );
	}

	public function set_due_date( DateTime $date ) {
		$formatted = $date->format( 'Y-m-d' );
		return $this->set_field( 'due_date', $formatted );
	}

	protected static function get_table_schema() : array {
		return [
			'fields' => [
				'id' => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT',
				'name' => 'varchar(255)',
				'due_date' => 'date',
			],
			'indexes' => [
				'PRIMARY KEY (id)',
			],
		];
	}
}
```

We've eliminated two methods, but retain control over the data storage in our schema. This means we could add a 40 character limit to our project name if we wanted, while giving deliverables a character limit of 255 characters, or even swapping it out for a `text` field for much longer names. (Our trait can use Foundry helpers to get information from the schema if needed.)

This style of code reuse can be as flexible or as rigid as desired. It allows you to reduce the amount of code needed, while not eliminating differences between models and making them overly generic. The choice of where to abstract is up to you.
