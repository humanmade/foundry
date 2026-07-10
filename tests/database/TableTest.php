<?php

namespace Foundry\Tests\Database;

use Foundry\Tests\Test_Model;
use function Foundry\Database\ensure_table;
use function Foundry\Database\conform_table;
use function Foundry\Database\parse_index;
use WP_UnitTestCase;

class TableTest extends WP_UnitTestCase {

	/**
	 * Remove the WP test framework's temporary table filters so we can
	 * test real CREATE TABLE / DROP TABLE behavior.
	 */
	private function remove_temp_table_filters() {
		remove_filter( 'query', [ $this, '_create_temporary_tables' ] );
		remove_filter( 'query', [ $this, '_drop_temporary_tables' ] );
	}

	/**
	 * Restore the WP test framework's temporary table filters.
	 */
	private function restore_temp_table_filters() {
		add_filter( 'query', [ $this, '_create_temporary_tables' ] );
		add_filter( 'query', [ $this, '_drop_temporary_tables' ] );
	}

	public function test_ensure_table_creates_table() {
		global $wpdb;

		$table = $wpdb->prefix . 'foundry_table_test';

		$this->remove_temp_table_filters();

		// Drop if exists from a prior run.
		$wpdb->query( "DROP TABLE IF EXISTS $table" );

		$schema = [
			'fields' => [
				'id'   => 'BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT',
				'name' => 'VARCHAR(255) NOT NULL',
			],
			'indexes' => [
				'PRIMARY KEY (id)',
			],
		];

		$result = ensure_table( $table, $schema );
		$this->assertTrue( $result );

		// Verify table exists.
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		$this->assertEquals( $table, $exists );

		// Clean up.
		$wpdb->query( "DROP TABLE IF EXISTS $table" );

		$this->restore_temp_table_filters();
	}

	public function test_ensure_table_adds_missing_columns() {
		global $wpdb;

		$table = $wpdb->prefix . 'foundry_conform_test';

		$this->remove_temp_table_filters();

		// Drop if exists from a prior run.
		$wpdb->query( "DROP TABLE IF EXISTS $table" );

		// Create with initial schema.
		$initial_schema = [
			'fields' => [
				'id'   => 'BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT',
				'name' => 'VARCHAR(255) NOT NULL',
			],
			'indexes' => [
				'PRIMARY KEY (id)',
			],
		];

		ensure_table( $table, $initial_schema );

		// Conform with an additional column.
		$updated_schema = [
			'fields' => [
				'id'    => 'BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT',
				'name'  => 'VARCHAR(255) NOT NULL',
				'email' => 'VARCHAR(255) NOT NULL',
			],
			'indexes' => [
				'PRIMARY KEY (id)',
			],
		];

		$result = conform_table( $table, $updated_schema );
		$this->assertTrue( $result );

		// Verify new column exists.
		$columns = $wpdb->get_results( "DESCRIBE $table" );
		$column_names = array_map( function ( $col ) {
			return $col->Field;
		}, $columns );

		$this->assertContains( 'email', $column_names );

		// Clean up.
		$wpdb->query( "DROP TABLE IF EXISTS $table" );

		$this->restore_temp_table_filters();
	}

	public function test_parse_index_primary_key() {
		$result = parse_index( 'PRIMARY KEY (id)' );
		$this->assertNotNull( $result );
		$this->assertEquals( 'PRIMARY KEY', $result['type'] );
		$this->assertEquals( 'PRIMARY', $result['name'] );
		$this->assertEquals( 'id', $result['columns'] );
	}

	public function test_parse_index_unique_key() {
		$result = parse_index( 'UNIQUE KEY email (email)' );
		$this->assertNotNull( $result );
		$this->assertEquals( 'UNIQUE KEY', $result['type'] );
		$this->assertEquals( 'email', $result['name'] );
		$this->assertEquals( 'email', $result['columns'] );
	}

	public function test_parse_index_regular_key() {
		$result = parse_index( 'KEY status (status)' );
		$this->assertNotNull( $result );
		$this->assertEquals( 'KEY', $result['type'] );
		$this->assertEquals( 'status', $result['name'] );
		$this->assertEquals( 'status', $result['columns'] );
	}

	public function test_parse_index_with_index_keyword() {
		$result = parse_index( 'INDEX status (status)' );
		$this->assertNotNull( $result );
		// INDEX is normalized to KEY.
		$this->assertEquals( 'KEY', $result['type'] );
	}

	public function test_parse_index_composite() {
		$result = parse_index( 'KEY name_status (name, status)' );
		$this->assertNotNull( $result );
		$this->assertEquals( 'name_status', $result['name'] );
		$this->assertEquals( 'name, status', $result['columns'] );
	}

	public function test_parse_index_invalid() {
		$result = parse_index( 'NOT A VALID INDEX' );
		$this->assertNull( $result );
	}
}
