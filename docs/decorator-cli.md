# CLI Decorator

`Foundry\Cli\Command` is a decorator which makes your models manageable from the terminal via WP-CLI. It scaffolds `create`, `get`, `update`, `delete`, and `list` subcommands driven by your model's schema.

To use it, extend the class and implement the abstract methods:

* `get_command()` — the command name (e.g. `example events`, giving `wp example events list`)
* `get_model()` — the model class the command operates on
* `get_item_schema()` — a schema describing your model's output fields
* `prepare_changes_for_database()` — map command arguments onto your model
* `prepare_model_for_output()` — map your model to output fields

Full documentation is still being written. In the meantime, see the source in [`inc/cli/`](https://github.com/humanmade/foundry/tree/main/inc/cli), and the [example project](./example.md) for a walkthrough.
