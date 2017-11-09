<?php

class PhpCodeForPosts_Database
{
	const TABLENAME = 'phppc_functions';

	public static function get_db()
	{
		global $wpdb;

		return $wpdb;
	}

	public static function get_full_table_name( $blog_id = FALSE )
	{
		$db = self::get_db();

		if ( $blog_id !== FALSE ) {
			$blog_id = max( intval( $blog_id ), 0 );
		}

		if ( $blog_id === FALSE || $blog_id == 0 ) {
			return $db->prefix . self::TABLENAME;
		}

		if ( $blog_id == 1 ) {
			return $db->base_prefix . self::TABLENAME;
		}

		return $db->base_prefix . $blog_id . '_' . self::TABLENAME;
	}

	public static function load_all_snippets()
	{
		$db = self::get_db();
		$query = sprintf(
			'SELECT * FROM %s ORDER BY id',
			self::get_full_table_name()
		);

		$snippets = $db->get_results( $query );

		if ( count( $snippets ) > 0 ) {
			foreach ( $snippets as $index => $snippet ) {
				$snippets[ $index ] = PhpCodeForPosts_Snippet::create_from_database_object( $snippet );
			}
		}

		return $snippets;
	}

	public static function load_multisite_shared_snippets()
	{
		$db = self::get_db();
		$query = sprintf(
			'SELECT * FROM %s WHERE shared = 1 ORDER BY id',
			self::get_full_table_name( 1 )
		);

		$snippets = $db->get_results( $query );

		if ( count( $snippets ) > 0 ) {
			foreach ( $snippets as $index => $snippet ) {
				$snippets[ $index ] = PhpCodeForPosts_Snippet::create_from_database_object( $snippet );
			}
		}

		return $snippets;
	}

	public static function load_single_snippet( $snippet_id, $blog_id = 0 )
	{
		if ( ! filter_var( $snippet_id, FILTER_VALIDATE_INT ) ) {
			throw new InvalidArgumentException;
		}

		$db = self::get_db();
		$query = sprintf(
			'SELECT * FROM %s WHERE id = %%d',
			self::get_full_table_name( $blog_id )
		);
		if ( $blog_id > 0 && PhpCodeForPosts::$options->get_blog_id() != $blog_id ) {
			$query .= ' AND shared = 1';
		}

		$query = $db->prepare( $query, $snippet_id );
		$snippet_row = $db->get_row( $query );
		if ( ! $snippet_row ) {
			$snippet_row = new StdClass;
		}

		return PhpCodeForPosts_Snippet::create_from_database_object( $snippet_row );
	}

	public static function load_single_snippet_by_slug( $slug, $blog_id = 0 )
	{
		if ( ! $slug ) {
			throw new InvalidArgumentException;
		}
		$db = self::get_db();
		$query = sprintf(
			'SELECT * FROM %s WHERE slug = %%s',
			self::get_full_table_name( $blog_id )
		);
		if ( $blog_id > 0 && PhpCodeForPosts::$options->get_blog_id() != $blog_id ) {
			$query .= ' AND shared = 1';
		}

		$query = $db->prepare( $query, $slug );
		$snippet_row = $db->get_row( $query );
		if ( ! $snippet_row ) {
			$snippet_row = new StdClass;
		}

		return PhpCodeForPosts_Snippet::create_from_database_object( $snippet_row );
	}

	public static function delete_snippet_by_id( $snippet_id )
	{
		if ( ! filter_var( $snippet_id, FILTER_VALIDATE_INT ) ) {
			throw new InvalidArgumentException( 'Invalid Snippet Id: ' . $snippet_id );
		}
		$db = self::get_db();

		return $db->delete( self::get_full_table_name(), array( 'id' => $snippet_id ), array( "%d" ) ) !== FALSE;
	}

	public static function save_snippet( PhpCodeForPosts_Snippet &$snippet )
	{
		$slug = $snippet->get_slug();
		if ( $slug == '' ) {
			$slug = sanitize_title( $snippet->get_name() );
			$snippet->set_slug( $slug );
		}
		$n = 1;
		while ( self::slug_exists( $snippet->get_id(), $slug ) ) {
			$n ++;
			$slug = $snippet->get_slug() . '-' . $n;
		}

		$snippet->set_slug( $slug );

		if ( $snippet->get_id() > 0 && self::id_exists( $snippet->get_id() ) ) {
			return self::_update_snippet( $snippet );
		}

		return self::_insert_snippet( $snippet );
	}

	private static function id_exists( $id )
	{
		$db = self::get_db();
		$query = 'SELECT id FROM ' . self::get_full_table_name() . ' WHERE id = %d';
		$query = $db->prepare( $query, $id );

		$row = $db->get_row( $query, ARRAY_N );

		return ( intval( $row[ 0 ] ) > 0 );
	}

	private static function slug_exists( $id, $slug )
	{
		$db = self::get_db();
		$query = 'SELECT id FROM ' . self::get_full_table_name() . ' WHERE id != %d AND slug = %s';
		$query = $db->prepare( $query, array( $id, $slug ) );

		$row = $db->get_row( $query, ARRAY_N );

		return ( intval( $row[ 0 ] ) > 0 );
	}

	private static function _insert_snippet( PhpCodeForPosts_Snippet &$snippet )
	{
		$db = self::get_db();
		$inserted = $db->insert(
				self::get_full_table_name(),
				$snippet->get_array_for_db(),
				$snippet->get_array_format_for_db()
			) !== FALSE;

		if ( $inserted ) {
			$snippet->set_id( $db->insert_id );
		}

		return $inserted;
	}

	private static function _update_snippet( PhpCodeForPosts_Snippet $snippet )
	{
		$db = self::get_db();

		return ( $db->update(
				self::get_full_table_name(),
				$snippet->get_array_for_db(),
				$snippet->get_where_for_update(),
				$snippet->get_array_format_for_db(),
				$snippet->get_where_format_for_update()
			) !== FALSE );
	}
}
