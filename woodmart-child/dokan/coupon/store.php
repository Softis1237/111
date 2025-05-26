<?php
/**
 * Dokan Store Coupons Template (Overridden)
 * Uses DokanIntegrationManager to render coupons.
 */
defined( 'ABSPATH' ) || exit;

// $coupon (WP_Post object) и $vendor_id передаются этим шаблоном из Dokan.

if ( ! is_a( $coupon, 'WP_Post' ) || 'shop_coupon' !== $coupon->post_type ) {
	// error_log( 'WoodmartChildRPG Dokan Template: Invalid $coupon object or post type.' );
	return;
}

$wc_coupon = new \WC_Coupon( $coupon->ID );
if ( ! $wc_coupon->get_id() ) {
	// error_log( 'WoodmartChildRPG Dokan Template: Could not instantiate WC_Coupon for ID: ' . $coupon->ID );
	return;
}

// Получаем экземпляр DokanIntegrationManager через наш главный класс темы
// Это предполагает, что Theme::get_instance()->get_dokan_integration_manager() будет добавлен
$theme_instance = null;
if ( class_exists( 'WoodmartChildRPG\Core\Theme' ) ) {
	$theme_instance = WoodmartChildRPG\Core\Theme::get_instance();
}

if ( $theme_instance && method_exists( $theme_instance, 'get_dokan_integration_manager' ) ) {
	$dokan_integration_manager = $theme_instance->get_dokan_integration_manager();
	if ( $dokan_integration_manager instanceof \WoodmartChildRPG\Integration\Dokan\DokanIntegrationManager ) {
		// Генерируем HTML с помощью нового менеджера
		$coupon_html = $dokan_integration_manager->get_single_dokan_coupon_html( $wc_coupon );
		
		// Вывод HTML (Dokan обычно выводит купоны в цикле)
		// Статическая переменная для обертки сетки, как в вашем старом шаблоне
		static $is_first_dokan_coupon_in_loop = true;
		if ( $is_first_dokan_coupon_in_loop ) {
			// Класс store-coupons-grid для стилизации из style.css
			echo '<div class="store-coupons-grid dokan-store-coupon-wrap">'; // Открываем обертку
			$is_first_dokan_coupon_in_loop = false;
		}
		
		echo $coupon_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		
		// Закрытие обертки должно происходить после цикла в файле, который вызывает этот шаблон
		// Например, если это последний купон в цикле. Dokan обычно сам управляет этим.
		// Если нет, то нужно будет найти способ закрыть div.
		// Для примера, если это виджет, он может иметь свой собственный хук "после виджета".

	} else {
		// error_log( 'WoodmartChildRPG Dokan Template: Could not get DokanIntegrationManager instance.' );
		// Можно вывести стандартный HTML купона Dokan как fallback, если наш менеджер недоступен
		// Например, вызвать стандартный шаблон Dokan:
		// include DOKAN_INC_DIR . '/coupons/store-coupon-content.php';
	}
} else {
	// error_log( 'WoodmartChildRPG Dokan Template: Theme instance or get_dokan_integration_manager method not found.' );
}

// Если это последний купон в цикле, и мы открывали div, его нужно закрыть.
// Это сложно определить изнутри этого шаблонного фрагмента.
// Dokan должен сам управлять оберткой списка купонов.
// Если вы видите незакрытый div, ищите, где Dokan начинает список купонов.
// Например, в файле dokan/templates/store/coupons.php или в виджете купонов.
// Обычно, если это последний элемент в цикле, можно использовать $wp_query->current_post + 1 === $wp_query->post_count
// но $wp_query здесь может быть не тем, что нужно.
// Для безопасности, пока не будем закрывать div здесь.
?>
