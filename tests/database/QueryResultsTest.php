<?php

namespace Foundry\Tests\Database;

use Foundry\Tests\Test_Model;
use Foundry\Database\QueryResults;
use WP_UnitTestCase;

class QueryResultsTest extends WP_UnitTestCase {

	public static function set_up_before_class() {
		parent::set_up_before_class();
		Test_Model::ensure_table();
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . Test_Model::get_table_name() );
	}

	/**
	 * Insert test rows and query them.
	 */
	protected function get_results_with_data( $count = 3, $args = [] ) {
		global $wpdb;
		$table = Test_Model::get_table_name();

		for ( $i = 1; $i <= $count; $i++ ) {
			$wpdb->insert( $table, [
				'name'   => "Item $i",
				'status' => 'active',
				'value'  => $i * 10,
			] );
		}

		return Test_Model::query(
			[ 'status' => 'active' ],
			array_merge( [ 'per_page' => 10 ], $args )
		)->get_results();
	}

	public function test_count() {
		$results = $this->get_results_with_data( 3 );
		$this->assertNotWPError( $results );
		$this->assertCount( 3, $results );
	}

	public function test_iteration() {
		$results = $this->get_results_with_data( 3 );
		$this->assertNotWPError( $results );

		$items = [];
		foreach ( $results as $model ) {
			$this->assertInstanceOf( Test_Model::class, $model );
			$items[] = $model->get_name();
		}
		$this->assertCount( 3, $items );
	}

	public function test_array_access() {
		$results = $this->get_results_with_data( 3 );
		$this->assertNotWPError( $results );

		$this->assertInstanceOf( Test_Model::class, $results[0] );
		$this->assertInstanceOf( Test_Model::class, $results[1] );
		$this->assertInstanceOf( Test_Model::class, $results[2] );
		$this->assertNull( $results[99] );
	}

	public function test_total_available() {
		global $wpdb;
		$table = Test_Model::get_table_name();

		// Insert 5 rows.
		for ( $i = 1; $i <= 5; $i++ ) {
			$wpdb->insert( $table, [
				'name'   => "Item $i",
				'status' => 'active',
				'value'  => $i,
			] );
		}

		// Query with per_page=2 to get a subset.
		$results = Test_Model::query(
			[ 'status' => 'active' ],
			[ 'page' => 1, 'per_page' => 2 ]
		)->get_results();

		$this->assertNotWPError( $results );
		$this->assertCount( 2, $results );
		$this->assertEquals( 5, $results->get_total_available() );
	}

	public function test_as_array() {
		$results = $this->get_results_with_data( 3 );
		$this->assertNotWPError( $results );

		$array = $results->as_array();
		$this->assertIsArray( $array );
		$this->assertCount( 3, $array );

		foreach ( $array as $item ) {
			$this->assertInstanceOf( Test_Model::class, $item );
		}
	}
}
