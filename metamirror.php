<?php
/**
 * Plugin Name: metamirror
 * Description: Non-invasive cast-free meta table indexing.
 * Plugin URI: https://github.com/soulseekah/metamirror
 * Author: soulseekah
 * Author URI: http://codeseekah.com
 * Version: 0.0.1
 * License: GPLv3
 */
namespace metamirror;

require __DIR__ . '/includes/class-error.php';
require __DIR__ . '/includes/class-mirror.php';

class Core {
	/**
	 * @var metamirror\Mirror[] A global registry of all mirrors.
	 */
	private static $mirrors = [];

	/**
	 * Early initialization.
	 *
	 * Adds the needed actions, filters.
	 */
	public static function infuse() : void {
	}

	/**
	 * Register a mirror.
	 *
	 * Do not forget to commit all mirrors after adding.
	 * Should be called on `init` or before.
	 *
	 * @param metamirror\Mirror $mirror A mirror to add.
	 *
	 * @throws metamirror\Error When adding after init.
	 * @throws metamirror\Error If the mirror already exists.
	 */
	public static function add( Mirror $mirror ) : void {
		if ( did_action( 'init' ) && ! defined( 'DOING_TESTS' ) ) {
			throw new Error( 'Adding mirrors should be done in `init` or earlier' );
		}

		if ( self::get( $mirror->id ) ) {
			throw new Error( "Mirror {$mirror->id} already exists. Whitelist additional keys instead." );
		}

		self::$mirrors[ $mirror->id ] = $mirror;
	}

	/**
	 * Retrieve a mirror.
	 *
	 * @param string $id The unique ID (Mirror::$id).
	 *
	 * @return metamirror\Mirror|null The mirror or null if not exists.
	 */
	public static function get( string $id ) {
		return isset( self::$mirrors[ $id ] ) ? self::$mirrors[ $id ] : null;
	}

	/**
	 * (Re)create all the mirrors registered so far.
	 *
	 * Do not call everytime your code is run.
	 * Think `flush_rewrite_rules` but 100000x more expensive.
	 * Should be called on `init` or before.
	 *
	 * @throws metamirror/Error When calling inapproriately.
	 */
	public static function commit() : void {
		if ( did_action( 'init' ) && ! defined( 'DOING_TESTS' ) ) {
			throw new Error( 'Committing mirrors should be done in `init` or earlier' );
		}
		
		add_action( 'init', [ Core::class, '_commit' ] );
	}

	/**
	 * Does a commit once.
	 *
	 * Called on `init`. Do not call yourself. Use `commit()` instead.
	 *
	 * @throws metamirror/Error When calling inapproriately.
	 */
	public static function _commit() : void {
		if ( current_action() !== 'init' && defined( 'DOING_TESTS' ) ) {
			throw new Error( 'Committing mirrors should be done in `init` or earlier' );
		}

		global $wpdb;

		foreach ( self::$mirrors as $mirror ) {
			/** Clear the table. */
			$wpdb->query( "DROP TABLE IF EXISTS $mirror->mirror_table;" );

			/** Create it. */
			$create = "CREATE TABLE $mirror->mirror_table";
			$columns = implode( ', ', [
				"{$mirror->meta_id} INT NOT NULL",
				"{$mirror->object_id} INT NOT NULL",
				"{$mirror->meta_key} VARCHAR(255)",
				"{$mirror->meta_value} {$mirror->cast}"
					. ( $mirror->args ? sprintf( '(%s)', implode( ',', $mirror->args ) ) : '' )
			] );
			$wpdb->query( "$create ($columns)" );
		}
	}

	/**
	 * Reset the global state of metamirror.
	 *
	 * @throws metamirror/Error When calling outside of tests.
	 */
	public static function reset() : void {
		if ( ! defined( 'DOING_TESTS' ) ) {
			throw new Error( 'Resetting metamirror is only possible in the test harness.' );
		}

		self::$mirrors = [];
	}
}

Core::infuse();
