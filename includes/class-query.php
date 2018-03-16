<?php
namespace metamirror;

class Query {
	/**
	 * @var metamirror\Mirror[] The mirrors.
	 */
	private $mirrors = [];

	/**
	 * @var string[] Meta tables being mirrored by this query.
	 */
	private $tables = [];

	/**
	 * Mirror mirror on the wall...
	 *
	 * @param metamirror\Mirror[] $mirrors An array of mirrors to route to.
	 */
	public function __construct( array $mirrors ) {
		$this->mirrors = $mirrors;

		$this->tables = array_unique( array_map( function( $mirror ) {
			return $mirror->meta_table;
		}, $this->mirrors ) );
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
		static $skip_pattern;
		if ( ! $skip_pattern ) {
			$skip_pattern = '#' . implode( '|', array_map( 'preg_quote', $this->tables ) ) . '#';
		}

		/** Skip queries that aren't even closely meta. */
		if ( ! preg_match( $skip_pattern, $query ) ) {
			return $query;
		}

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
	 * This is where most issues will arise. While the core WP_*Query
	 * classes don't do anything too fancy, custom SQL can freak the
	 * whole method out with exotic CROSS JOIN syntaxing, etc.
	 *
	 * Yet, our goal is to only works when we're 100% sure that
	 * we parsed it correctly. Weird syntax? Broken SQL? Target the
	 * original tables.
	 *
	 * Should probably be substituted by a lexer instead. Which
	 * will be pretty slow, unless we cache the parsed trees?
	 *
	 * @param string $string The SQL query.
	 *
	 * @return array Not sure yet
	 */
	public static function parse( string $query ) : array {
		$result = [
			'query'      => '',
			'operation'  => '',
			'table'      => '',
			'alias'      => '',
			'joins'      => [],
			'subqueries' => [],
			'parsed'     => false,
		];

		/** First and foremost gobble up the operation */
		if ( ! preg_match( "#^\s*(SELECT|UPDATE|DELETE)\s+#i", $query, $matches ) ) {
			return $result;
		}

		$result['operation'] = strtoupper( $matches[1] );

		$result['query'] .= $matches[0];
		$query = substr( $query, strlen( $matches[0] ) );

		/** Generic patterns. */
		$table_definition  = '\w[\d\w]*';
		$column_definition = "(?:$table_definition\.)?$table_definition";
		$operator          = '(?:\!?=|(?:NOT\s+)?LIKE|IS\s+(?:NOT\s+))';

		/** Main table */
		switch ( $result['operation'] ):
			case 'SELECT':
				if ( ! preg_match( $pattern = "#^(\S*\s+FROM\s+`?)($table_definition)(`?\s*)#i", $query, $matches ) ) {
					return $result;
				}
				break;
			case 'UPDATE':
				if ( ! preg_match( $pattern = "#^(`?)($table_definition)(`?\s*)#i", $query, $matches ) ) {
					return $result;
				}
				break;
			case 'DELETE':
				if ( ! preg_match( $pattern = "#^(FROM\s+`?)($table_definition)(`?s*)#i", $query, $matches ) ) {
					return $result;
				}
				break;
			default:
				return $result;
		endswitch;

		$result['table'] = $matches[2];

		/** Wrap the table in a marker for replacement later on. */
		$result['query'] .= preg_replace( $pattern, '\1[[$table:\2]]\3', $matches[0] );
		$query = substr( $query, strlen( $matches[0] ) );

		if ( 'SELECT' == $result['operation'] ) {
			/** Alias? */
			if ( preg_match( "#^(?:AS\s+)?`?(?!JOIN|LEFT|INNER|OUTER|WHERE|ORDER|LIMIT)($table_definition)`?s*#i", $query, $matches ) ) {
				$result['alias'] = $matches[1];
				$result['query'] .= $matches[0];
				$query = substr( $query, strlen( $matches[0] ) );
			}

			/** Joins? */
			while ( $query ) {
				if ( ! preg_match( $pattern = "#^((?:LEFT|INNER|OUTER|)JOIN `?)($table_definition)(`?(?:\s+AS)?\s+`?)($table_definition)(`?\s*)#i", $query, $matches ) ) {
					break;
				}

				$result['joins'] []= [
					'table' => $matches[2],
					'alias' => $matches[4],
				];

				$result['query'] .= preg_replace( $pattern, '\1[[$table:\2]]\3\4\5', $matches[0] );
				$query = substr( $query, strlen( $matches[0] ) );

				/** Goobble up the rest... */
				if ( ! preg_match( "#^ON\s+(:?$column_definition)\s+$operator\s+(?:$column_definition)\s*#i", $query, $matches ) ) {
					break;
				}

				$result['query'] .= $matches[0];
				$query = substr( $query, strlen( $matches[0] ) );
			}
		}

		/** Where */

		$query = rtrim( $query, ';' );

		$result['parsed'] = strlen( $query ) == 0;

		return $result;
	}
}
