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
}
