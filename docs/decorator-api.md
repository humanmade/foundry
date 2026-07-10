# REST API Decorator

`Foundry\Api\Controller` is a decorator which exposes your models via the WordPress REST API. It extends `WP_REST_Controller`, registering full CRUD routes (list, get, create, update, delete) with permission checks for your model, while letting you override any part of the behaviour.

To use it, extend the class and implement the abstract methods:

* `get_namespace()` — the REST namespace (e.g. `example/v1`)
* `get_rest_base()` — the route base (e.g. `events`)
* `get_model()` — the model class the controller operates on
* `prepare_changes_for_database()` — map request data onto your model
* `prepare_model_for_response()` — map your model to response data

Full documentation is still being written. In the meantime, see the source in [`inc/api/`](https://github.com/humanmade/foundry/tree/main/inc/api).
