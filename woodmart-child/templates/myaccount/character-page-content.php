<?php
/**
 * Шаблон контента страницы "Персонаж"
 * Файл: woodmart-child/templates/myaccount/character-page-content.php
 * Использует переменные, переданные из CharacterPage::render_page_content()
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Эти переменные УЖЕ ДОЛЖНЫ БЫТЬ ДОСТУПНЫ здесь благодаря extract() в CharacterPage.php:
// $user_id, $user_race_slug, $user_level, $total_spent, $experience_points, $user_gold,
// $user_race_name, $race_bonuses_description, $max_level, $xp_for_next_level, $progress_percent,
// $rpg_coupons, $active_rpg_item_coupon, $active_rpg_cart_coupon,
// $dokan_coupons_per_page, $current_dokan_page, $filter_vendor_id,
// $dokan_coupon_entries, $total_dokan_coupons, $active_dokan_vendor_coupon_code,
// $elf_sense_pending, $can_activate_elf_sense, $elf_sense_max_items,
// $rage_pending, $can_activate_orc_rage

?>
<div class="woocommerce-MyAccount-content rpg-character-page">
	<h2><?php esc_html_e( 'Персонаж', 'woodmart-child' ); ?></h2>
	<div class="rpg-message-box глобальное-сообщение" style="display: none; margin-bottom: 20px;"></div>

	<?php if ( $active_rpg_item_coupon || $active_rpg_cart_coupon || $active_dokan_vendor_coupon_code ) : ?>
		<div class="rpg-section active-coupons-section">
			<h3><?php esc_html_e( 'Активные купоны', 'woodmart-child' ); ?></h3>
			<?php if ( $active_rpg_item_coupon && is_array( $active_rpg_item_coupon ) && isset( $active_rpg_item_coupon['type'], $active_rpg_item_coupon['value'] ) ) : ?>
				<div class="active-coupon-notice rpg-active-notice">
					<i class="<?php echo esc_attr( \WoodmartChildRPG\Core\Utils::get_coupon_icon_class( $active_rpg_item_coupon['type'] ) ); ?> coupon-icon"></i>
					<?php printf( esc_html__( 'Активен RPG купон на товар: %s%% (%s)', 'woodmart-child' ), '<strong>' . esc_html( $active_rpg_item_coupon['value'] ) . '</strong>', esc_html( $active_rpg_item_coupon['type'] ) ); ?>
					<button type="button" class="button rpg-deactivate-coupon-btn" data-coupon-type="rpg_item"><?php esc_html_e( 'Вернуть в инвентарь', 'woodmart-child' ); ?></button>
				</div>
			<?php endif; ?>
			<?php if ( $active_rpg_cart_coupon && is_array( $active_rpg_cart_coupon ) && isset( $active_rpg_cart_coupon['type'], $active_rpg_cart_coupon['value'] ) ) : ?>
				<div class="active-coupon-notice rpg-active-notice">
					<i class="<?php echo esc_attr( \WoodmartChildRPG\Core\Utils::get_coupon_icon_class( $active_rpg_cart_coupon['type'] ) ); ?> coupon-icon"></i>
					<?php printf( esc_html__( 'Активен RPG купон на корзину: %s%% (%s)', 'woodmart-child' ), '<strong>' . esc_html( $active_rpg_cart_coupon['value'] ) . '</strong>', esc_html( $active_rpg_cart_coupon['type'] ) ); ?>
					<button type="button" class="button rpg-deactivate-coupon-btn" data-coupon-type="rpg_cart"><?php esc_html_e( 'Вернуть в инвентарь', 'woodmart-child' ); ?></button>
				</div>
			<?php endif; ?>
			<?php
			if ( $active_dokan_vendor_coupon_code ) :
				$active_dokan_coupon_obj = new \WC_Coupon( $active_dokan_vendor_coupon_code );
				if ( $active_dokan_coupon_obj->get_id() ) :
					?>
				<div class="active-coupon-notice dokan-active-notice">
					<i class="<?php echo esc_attr( \WoodmartChildRPG\Core\Utils::get_coupon_icon_class( $active_dokan_coupon_obj->get_discount_type() ) ); ?> coupon-icon"></i>
					<?php
						$value_label_active = $active_dokan_coupon_obj->get_discount_type() === 'percent'
							? $active_dokan_coupon_obj->get_amount() . '%'
							: wc_price( $active_dokan_coupon_obj->get_amount(), array( 'decimals' => 0 ) );
						printf(
							esc_html__( 'Активен купон продавца (%1$s): %2$s', 'woodmart-child' ),
							esc_html( $active_dokan_vendor_coupon_code ),
							'<strong>' . $value_label_active . '</strong>'
						);
						?>
					<button type="button" class="button rpg-deactivate-coupon-btn" data-coupon-type="dokan_vendor"><?php esc_html_e( 'Вернуть в инвентарь', 'woodmart-child' ); ?></button>
				</div>
					<?php
				else :
					// Если купон больше не существует, сессия должна была быть очищена AJAXHandler-ом или при проверке в DiscountManager
					// WC()->session->set('active_rpg_dokan_coupon_code', null); // Эту логику лучше держать в PHP классах
				endif;
			endif;
			?>
		</div>
	<?php endif; ?>

	<div class="rpg-section rpg-info">
		<h3><?php esc_html_e( 'Основная информация', 'woodmart-child' ); ?></h3>
		<p>
			<i class="<?php echo esc_attr( \WoodmartChildRPG\Core\Utils::get_race_icon_class( $user_race_slug ) ); ?>"></i>
			<strong><?php esc_html_e( 'Раса:', 'woodmart-child' ); ?></strong> <?php echo esc_html( $user_race_name ); ?>
		</p>
		<p><strong><?php esc_html_e( 'Уровень:', 'woodmart-child' ); ?></strong> <?php echo esc_html( $user_level ); ?></p>
		<p>
			<strong><?php esc_html_e( 'Опыт:', 'woodmart-child' ); ?></strong> <?php echo esc_html( number_format_i18n( $experience_points, 0 ) ); ?> / <?php echo ( $user_level < $max_level ) ? esc_html( number_format_i18n( $xp_for_next_level, 0 ) ) : esc_html__( 'Максимум', 'woodmart-child' ); ?>
			<br>
			<strong><?php esc_html_e( 'Прогресс до следующего уровня:', 'woodmart-child' ); ?></strong> <?php echo round( $progress_percent, 2 ); ?>%
			<?php if ( $user_level < $max_level && $progress_percent >= 0 ) : ?>
			<progress value="<?php echo esc_attr( round( $progress_percent, 2 ) ); ?>" max="100" title="<?php echo esc_attr( round( $progress_percent, 2 ) ); ?>%"></progress>
			<?php endif; ?>
		</p>
		<p><strong><?php esc_html_e( 'Всего потрачено:', 'woodmart-child' ); ?></strong> <?php echo wc_price( $total_spent ); ?></p>
		<?php if (isset($user_gold)) : ?>
		<p><strong><?php esc_html_e( 'Золото:', 'woodmart-child' ); ?></strong> <?php echo esc_html( number_format_i18n( $user_gold, 0 ) ); ?></p>
		<?php endif; ?>
		<p><strong><?php esc_html_e( 'Бонусы расы:', 'woodmart-child' ); ?></strong> <?php echo wp_kses_post( $race_bonuses_description ); ?></p>
	</div>

	<div class="rpg-section rpg-abilities">
		<h3><?php esc_html_e( 'Способности', 'woodmart-child' ); ?></h3>
		<?php if ( ( 'elf' !== $user_race_slug || $user_level < 3 ) && 'orc' !== $user_race_slug ) : ?>
			<p><?php esc_html_e( 'У вашей расы нет активных способностей или они доступны на более высоких уровнях.', 'woodmart-child' ); ?></p>
		<?php else : ?>
			<?php if ( 'elf' === $user_race_slug && $user_level >= 3 ) : ?>
				<div class="ability-block">
					<h4><?php esc_html_e( 'Способность "Чутье"', 'woodmart-child' ); ?></h4>
					<?php if ( $elf_sense_pending ) : ?>
						<p><i><?php esc_html_e( 'Статус: Ожидает выбора товаров в корзине. Перейдите в корзину для выбора.', 'woodmart-child' ); ?></i></p>
					<?php else : ?>
						<p><?php printf( esc_html__( 'Активируйте, чтобы выбрать до %d товар(а/ов) в корзине и получить на них эльфийскую скидку. Действует раз в неделю.', 'woodmart-child' ), esc_html( $elf_sense_max_items ) ); ?></p>
						<button type="button" id="activate-elf-sense" class="button" <?php disabled( ! $can_activate_elf_sense ); ?>>
							<?php echo $can_activate_elf_sense ? esc_html__( 'Активировать "Чутье"', 'woodmart-child' ) : esc_html__( 'Уже активировано на этой неделе', 'woodmart-child' ); ?>
						</button>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<?php if ( 'orc' === $user_race_slug ) : ?>
				<div class="ability-block">
					<h4><?php esc_html_e( 'Способность "Ярость"', 'woodmart-child' ); ?></h4>
					<?php if ( $rage_pending ) : ?>
						<p><i><?php esc_html_e( 'Статус: Ожидает выбора товара в корзине. Перейдите в корзину для выбора.', 'woodmart-child' ); ?></i></p>
					<?php else : ?>
						<p><?php esc_html_e( 'Активируйте, чтобы выбрать 1 товар в корзине и получить на него максимальную скидку орка. Действует раз в неделю.', 'woodmart-child' ); ?></p>
						<button type="button" id="activate-orc-rage" class="button" <?php disabled( ! $can_activate_orc_rage ); ?>>
							<?php echo $can_activate_orc_rage ? esc_html__( 'Активировать "Ярость"', 'woodmart-child' ) : esc_html__( 'Уже активировано на этой неделе', 'woodmart-child' ); ?>
						</button>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>

	<div class="rpg-section rpg-coupons rpg-inventory-магазина">
		<h3><?php esc_html_e( 'Инвентарь Магазина (RPG Купоны)', 'woodmart-child' ); ?></h3>
		<div class="rpg-message-box rpg-coupons-msg" style="display: none;"></div>
		<?php if ( ! empty( $rpg_coupons ) ) : ?>
			<div class="coupon-inventory-grid">
				<?php foreach ( $rpg_coupons as $index => $coupon_data_rpg ) : ?>
					<?php
						$type_rpg        = $coupon_data_rpg['type'] ?? 'common';
						$value_rpg       = $coupon_data_rpg['value'] ?? 0;
						$description_rpg = $coupon_data_rpg['description'] ?? '';
						$icon_class_rpg  = \WoodmartChildRPG\Core\Utils::get_coupon_icon_class( $type_rpg );
						
						$is_rpg_item_type = in_array( $type_rpg, array( 'daily', 'exclusive_item', 'common' ), true );
						$is_rpg_cart_type = in_array( $type_rpg, array( 'weekly', 'exclusive_cart' ), true );
						
						$can_activate_this_rpg_coupon = false;
						if ( $is_rpg_item_type && ! $active_rpg_item_coupon && ! $active_dokan_vendor_coupon_code ) {
							$can_activate_this_rpg_coupon = true;
						} elseif ( $is_rpg_cart_type && ! $active_rpg_cart_coupon && ! $active_dokan_vendor_coupon_code ) {
							$can_activate_this_rpg_coupon = true;
						}
						
						$button_text_rpg          = __( 'Активировать', 'woodmart-child' );
						$button_disabled_reason_rpg = '';
						if ( $active_dokan_vendor_coupon_code ) {
							$button_disabled_reason_rpg = __( 'Купон продавца активен', 'woodmart-child' );
						} elseif ( $is_rpg_item_type && $active_rpg_item_coupon ) {
							$button_disabled_reason_rpg = __( 'RPG купон на товар уже активен', 'woodmart-child' );
						} elseif ( $is_rpg_cart_type && $active_rpg_cart_coupon ) {
							$button_disabled_reason_rpg = __( 'RPG купон на корзину уже активен', 'woodmart-child' );
						}
						if ( ! $can_activate_this_rpg_coupon && ! empty( $button_disabled_reason_rpg ) ) {
							$button_text_rpg = $button_disabled_reason_rpg;
						}
						
						$type_label_rpg = $description_rpg ?: ucfirst( $type_rpg );
						switch ( strtolower( $type_rpg ) ) {
							case 'daily': $type_label_rpg = $description_rpg ?: __( 'Ежедневный', 'woodmart-child' ); break;
							case 'weekly': $type_label_rpg = $description_rpg ?: __( 'Еженедельный', 'woodmart-child' ); break;
							case 'exclusive_item': $type_label_rpg = $description_rpg ?: __( 'Эксклюзивный (на товар)', 'woodmart-child' ); break;
							case 'exclusive_cart': $type_label_rpg = $description_rpg ?: __( 'Эксклюзивный (на корзину)', 'woodmart-child' ); break;
							case 'common': $type_label_rpg = $description_rpg ?: __( 'Общий', 'woodmart-child' ); break;
						}
						$value_label_rpg = $value_rpg . '%';
					?>
					<div class="coupon-item rpg-coupon-item">
						<i class="<?php echo esc_attr( $icon_class_rpg ); ?> coupon-icon" title="<?php echo esc_attr( $type_rpg ); ?>"></i>
						<div class="coupon-text">
							<?php echo esc_html( $type_label_rpg ); ?><br>
							<strong><?php echo esc_html( $value_label_rpg ); ?></strong>
						</div>
						<button type="button" class="activate-rpg-coupon button" data-index="<?php echo intval( $index ); ?>" <?php disabled( ! $can_activate_this_rpg_coupon ); ?>>
							<?php echo esc_html( $button_text_rpg ); ?>
						</button>
					</div>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<p><?php esc_html_e( 'Ваш инвентарь RPG купонов пуст.', 'woodmart-child' ); ?></p>
		<?php endif; ?>
	</div>

	<div class="rpg-section rpg-vendor-coupons rpg-inventory-продавцов">
		<h3><?php esc_html_e( 'Инвентарь Купонов Продавцов', 'woodmart-child' ); ?></h3>
		<div class="rpg-message-box dokan-coupons-msg" style="display: none;"></div>
		<div class="add-dokan-coupon-by-code-form">
			<h4><?php esc_html_e( 'Добавить купон продавца по коду', 'woodmart-child' ); ?></h4>
			<input type="text" id="dokan-coupon-code-input"обработать через AJAX для добавления купона продавца по введенному коду */ ?>
			<input type="text" id="dokan-coupon-code-input" placeholder="<?php esc_attr_e( 'Введите код купона продавца', 'woodmart-child' ); ?>">
			<button type="button" id="rpg-add-dokan-coupon-by-code-btn" class="button"><?php esc_html_e( 'Добавить в инвентарь', 'woodmart-child' ); ?></button>
		</div>

		<?php if ( $total_dokan_coupons > 0 ) : ?>
			<form method="get" class="dokan-coupon-filters-form" action="<?php echo esc_url( wc_get_account_endpoint_url( 'character' ) ); ?>">
				<label for="dokan-vendor-filter"><?php esc_html_e( 'Фильтр по магазину:', 'woodmart-child' ); ?></label>
				<select id="dokan-vendor-filter" name="filter_vendor_id">
					<option value="0"><?php esc_html_e( 'Все магазины', 'woodmart-child' ); ?></option>
					<?php
					// ЗАМЕНА: Эта логика теперь в CharacterPage::prepare_data_for_template(),
					// и результат передается в $vendor_options_for_filter (если вы его добавите)
					// или вы можете оставить как есть, если прямой $wpdb запрос здесь допустим для вас.
					// Для примера, я оставлю ваш старый код здесь, но он нарушает принцип "шаблон только для отображения".
					global $wpdb; 
					$dokan_table_name_temp = $wpdb->prefix . 'rpg_user_dokan_coupons';
					$vendor_ids_in_inventory_temp = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT vendor_id FROM {$dokan_table_name_temp} WHERE user_id = %d AND vendor_id > 0 ORDER BY vendor_id ASC", $user_id ) );
					if ( $vendor_ids_in_inventory_temp ) {
						foreach ( $vendor_ids_in_inventory_temp as $vid_temp ) {
							$store_name_filter_temp = sprintf( esc_html__( 'Продавец ID: %d', 'woodmart-child' ), $vid_temp );
							if ( function_exists( 'dokan_get_store_info' ) ) {
								$store_info_temp = dokan_get_store_info( $vid_temp );
								if ( $store_info_temp && ! empty( $store_info_temp['store_name'] ) ) {
									$store_name_filter_temp = $store_info_temp['store_name'];
								}
							}
							echo '<option value="' . esc_attr( $vid_temp ) . '" ' . selected( $filter_vendor_id, $vid_temp, false ) . '>' . esc_html( $store_name_filter_temp ) . '</option>';
						}
					}
					?>
				</select>
				<button type="submit" class="button"><?php esc_html_e( 'Фильтр', 'woodmart-child' ); ?></button>
				<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'character' ) ); ?>" class="button dokan-clear-filters"><?php esc_html_e( 'Сбросить', 'woodmart-child' ); ?></a>
			</form>
			<button type="button" id="rpg-refresh-dokan-coupons-status" class="button" title="<?php esc_attr_e( 'Проверить актуальность купонов продавцов (удалить недействительные из списка)', 'woodmart-child' ); ?>"><?php esc_html_e( 'Обновить статус списка', 'woodmart-child' ); ?></button>

			<div class="coupon-inventory-grid dokan-coupon-inventory-grid">
				<?php
				$coupons_displayed_on_page = 0;
				if ( ! empty( $dokan_coupon_entries ) ) { // Используем $dokan_coupon_entries из CharacterPage
					foreach ( $dokan_coupon_entries as $entry ) {
						$coupon_obj = new \WC_Coupon( intval( $entry->coupon_id ) );
						if ( ! $coupon_obj->get_id() ) {
							continue;
						}
						$coupons_displayed_on_page++;
						$vendor_id_from_entry = intval( $entry->vendor_id );
						$store_name           = sprintf( esc_html__( 'Магазин (ID: %d)', 'woodmart-child' ), $vendor_id_from_entry );
						if ( $vendor_id_from_entry && function_exists( 'dokan_get_store_info' ) ) {
							$s_info = dokan_get_store_info( $vendor_id_from_entry );
							if ( $s_info && ! empty( $s_info['store_name'] ) ) {
								$store_name = $s_info['store_name'];
							}
						}
						$icon_class_dokan  = \WoodmartChildRPG\Core\Utils::get_coupon_icon_class( $coupon_obj->get_discount_type() );
						$value_label_dokan = $coupon_obj->get_discount_type() === 'percent'
							? $coupon_obj->get_amount() . '%'
							: wc_price( $coupon_obj->get_amount(), array( 'decimals' => 0 ) );

						$dokan_button_text                         = __( 'Активировать', 'woodmart-child' );
						$can_activate_this_specific_dokan_coupon = true;

						$discounts = new WC_Discounts( WC()->cart );
						$validity = $discounts->is_coupon_valid( $coupon_obj );
						if ( is_wp_error( $validity ) ) {
							$can_activate_this_specific_dokan_coupon = false;
							if ( $coupon_obj->get_date_expires() && $coupon_obj->get_date_expires()->getTimestamp() < current_time( 'timestamp', true ) ) {
								$dokan_button_text = __( 'Истек', 'woodmart-child' );
							} elseif ( $coupon_obj->get_usage_limit() > 0 && $coupon_obj->get_usage_count() >= $coupon_obj->get_usage_limit() ) {
								$dokan_button_text = __( 'Лимит исчерпан', 'woodmart-child' );
							} else { $dokan_button_text = __( 'Недействителен', 'woodmart-child' ); }
						}
						if ( $can_activate_this_specific_dokan_coupon ) {
							if ( $active_dokan_vendor_coupon_code === $coupon_obj->get_code() ) {
								$dokan_button_text                         = __( 'Уже активен', 'woodmart-child' );
								$can_activate_this_specific_dokan_coupon = false;
							} elseif ( $active_dokan_vendor_coupon_code ) {
								$dokan_button_text                         = __( 'Другой купон продавца активен', 'woodmart-child' );
								$can_activate_this_specific_dokan_coupon = false;
							} elseif ( $active_rpg_item_coupon || $active_rpg_cart_coupon ) {
								$dokan_button_text                         = __( 'RPG купон активен', 'woodmart-child' );
								$can_activate_this_specific_dokan_coupon = false;
							}
						}
						?>
						<div class="coupon-item dokan-coupon-item"
							data-vendor-id="<?php echo esc_attr( $vendor_id_from_entry ); ?>"
							data-coupon-code="<?php echo esc_attr( $coupon_obj->get_code() ); ?>"
							data-coupon-id="<?php echo esc_attr( $coupon_obj->get_id() ); ?>">
							<i class="<?php echo esc_attr( $icon_class_dokan ); ?> coupon-icon" title="<?php echo esc_attr( $coupon_obj->get_discount_type() ); ?>"></i>
							<div class="coupon-text">
								<span class="dokan-coupon-store-name"><strong><?php echo esc_html( $store_name ); ?></strong></span>
								<?php
									$description_dokan = $coupon_obj->get_description();
									echo esc_html( $description_dokan ? wp_trim_words( $description_dokan, 10, '...' ) : ( __( 'Скидка', 'woodmart-child' ) . ' ' . $value_label_dokan ) );
								?><br>
								<strong><?php echo $value_label_dokan; ?></strong><br>
								<?php if ( $coupon_obj->get_date_expires() ) : ?>
									<small><?php esc_html_e( 'Истекает:', 'woodmart-child' ); ?> <?php echo esc_html( date_i18n( get_option( 'date_format' ), $coupon_obj->get_date_expires()->getTimestamp() ) ); ?></small><br>
								<?php endif; ?>
								<?php
									$min_amount_dokan = $coupon_obj->get_minimum_amount();
								if ( $min_amount_dokan > 0 ) {
									echo '<small>' . esc_html__( 'Мин. заказ:', 'woodmart-child' ) . ' ' . wc_price( $min_amount_dokan, array( 'decimals' => 0 ) ) . '</small>';
								}
								?>
							</div>
							<button type="button" class="activate-dokan-coupon button" data-original-text="<?php esc_attr_e( 'Активировать', 'woodmart-child' ); ?>" <?php disabled( ! $can_activate_this_specific_dokan_coupon ); ?>>
								<?php echo esc_html( $dokan_button_text ); ?>
							</button>
						</div>
					<?php } // end foreach dokan_coupon_entries
				} // end if !empty dokan_coupon_entries

				if ( $coupons_displayed_on_page === 0 && $total_dokan_coupons > 0 && $current_dokan_page > 1 ) {
					echo '<p>' . esc_html__( 'Все купоны на этой странице были удалены. Попробуйте вернуться на предыдущую страницу.', 'woodmart-child' ) . '</p>';
				} elseif ( $coupons_displayed_on_page === 0 && $total_dokan_coupons === 0 && $filter_vendor_id > 0 ) {
					echo '<p>' . esc_html__( 'Нет купонов от этого продавца в вашем инвентаре.', 'woodmart-child' ) . '</p>';
				} elseif ( $coupons_displayed_on_page === 0 && $total_dokan_coupons === 0 ) { 
					 echo '<p>' . esc_html__( 'Ваш инвентарь купонов продавцов пуст.', 'woodmart-child' ) . '</p>';
				}
				?>
			</div>
			<?php
			// Пагинация
			if ( $total_dokan_coupons > $dokan_coupons_per_page ) {
				$base_paginate_url = wc_get_account_endpoint_url( 'character' );
				$pagination_args   = array(
					'base'      => add_query_arg( 'dokan_coupon_page', '%#%', $base_paginate_url ),
					'format'    => '',
					'current'   => $current_dokan_page,
					'total'     => ceil( $total_dokan_coupons / $dokan_coupons_per_page ),
					'prev_text' => __( '« Назад', 'woodmart-child' ),
					'next_text' => __( 'Вперед »', 'woodmart-child' ),
					'add_args'  => array(), 
				);
				if ( $filter_vendor_id > 0 ) { 
					$pagination_args['add_args']['filter_vendor_id'] = $filter_vendor_id;
				}
				echo '<nav class="woocommerce-pagination rpg-dokan-pagination">';
				echo paginate_links( $pagination_args );
				echo '</nav>';
			}
			?>
		<?php elseif ($filter_vendor_id > 0) : ?>
			<p><?php esc_html_e( 'Нет купонов от этого продавца в вашем инвентаре.', 'woodmart-child' ); ?></p>
		<?php else : ?>
			<p><?php esc_html_e( 'Ваш инвентарь купонов продавцов пуст. Вы можете получить их на страницах магазинов или добавить по коду выше.', 'woodmart-child' ); ?></p>
		<?php endif; ?>
	</div>
</div>