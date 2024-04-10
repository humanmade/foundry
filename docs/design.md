# Design

Foundry uses modern PHP conventions, including strong typing and horizontal reuse with traits. It combines these modern paradigms with direct use of WordPress' native APIs, including wpdb, wp-cli, and the REST API.

This means the patterns will feel familiar to many developers already, and the codebase is suitable for use in any WordPress codebase, including redistributed plugins.

Foundry is designed to be a library for developers, and contains many abstract pieces of functionality. It doesn't eliminate the need to write code, but rather makes writing that code a delight, and allows focussing on the business logic rather than the boring parts.

Unlike other solutions, Foundry is designed to predictable and controllable while remaining ergonomic. This means we don't design magic solutions, and prefer explicit over implicit; while we can offer smart defaults, they are only defaults.


## Model

The core of foundry is `Foundry\Database\Model`, which implements an [Active Record-style model](https://en.wikipedia.org/wiki/Active_record_pattern). That is, the class contains both the data storage as well as create/read/update/delete (CRUD) operations upon the model. If you've ever used Rails, Laravel, or Doctrine (Symfony), the Model class will feel familiar.

Model's behaviour is driven primarily by your database schema, which you provide in your implementing class. Your class specifies the columns, called "fields", as well as any indexes for the table, and Foundry handles creating this table and (optionally) performing database migrations.

Model is unopinionated about how you implement data access, allowing you to implement object accessors or methods per your own preference. For example, you might implement a `name` field via `get_name()` and `set_name()` methods, or by implementing magic accessors; it's up to you.


### Traits

Common properties you might want on your model are implemented via traits, which are prefixed with `With`. For example, adding foreign-key relationships is done with a `use WithRelationships` trait. We recommend using this pattern across your own projects too; for example, if you have many models which have `name` fields, you might want to implement a `WithName` trait to implement `get_name()` and `set_name()` with common handling across every model.


## Decorators

Layered on top are the [decorators](https://en.wikipedia.org/wiki/Decorator_pattern) which provide other integrations into WordPress and related systems, mapping your model to systems that feel native.

Decorators are typically implemented as abstract classes, which implement default behaviours using your model, but still provide the flexibility to build your own system the way you'd like.

The decorators currently provided by Foundry are:

* `Foundry\Api\Controller`: REST API controller
* `Foundry\Cli\Command`: wp-cli command

Additionally, we expect to implement the following:

* List table
* Importer
* Migrations


## Query

Foundry also implements another helper, the `Foundry\Database\Query` class (using [forwarding](https://en.wikipedia.org/wiki/Forwarding_(object-oriented_programming)), similar to delegation). This works similarly to `WP_Query`, but designed specifically for your model. Since this is used so frequently with models, it's implemented by default rather than as a decorator, and you can access it through `Model::query()`.

Query's behaviour is derived from your model's schema. If you have a field called `name`, you can query by this field with `Model::query( [ 'name' => 'foo' ] )`.
