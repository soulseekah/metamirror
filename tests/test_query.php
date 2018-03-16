<?php
namespace metamirror;

class TestQuery extends \WP_UnitTestCase {
	public function test_parse_from() {
		$result = Query::parse( 'SELECT * FROM `table1`' );
		$this->assertEquals( 'SELECT', $result['operation'] );
		$this->assertEquals( 'table1', $result['table'] );
		$this->assertTrue( $result['parsed'] );

		$result = Query::parse( '  update   od' );
		$this->assertEquals( 'UPDATE', $result['operation'] );
		$this->assertEquals( 'od', $result['table'] );
		$this->assertTrue( $result['parsed'] );

		$result = Query::parse( "\n\t delete \n from \n table2" );
		$this->assertEquals( 'DELETE', $result['operation'] );
		$this->assertEquals( 'table2', $result['table'] );
		$this->assertTrue( $result['parsed'] );

		$result = Query::parse( 'SELECT * FROM `table1` AS t1' );
		$this->assertEquals( 'table1', $result['table'] );
		$this->assertEquals( 't1', $result['alias'] );
		$this->assertTrue( $result['parsed'] );

		$result = Query::parse( 'SELECT * FROM `table1` AS `t1`' );
		$this->assertEquals( 'table1', $result['table'] );
		$this->assertEquals( 't1', $result['alias'] );
		$this->assertTrue( $result['parsed'] );

		$result = Query::parse( 'SELECT * FROM `table1` AS `t1` WHERE' );
		$this->assertEquals( 'table1', $result['table'] );
		$this->assertEquals( 't1', $result['alias'] );
		$this->assertFalse( $result['parsed'] );

		$result = Query::parse( 'SELECT * FROM `table1` AS t1 ORDER' );
		$this->assertEquals( 'table1', $result['table'] );
		$this->assertEquals( 't1', $result['alias'] );
		$this->assertFalse( $result['parsed'] );

		$result = Query::parse( 'SELECT * FROM `table1` WHERE' );
		$this->assertEquals( 'table1', $result['table'] );
		$this->assertEmpty( '', $result['alias'] );
		$this->assertFalse( $result['parsed'] );

		$result = Query::parse( 'SELECT * FROM `table1` JOIN table4 t ON post_id = t.post_id' );
		$this->assertEquals( 'table1', $result['table'] );
		$this->assertEmpty( '', $result['alias'] );
		$this->assertTrue( $result['parsed'] );
	}

	public function test_parse_join() {
		$result = Query::parse( 'SELECT * FROM `table1` JOIN table4 t ON post_id = t.post_id;' );
		$this->assertEquals( 'table1', $result['table'] );
		$joins = [ [ 'table' => 'table4', 'alias' => 't' ] ];
		$this->assertEqualSets( $joins, $result['joins'] );
		$this->assertTrue( $result['parsed'] );
		$this->assertEquals( 'SELECT * FROM `[[$table:table1]]` JOIN [[$table:table4]] t ON post_id = t.post_id', $result['query'] );
	}

	public function test_parse_join_multi() {
		$result = Query::parse( 'SELECT * FROM `table1` JOIN table4 t ON post_id = t.post_id LEFT JOIN table5 AS t_45 ON col1 != col3;' );
		$this->assertEquals( 'table1', $result['table'] );
		$joins = [ [ 'table' => 'table4', 'alias' => 't' ], [ 'table' => 'table5', 'alias' => 't_45' ] ];
		$this->assertEqualSets( $joins, $result['joins'] );
		$this->assertTrue( $result['parsed'] );
		$expected = 'SELECT * FROM `[[$table:table1]]` JOIN [[$table:table4]] t ON post_id = t.post_id LEFT JOIN [[$table:table5]] AS t_45 ON col1 != col3';
		$this->assertEquals( $expected, $result['query'] );
	}

	/**
	 * @dataProvider get_string_literals_flate
	 */
	public function test_delfate_string_literals( $deflated, $inflated ) {
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
		return [
			[
				[ 'SELECT * FROM table1', [] ],
				'SELECT * FROM table1'
			],
			[
				[ 'SELECT * FROM table1 WHERE meta_key = [[$literal:1]]', [ 1 => 'hello' ] ],
				'SELECT * FROM table1 WHERE meta_key = "hello"'
			],
			[
				[ 'SELECT * FROM table1 WHERE meta_key IN ([[$literal:1]], [[$literal:2]])', [ 1 => 'hello', 2 => 'world' ] ],
				'SELECT * FROM table1 WHERE meta_key IN ("hello", \'world\')'
			]
		];
	}

	public function test_parse_where() {
		$result = Query::parse( 'SELECT * FROM `table1` WHERE meta_key = 4 AND meta_value < 100;' );
		$this->assertTrue( $result['parsed'] );
	}
}
