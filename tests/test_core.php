<?php
namespace metamirror;

class TestCore extends \WP_UnitTestCase {
	public function setUp() {
		Core::reset();

		global $wpdb;

		$this->mirror = new Mirror( $wpdb->postmeta, 'VARCHAR', [ 16 ] );
	}

	public function test_add_mirror() {
		Core::add_mirror( $this->mirror );

		$this->assertSame( $this->mirror, Core::get_mirror( $this->mirror->id ) );
		$this->assertNull( Core::get_mirror( 'world' ) );

		$this->expectException( Error::class );
		
		Core::add_mirror( $this->mirror );
	}

	public function test_commit() {
		Core::add_mirror( $this->mirror );

		Core::commit_mirrors();

		global $wpdb;
	}
}
