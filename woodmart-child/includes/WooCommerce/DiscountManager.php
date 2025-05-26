<?php
/**
 * Менеджер скидок RPG в WooCommerce.
 *
 * @package WoodmartChildRPG\WooCommerce
 */

namespace WoodmartChildRPG\WooCommerce;

use WoodmartChildRPG\RPG\Character as RPGCharacter;
use WoodmartChildRPG\RPG\RaceFactory;
use WoodmartChildRPG\RPG\Races\Dwarf;
use WoodmartChildRPG\RPG\Races\Elf;
use WoodmartChildRPG\RPG\Races\Orc;
use WoodmartChildRPG\Dokan\DokanUserCouponDB;
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Запрещаем прямой доступ.
}

class DiscountManager {

	private $character_manager;

	public function __construct( RPGCharacter $character_manager ) {
		$this->character_manager = $character_manager;
	}

	/**
	 * Главный метод применения всех RPG скидок к корзине.
	 * Вызывается хуком 'woocommerce_cart_calculate_fees'.
	 *
	 * @param \WC_Cart $cart Объект корзины WooCommerce.
	 */
	public function apply_rpg_cart_discounts( \WC_Cart $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		if ( $cart->is_empty() ) {
			return;
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return; // Только для авторизованных пользователей
		}

		// Получаем данные пользователя один раз
		$user_race_slug = $this->character_manager->get_race( $user_id );
		$user_level     = $this->character_manager->get_level( $user_id );
		$race_object    = RaceFactory::create_race( $user_race_slug );

		// Применяем скидки в определенном порядке, как было в discount-logic.php
		// Сумма для расчета скидки будет обновляться после каждого этапа

		// Этап 1: Общая скидка за уровень (кроме дварфов) + бонусы Эльфов и Орков
		$this->apply_base_race_and_level_discounts( $cart, $user_id, $user_race_slug, $user_level, $race_object );

		// Этап 2: Скидка за уровень Дварфов
		$this->apply_dwarf_level_discount( $cart, $user_id, $user_race_slug, $user_level, $race_object );

		// Этап 3: Скидки по активированным RPG купонам (из инвентаря)
		$this->apply_active_rpg_coupons( $cart, $user_id );

		// Этап 4: Скидки по активированным купонам ПРОДАВЦОВ (Dokan)
		$this->apply_active_dokan_coupons( $cart, $user_id );
	}

	/**
	 * Применяет общую скидку за уровень (кроме дварфов) и бонусы рас Эльфов/Орков.
	 * (Аналог rpg_apply_base_race_discounts)
	 */
	private function apply_base_race_and_level_discounts( \WC_Cart $cart, $user_id, $race_slug, $level, $race_object ) {
		// 1. Бонусы конкретной расы (Эльфы, Орки)
		if ( $race_object instanceof Elf ) { // <--- ИЗМЕНЕНО
			$race_object->apply_passive_cart_discount( $cart, $user_id );
		} elseif ( $race_object instanceof Orc ) { // <--- ИЗМЕНЕНО
			$race_object->apply_passive_cart_discount( $cart, $user_id );
		}

		// 2. Общая скидка за уровень (для всех, кроме Дварфов)
		if ( 'dwarf' !== $race_slug ) {
			// ... (остальная логика общей скидки за уровень без изменений) ...
			$discount_percent = min( $level, 5 ); 
			if ( $discount_percent > 0 ) {
				$subtotal_for_level_discount = 0;
				foreach ( $cart->get_cart() as $cart_item ) {
					$_product = $cart_item['data'];
					if ( $_product && $_product->exists() && $cart_item['quantity'] > 0 ) {
						$subtotal_for_level_discount += (float) $_product->get_price() * $cart_item['quantity'];
					}
				}

				if ( $subtotal_for_level_discount > 0 ) {
					$discount_amount = ( $subtotal_for_level_discount * $discount_percent ) / 100;
					if ( $discount_amount > 0 ) {
						$cart->add_fee( __( 'Общая скидка за уровень', 'woodmart-child' ) . ' (' . $discount_percent . '%)', - $discount_amount );
					}
				}
			}
		}
	}
	
	/**
	 * Применяет скидку за уровень для Дварфов.
	 * (Аналог rpg_apply_dwarf_level_discount)
	 */
	private function apply_dwarf_level_discount( \WC_Cart $cart, $user_id, $race_slug, $level, $race_object ) {
		if ( 'dwarf' !== $race_slug || ! ( $race_object instanceof Dwarf ) ) {
			return;
		}

		$discount_percentage = $race_object->get_level_based_discount_percentage( $level );

		if ( $discount_percentage > 0 ) {
			// Считаем от промежуточной суммы корзины (после скидок предыдущего этапа)
			$subtotal_after_base_fees = (float) $cart->get_cart_contents_total() + (float) $cart->get_fee_total();

			if ( $subtotal_after_base_fees > 0 ) {
				$discount_amount = ( $subtotal_after_base_fees * $discount_percentage ) / 100;
				if ( $discount_amount > 0 ) {
					$cart->add_fee( __( 'Скидка Дварфа за уровень', 'woodmart-child' ) . ' (' . $discount_percentage . '%)', - $discount_amount );
				}
			}
		}
	}

	/**
	 * Применяет скидки по активным RPG купонам из сессии.
	 * (Аналог rpg_apply_rpg_coupon_discounts)
	 */
	private function apply_active_rpg_coupons( \WC_Cart $cart, $user_id ) {
		if ( ! WC()->session ) {
			return;
		}

		$active_item_coupon = WC()->session->get( 'active_item_coupon' ); // Ожидается массив ['type' => 'item_percent', 'value' => 25]
		$active_cart_coupon = WC()->session->get( 'active_cart_coupon' ); // Ожидается массив ['type' => 'cart_percent', 'value' => 10]

		if ( ! $active_item_coupon && ! $active_cart_coupon ) {
			return;
		}

		$subtotal_after_level_fees = (float) $cart->get_cart_contents_total() + (float) $cart->get_fee_total();

		// RPG Купон на товар (скидка на первый товар в корзине)
		if ( $active_item_coupon && is_array( $active_item_coupon ) && isset( $active_item_coupon['value'] ) && $subtotal_after_level_fees > 0 ) {
			$cart_items = $cart->get_cart();
			if ( ! empty( $cart_items ) ) {
				$first_item_key = array_key_first( $cart_items );
				$first_item     = $cart_items[ $first_item_key ];

				if ( $first_item && isset( $first_item['data'] ) ) {
					$_product   = $first_item['data'];
					$item_price = (float) $_product->get_price();
					$coupon_value = (float) $active_item_coupon['value'];

					$discount_amount_for_one_item = ( $item_price * $coupon_value ) / 100;

					if ( $discount_amount_for_one_item > 0 ) {
						$discount_amount_for_one_item = min( $discount_amount_for_one_item, $item_price ); // Не больше цены товара
						$cart->add_fee( __( 'Скидка по RPG купону на товар', 'woodmart-child' ) . ' (' . $coupon_value . '%)', - $discount_amount_for_one_item );
						WC()->session->set( 'active_item_coupon', null );
						$subtotal_after_level_fees -= $discount_amount_for_one_item; // Обновляем для следующего купона
					}
				}
			}
		}

		// RPG Купон на корзину
		if ( $active_cart_coupon && is_array( $active_cart_coupon ) && isset( $active_cart_coupon['value'] ) && $subtotal_after_level_fees > 0 ) {
			$coupon_value = (float) $active_cart_coupon['value'];
			$discount_amount = ( $subtotal_after_level_fees * $coupon_value ) / 100;

			if ( $discount_amount > 0 ) {
				$discount_amount = min( $discount_amount, $subtotal_after_level_fees ); // Не больше оставшейся суммы
				$cart->add_fee( __( 'Скидка по RPG купону на корзину', 'woodmart-child' ) . ' (' . $coupon_value . '%)', - $discount_amount );
				WC()->session->set( 'active_cart_coupon', null );
			}
		}
	}

	/**
	 * Применяет скидку по активному купону ПРОДАВЦА (Dokan) из сессии.
	 */
	private function apply_active_dokan_coupons( \WC_Cart $cart, $user_id ) {
		if ( ! WC()->session ) {
			return;
		}

		$active_dokan_coupon_details = WC()->session->get( 'active_rpg_dokan_coupon_details' );

		if ( empty( $active_dokan_coupon_details ) || ! isset( $active_dokan_coupon_details['id'], $active_dokan_coupon_details['code'] ) ) {
			return;
		}

		$coupon_id_from_session     = (int) $active_dokan_coupon_details['id'];
		$expected_code_from_session = $active_dokan_coupon_details['code'];

		$wc_coupon = new \WC_Coupon( $coupon_id_from_session );

		// Проверка актуальности и валидности купона
		if ( ! $wc_coupon->get_id() || $wc_coupon->get_code() !== $expected_code_from_session || ! $wc_coupon->is_valid() ) {
			WC()->session->set( 'active_rpg_dokan_coupon_details', null ); // Очищаем невалидный купон из сессии
			
			// Опционально: удалить из инвентаря пользователя, если он стал невалидным
			 //$dokan_coupon_db = new DokanUserCouponDB(); // Потребуется экземпляр
			 //$dokan_coupon_db->remove_coupon_from_inventory($user_id, $coupon_id_from_session);
			 wc_add_notice( __( 'Активированный купон продавца больше не действителен и был удален.', 'woodmart-child' ), 'error' );
			return;
		}

		// Проверяем, не применен ли уже этот купон стандартным механизмом WooCommerce
		// (на случай, если пользователь ввел его вручную после нашей RPG активации)
		if ( $cart->has_discount( $wc_coupon->get_code() ) ) {
			WC()->session->set( 'active_rpg_dokan_coupon_details', null ); // Уже применен, очищаем нашу сессию
			return;
		}

		// Попытка применить купон Dokan через стандартный механизм WooCommerce
		$applied_successfully = $cart->apply_coupon( $wc_coupon->get_code() );

		if ( $applied_successfully ) {
			// WooCommerce сам добавит уведомление.
			// После успешного применения, он будет в $cart->get_applied_coupons().
		} else {
			// Если WC()->cart->apply_coupon() вернул false, WooCommerce должен был добавить свое уведомление.
			// wc_add_notice( sprintf( __( 'Не удалось применить купон продавца "%s" из RPG инвентаря.', 'woodmart-child' ), esc_html( $wc_coupon->get_code() ) ), 'error' );
		}
		// Сбрасываем из нашей кастомной сессии в любом случае после попытки применения
		WC()->session->set( 'active_rpg_dokan_coupon_details', null );
	}
}

		