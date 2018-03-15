<?php
namespace metamirror;

class Mirror {
	/**
	 * @var string The unique ID.
	 */
	public $id = '';

	/**
	 * @var string The meta_id column name.
	 */
	public $meta_id = '';

	/**
	 * @var string The object_id column name.
	 */
	public $object_id = '';

	/**
	 * @var string The meta_key column name.
	 */
	public $meta_key = 'meta_key';

	/**
	 * @var string The meta_value column name.
	 */
	public $meta_value = 'meta_value';

	/**
	 * @var string The source meta table.
	 */
	public $meta_table = '';

	/**
	 * @var string The mirror table.
	 */
	public $mirror_table = '';

	/**
	 * @var string[] The meta key whitelist.
	 */
	public $whitelist = [];

	/**
	 * @var string The cast type.
	 */
	public $cast = '';

	/**
	 * @var int[] Cast arguments.
	 */
	public $args = [];

	/**
	 * A mirror definition object.
	 *
	 * @param string $meta_table The meta table we are mirroring.
	 * @param string $cast The cast to apply/column type.
	 * @param array $args Precision, length, etc. arguments.
	 *
	 * @throws metamirror/Error If mirror cannot be created.
	 */
	public function __construct( string $meta_table, string $cast, array $args = [] ) {
		global $wpdb;

		$tables = apply_filters( 'metamirror/tables', [ $wpdb->postmeta, $wpdb->usermeta, $wpdb->commentmeta, $wpdb->termmeta ] );

		if ( ! in_array( $meta_table, $tables ) ) {
			throw new Error( "The mirroring of the `$meta_table` table is not supported." );
		}

		$this->meta_table = $meta_table;

		/** Try to autodetect the column names. */
		$meta_id_maps = apply_filters( 'metamirror/columns/meta_id', [
			$wpdb->postmeta    => 'meta_id',
			$wpdb->termmeta    => 'meta_id',
			$wpdb->commentmeta => 'meta_id',
			$wpdb->usermeta    => 'umeta_id',
		], $meta_table );

		$object_id_maps = apply_filters( 'metamirror/columns/objec_id', [
			$wpdb->postmeta    => 'post_id',
			$wpdb->termmeta    => 'term_id',
			$wpdb->commentmeta => 'comment_id',
			$wpdb->usermeta    => 'user_id',
		], $meta_table );

		/** Throw a warning here, no problem. The developer should know. */
		$this->meta_id   = $meta_id_maps[ $meta_table ];
		$this->object_id = $object_id_maps[ $meta_table ];

		$casts = [ 'INTEGER', 'VARCHAR', 'FLOAT', 'DECIMAL', 'LONGTEXT', 'BIT' ];
		$casts = apply_filters( 'metamirror/casts', $casts );

		if ( ! in_array( strtoupper( $cast ), $casts ) ) {
			throw new Error( "Casting to $cast for is not supported." );
		}

		$this->cast = $cast;

		/** Cleanup */
		$this->args = array_filter( array_map( 'intval', $args ), 'strlen' );

		$meta_table = preg_replace( "/^$wpdb->prefix/", '', $meta_table );

		$this->mirror_table = sprintf( '%smm_%s_%s', $wpdb->prefix, $meta_table, implode( '_', array_merge( [ strtolower( $this->cast ) ], $this->args ) ) );
		$this->id = &$this->mirror_table;
	}

	/**
	 * Whitelist meta keys for this mirror.
	 *
	 * Regular expressions are supported.
	 *
	 * @param string $meta_key The meta_key
	 *
	 * @throws metamirror/Error
	 */
	public function add_meta_key( string $meta_key ) {
		if ( empty( $meta_key ) ) {
			throw Error( 'meta_key cannot be empty' );
		}

		$this->whitelist []= $meta_key;
	}
}
