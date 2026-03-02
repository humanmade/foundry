<?php

namespace Foundry\Tests\Database;

use Foundry\Tests\Test_Model;
use Foundry\Database\QueryResults;
use WP_UnitTestCase;

class QueryTest extends WP_UnitTestCase {

	public static function set_up_before_class() {
		parent::set_up_before_class();
		Test_Model::ensure_table();
		// Clean up any leftover data from previous test runs or classes.
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . Test_Model::get_table_name() );
	}

	/**
	 * Insert test rows directly via wpdb.
	 */
	protected function insert_test_data() {
		global $wpdb;
		$table = Test_Model::get_table_name();

		$wpdb->insert( $table, [ 'name' => 'Alice', 'status' => 'active', 'value' => 10 ] );
		$wpdb->insert( $table, [ 'name' => 'Bob', 'status' => 'draft', 'value' => 20 ] );
		$wpdb->insert( $table, [ 'name' => 'Charlie', 'status' => 'active', 'value' => 30 ] );
		$wpdb->insert( $table, [ 'name' => 'Diana', 'status' => 'draft', 'value' => 5 ] );
		$wpdb->insert( $table, [ 'name' => 'Eve', 'status' => 'active', 'value' => 15 ] );
	}

	public function test_query_basic_where() {
		$this->insert_test_data();

		$results = Test_Model::query( [ 'name' => 'Alice' ] )->get_results();
		$this->assertNotWPError( $results );
		$this->assertCount( 1, $results );
		$this->assertEquals( 'Alice', $results[0]->get_name() );
	}

	public function test_query_comparison_operators() {
		$this->insert_test_data();

		// Greater than.
		$results = Test_Model::query( [
			'value' => [ 'compare' => '>', 'value' => 15 ],
		], [ 'per_page' => 10 ] )->get_results();
		$this->assertNotWPError( $results );
		$this->assertCount( 2, $results ); // Bob (20), Charlie (30).

		// Less than.
		$results = Test_Model::query( [
			'value' => [ 'compare' => '<', 'value' => 15 ],
		], [ 'per_page' => 10 ] )->get_results();
		$this->assertNotWPError( $results );
		$this->assertCount( 2, $results ); // Alice (10), Diana (5).

		// Greater than or equal.
		$results = Test_Model::query( [
			'value' => [ 'compare' => '>=', 'value' => 15 ],
		], [ 'per_page' => 10 ] )->get_results();
		$this->assertNotWPError( $results );
		$this->assertCount( 3, $results ); // Bob (20), Charlie (30), Eve (15).

		// Less than or equal.
		$results = Test_Model::query( [
			'value' => [ 'compare' => '<=', 'value' => 15 ],
		], [ 'per_page' => 10 ] )->get_results();
		$this->assertNotWPError( $results );
		$this->assertCount( 3, $results ); // Alice (10), Diana (5), Eve (15).

		// Not equal.
		$results = Test_Model::query( [
			'status' => [ 'compare' => '!=', 'value' => 'active' ],
		], [ 'per_page' => 10 ] )->get_results();
		$this->assertNotWPError( $results );
		$this->assertCount( 2, $results ); // Bob, Diana.

		// LIKE.
		$results = Test_Model::query( [
			'name' => [ 'compare' => 'LIKE', 'value' => 'Ali%' ],
		], [ 'per_page' => 10 ] )->get_results();
		$this->assertNotWPError( $results );
		$this->assertCount( 1, $results );
		$this->assertEquals( 'Alice', $results[0]->get_name() );
	}

	public function test_query_or_relation() {
		$this->insert_test_data();

		$results = Test_Model::query( [
			'relation' => 'OR',
			'fields'   => [
				'status' => 'active',
				'value'  => [ 'compare' => '>', 'value' => 15 ],
			],
		], [ 'per_page' => 10 ] )->get_results();

		$this->assertNotWPError( $results );
		// active: Alice(10), Charlie(30), Eve(15). value>15: Bob(20), Charlie(30).
		// Union: Alice, Bob, Charlie, Eve = 4.
		$this->assertCount( 4, $results );
	}

	public function test_query_pagination() {
		$this->insert_test_data();

		// Page 1, 2 per page.
		$results = Test_Model::query(
			[],
			[ 'page' => 1, 'per_page' => 2 ]
		)->get_results();

		$this->assertNotWPError( $results );
		$this->assertCount( 2, $results );
		$this->assertEquals( 5, $results->get_total_available() );

		// Page 2, 2 per page.
		$results = Test_Model::query(
			[],
			[ 'page' => 2, 'per_page' => 2 ]
		)->get_results();

		$this->assertNotWPError( $results );
		$this->assertCount( 2, $results );
		$this->assertEquals( 5, $results->get_total_available() );

		// Page 3, 2 per page — only 1 left.
		$results = Test_Model::query(
			[],
			[ 'page' => 3, 'per_page' => 2 ]
		)->get_results();

		$this->assertNotWPError( $results );
		$this->assertCount( 1, $results );
		$this->assertEquals( 5, $results->get_total_available() );
	}
}
