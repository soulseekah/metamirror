<?php
namespace metamirror;

class TestQuery extends \WP_UnitTestCase {
	/**
	 * @dataProvider get_basic_sql
	 */
	public function test_query_basic( $mirrors, $in, $out ) {
		$query = new Query( $mirrors );
		$this->assertEquals( $out, $query->rewrite( $in ) );
	}

	public function get_basic_sql() {
		global $wpdb;
		$mirror = new Mirror( $wpdb->postmeta, 'INTEGER' );

		return [
			[
				[ $mirror ],
				"SELECT * FROM $mirror->meta_table",
				"SELECT * FROM $mirror->mirror_table"
			],
			[
				[ $mirror ],
				"SELECT meta_id FROM $mirror->meta_table m1 LEFT JOIN $mirror->meta_table m2 ON m1.meta_id = m2.meta_id",
				"SELECT meta_id FROM $mirror->mirror_table m1 LEFT JOIN $mirror->mirror_table m2 ON m1.meta_id = m2.meta_id",
			],
			[
				[ $mirror ],
				"WHERE $mirror->meta_table = $mirror->meta_table",
				"WHERE $mirror->mirror_table = $mirror->mirror_table",
			],
		];
	}
}
