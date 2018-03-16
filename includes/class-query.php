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
	 * Probably parsing SQL with regular expressions.
	 * What could go wrong?
	 *
	 * @param string $query The SQL query.
	 *
	 * @return string The rewritten SQL.
	 */
	public function rewrite( string $query ) : string {
		/** Parse the query and analyze it. */
		$_query = self::parse( $query );

		foreach ( $this->mirrors as $mirror ) {
			// route by key

			// route by cast
		}

		return $query;
	}

	/**
	 * Retrieve table names, their aliases and column values.
	 *
	 * Hopefully the subset we understand and the inferences we
	 * make will work for most cases.
	 *
	 * @param string $string The SQL query.
	 *
	 * @return array Not sure yet
	 */
	public static function parse( string $query ) : array {
		$result = [
			'query'     => '',
			'operation' => '',
			'table'     => '',
			'alias'     => '',
		];

		/** First and foremost gobble up the operation */
		if ( ! preg_match( "#^\s*(select|update|delete)\s+#i", $query, $matches ) ) {
			return $result;
		}

		$result['operation'] = strtoupper( $matches[1] );

		$result['query'] .= $matches[0];
		$query = substr( $query, strlen( $matches[0] ) );

		$table_definition = '\w[\d\w]*';

		/** Main table */
		switch ( $result['operation'] ):
			case 'SELECT':
				if ( ! preg_match( $pattern = "#^\S*\s+FROM\s+`?($table_definition)`?\s*#i", $query, $matches ) ) {
					return $result;
				}
				break;
			case 'UPDATE':
				if ( ! preg_match( $pattern = "#^`?($table_definition)`?\s*#i", $query, $matches ) ) {
					return $result;
				}
				break;
			case 'DELETE':
				if ( ! preg_match( $pattern = "#^FROM\s+`?($table_definition)`?s*#i", $query, $matches ) ) {
					return $result;
				}
				break;
		endswitch;

		$result['table'] = $matches[1];

		/** Wrap the table in a marker for replacement later on. */
		$result['query'] .= preg_replace( $pattern, '[[$table:\1]]', $matches[0] );
		$query = substr( $query, strlen( $matches[0] ) );

		/** Alias? */
		if ( 'SELECT' == $result['operation'] ) {
			if ( preg_match( "#^(?:AS\s+)?`?(?!WHERE\s|ORDER\s|LIMIT\s)($table_definition)`?#", $query, $matches ) ) {
				$result['alias'] = $matches[1];
				$query = substr( $query, strlen( $matches[0] ) );
			}
		}

		return $result;
	}
}
