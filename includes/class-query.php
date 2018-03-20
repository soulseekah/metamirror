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
	 * @return array
	 */
	public static function parse( string $query ) : array {
		$result = [
			'operation'  => '',
			'tables'     => [],
			'subqueries' => [],
			'literals'   => [],
			'parsed'     => false,
			'query'      => '',
		];

		/** Deflate all the string literals, which simplifies parsing. */
		list( $query, $result['literals'] ) = self::_deflate_string_literals( $query );

		/** First and foremost gobble up the operation */
		if ( ! preg_match( "#^\s*(SELECT|UPDATE|DELETE)\s+#i", $query, $matches ) ) {
			return $result;
		}

		$result['operation'] = strtoupper( $matches[1] );

		$result['query'] .= $matches[0];
		$query = substr( $query, strlen( $matches[0] ) );

		/** Generic patterns. */
		$table_definition  = '\w[\d\w]*';
		$column_definition = "`?(?:$table_definition`?\.)?`?$table_definition`?";
		$operator          = '(?:\!?=|[<>=]+|(?:NOT\s+)?LIKE|IS\s+(?:NOT\s+)|(?:NOT\s+)?(?:EXISTS|IN)\s+\()';
		$keywords          = 'JOIN|LEFT|INNER|OUTER|WHERE|ORDER|LIMIT|OFFSET|SET|VALUES';

		switch ( $result['operation'] ):
			case 'SELECT':
				if ( ! preg_match( "#^.*?\s+FROM\s+#i", $query, $matches ) ) {;
					return $result;
				}

				$result['query'] .= $matches[0];
				$query = substr( $query, strlen( $matches[0] ) );
				/** fallthrough */
			case 'UPDATE':
				$alias = "(?:AS\s+)?(?!$keywords)(`?)($table_definition)(`?\s*)";

				while ( $query ) {
					if ( ! preg_match( $pattern = "#^(?!$keywords)(`?)($table_definition)(`?\s*(?:$alias)?(,?)\s*)#i", $query, $matches ) ) {
						break;
					}

					$before_table = $matches[1];
					$table        = $matches[2];
					$after_table  = $matches[3];

					$result['tables'][ $matches[5] ? : $table ] = $table;

					/** Wrap the table in a marker for replacement later on. */
					$result['query'] .= preg_replace( $pattern, '\1[[$table:\2]]\3', $matches[0] );
					$query = substr( $query, strlen( $matches[0] ) );
				}

				break;
			case 'DELETE':
				if ( ! preg_match( $pattern = "#^(.*?FROM\s+`?)($table_definition)(`?\s*)#i", $query, $matches ) ) {
					return $result;
				}

				$result['tables'][ $matches[2] ] = $matches[2];

				/** Wrap the table in a marker for replacement later on. */
				$result['query'] .= preg_replace( $pattern, '\1[[$table:\2]]\3', $matches[0] );
				$query = substr( $query, strlen( $matches[0] ) );

				break;
			default:
				return $result;
		endswitch;

		/** Joins? */
		while ( preg_match( $pattern = "#^((?:(?:LEFT|INNER|OUTER)\s+|)JOIN\s+`?)(\w[\d\w]*)(`?(?:\s+AS)?\s+`?)(\w[\d\w]*)(`?\s*)#i", $query, $matches ) ) {
			$result['tables'][ $matches[4] ] = $matches[2];

			$result['query'] .= preg_replace( $pattern, '\1[[$table:\2]]\3\4\5', $matches[0] );
			$query = substr( $query, strlen( $matches[0] ) );

			/** Goobble up the rest... */
			if ( ! preg_match( "#^ON\s+(:?$column_definition)\s+$operator\s+(?:$column_definition)\s*#i", $query, $matches ) ) {
				break;
			}

			$result['query'] .= $matches[0];
			$query = substr( $query, strlen( $matches[0] ) );
		}

		/** Where? */
		if ( preg_match( '#^(WHERE\s+)#', $query, $matches ) ) {
			$result['query'] .= $matches[0];
			$query = substr( $query, strlen( $matches[0] ) );

			$keywords = 'GROUP\s+BY|ORDER\s+BY|LIMIT|OFFSET';

			while ( preg_match( "#^(?!\(\s*SELECT|$keywords|$)(AND\s+|OR\s+)?(.+?)(\(\s*(?<subquery>SELECT)|$keywords|AND|OR|;?$)\s*#", $query, $matches ) ) {
				$where_condition = $matches[2];

				if ( ! preg_match( "#^($column_definition)(\s+$operator.*)#", $where_condition, $submatches ) ) {
					break;
				}

				$result['query'] .= $matches[1] . '[[$column:' . $submatches[1] . ']]' . $submatches[2];
				$query = substr( $query, strlen( $matches[0] ) );

				/** Parse subquery */
				if ( ! empty( $matches['subquery'] ) ) {
					throw new Error( 'Not implemented' );
				}
			}
		}

		$query = rtrim( $query, ';' );

		$result['parsed'] = strlen( $query ) == 0;

		return $result;
	}

	/**
	 * Deflate a SQL query removing all string literals.
	 *
	 * @param string $query The deflated SQL query.
	 * @param string[] $map The map of replacements.
	 *
	 * @return string The inflated SQL.
	 */
	public static function _deflate_string_literals( string $query ) : array {
		$slash = preg_quote( '\\' );
		$dblqt = preg_quote( '"' );
		$pattern = "#(?:(')((?:([^'$slash])|$slash.)*)'|($dblqt)((?:([^$dblqt$slash])|$slash.)*)$dblqt)#";

		$map = [];

		$query = preg_replace_callback( $pattern, function( $matches ) use ( &$map ) {
			$quote = empty( $matches[4] ) ? "'" : '"';
			$map[ $id = count( $map ) + 1 ] = $matches[ $quote === "'" ? 2 : 5 ];
			return "{$quote}[[\$literal:$id]]{$quote}";
		}, $query );

		return [ $query, $map ];
	}

	/**
	 * Inflate a SQL query back with literals.
	 *
	 * @param string $query The deflated SQL query.
	 * @param string[] $map The map of replacements.
	 *
	 * @return string The inflated SQL.
	 */
	public static function _inflate_string_literals( string $query, array $map ) : string {
		$pattern = '#\[\[\$literal:(\d+)\]\]#';

		return preg_replace_callback( $pattern, function( $matches ) use ( &$map ) {
			return $map[ $matches[1] ];
		}, $query );
	}
}
