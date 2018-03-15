<?php
namespace metamirror;

class TestMirror extends \WP_UnitTestCase {
	public function test_mirror_table_naming() {
		global $wpdb;

		$mirror = new Mirror( $wpdb->postmeta, 'VARCHAR', [ 16 ] );
		$this->assertEquals( $wpdb->prefix . 'mm_postmeta_varchar_16', $mirror->mirror_table );

		$this->assertSame( $mirror->id, $mirror->mirror_table );

		$mirror = new Mirror( $wpdb->postmeta, 'INTEGER' );
		$this->assertEquals( $wpdb->prefix . 'mm_postmeta_integer', $mirror->mirror_table );
	}

	public function test_mirror_meta_key_add() {
		global $wpdb;

		$mirror = new Mirror( $wpdb->postmeta, 'VARCHAR', [ 16 ] );
		$mirror->add_meta_key( '.*' );
		$this->assertEquals( '.*', $mirror->whitelist[0] );
	}

	public function test_mirror_meta_columns() {
		global $wpdb;

		$mirror = new Mirror( $wpdb->postmeta, 'VARCHAR', [ 16 ] );
		$this->assertEquals( 'meta_id', $mirror->meta_id );
		$this->assertEquals( 'post_id', $mirror->object_id );

		$mirror = new Mirror( $wpdb->usermeta, 'VARCHAR', [ 16 ] );
		$this->assertEquals( 'umeta_id', $mirror->meta_id );
		$this->assertEquals( 'user_id', $mirror->object_id );
	}
}
