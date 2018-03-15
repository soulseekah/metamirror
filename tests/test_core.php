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

	public function test_commit_basic() {
		Core::add( $this->mirror );
		Core::commit();

		global $wpdb;

		$this->assertNotNull( $wpdb->get_row( $wpdb->prepare( 'SHOW TABLES LIKE %s', $this->mirror->mirror_table ) ) );
	}

	public function test_commit_mirror_meta() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'test1', '1' );

		Core::add( $this->mirror );
		Core::commit();

		global $wpdb;

		$this->assertEquals( '1', $wpdb->get_var( "SELECT {$this->mirror->meta_value} FROM {$this->mirror->mirror_table}" ) );
	}

	public function test_commit_mirror_truncation() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'test1', 'abcdefghijklmnopqrstuv' );

		Core::add( $this->mirror );
		$this->expectException( Error::class );
		Core::commit();
	}

	public function test_commit_mirror_whitelist() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'def/0', '0' );
		update_post_meta( $post_id, 'abc/1', 'ABC' );
		update_post_meta( $post_id, 'abc/2', 'DEF' );
		update_post_meta( $post_id, 'abc/3', 'GHI' );

		$this->mirror->add_meta_key( 'abc/%' );
		Core::add( $this->mirror );

		Core::commit();

		global $wpdb;

		$expected = [ 'ABC', 'DEF', 'GHI' ];

		$this->assertEqualSets( $expected, $wpdb->get_col( "SELECT {$this->mirror->meta_value} FROM {$this->mirror->mirror_table}" ) );
	}
}
