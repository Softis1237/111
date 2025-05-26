<?php
/**
 * Обработчик AJAX-запросов для RPG системы.
 *
 * @package WoodmartChildRPG\Core
 */

namespace WoodmartChildRPG\Core;

use WoodmartChildRPG\RPG\Character as RPGCharacter;
use WoodmartChildRPG\RPG\RaceFactory; // Может понадобиться для проверки условий способности
use WoodmartChildRPG\Core\Utils;
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Запрещаем прямой доступ.
}

class AJAXHandler {

	private $character_manager;

	public function __construct( RPGCharacter $character_manager ) {
		$this->character_manager = $character_manager;
	}

	public function handle_use_rpg_coupon() {
		check_ajax_referer( 'rpg_ajax_nonce', '_ajax_nonce' );

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Требуется авторизация', 'woodmart-child' ) ) );
		}

		$race  = $this->character_manager->get_race( $user_id );
		$level = $this->character_manager->get_level( $user_id );
		$index = isset( $_POST['index'] ) ? intval( $_POST['index'] ) : -1;

		if ( $index < 0 ) {
			wp_send_json_error( array( 'message' => __( 'Неверный индекс RPG купона', 'woodmart-child' ) ) );
		}

		$coupons = $this->character_manager->get_coupon_inventory( $user_id );
		if ( ! is_array( $coupons ) || ! isset( $coupons[ $index ] ) ) {
			wp_send_json_error( array( 'message' => __( 'RPG купон не найден', 'woodmart-child' ) ) );
		}
		$coupon_to_activate = $coupons[ $index ];

		if ( WC()->session && WC()->session->get( 'active_rpg_dokan_coupon_code' ) ) {
			wp_send_json_error( array( 'message' => __( 'У вас уже активирован купон продавца. Нельзя активировать RPG купон одновременно.', 'woodmart-child' ) ) );
		}

		$active_item_coupon_session = WC()->session ? WC()->session->get( 'active_item_coupon' ) : null;
		$active_cart_coupon_session = WC()->session ? WC()->session->get( 'active_cart_coupon' ) : null;

		$is_item_coupon = in_array( $coupon_to_activate['type'], array( 'daily', 'exclusive_item', 'common' ), true );
		$is_cart_coupon = in_array( $coupon_to_activate['type'], array( 'weekly', 'exclusive_cart' ), true );

		if ( $is_item_coupon && $active_item_coupon_session ) {
			wp_send_json_error( array( 'message' => __( 'Уже активирован RPG купон на товар', 'woodmart-child' ) ) );
		}
		if ( $is_cart_coupon && $active_cart_coupon_session ) {
			wp_send_json_error( array( 'message' => __( 'Уже активирован RPG купон на корзину', 'woodmart-child' ) ) );
		}

		$value_to_apply = $coupon_to_activate['value'];
		$save_coupon    = false;

		if ( 'human' === $race && 'daily' === $coupon_to_activate['type'] ) {
			$upgrade_chance_map = array( 1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5 );
			$save_chance_map    = array( 1 => 5, 2 => 7, 3 => 10, 4 => 12, 5 => 15 );
			$upgrade_chance     = isset( $upgrade_chance_map[ $level ] ) ? $upgrade_chance_map[ $level ] : 1;
			$save_chance        = isset( $save_chance_map[ $level ] ) ? $save_chance_map[ $level ] : 5;
			if ( wp_rand( 1, 100 ) <= $upgrade_chance ) $value_to_apply += 2;
			if ( wp_rand( 1, 100 ) <= $save_chance ) $save_coupon = true;
		}
		
		$coupon_for_session = array( 'type' => $coupon_to_activate['type'], 'value' => $value_to_apply );

		if ( WC()->session ) {
			if ( $is_item_coupon ) WC()->session->set( 'active_item_coupon', $coupon_for_session );
			elseif ( $is_cart_coupon ) WC()->session->set( 'active_cart_coupon', $coupon_for_session );
			else wp_send_json_error( array( 'message' => __( 'Не удалось определить тип RPG купона для активации в сессии.', 'woodmart-child' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Ошибка сессии WooCommerce.', 'woodmart-child' ) ) );
		}
		
		$message = __( 'RPG купон успешно активирован', 'woodmart-child' );
		if ( ! $save_coupon ) {
			unset( $coupons[ $index ] );
			$this->character_manager->update_coupon_inventory( $user_id, array_values( $coupons ) );
		} else {
			$message = __( 'RPG купон успешно активирован и сохранен!', 'woodmart-child' );
		}
		wp_send_json_success( array( 'message' => $message ) );
	}

	/**
	 * Обрабатывает первую стадию активации "Чутья" Эльфов.
	 * (Аналог rpg_handle_activate_elf_sense_ajax)
	 */
	public function handle_activate_elf_sense_pending() { // <--- НОВЫЙ МЕТОД
		check_ajax_referer( 'rpg_ajax_nonce', '_ajax_nonce' );
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Требуется авторизация', 'woodmart-child' ) ) );
		}

		if ( 'elf' !== $this->character_manager->get_race( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Доступно только для эльфов!', 'woodmart-child' ) ) );
		}

		$level = $this->character_manager->get_level( $user_id );
		if ( $level < 3 ) {
			wp_send_json_error( array( 'message' => __( 'Способность доступна с 3 уровня', 'woodmart-child' ) ) );
		}

		if ( ! Utils::can_activate_weekly_ability( $user_id, 'last_elf_activation' ) ) {
			wp_send_json_error( array( 'message' => __( 'Способность уже активирована на этой неделе', 'woodmart-child' ) ) );
		}

		$this->character_manager->update_meta( $user_id, 'elf_sense_pending', true );
		wp_send_json_success( array( 'message' => __( 'Выберите товары в корзине для применения "Чутья".', 'woodmart-child' ) ) );
	}

	/**
	 * Обрабатывает вторую стадию активации "Чутья" (выбор товаров).
	 * (Аналог rpg_handle_select_elf_items_ajax)
	 */
	public function handle_select_elf_items() { // <--- НОВЫЙ МЕТОД
		check_ajax_referer( 'rpg_cart_ajax_nonce', '_ajax_nonce' ); // Используем cart_nonce
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Требуется авторизация', 'woodmart-child' ) ) );
		}
		if ( 'elf' !== $this->character_manager->get_race( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Доступно только для эльфов!', 'woodmart-child' ) ) );
		}

		$level = $this->character_manager->get_level( $user_id );

		if ( ! $this->character_manager->get_meta( $user_id, 'elf_sense_pending' ) ) {
			wp_send_json_error( array( 'message' => __( 'Сначала активируйте "Чутье" на странице персонажа', 'woodmart-child' ) ) );
		}

		$product_ids = isset( $_POST['product_ids'] ) ? array_map( 'intval', (array) $_POST['product_ids'] ) : array();
		if ( empty( $product_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Не выбраны товары', 'woodmart-child' ) ) );
		}

		$sense_max_items_map = array( 3 => 1, 4 => 2, 5 => 3 );
		$max_items           = isset( $sense_max_items_map[ $level ] ) ? $sense_max_items_map[ $level ] : 0;

		if ( $max_items <= 0 || count( $product_ids ) > $max_items ) {
			wp_send_json_error( array( 'message' => sprintf( __( 'Превышен лимит товаров (максимум %d на уровне %d)', 'woodmart-child' ), $max_items, $level ) ) );
		}

		$this->character_manager->update_meta( $user_id, 'elf_items', $product_ids );
		$this->character_manager->update_meta( $user_id, 'last_elf_activation', date( 'W-Y' ) );
		$this->character_manager->delete_meta( $user_id, 'elf_sense_pending' ); // Используем delete_meta
		wp_send_json_success( array( 'message' => __( 'Товары успешно выбраны для "Чутья". Скидка будет применена в корзине.', 'woodmart-child' ) ) );
	}

	/**
	 * Обрабатывает первую стадию активации "Ярости" Орков.
	 * (Аналог rpg_handle_activate_orc_rage_ajax)
	 */
	public function handle_activate_orc_rage_pending() { // <--- НОВЫЙ МЕТОД
		check_ajax_referer( 'rpg_ajax_nonce', '_ajax_nonce' );
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Требуется авторизация', 'woodmart-child' ) ) );
		}
		if ( 'orc' !== $this->character_manager->get_race( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Доступно только для орков!', 'woodmart-child' ) ) );
		}

		if ( ! Utils::can_activate_weekly_ability( $user_id, 'last_rage_activation' ) ) {
			wp_send_json_error( array( 'message' => __( 'Способность уже активирована на этой неделе', 'woodmart-child' ) ) );
		}

		$this->character_manager->update_meta( $user_id, 'rage_pending', true );
		wp_send_json_success( array( 'message' => __( 'Выберите товар в корзине для применения "Ярости".', 'woodmart-child' ) ) );
	}

	/**
	 * Обрабатывает вторую стадию активации "Ярости" (выбор товара).
	 * (Аналог rpg_handle_select_rage_product_ajax)
	 */
	public function handle_select_orc_rage_product() { // <--- НОВЫЙ МЕТОД
		check_ajax_referer( 'rpg_cart_ajax_nonce', '_ajax_nonce' ); // Используем cart_nonce
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Требуется авторизация', 'woodmart-child' ) ) );
		}
		if ( 'orc' !== $this->character_manager->get_race( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Доступно только для орков!', 'woodmart-child' ) ) );
		}

		if ( ! $this->character_manager->get_meta( $user_id, 'rage_pending' ) ) {
			wp_send_json_error( array( 'message' => __( 'Сначала активируйте "Ярость" на странице персонажа', 'woodmart-child' ) ) );
		}

		$product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
		if ( empty( $product_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Не выбран товар', 'woodmart-child' ) ) );
		}

		$this->character_manager->update_meta( $user_id, 'rage_product', $product_id );
		$this->character_manager->update_meta( $user_id, 'last_rage_activation', date( 'W-Y' ) );
		$this->character_manager->delete_meta( $user_id, 'rage_pending' );
		wp_send_json_success( array( 'message' => __( 'Товар успешно выбран для "Ярости". Скидка будет применена в корзине.', 'woodmart-child' ) ) );
	}
    
    /**
	 * Обрабатывает деактивацию активного купона (RPG или Dokan).
	 * (Аналог rpg_handle_deactivate_coupon_ajax)
	 */
	public function handle_deactivate_coupon() { // <--- НОВЫЙ МЕТОД
		check_ajax_referer( 'rpg_ajax_nonce', '_ajax_nonce' );
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Требуется авторизация', 'woodmart-child' ) ) );
		}

		$coupon_type_to_deactivate = isset( $_POST['coupon_type'] ) ? sanitize_key( $_POST['coupon_type'] ) : '';
		$message = '';
		$success = false;

		if ( ! WC()->session ) {
			wp_send_json_error( array( 'message' => __( 'Ошибка сессии.', 'woodmart-child' ) ) );
		}

		switch ( $coupon_type_to_deactivate ) {
			case 'rpg_item':
				$active_coupon_data = WC()->session->get( 'active_item_coupon' );
				if ( $active_coupon_data ) {
					WC()->session->set( 'active_item_coupon', null );
					// Попытка вернуть в инвентарь
					if ( $this->character_manager->add_rpg_coupon_to_inventory( $user_id, $active_coupon_data ) ) {
						$message = __( 'RPG купон на товар деактивирован и возвращен в инвентарь.', 'woodmart-child' );
					} else {
						$message = __( 'RPG купон на товар деактивирован. Инвентарь полон, купон не возвращен.', 'woodmart-child' );
					}
					$success = true;
				} else {
					$message = __( 'Активный RPG купон на товар не найден.', 'woodmart-child' );
				}
				break;

			case 'rpg_cart':
				$active_coupon_data = WC()->session->get( 'active_cart_coupon' );
				if ( $active_coupon_data ) {
					WC()->session->set( 'active_cart_coupon', null );
					if ( $this->character_manager->add_rpg_coupon_to_inventory( $user_id, $active_coupon_data ) ) {
						$message = __( 'RPG купон на корзину деактивирован и возвращен в инвентарь.', 'woodmart-child' );
					} else {
						$message = __( 'RPG купон на корзину деактивирован. Инвентарь полон, купон не возвращен.', 'woodmart-child' );
					}
					$success = true;
				} else {
					$message = __( 'Активный RPG купон на корзину не найден.', 'woodmart-child' );
				}
				break;

			case 'dokan_vendor':
				if ( WC()->session->get( 'active_rpg_dokan_coupon_code' ) ) {
					WC()->session->set( 'active_rpg_dokan_coupon_code', null );
					// Купоны Dokan обычно не возвращаются в RPG инвентарь таким образом,
					// так как их управление происходит через другую систему.
					// Если нужно, можно добавить логику возврата в Dokan инвентарь (если он есть).
					$message = __( 'Купон продавца деактивирован.', 'woodmart-child' );
					$success = true;
				} else {
					$message = __( 'Активный купон продавца не найден.', 'woodmart-child' );
				}
				break;

			default:
				$message = __( 'Неверный тип купона для деактивации.', 'woodmart-child' );
				break;
		}

		if ( $success ) {
			wp_send_json_success( array( 'message' => $message ) );
		} else {
			wp_send_json_error( array( 'message' => $message ) );
		}
	}

	

	// TODO: Остальные AJAX-обработчики (админские, Dokan)
}
