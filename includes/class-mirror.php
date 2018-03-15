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
	 * @var string The value type.
	 */
	public $type = '';

	/**
	 * @var int[] Type arguments.
	 */
	public $typeargs = [];

	/**
	 * A mirror definition object.
	 *
	 * @param string $meta_table The meta table we are mirroring.
	 * @param string $type The type to apply/column type.
	 * @param array $typeargs Precision, length, etc. arguments.
	 *
	 * @throws metamirror/Error If mirror cannot be created.
	 */
	public function __construct( string $meta_table, string $type, array $typeargs = [] ) {
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

		$types = [ 'INTEGER', 'VARCHAR', 'FLOAT', 'DECIMAL', 'LONGTEXT', 'BIT' ];
		$types = apply_filters( 'metamirror/types', $types );

		if ( ! in_array( strtoupper( $type ), $types ) ) {
			throw new Error( "typeing to $type for is not supported." );
		}

		$this->type = $type;

		/** Cleanup */
		$this->typeargs = array_filter( array_map( 'intval', $typeargs ), 'strlen' );

		$meta_table = preg_replace( "/^$wpdb->prefix/", '', $meta_table );

		$this->mirror_table = sprintf( '%smm_%s_%s', $wpdb->prefix, $meta_table, implode( '_', array_merge( [ strtolower( $this->type ) ], $this->typeargs ) ) );
		$this->id = &$this->mirror_table;
	}

	/**
	 * Whitelist meta keys for this mirror.
	 *
	 * LIKE placeholders are supported.
	 *
	 * After adding a new meta_key that's not been mirrored
	 * you will need to call `metamirror/Core::commit()` to
	 * recreate the mirror tables.
	 *
	 * @param string $meta_key The meta_key
	 *
	 * @throws metamirror/Error With empty keys, and after `init`.
	 */
	public function add_meta_key( string $meta_key ) {
		if ( empty( $meta_key ) ) {
			throw new Error( 'meta_key cannot be empty' );
		}

		if ( did_action( 'init' ) && ! defined( 'DOING_TESTS' ) ) {
			throw new Error( 'Cannot add meta keys after `init`.' );
		}

		$this->whitelist []= $meta_key;
	}
}
