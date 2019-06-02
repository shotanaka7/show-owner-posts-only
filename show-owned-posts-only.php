<?php
/**
 * Plugin Name:show owned posts only
 * Description:オーナー権限を持たないユーザーへの、自分以外の投稿者、寄稿者の記事一覧を非表示にします。
 * Version:1.0.0
 * Author:Sho Tanaka
 *
 * @package show_owned_posts_only
 * @version 1.0.0
 */

/**
* 管理画面の投稿一覧をログイン中のユーザーの投稿のみに制限します。(管理者以外)
*/
function pre_get_author_posts( $query ) {
	// 管理画面 かつ 非管理者 かつ メインクエリ
	// かつ authorパラメータがないかauthorパラメータが自分のIDの場合、
	// 投稿者を絞った状態を前提として表示を調整します。
	if ( is_admin() && ! current_user_can( 'administrator' ) && $query->is_main_query() && ( ! isset( $_GET['author'] ) || get_current_user_id() == $_GET['author'] ) ) {
		// クエリの条件を追加
		$query->set( 'author', get_current_user_id() );
		// 値があると WP_Posts_List_Table#is_base_request() がfalseになり
		// 「すべて」のリンクが選択表示にならないため削除
		unset( $_GET['author'] );
	}
}
add_action( 'pre_get_posts', 'pre_get_author_posts' );

/**
* ログイン中のユーザーが投稿した投稿数を取得します。
* ※ wp_count_posts()をベースにして下記を行っています。
* 1. フィルターの削除 (無限ループを防ぐ)
* 2. キャッシュキーの変更 (変更が反映されない問題を防ぐ)
* 3. SQLの変更
*/
function count_author_posts( $counts, $type = 'post', $perm = '' ) {
	// 管理画面側ではない場合、または管理画面側でも管理者の場合は投稿数を調整せず終了します。
	if ( ! is_admin() || current_user_can( 'administrator' ) ) {
		return $counts;
	}

	global $wpdb;
	if ( ! post_type_exists( $type ) ) {
		return new stdClass;
	}

	$cache_key = _count_posts_cache_key( $type, $perm ) . '_author'; // 2
	$counts    = wp_cache_get( $cache_key, 'counts' );
	if ( false !== $counts ) {
		return $counts; // 1
	}

	// 3
	$query  = "SELECT post_status, COUNT( * ) AS num_posts FROM {$wpdb->posts} WHERE post_type = %s";
	$query .= $wpdb->prepare( ' AND ( post_author = %d )', get_current_user_id() );
	$query .= ' GROUP BY post_status';

	$results = (array) $wpdb->get_results( $wpdb->prepare( $query, $type ), ARRAY_A );
	$counts  = array_fill_keys( get_post_stati(), 0 );
	foreach ( $results as $row ) {
		$counts[ $row['post_status'] ] = $row['num_posts'];
	}
	$counts = (object) $counts;
	wp_cache_set( $cache_key, $counts, 'counts' );
	return $counts; // 1
}
add_filter( 'wp_count_posts', 'count_author_posts', 10, 3 );
