<?php

namespace Foundry\Tests;

class ImporterTest extends \WP_UnitTestCase {

	public static function set_up_before_class() {
		parent::set_up_before_class();
		Test_Model::ensure_table();
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . Test_Model::get_table_name() );
	}

	/**
	 * save_many uses nested transactions, so TRUNCATE to reliably clean up.
	 */
	public function tear_down() {
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . Test_Model::get_table_name() );
		parent::tear_down();
	}

	public function test_import_inserts_new_items() {
		$importer = new Test_Importer();
		$items = [
			[ 'name' => 'Import A', 'status' => 'active', 'value' => 10 ],
			[ 'name' => 'Import B', 'status' => 'draft', 'value' => 20 ],
		];

		$result = $importer->import_items( $items );
		$this->assertIsArray( $result );
		$this->assertEquals( 2, $result['total'] );
		$this->assertEquals( 2, $result['inserted'] );
		$this->assertEquals( 0, $result['updated'] );

		// Verify data persists.
		global $wpdb;
		$count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Test_Model::get_table_name() );
		$this->assertEquals( 2, $count );
	}

	public function test_import_updates_existing_items() {
		// Create an existing model.
		$model = new Test_Model();
		$model->set_name( 'Existing' );
		$model->set_value( 1 );
		$model->save();
		$id = $model->get_id();

		// Now save_many committed, the model exists. Import with its ID.
		$importer = new Test_Importer();
		$items = [
			[ 'id' => $id, 'name' => 'Updated', 'value' => 99 ],
			[ 'name' => 'Brand New' ],
		];

		$result = $importer->import_items( $items );
		$this->assertIsArray( $result );
		$this->assertEquals( 2, $result['total'] );
		$this->assertEquals( 1, $result['inserted'] );
		$this->assertEquals( 1, $result['updated'] );
	}

	public function test_import_dry_run_does_not_persist() {
		$importer = new Test_Importer();
		$items = [
			[ 'name' => 'Dry A' ],
			[ 'name' => 'Dry B' ],
		];

		$result = $importer->import_items( $items, true );
		$this->assertIsArray( $result );
		$this->assertEquals( 2, $result['total'] );

		// Dry run — data should not persist.
		global $wpdb;
		$count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM " . Test_Model::get_table_name() . " WHERE name LIKE 'Dry%'"
		);
		$this->assertEquals( 0, $count );
	}

	public function test_import_returns_counts() {
		$importer = new Test_Importer();
		$items = [
			[ 'name' => 'Count A' ],
			[ 'name' => 'Count B' ],
			[ 'name' => 'Count C' ],
		];

		$result = $importer->import_items( $items );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertArrayHasKey( 'inserted', $result );
		$this->assertArrayHasKey( 'updated', $result );
		$this->assertEquals( 3, $result['total'] );
	}
}
