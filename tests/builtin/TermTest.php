<?php

namespace Foundry\Tests\Builtin;

use Foundry\Builtin\Term;
use WP_UnitTestCase;
use WP_Term;
use TypeError;

class TermTest extends WP_UnitTestCase {

	public function test_from_term_and_get_id() {
		$term_data = wp_insert_term( 'Test Tag', 'post_tag' );
		$wp_term = get_term( $term_data['term_id'] );

		$model = Term::from_term( $wp_term );
		$this->assertEquals( $wp_term->term_id, $model->get_id() );
	}

	public function test_from_id() {
		$term_data = wp_insert_term( 'Another Tag', 'post_tag' );

		$model = Term::from_id( $term_data['term_id'] );
		$this->assertNotWPError( $model );
		$this->assertEquals( $term_data['term_id'], $model->get_id() );
	}

	public function test_from_id_throws_for_invalid() {
		// get_term() returns null for non-existent IDs; from_id doesn't
		// guard against null, so from_term() receives null and throws.
		$this->expectException( TypeError::class );
		Term::from_id( 999999 );
	}

	public function test_as_term_returns_wp_term() {
		$term_data = wp_insert_term( 'Roundtrip Tag', 'post_tag' );
		$wp_term = get_term( $term_data['term_id'] );

		$model = Term::from_term( $wp_term );
		$result = $model->as_term();

		$this->assertInstanceOf( WP_Term::class, $result );
		$this->assertEquals( $wp_term->term_id, $result->term_id );
		$this->assertEquals( 'Roundtrip Tag', $result->name );
	}

	public function test_save_returns_error() {
		$term_data = wp_insert_term( 'Immutable Tag', 'post_tag' );
		$wp_term = get_term( $term_data['term_id'] );

		$model = Term::from_term( $wp_term );
		$result = $model->save();

		$this->assertWPError( $result );
		$this->assertEquals( 'foundry.builtin.model.save.cannot_save', $result->get_error_code() );
	}

	public function test_delete_returns_error() {
		$term_data = wp_insert_term( 'Undeletable Tag', 'post_tag' );
		$wp_term = get_term( $term_data['term_id'] );

		$model = Term::from_term( $wp_term );
		$result = $model->delete();

		$this->assertWPError( $result );
		$this->assertEquals( 'foundry.builtin.model.delete.cannot_delete', $result->get_error_code() );
	}

	public function test_get_table_name() {
		global $wpdb;
		$this->assertEquals( $wpdb->prefix . 'terms', Term::get_table_name() );
	}

	public function test_get_table_schema_has_expected_fields() {
		$schema = Term::get_table_schema();
		$this->assertArrayHasKey( 'fields', $schema );
		$this->assertArrayHasKey( 'term_id', $schema['fields'] );
		$this->assertArrayHasKey( 'name', $schema['fields'] );
		$this->assertArrayHasKey( 'slug', $schema['fields'] );
	}
}
