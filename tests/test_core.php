<?php
namespace metamirror;

class TestCore extends \WP_UnitTestCase {
	public function setUp() {
		Core::reset();

		global $wpdb;

		$this->mirror = new Mirror( $wpdb->postmeta, 'VARCHAR', [ 16 ] );
	}

	public function test_add() {
		Core::add( $this->mirror );

		$this->assertSame( $this->mirror, Core::get( $this->mirror->id ) );
		$this->assertNull( Core::get( 'world' ) );

		$this->expectException( Error::class );
		
		Core::add( $this->mirror );
	}

	public function test_commit() {
		Core::add( $this->mirror );

		Core::commit();

		global $wpdb;

		$this->assertNotEmpty( $wpdb->get_row( $wpdb->prepare( 'SHOW TABLES LIKE %s', $this->mirror->meta_table ) ) );
	}
}
