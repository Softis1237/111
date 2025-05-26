<?php
/**
 * Управляет взаимодействием с базой данных для пользовательских купонов Dokan.
 *
 * @package WoodmartChildRPG\Integration\Dokan
 */

namespace WoodmartChildRPG\Integration\Dokan;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Запрещаем прямой доступ.
}

class DokanUserCouponDB {

	/**
	 * Имя таблицы в БД.
	 * @var string
	 */
	private $table_name;

	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'rpg_user_dokan_coupons';
	}

	/**
	 * Добавляет купон Dokan в инвентарь пользователя.
	 *
	 * @param int    $user_id ID пользователя.
	 * @param int    $coupon_id ID поста купона WooCommerce.
	 * @param int    $vendor_id ID продавца Dokan.
	 * @param string $original_code Оригинальный код купона.
	 * @param int    $limit Максимальное количество купонов Dokan в инвентаре.
	 * @return bool|\WP_Error True в случае успеха, WP_Error в случае ошибки.
	 */
	public function add_coupon_to_inventory( $user_id, $coupon_id, $vendor_id, $original_code, $limit = 20 ) {
		global $wpdb;

		// Проверка на существование
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->table_name} WHERE user_id = %d AND coupon_id = %d",
				$user_id,
				$coupon_id
			)
		);
		if ( $exists ) {
			return new \WP_Error( 'dokan_coupon_already_in_inventory', __( 'Этот купон продавца уже есть в вашем инвентаре.', 'woodmart-child' ) );
		}

		// Проверка лимита
		$current_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(id) FROM {$this->table_name} WHERE user_id = %d",
				$user_id
			)
		);
		if ( $current_count >= $limit ) {
			return new \WP_Error( 'dokan_inventory_full', __( 'Инвентарь купонов продавцов полон.', 'woodmart-child' ) );
		}

		$inserted = $wpdb->insert(
			$this->table_name,
			array(
				'user_id'         => $user_id,
				'coupon_id'       => $coupon_id,
				'vendor_id'       => $vendor_id,
				'original_code'   => $original_code,
				'added_timestamp' => current_time( 'timestamp' ),
			),
			array( '%d', '%d', '%d', '%s', '%d' )
		);

		if ( ! $inserted ) {
			// error_log( "Failed to insert Dokan coupon for user {$user_id}, coupon {$coupon_id}. DB Error: " . $wpdb->last_error );
			return new \WP_Error( 'dokan_coupon_add_db_error', __( 'Не удалось добавить купон продавца в инвентарь (ошибка БД).', 'woodmart-child' ) );
		}
		return true;
	}

	/**
	 * Проверяет, есть ли у пользователя указанный купон Dokan в инвентаре.
	 *
	 * @param int $user_id ID пользователя.
	 * @param int $coupon_id ID купона.
	 * @return bool
	 */
	public function user_has_coupon( $user_id, $coupon_id ) {
		global $wpdb;
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(id) FROM {$this->table_name} WHERE user_id = %d AND coupon_id = %d",
				$user_id,
				$coupon_id
			)
		);
		return $count > 0;
	}

	/**
	 * Удаляет купон Dokan из инвентаря пользователя.
	 *
	 * @param int $user_id ID пользователя.
	 * @param int $coupon_id ID купона.
	 * @return bool True если удалено, иначе false.
	 */
	public function remove_coupon_from_inventory( $user_id, $coupon_id ) {
		global $wpdb;
		$deleted_rows = $wpdb->delete(
			$this->table_name,
			array( 'user_id' => $user_id, 'coupon_id' => $coupon_id ),
			array( '%d', '%d' )
		);
		return $deleted_rows > 0;
	}

	/**
	 * Получает купоны Dokan из инвентаря пользователя с пагинацией.
	 *
	 * @param int $user_id ID пользователя.
	 * @param int $vendor_id_filter ID продавца для фильтрации (0 для всех).
	 * @param int $per_page Количество на странице.
	 * @param int $current_page Текущая страница.
	 * @return array Массив объектов купонов.
	 */
	public function get_user_coupons( $user_id, $vendor_id_filter = 0, $per_page = 10, $current_page = 1 ) {
		global $wpdb;
		$offset = ( $current_page - 1 ) * $per_page;

		$where_clauses = array( "user_id = %d" );
		$query_params  = array( $user_id );

		if ( $vendor_id_filter > 0 ) {
			$where_clauses[] = "vendor_id = %d";
			$query_params[]  = $vendor_id_filter;
		}
		$where_sql = implode( " AND ", $where_clauses );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT coupon_id, vendor_id, original_code FROM {$this->table_name} WHERE {$where_sql} ORDER BY added_timestamp DESC LIMIT %d OFFSET %d",
				array_merge( $query_params, array( $per_page, $offset ) )
			)
		);
	}

	/**
	 * Получает общее количество купонов Dokan в инвентаре пользователя (с учетом фильтра).
	 *
	 * @param int $user_id ID пользователя.
	 * @param int $vendor_id_filter ID продавца для фильтрации (0 для всех).
	 * @return int
	 */
	public function get_user_coupons_count( $user_id, $vendor_id_filter = 0 ) {
		global $wpdb;
		$where_clauses = array( "user_id = %d" );
		$query_params  = array( $user_id );

		if ( $vendor_id_filter > 0 ) {
			$where_clauses[] = "vendor_id = %d";
			$query_params[]  = $vendor_id_filter;
		}
		$where_sql = implode( " AND ", $where_clauses );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(id) FROM {$this->table_name} WHERE {$where_sql}",
				$query_params
			)
		);
	}
    
    /**
	 * Получает все купоны пользователя для проверки статуса.
	 *
	 * @param int $user_id ID пользователя.
	 * @return array Массив объектов с coupon_id и original_code.
	 */
	public function get_all_user_coupons_for_status_check( $user_id ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT coupon_id, original_code FROM {$this->table_name} WHERE user_id = %d",
				$user_id
			)
		);
	}

	/**
	 * Получает данные конкретного купона Dokan из инвентаря пользователя.
	 *
	 * @param int $user_id ID пользователя.
	 * @param int $coupon_id ID купона.
	 * @return object|null Объект с данными купона (coupon_id, vendor_id, original_code, added_timestamp) или null.
	 */
	public function get_specific_user_coupon_data( $user_id, $coupon_id ) { // <--- НОВЫЙ МЕТОД
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT coupon_id, vendor_id, original_code, added_timestamp FROM {$this->table_name} WHERE user_id = %d AND coupon_id = %d",
				$user_id,
				$coupon_id
			)
		);
	}
}

