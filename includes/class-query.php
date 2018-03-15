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
	 * Reroute the query.
	 *
	 * Rewrite meta without breaking it ;)
	 *
	 * @param string $query The SQL query.
	 *
	 * @return string The rewritten SQL.
	 */
	public function route( string $query ) : string {
		return $query;
	}
}
