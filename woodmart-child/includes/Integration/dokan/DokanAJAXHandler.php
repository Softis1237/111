<?php
/**
 * Обработчик AJAX-запросов для интеграции с Dokan.
 *
 * @package WoodmartChildRPG\Integration\Dokan
 */

namespace WoodmartChildRPG\Integration\Dokan;

use WoodmartChildRPG\RPG\Character as RPGCharacter; // Не используется напрямую здесь, но может понадобиться для других проверок
use WoodmartChildRPG\Integration\Dokan\DokanUserCouponDB; // <--- ДОБАВИТЬ

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Запрещаем прямой доступ.
}

class DokanAJAXHandler {

	private $character_manager; // Пока не используется, но оставим для единообразия
	private $dokan_coupon_db;   // <--- ДОБАВИТЬ

	/**
	 * Конструктор.
	 * @param RPGCharacter      $character_manager Экземпляр Character manager.
	 * @param DokanUserCouponDB $dokan_coupon_db   Экземпляр DokanUserCouponDB.
	 */
	public function __construct( RPGCharacter $character_manager, DokanUserCouponDB $dokan_coupon_db ) { // <--- ИЗМЕНИТЬ
		$this->character_manager = $character_manager;
		$this->dokan_coupon_db   = $dokan_coupon_db; // <--- ДОБАВИТЬ
	}

	// Удаляем приватный метод-заглушку add_dokan_coupon_to_user_inventory

	public function handle_take_dokan_coupon() {
		check_ajax_referer( 'rpg_ajax_nonce', '_ajax_nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Пожалуйста, войдите в систему, чтобы взять купон.', 'woodmart-child' ) ) );
		}
		$user_id = get_current_user_id();

		$dokan_coupon_id = isset( $_POST['coupon_id'] ) ? intval( $_POST['coupon_id'] ) : 0;
		if ( ! $dokan_coupon_id ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Неверный ID купона.', 'woodmart-child' ) ) );
		}

		$coupon_obj = new \WC_Coupon( $dokan_coupon_id );
		if ( ! $coupon_obj->get_id() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Не удалось получить данные купона.', 'woodmart-child' ) ) );
		}
		$current_coupon_code = $coupon_obj->get_code();
		$vendor_id           = $coupon_obj->get_meta( 'dokan_coupon_author', true ); // Получаем ID автора купона Dokan

		// Используем метод из DokanUserCouponDB
		$result = $this->dokan_coupon_db->add_coupon_to_inventory( $user_id, $dokan_coupon_id, (int) $vendor_id, $current_coupon_code );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		} else {
			wp_send_json_success( array( 'message' => esc_html__( 'Купон продавца успешно добавлен в ваш инвентарь!', 'woodmart-child' ) ) );
		}
	}

	public function handle_add_dokan_coupon_by_code() {
		check_ajax_referer( 'rpg_ajax_nonce', '_ajax_nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Пожалуйста, войдите в систему.', 'woodmart-child' ) ) );
		}
		$user_id = get_current_user_id();

		$coupon_code_entered = isset( $_POST['coupon_code'] ) ? sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) : '';
		if ( empty( $coupon_code_entered ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Пожалуйста, введите код купона.', 'woodmart-child' ) ) );
		}

		$coupon_id_by_code = wc_get_coupon_id_by_code( $coupon_code_entered );
		$coupon_obj        = null;
		if ( $coupon_id_by_code && 'shop_coupon' === get_post_type( $coupon_id_by_code ) ) {
			$coupon_obj = new \WC_Coupon( $coupon_id_by_code );
		}

		if ( ! $coupon_obj || ! $coupon_obj->get_id() ) {
			wp_send_json_error( array( 'message' => sprintf( esc_html__( 'Купон с кодом "%s" не найден или недействителен.', 'woodmart-child' ), esc_html( $coupon_code_entered ) ) ) );
		}
		$vendor_id = $coupon_obj->get_meta( 'dokan_coupon_author', true );

		$result = $this->dokan_coupon_db->add_coupon_to_inventory( $user_id, $coupon_obj->get_id(), (int) $vendor_id, $coupon_obj->get_code() );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		} else {
			wp_send_json_success( array( 'message' => sprintf( esc_html__( 'Купон продавца "%s" успешно добавлен в ваш инвентарь!', 'woodmart-child' ), esc_html( $coupon_code_entered ) ) ) );
		}
	}

	public function handle_activate_dokan_coupon_from_inventory() {
		check_ajax_referer( 'rpg_ajax_nonce', '_ajax_nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Пожалуйста, войдите в систему.', 'woodmart-child' ) ) );
		}
		$user_id = get_current_user_id();

		$coupon_id_to_activate = isset( $_POST['dokan_coupon_id_to_activate'] ) ? intval( $_POST['dokan_coupon_id_to_activate'] ) : 0;
		if ( $coupon_id_to_activate <= 0 ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Неверный ID купона продавца.', 'woodmart-child' ) ) );
		}

		if ( ! $this->dokan_coupon_db->user_has_coupon( $user_id, $coupon_id_to_activate ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Купон продавца не найден в вашем инвентаре.', 'woodmart-child' ) ) );
		}

		$wc_coupon = new \WC_Coupon( $coupon_id_to_activate );
		if ( ! $wc_coupon->get_id() ) {
			$this->dokan_coupon_db->remove_coupon_from_inventory( $user_id, $coupon_id_to_activate );
			wp_send_json_error( array( 'message' => esc_html__( 'Этот купон больше не действителен и был удален из вашего инвентаря.', 'woodmart-child' ) ) );
		}

		// Дополнительная проверка: совпадает ли текущий код купона с сохраненным оригинальным кодом
		// Это важно, если продавец изменил код существующего купона (с тем же ID поста)
		$stored_coupon_data = $this->dokan_coupon_db->get_specific_user_coupon_data( $user_id, $coupon_id_to_activate ); // Предполагаем, что такой метод есть
		if ( $stored_coupon_data && isset( $stored_coupon_data->original_code ) && $wc_coupon->get_code() !== $stored_coupon_data->original_code ) {
			$this->dokan_coupon_db->remove_coupon_from_inventory( $user_id, $coupon_id_to_activate );
			wp_send_json_error( array( 'message' => esc_html__( 'Код этого купона был изменен продавцом. Купон удален из вашего инвентаря.', 'woodmart-child' ) ) );
		}


		try {
			$is_valid = $wc_coupon->is_valid();
			if (!$is_valid) {
				$error_code = 'invalid_coupon';
				$remove_on_error = ['coupon_is_expired', 'coupon_usage_limit_reached', 'coupon_not_found'];
				$error_message_text = __('This coupon is no longer valid.', 'woodmart-child');
			}
		} catch (\Exception $e) {
			$error_code = $e->getCode();
			$remove_on_error = ['coupon_is_expired', 'coupon_usage_limit_reached', 'coupon_not_found'];
			$error_message_text = $e->getMessage();

			if ( in_array( $error_code, $remove_on_error, true ) ) {
				$this->dokan_coupon_db->remove_coupon_from_inventory( $user_id, $coupon_id_to_activate );
				$error_message_text .= ' ' . esc_html__( 'Купон был удален из инвентаря.', 'woodmart-child' );
			}
			wp_send_json_error( array( 'message' => $error_message_text ) );
		}

		if ( ! WC()->session ) {
			wp_send_json_error( array( 'message' => __( 'Ошибка сессии.', 'woodmart-child' ) ) );
		}
		if ( WC()->session->get( 'active_rpg_dokan_coupon_details' ) ) { // Проверяем новое имя ключа сессии
			wp_send_json_error( array( 'message' => esc_html__( 'У вас уже активирован другой купон продавца.', 'woodmart-child' ) ) );
		}
		if ( WC()->session->get( 'active_item_coupon' ) || WC()->session->get( 'active_cart_coupon' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'У вас уже активирован RPG купон. Нельзя активировать купон продавца одновременно.', 'woodmart-child' ) ) );
		}

		// Сохраняем ID и актуальный код в сессию
		WC()->session->set(
			'active_rpg_dokan_coupon_details',
			array(
				'id'   => $wc_coupon->get_id(),
				'code' => $wc_coupon->get_code(),
			)
		);

		wp_send_json_success(
			array(
				'message'     => sprintf( esc_html__( 'Купон продавца "%s" активирован и будет применен в корзине.', 'woodmart-child' ), esc_html( $wc_coupon->get_code() ) ),
				'coupon_code' => $wc_coupon->get_code(),
			)
		);
	}

	public function handle_refresh_dokan_coupons_status() {
		check_ajax_referer( 'rpg_ajax_nonce', '_ajax_nonce' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Пожалуйста, войдите в систему.', 'woodmart-child' ) ) );
		}
		$user_id = get_current_user_id();

		$user_watched_coupons = $this->dokan_coupon_db->get_all_user_coupons_for_status_check( $user_id );
		if ( empty( $user_watched_coupons ) ) {
			wp_send_json_success(
				array(
					'message'     => esc_html__( 'Ваш инвентарь купонов продавцов пуст.', 'woodmart-child' ),
					'reload_page' => false,
				)
			);
		}

		$removed_count = 0;
		foreach ( $user_watched_coupons as $watched_coupon_entry ) {
			$coupon_id     = intval( $watched_coupon_entry->coupon_id );
			$original_code = isset( $watched_coupon_entry->original_code ) ? $watched_coupon_entry->original_code : null;
			$wc_coupon     = new \WC_Coupon( $coupon_id );

			$should_remove = false;
			if ( ! $wc_coupon->get_id() ) {
				$should_remove = true;
			} elseif ( $original_code !== null && $wc_coupon->get_code() !== $original_code ) {
				$should_remove = true;
			} elseif ( $wc_coupon->get_date_expires() && $wc_coupon->get_date_expires()->getTimestamp() < current_time( 'timestamp', true ) ) {
				$should_remove = true;
			} elseif ( $wc_coupon->get_usage_limit() > 0 && $wc_coupon->get_usage_count() >= $wc_coupon->get_usage_limit() ) {
				$should_remove = true;
			}

			if ( $should_remove ) {
				if ( $this->dokan_coupon_db->remove_coupon_from_inventory( $user_id, $coupon_id ) ) {
					$removed_count++;
				}
			}
		}

		if ( $removed_count > 0 ) {
			wp_send_json_success(
				array(
					'message'     => sprintf( esc_html__( 'Статус купонов продавцов обновлен. Удалено недействительных или измененных купонов: %d.', 'woodmart-child' ), $removed_count ),
					'reload_page' => true,
				)
			);
		} else {
			wp_send_json_success(
				array(
					'message'     => esc_html__( 'Все купоны продавцов в вашем инвентаре актуальны.', 'woodmart-child' ),
					'reload_page' => false,
				)
			);
		}
	}
}