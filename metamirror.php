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
require __DIR__ . '/includes/class-query.php';

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
		add_filter( 'query', [ new Query( Core::$mirrors ), 'route' ] );
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
		if ( did_action( 'init' ) && ! defined( 'DOING_TESTS' ) ) { throw new Error( 'Adding mirrors should be done in `init` or earlier' );
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
		if ( defined( 'DOING_TESTS' ) ) {
			self::_commit();
			return;
		}

		if ( did_action( 'init' ) ) {
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
		if ( current_action() !== 'init' && ! defined( 'DOING_TESTS' ) ) {
			throw new Error( 'Committing mirrors should be done in `init` or earlier' );
		}

		global $wpdb;

		$trigger_conditions = [];

		/** Create and hydrate the mirror tables */
		foreach ( self::$mirrors as $mirror ) {
			$wpdb->query( "DROP TRIGGER IF EXISTS _insert_mm_$mirror->meta_table" );
			$wpdb->query( "DROP TRIGGER IF EXISTS _update_mm_$mirror->meta_table" );
			$wpdb->query( "DROP TRIGGER IF EXISTS _delete_mm_$mirror->meta_table" );

			$wpdb->query( "DROP TABLE IF EXISTS $mirror->mirror_table;" );

			/** Create it. */
			$create = "CREATE TABLE $mirror->mirror_table";
			$columns = implode( ', ', [
				"{$mirror->meta_id} INT NOT NULL",
				"{$mirror->object_id} INT NOT NULL",
				"{$mirror->meta_key} VARCHAR(255)",
				"{$mirror->meta_value} {$mirror->type}"
					. ( $mirror->typeargs ? sprintf( '(%s)', implode( ',', $mirror->typeargs ) ) : '' )
			] );

			$like_columns = implode( ', ', [
				$mirror->meta_id,
				$mirror->object_id,
				$mirror->meta_key,
				$mirror->meta_value
			] );

			$whitelist = [];
			if ( $mirror->whitelist ) {
				foreach ( $mirror->whitelist as $meta_key ) {
					$whitelist []= $wpdb->prepare( "$mirror->meta_key LIKE %s", $meta_key );
				}
			}

			if ( empty ( $trigger_conditions[ $mirror->meta_table ] ) ) {
				$trigger_conditions[ $mirror->meta_table ] = [];
			}
			$trigger_conditions[ $mirror->meta_table ] []= [ $mirror, $whitelist /** prepared conditions */ ];

			/** Prefill. */
			$like = "SELECT $like_columns FROM $mirror->meta_table";
			if ( $whitelist ) {
				$like .= sprintf( " WHERE %s", implode( ' OR ', $whitelist ) );
			}

			$wpdb->query( "$create ($columns) $like;" );

			$warnings = [];
			if ( ( $error = $wpdb->last_error ) || ( $warnings = $wpdb->get_results( "SHOW WARNINGS;" ) ) ) {
				throw new Error( sprintf( "Errors committing mirror $mirror->id: %s", var_export( [ $error, $warnings ], true ) ) ); 
			}
		}
		
		$prefix = function( $prefix ) {
			return function( $column ) use ( $prefix ) { return "$prefix.$column"; };
		};

		/** Setup the triggers */
		foreach ( $trigger_conditions as $meta_table => $mirrors ) {
			$statements = $conditions = [ 'insert' => [], 'update' => [], 'delete' => [] ];

			foreach ( $mirrors as list( $mirror, $whitelist ) ) {
				$insert = "INSERT INTO $mirror->mirror_table VALUES (NEW.$mirror->meta_id, NEW.$mirror->object_id, NEW.$mirror->meta_key, NEW.$mirror->meta_value);";

				$sets = implode( ', ', [
					"$mirror->meta_id = NEW.$mirror->meta_id",
					"$mirror->object_id = NEW.$mirror->object_id",
					"$mirror->meta_key = NEW.$mirror->meta_key",
					"$mirror->meta_value = NEW.$mirror->meta_value",
				] );
				$update = "UPDATE $mirror->mirror_table SET $sets WHERE $mirror->meta_id = OLD.$mirror->meta_id;";

				$delete = "DELETE FROM $mirror->mirror_table WHERE $mirror->meta_id = OLD.$mirror->meta_id;";

				if ( $whitelist ) {
					$conditions['insert'] []= sprintf( "ELSEIF (%s) THEN $insert", implode( ' OR ', array_map( $prefix( 'NEW' ), $whitelist ) ) );
					$conditions['update'] []= sprintf( "ELSEIF (%s) THEN $update", implode( ' OR ', array_map( $prefix( 'OLD' ), $whitelist ) ) );
					$conditions['delete'] []= sprintf( "ELSEIF (%s) THEN $delete", implode( ' OR ', array_map( $prefix( 'OLD' ), $whitelist ) ) );
				} else {
					$statements['insert'] []= $insert;
					$statements['update'] []= $update;
					$statements['delete'] []= $delete;
				}
			}

			foreach ( $statements as $trigger => $_ ) {
				if ( count( $conditions[ $trigger ] ) ) {
					$conditions[ $trigger ][0] = preg_replace( '#^ELSEIF#', 'IF', $conditions[ $trigger ][0] );
					$conditions[ $trigger ] []= 'END IF;';
				}

				$TRIGGER = strtoupper( $trigger );
				$create_trigger = "CREATE TRIGGER _{$trigger}_mm_$meta_table AFTER $TRIGGER ON $meta_table FOR EACH ROW";
				$wpdb->query( $sql = sprintf( "$create_trigger BEGIN %s END;", implode( "\n", array_merge( $conditions[ $trigger ], $statements[ $trigger ] ) ) ) );

				$warnings = [];
				if ( ( $error = $wpdb->last_error ) || ( $warnings = $wpdb->get_results( "SHOW WARNINGS;" ) ) ) {
					throw new Error( sprintf( "Errors committing mirror $mirror->id: %s", var_export( [ $error, $warnings ], true ) ) ); 
				}
			}
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
