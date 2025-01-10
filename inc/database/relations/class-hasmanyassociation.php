<?php

namespace Foundry\Database\Relations;

use Foundry\Database\Model;

class HasManyAssociation {
	use ManyToMany;

	/** @var string */
	protected $table;

	/** @var Model */
	protected $parent;

	/** @var string */
	protected $id;

	/** @var string */
	protected $child_model;

	public function __construct( string $table, Model $parent, string $id, string $child_model ) {
		$this->table = $table;
		$this->parent = $parent;
		$this->id = $id;
		$this->child_model = $child_model;
	}

	protected function get_relationship_table_name() : string {
		return $this->table;
	}

	protected function get_relationship_id() : string {
		return $this->id;
	}

	protected function get_left_model() : string {
		return get_class( $this->parent );
	}

	protected function get_right_model() : string {
		return $this->child_model;
	}

	public function get_items() : array {
		return $this->get_right( $this->parent );
	}

	public function add( Model $model ) {
		return $this->create_relation( $this->parent, $model );
	}

	public function remove( Model $model ) {
		return $this->remove_relation( $this->parent, $model );
	}
}
