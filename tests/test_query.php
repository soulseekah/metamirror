<?php
namespace metamirror;

class TestQuery extends \WP_UnitTestCase {
	public function test_parse_basic() {
		$result = Query::parse( 'SELECT * FROM `table1`' );
		$this->assertEquals( 'SELECT', $result['operation'] );
		$this->assertEquals( 'table1', $result['table'] );

		$result = Query::parse( '  update   od' );
		$this->assertEquals( 'UPDATE', $result['operation'] );
		$this->assertEquals( 'od', $result['table'] );

		$result = Query::parse( "\n\t delete \n from \n table2" );
		$this->assertEquals( 'DELETE', $result['operation'] );
		$this->assertEquals( 'table2', $result['table'] );

		$result = Query::parse( 'SELECT * FROM `table1` AS t1' );
		$this->assertEquals( 'table1', $result['table'] );
		$this->assertEquals( 't1', $result['alias'] );

		$result = Query::parse( 'SELECT * FROM `table1` AS `t1`' );
		$this->assertEquals( 'table1', $result['table'] );
		$this->assertEquals( 't1', $result['alias'] );

		$result = Query::parse( 'SELECT * FROM `table1` AS `t1` WHERE' );
		$this->assertEquals( 'table1', $result['table'] );
		$this->assertEquals( 't1', $result['alias'] );

		$result = Query::parse( 'SELECT * FROM `table1` AS t1 ORDER' );
		$this->assertEquals( 'table1', $result['table'] );
		$this->assertEquals( 't1', $result['alias'] );
	}
}
