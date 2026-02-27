<?php

namespace Foundry\Tests\Database;

use Foundry\Tests\Test_Model;
use Foundry\Database\QueryResults;
use WP_UnitTestCase;

class ModelTest extends WP_UnitTestCase {

	public static function set_up_before_class() {
		parent::set_up_before_class();
		Test_Model::ensure_table();
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . Test_Model::get_table_name() );
	}

	public function test_create_and_get() {
		$model = new Test_Model();
		$model->set_name( 'Alice' );
		$model->set_status( 'active' );
		$model->set_value( 42 );

		$result = $model->save();
		$this->assertTrue( $result );

		$id = $model->get_id();
		$this->assertNotNull( $id );

		$fetched = Test_Model::get( $id );
		$this->assertNotNull( $fetched );
		$this->assertEquals( 'Alice', $fetched->get_name() );
		$this->assertEquals( 'active', $fetched->get_status() );
		$this->assertEquals( 42, $fetched->get_value() );
	}

	public function test_update() {
		$model = new Test_Model();
		$model->set_name( 'Bob' );
		$model->save();

		$id = $model->get_id();

		$model->set_name( 'Bobby' );
		$result = $model->save();
		$this->assertTrue( $result );

		$fetched = Test_Model::get( $id );
		$this->assertEquals( 'Bobby', $fetched->get_name() );
	}

	public function test_delete() {
		$model = new Test_Model();
		$model->set_name( 'Charlie' );
		$model->save();

		$id = $model->get_id();

		$result = $model->delete();
		$this->assertTrue( $result );

		$fetched = Test_Model::get( $id );
		$this->assertNull( $fetched );
	}

	public function test_is_new() {
		$model = new Test_Model();
		$this->assertTrue( $model->is_new() );

		$model->set_name( 'Test' );
		$model->save();
		$this->assertFalse( $model->is_new() );
	}

	public function test_is_modified() {
		$model = new Test_Model();
		$model->set_name( 'Test' );
		$model->save();

		$this->assertFalse( $model->is_modified() );

		$model->set_name( 'Updated' );
		$this->assertTrue( $model->is_modified() );
	}

	public function test_is_deleted() {
		$model = new Test_Model();
		$model->set_name( 'Test' );
		$model->save();

		$this->assertFalse( $model->is_deleted() );

		$model->delete();
		$this->assertTrue( $model->is_deleted() );
	}

	public function test_query_returns_results() {
		global $wpdb;
		$table = Test_Model::get_table_name();

		$wpdb->insert( $table, [ 'name' => 'Alice', 'status' => 'active', 'value' => 10 ] );
		$wpdb->insert( $table, [ 'name' => 'Bob', 'status' => 'draft', 'value' => 20 ] );

		$results = Test_Model::query( [ 'status' => 'active' ] )->get_results();
		$this->assertNotWPError( $results );
		$this->assertInstanceOf( QueryResults::class, $results );
		$this->assertCount( 1, $results );
		$this->assertEquals( 'Alice', $results[0]->get_name() );
	}

	public function test_query_pagination() {
		global $wpdb;
		$table = Test_Model::get_table_name();

		for ( $i = 1; $i <= 5; $i++ ) {
			$wpdb->insert( $table, [ 'name' => "Item $i", 'status' => 'active', 'value' => $i ] );
		}

		$results = Test_Model::query(
			[],
			[ 'page' => 1, 'per_page' => 2 ]
		)->get_results();

		$this->assertNotWPError( $results );
		$this->assertCount( 2, $results );
		$this->assertEquals( 5, $results->get_total_available() );
	}

	public function test_reload() {
		$model = new Test_Model();
		$model->set_name( 'Original' );
		$model->save();

		$model->set_name( 'Changed' );
		$this->assertTrue( $model->is_modified() );
		$this->assertEquals( 'Changed', $model->get_name() );

		$model->reload();
		$this->assertFalse( $model->is_modified() );
		$this->assertEquals( 'Original', $model->get_name() );
	}
}
