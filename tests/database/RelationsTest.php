<?php

namespace Foundry\Tests\Database;

use Foundry\Tests\Test_Parent_Model;
use Foundry\Tests\Test_Child_Model;
use Foundry\Database\Relations\HasManyAssociation;
use Foundry\Database\RelationalQuery;

class RelationsTest extends \WP_UnitTestCase {

	public static function set_up_before_class() {
		parent::set_up_before_class();
		Test_Child_Model::ensure_table();
		Test_Parent_Model::ensure_table();

		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . Test_Parent_Model::get_table_name() );
		$wpdb->query( 'TRUNCATE TABLE ' . Test_Child_Model::get_table_name() );
		$wpdb->query( 'TRUNCATE TABLE ' . Test_Parent_Model::get_table_name() . '_relationships' );
	}

	/**
	 * Create and save a parent model.
	 */
	protected function create_parent( $label ) {
		$parent = new Test_Parent_Model();
		$parent->set_label( $label );
		$parent->save();
		return $parent;
	}

	/**
	 * Create and save a child model.
	 */
	protected function create_child( $title ) {
		$child = new Test_Child_Model();
		$child->set_title( $title );
		$child->save();
		return $child;
	}

	public function test_ensure_table_creates_relationship_table() {
		global $wpdb;

		$rel_table = Test_Parent_Model::get_table_name() . '_relationships';

		// Remove temp table filters to check real tables.
		remove_filter( 'query', [ $this, '_create_temporary_tables' ] );
		remove_filter( 'query', [ $this, '_drop_temporary_tables' ] );

		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $rel_table ) );
		$this->assertEquals( $rel_table, $exists );

		// Verify schema: relationship, left_id, right_id columns.
		$columns = $wpdb->get_results( "DESCRIBE $rel_table" );
		$column_names = array_map( function ( $col ) {
			return $col->Field;
		}, $columns );
		$this->assertContains( 'relationship', $column_names );
		$this->assertContains( 'left_id', $column_names );
		$this->assertContains( 'right_id', $column_names );

		add_filter( 'query', [ $this, '_create_temporary_tables' ] );
		add_filter( 'query', [ $this, '_drop_temporary_tables' ] );
	}

	public function test_get_relation_returns_association() {
		$parent = $this->create_parent( 'Parent A' );
		$relation = $parent->children();

		$this->assertInstanceOf( HasManyAssociation::class, $relation );
	}

	public function test_get_relation_returns_null_for_invalid() {
		$parent = $this->create_parent( 'Parent B' );
		$relation = $parent->get_relation_public( 'nonexistent' );

		$this->assertNull( $relation );
	}

	public function test_get_relation_caches_handler() {
		$parent = $this->create_parent( 'Parent C' );
		$first = $parent->children();
		$second = $parent->children();

		$this->assertSame( $first, $second );
	}

	public function test_add_and_get_related_items() {
		$parent = $this->create_parent( 'Parent D' );
		$child_a = $this->create_child( 'Child A' );
		$child_b = $this->create_child( 'Child B' );

		$parent->children()->add( $child_a );
		$parent->children()->add( $child_b );

		$items = $parent->children()->get_items();
		$this->assertCount( 2, $items );

		$titles = array_map( function ( $item ) {
			return $item->get_title();
		}, $items );
		sort( $titles );
		$this->assertSame( [ 'Child A', 'Child B' ], $titles );
	}

	public function test_remove_related_item() {
		$parent = $this->create_parent( 'Parent E' );
		$child_a = $this->create_child( 'Child C' );
		$child_b = $this->create_child( 'Child D' );

		$parent->children()->add( $child_a );
		$parent->children()->add( $child_b );
		$this->assertCount( 2, $parent->children()->get_items() );

		$parent->children()->remove( $child_a );
		$items = $parent->children()->get_items();
		$this->assertCount( 1, $items );
		$this->assertEquals( 'Child D', $items[0]->get_title() );
	}

	public function test_related_items_are_scoped_to_parent() {
		$parent_1 = $this->create_parent( 'Parent F' );
		$parent_2 = $this->create_parent( 'Parent G' );
		$child = $this->create_child( 'Shared Child' );

		$parent_1->children()->add( $child );

		$this->assertCount( 1, $parent_1->children()->get_items() );
		$this->assertCount( 0, $parent_2->children()->get_items() );
	}

	public function test_query_returns_relational_query() {
		$query = Test_Parent_Model::query( [] );
		$this->assertInstanceOf( RelationalQuery::class, $query );
	}

	public function test_query_without_relationships_works() {
		$this->create_parent( 'Query Parent' );

		$results = Test_Parent_Model::query(
			[ 'label' => 'Query Parent' ],
			[ 'per_page' => 10 ]
		)->get_results();

		$this->assertNotWPError( $results );
		$this->assertCount( 1, $results );
		$this->assertEquals( 'Query Parent', $results[0]->get_label() );
	}

	public function test_query_with_relationship_filter() {
		$parent_a = $this->create_parent( 'Has Child' );
		$parent_b = $this->create_parent( 'No Child' );
		$child = $this->create_child( 'Filter Child' );

		$parent_a->children()->add( $child );

		$results = Test_Parent_Model::query( [
			'relationships' => [
				'children' => $child->get_id(),
			],
		], [ 'per_page' => 10 ] )->get_results();

		$this->assertNotWPError( $results );
		$this->assertCount( 1, $results );
		$this->assertEquals( 'Has Child', $results[0]->get_label() );
	}
}
