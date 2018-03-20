<?php
namespace metamirror;

class TestQuery extends \WP_UnitTestCase {
	/**
	 * @dataProvider get_parse_from
	 */
	public function test_parse_from( $query, $operation, $tables, $parsed ) {
		$result = Query::parse( $query );
		$this->assertEquals( $operation, $result['operation'] );
		$this->assertEquals( $tables, $result['tables'] );
		$this->assertEquals( $parsed, $result['parsed'] );
	}

	public function get_parse_from() {
		return [
			[ " \nSELECT\ncolumn, column\n FROM  `table1`"  , 'SELECT', [ 'table1' => 'table1' ], true ],
			[ 'SELECT * FROM `table1` AS t1', 'SELECT', [ 't1' => 'table1' ], true ],
			[ 'SELECT * FROM `table1` AS `t1`', 'SELECT', [ 't1' => 'table1' ], true ],
			[ 'SELECT * FROM `table1` AS `t1` WHERE', 'SELECT', [ 't1' => 'table1' ], false ],
			[ 'SELECT * FROM `table1` AS `t1` ORDER', 'SELECT', [ 't1' => 'table1' ], false ],
			[ 'SELECT * FROM `table1` WHERE', 'SELECT', [ 'table1' => 'table1' ], false ],
			[ 'SELECT * FROM `table1`, `table2` OFFSET', 'SELECT', [ 'table1' => 'table1', 'table2' => 'table2' ], false ],
			[ 'SELECT * FROM `table1` AS a, table2 `b` LIMIT', 'SELECT', [ 'a' => 'table1', 'b' => 'table2' ], false ],
			[ 'SELECT * FROM `table1` AS m, `tables2` `p`, tables4 AS `z`', 'SELECT', [ 'm' => 'table1', 'p' => 'tables2', 'z' => 'tables4' ], true ],

			[ 'UPDATE table1 SET', 'UPDATE', [ 'table1' => 'table1' ], false ],
			[ 'DELETE FROM table2 WHERE', 'DELETE', [ 'table2' => 'table2' ], false ],
			[ 'DELETE table2 FROM table2 LEFT JOIN table1 b ON id = id;', 'DELETE', [ 'table2' => 'table2', 'b' => 'table1' ], true ],
		];
	}

	public function test_parse_join() {
		$result = Query::parse( 'SELECT * FROM `table1` JOIN table4 t ON post_id = t.post_id;' );
		$tables = [ 'table1' => 'table1', 't' => 'table4' ];
		$this->assertEqualSets( $tables, $result['tables'] );
		$this->assertTrue( $result['parsed'] );
		$this->assertEquals( 'SELECT * FROM `[[$table:table1]]` JOIN [[$table:table4]] t ON post_id = t.post_id', $result['query'] );
	}

	public function test_parse_join_multi() {
		$result = Query::parse( 'SELECT * FROM `table1` JOIN table4 t ON post_id = t.post_id LEFT JOIN table5 AS t_45 ON col1 != col3;' );
		$tables = [ 'table1' => 'table1', 't' => 'table4', 't_45' => 'table5' ];
		$this->assertEqualSets( $tables, $result['tables'] );
		$this->assertTrue( $result['parsed'] );
		$expected = 'SELECT * FROM `[[$table:table1]]` JOIN [[$table:table4]] t ON post_id = t.post_id LEFT JOIN [[$table:table5]] AS t_45 ON col1 != col3';
		$this->assertEquals( $expected, $result['query'] );
	}

	/**
	 * @dataProvider get_string_literals_flate
	 */
	public function test_deflate_string_literals( $deflated, $inflated ) {
		$out = Query::_deflate_string_literals( $inflated );
		$this->assertEquals( $deflated, $out );
	}

	/**
	 * @dataProvider get_string_literals_flate
	 */
	public function test_inflate_string_literals( $deflated, $inflated ) {
		$out = call_user_func_array( [ Query::class, '_inflate_string_literals' ], $deflated );
		$this->assertEquals( $inflated, $out );
	}

	public function get_string_literals_flate() {
		$slash = '\\';
		return [
			[
				[ 'SELECT * FROM table1', [] ],
				'SELECT * FROM table1'
			],
			[
				[ 'SELECT * FROM table1 WHERE meta_key = "[[$literal:1]]"', [ 1 => 'hello' ] ],
				'SELECT * FROM table1 WHERE meta_key = "hello"'
			],
			[
				[ 'SELECT * FROM table1 WHERE meta_key IN ("[[$literal:1]]", \'[[$literal:2]]\')', [ 1 => 'hello', 2 => 'world' ] ],
				'SELECT * FROM table1 WHERE meta_key IN ("hello", \'world\')'
			],
			[
				[ 'SELECT * FROM table1 WHERE meta_key LIKE "[[$literal:1]]"', [ 1 => '%\"one\'two' ] ],
				'SELECT * FROM table1 WHERE meta_key LIKE "%\"one\'two"'
			],
			[
				[ 'SELECT REPLACE(meta_value, \'[[$literal:1]]\', "[[$literal:2]]") FROM table1 WHERE meta_key = "[[$literal:3]]"', [ 1 => '"', 2 => "'", 3 => '\"' ] ],
				'SELECT REPLACE(meta_value, \'"\', "\'") FROM table1 WHERE meta_key = "\""'
			],
			[
				[ 'SELECT "[[$literal:1]]", "[[$literal:2]]"', [ 1 => $slash . $slash, 2 => $slash . "'$slash$slash" ] ],
				'SELECT "' . $slash . $slash . '", "' . $slash . "'$slash$slash" . '"'
			],
			[
				[ 'WHERE meta_value != "[[$literal:1]]"', [ 1 => '' ] ],
				'WHERE meta_value != ""'
			]
		];
	}

	public function test_parse_where() {
		$result = Query::parse( 'SELECT * FROM `table1` WHERE `table1`.`meta_key` = 4 AND meta_value < 100;' );
		$this->assertTrue( $result['parsed'] );
	}
}
