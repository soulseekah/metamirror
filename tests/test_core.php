<?php
namespace metamirror;

class TestCore extends \WP_UnitTestCase {
	public function setUp() {
		Core::reset();

		global $wpdb;

		$this->mirror = new Mirror( $wpdb->postmeta, 'VARCHAR', [ 16 ] );

		$this->post_id = self::factory()->post->create();

		$wpdb->query( "DELETE FROM $wpdb->postmeta;" );

		parent::setUp();
	}

	public function test_add() {
		Core::add( $this->mirror );

		$this->assertSame( $this->mirror, Core::get( $this->mirror->id ) );
		$this->assertNull( Core::get( 'world' ) );

		$this->expectException( Error::class );
		
		Core::add( $this->mirror );
	}

	public function test_commit_mirror_meta() {
		add_post_meta( $this->post_id, 'test1', '1' );

		Core::add( $this->mirror );
		Core::commit();

		global $wpdb;

		$this->assertEquals( '1', $wpdb->get_var( "SELECT {$this->mirror->meta_value} FROM {$this->mirror->mirror_table}" ) );
	}

	public function test_commit_mirror_truncation() {
		add_post_meta( $this->post_id, 'test1', 'abcdefghijklmnopqrstuv' );

		Core::add( $this->mirror );
		$this->expectException( Error::class );
		Core::commit();
	}

	public function test_commit_mirror_whitelist() {
		add_post_meta( $this->post_id, 'def/0', '0' );
		add_post_meta( $this->post_id, 'abc/1', 'ABC' );
		add_post_meta( $this->post_id, 'abc/2', 'DEF' );
		add_post_meta( $this->post_id, 'abc/3', 'GHI' );

		$this->mirror->add_meta_key( 'abc/%' );
		Core::add( $this->mirror );

		Core::commit();

		global $wpdb;

		$expected = [ 'ABC', 'DEF', 'GHI' ];
		$this->assertEqualSets( $expected, $wpdb->get_col( "SELECT {$this->mirror->meta_value} FROM {$this->mirror->mirror_table}" ) );
	}

	public function test_commit_mirror_numeric() {
		global $wpdb;

		Core::reset();

		$this->mirror = new Mirror( $wpdb->postmeta, 'INTEGER' );

		add_post_meta( $this->post_id, 'test1', '123' );

		Core::add( $this->mirror );
		Core::commit();

		$this->assertEquals( 123, $wpdb->get_var( "SELECT {$this->mirror->meta_value} FROM {$this->mirror->mirror_table}" ) );
	}

	public function test_commit_mirror_bad_numeric() {
		global $wpdb;

		Core::reset();

		$this->mirror = new Mirror( $wpdb->postmeta, 'INTEGER' );

		add_post_meta( $this->post_id, 'test1', 'x123' );

		Core::add( $this->mirror );

		$this->expectException( Error::class );

		Core::commit();
	}

	public function test_trigger_insert() {
		global $wpdb;

		Core::add( $this->mirror );
		Core::commit();

		add_post_meta( $this->post_id, 'test1', 'hello' );

		$this->assertEquals( 'hello', $wpdb->get_var( "SELECT {$this->mirror->meta_value} FROM {$this->mirror->mirror_table}" ) );
	}

	public function test_trigger_insert_whitelist() {
		global $wpdb;

		$this->mirror->add_meta_key( 'hel%' );
		$this->mirror->add_meta_key( 'w_r%' );

		Core::add( $this->mirror );
		Core::commit();

		add_post_meta( $this->post_id, 'hello', 'hello' );
		add_post_meta( $this->post_id, 'world', 'world' );
		add_post_meta( $this->post_id, 'no', 'no' );
		add_post_meta( $this->post_id, 'wired', 'wired' );

		$expected = [ 'hello', 'world', 'wired' ];
		$this->assertEqualSets( $expected, $wpdb->get_col( "SELECT {$this->mirror->meta_value} FROM {$this->mirror->mirror_table}" ) );
	}

	public function test_trigger_update() {
		global $wpdb;

		add_post_meta( $this->post_id, 'test1', 'hello' );

		Core::add( $this->mirror );
		Core::commit();

		update_post_meta( $this->post_id, 'test1', 'world' );

		$this->assertEquals( 'world', $wpdb->get_var( "SELECT {$this->mirror->meta_value} FROM {$this->mirror->mirror_table}" ) );
	}

	public function test_trigger_update_whitelist() {
		global $wpdb;

		add_post_meta( $this->post_id, 'hello', 'hello' );
		add_post_meta( $this->post_id, 'world', 'world' );
		add_post_meta( $this->post_id, 'no', 'no' );
		add_post_meta( $this->post_id, 'wired', 'wired' );

		$this->mirror->add_meta_key( 'hel%' );
		$this->mirror->add_meta_key( 'w_r%' );

		update_post_meta( $this->post_id, 'hello', 'bye' );
		update_post_meta( $this->post_id, 'world', 'mars' );
		update_post_meta( $this->post_id, 'no', 'no' );
		update_post_meta( $this->post_id, 'wired', 'hired' );


		Core::add( $this->mirror );
		Core::commit();

		$expected = [ 'bye', 'mars', 'hired' ];
		$this->assertEqualSets( $expected, $wpdb->get_col( "SELECT {$this->mirror->meta_value} FROM {$this->mirror->mirror_table}" ) );
	}

	public function test_trigger_delete() {
		global $wpdb;

		add_post_meta( $this->post_id, 'test1', 'hello' );

		Core::add( $this->mirror );
		Core::commit();

		update_post_meta( $this->post_id, 'test1', 'world' );

		$this->assertEquals( 'world', $wpdb->get_var( "SELECT {$this->mirror->meta_value} FROM {$this->mirror->mirror_table}" ) );
	}

	public function test_trigger_delete_whitelist() {
		global $wpdb;

		add_post_meta( $this->post_id, 'hello', 'bye' );
		add_post_meta( $this->post_id, 'world', 'mars' );
		add_post_meta( $this->post_id, 'no', 'no' );
		add_post_meta( $this->post_id, 'wired', 'hired' );

		$this->mirror->add_meta_key( 'hel%' );
		$this->mirror->add_meta_key( 'w_r%' );

		Core::add( $this->mirror );
		Core::commit();

		delete_post_meta( $this->post_id, 'hello', 'bye' );
		delete_post_meta( $this->post_id, 'world', 'mars' );
		delete_post_meta( $this->post_id, 'no', 'no' );

		$this->assertEquals( [ 'hired' ], $wpdb->get_col( "SELECT {$this->mirror->meta_value} FROM {$this->mirror->mirror_table}" ) );
	}

	public function test_trigger_multi() {
		global $wpdb;

		$this->mirror->add_meta_key( 'hello' );

		$mirror = new Mirror( $wpdb->postmeta, 'INTEGER' );
		$mirror->add_meta_key( 'bye' );

		Core::add( $this->mirror );
		Core::add( $mirror );
		Core::commit();

		$post_id = self::factory()->post->create();
		$wpdb->query( "DELETE FROM $wpdb->postmeta;" );

		add_post_meta( $this->post_id, 'hello', 'hello' );
		add_post_meta( $post_id, 'bye', '42' );

		$this->assertEquals( '42', $wpdb->get_var( "SELECT {$this->mirror->meta_value} FROM {$mirror->mirror_table}" ) );
		$this->assertEquals( 'hello', $wpdb->get_var( "SELECT {$this->mirror->meta_value} FROM {$this->mirror->mirror_table}" ) );

		update_post_meta( $post_id, 'bye', '44' );

		$this->assertEquals( '44', $wpdb->get_var( "SELECT {$this->mirror->meta_value} FROM {$mirror->mirror_table}" ) );
		$this->assertEquals( 'hello', $wpdb->get_var( "SELECT {$this->mirror->meta_value} FROM {$this->mirror->mirror_table}" ) );

		delete_post_meta( $this->post_id, 'hello' );

		$this->assertEquals( '44', $wpdb->get_var( "SELECT {$this->mirror->meta_value} FROM {$mirror->mirror_table}" ) );
		$this->assertEmpty( $wpdb->get_var( "SELECT {$this->mirror->meta_value} FROM {$this->mirror->mirror_table}" ) );
	}

	public function test_trigger_multi_no_filter() {
		global $wpdb;

		$mirror = new Mirror( $wpdb->postmeta, 'INTEGER' );

		Core::add( $this->mirror );
		Core::add( $mirror );
		Core::commit();

		$post_id = self::factory()->post->create();
		$wpdb->query( "DELETE FROM $wpdb->postmeta;" );

		add_post_meta( $this->post_id, 'hello', 'hello' );
		add_post_meta( $post_id, 'bye', '42' );

		$this->assertEqualSets( [ '42', '0'] , $wpdb->get_col( "SELECT {$this->mirror->meta_value} FROM {$mirror->mirror_table}" ) );
		$this->assertEqualSets( [ '42', 'hello'] , $wpdb->get_col( "SELECT {$this->mirror->meta_value} FROM {$this->mirror->mirror_table}" ) );

		update_post_meta( $post_id, 'bye', '44' );

		$this->assertEqualSets( [ '44', '0'] , $wpdb->get_col( "SELECT {$this->mirror->meta_value} FROM {$mirror->mirror_table}" ) );
		$this->assertEqualSets( [ '44', 'hello'] , $wpdb->get_col( "SELECT {$this->mirror->meta_value} FROM {$this->mirror->mirror_table}" ) );

		delete_post_meta( $this->post_id, 'hello' );

		$this->assertEqualSets( [ '44' ] , $wpdb->get_col( "SELECT {$this->mirror->meta_value} FROM {$mirror->mirror_table}" ) );
		$this->assertEqualSets( [ '44' ] , $wpdb->get_col( "SELECT {$this->mirror->meta_value} FROM {$this->mirror->mirror_table}" ) );
	}
}
