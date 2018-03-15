<?php
namespace metamirror;

class Query {
	/**
	 * @var metamirror\Mirror[] The mirrors.
	 */
	private $mirrors = [];

	/**
	 * Mirror mirror on the wall...
	 *
	 * @param metamirror\Mirror[] $mirrors An array of mirrors to route to.
	 */
	public function __construct( array $mirrors ) {
		$this->mirrors = $mirrors;
	}

	/**
	 * Rewrite the query.
	 *
	 * Route meta without breaking it ;)
	 *
	 * @param string $query The SQL query.
	 *
	 * @return string The rewritten SQL.
	 */
	public function rewrite( string $query ) : string {
		foreach ( $this->mirrors as $mirror ) {
			/** Can't be this simple, can it? */
			$query = str_replace( $mirror->meta_table, $mirror->mirror_table, $query );
		}
		return $query;
	}
}
