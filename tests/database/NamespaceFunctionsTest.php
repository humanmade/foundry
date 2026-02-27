<?php

namespace Foundry\Tests\Database;

use Foundry\Tests\Test_Model;
use Foundry\Tests\Failing_Test_Model;
use function Foundry\Database\get_primary_column;
use function Foundry\Database\save_many;
use WP_UnitTestCase;

class NamespaceFunctionsTest extends WP_UnitTestCase {

	public static function set_up_before_class() {
		parent::set_up_before_class();
		Test_Model::ensure_table();
	}

	/**
	 * Clean up after each test since save_many uses its own transactions
	 * which interfere with the WP test framework's transaction rollback.
	 *
	 * TRUNCATE is DDL (implicit commit, not rollback-able) so it reliably
	 * cleans up even when the WP framework's transaction state is broken
	 * by save_many's nested START TRANSACTION / COMMIT.
	 */
	public function tear_down() {
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . Test_Model::get_table_name() );
		parent::tear_down();
	}

	public function test_get_primary_column() {
		$schema = Test_Model::get_table_schema();
		$primary = get_primary_column( $schema );
		$this->assertEquals( 'id', $primary );
	}

	public function test_save_many_atomic() {
		$model_a = new Test_Model();
		$model_a->set_name( 'Atomic A' );

		$model_b = new Test_Model();
		$model_b->set_name( 'Atomic B' );

		$result = save_many( [ $model_a, $model_b ] );
		$this->assertTrue( $result );

		// Verify both were saved.
		$this->assertNotNull( $model_a->get_id() );
		$this->assertNotNull( $model_b->get_id() );

		$fetched_a = Test_Model::get( $model_a->get_id() );
		$fetched_b = Test_Model::get( $model_b->get_id() );
		$this->assertNotNull( $fetched_a );
		$this->assertNotNull( $fetched_b );
		$this->assertEquals( 'Atomic A', $fetched_a->get_name() );
		$this->assertEquals( 'Atomic B', $fetched_b->get_name() );
	}

	public function test_save_many_rollback_on_error() {
		$model_a = new Test_Model();
		$model_a->set_name( 'Should Not Persist' );

		// This model's save() always returns WP_Error.
		$model_b = new Failing_Test_Model();
		$model_b->set_name( 'Will Fail' );

		$result = save_many( [ $model_a, $model_b ] );
		$this->assertWPError( $result );

		// model_a was saved within the transaction, but it should have been rolled back.
		// Since model_a got an insert_id before the rollback, check the DB directly.
		global $wpdb;
		$table = Test_Model::get_table_name();
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE name = 'Should Not Persist'" );
		$this->assertEquals( 0, $count );
	}

	public function test_save_many_dry_run() {
		$model_a = new Test_Model();
		$model_a->set_name( 'Dry Run A' );

		$model_b = new Test_Model();
		$model_b->set_name( 'Dry Run B' );

		$result = save_many( [ $model_a, $model_b ], true );
		$this->assertTrue( $result );

		// Dry run rolls back — data should not persist.
		global $wpdb;
		$table = Test_Model::get_table_name();
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE name LIKE 'Dry Run%'" );
		$this->assertEquals( 0, $count );
	}
}
