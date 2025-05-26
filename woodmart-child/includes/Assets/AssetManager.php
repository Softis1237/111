<?php
/**
 * Менеджер ассетов (CSS/JS) для фронтенда и админ-панели.
 *
 * @package WoodmartChildRPG\Assets
 */

namespace WoodmartChildRPG\Assets;

use WoodmartChildRPG\RPG\Character;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Запрещаем прямой доступ.
}

class AssetManager {

    /**
     * Экземпляр менеджера персонажа для получения данных пользователя.
     *
     * @var Character
     */
    private $character_manager;

    /**
     * Конструктор.
     *
     * @param Character $character_manager Экземпляр Character manager.
     */
    public function __construct( Character $character_manager ) {
        $this->character_manager = $character_manager;
    }

    /**
     * Подключает скрипты и стили для фронтенда:
     * - страницу входа/регистрации (неавторизованные на аккаунте);
     * - страницу аккаунта (авторизованные);
     * - страницу корзины.
     */
    public function enqueue_frontend_assets() {
        // Общие условия подключения: аккаунт или корзина
        if ( ! is_account_page() && ! is_cart() ) {
            return;
        }

        $user_id   = get_current_user_id();
        $user_race = $user_id ? $this->character_manager->get_race( $user_id ) : '';

        // Общие данные для всех JS
        $rpg_common_data = array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'user_id'  => $user_id,
            'race'     => $user_race,
            'text'     => array(
                'error_network'        => __( 'Ошибка сети.', 'woodmart-child' ),
                'error_generic'        => __( 'Произошла ошибка.', 'woodmart-child' ),
                'confirm_elf_select'   => __( 'Выберите хотя бы один товар.', 'woodmart-child' ),
                'confirm_deactivate'   => __( 'Вы уверены, что хотите деактивировать этот купон?', 'woodmart-child' ),
                'ability_activated'    => __( 'Способность активирована!', 'woodmart-child' ),
                'ability_select_item'  => __( 'Выберите товар(ы) в корзине для применения способности.', 'woodmart-child' ),
                'ability_already_used' => __( 'Способность уже активирована на этой неделе.', 'woodmart-child' ),
                'ability_level_low'    => __( 'Способность доступна с более высокого уровня.', 'woodmart-child' ),
            ),
        );

        // 1) Страница аккаунта для неавторизованных
        if ( is_account_page() && ! is_user_logged_in() ) {
            wp_enqueue_style(
                'rpg-login-register-style',
                WOODMART_CHILD_RPG_DIR_URI . 'assets/css/login-register.css',
                array(),
                '1.0.1'
            );
            wp_enqueue_script(
                'rpg-login-register-script',
                WOODMART_CHILD_RPG_DIR_URI . 'assets/js/login-register.js',
                array( 'jquery' ),
                '1.0.1',
                true
            );
            // Локализация данных для login-register.js (при необходимости)
            // wp_localize_script('rpg-login-register-script', 'rpg_login_reg_settings', $rpg_common_data);
        }

        // 2) Страница аккаунта для авторизованных
        if ( is_account_page() && is_user_logged_in() ) {
            global $wp;
            $is_character_page = isset( $wp->query_vars['character'] );

            // Если нужен скрипт только на странице персонажа, раскомментируйте:
            // if ( $is_character_page ) {
                wp_enqueue_script(
                    'rpg-account-script',
                    WOODMART_CHILD_RPG_DIR_URI . 'assets/js/rpg-account.js',
                    array( 'jquery' ),
                    '1.2',
                    true
                );
                $account_data          = $rpg_common_data;
                $account_data['nonce'] = wp_create_nonce( 'rpg_ajax_nonce' );
                wp_localize_script( 'rpg-account-script', 'rpg_settings', $account_data );

                // При необходимости подключите стили:
                // wp_enqueue_style('rpg-account-style', WOODMART_CHILD_RPG_DIR_URI . 'assets/css/rpg-account.css');
            // }
        }

        // 3) Страница корзины
        if ( is_cart() ) {
            wp_enqueue_script(
                'rpg-cart-script',
                WOODMART_CHILD_RPG_DIR_URI . 'assets/js/rpg-cart.js',
                array( 'jquery' ),
                '1.2',
                true
            );
            $cart_data               = $rpg_common_data;
            $cart_data['cart_nonce'] = wp_create_nonce( 'rpg_cart_ajax_nonce' );
            wp_localize_script( 'rpg-cart-script', 'rpg_cart_settings', $cart_data );

            // Если нужны стили для корзины:
            // wp_enqueue_style('rpg-cart-style', WOODMART_CHILD_RPG_DIR_URI . 'assets/css/rpg-cart.css');
        }
    }

    /**
     * Подключает скрипты и стили для админ-панели.
     *
     * @param string $hook_suffix Суффикс текущей страницы админки.
     */
    public function enqueue_admin_assets( $hook_suffix ) {
        if ( in_array( $hook_suffix, array( 'profile.php', 'user-edit.php', 'users.php' ), true ) ) {
            wp_enqueue_script(
                'rpg-admin-script',
                WOODMART_CHILD_RPG_DIR_URI . 'assets/js/rpg-admin-profile.js',
                array( 'jquery' ),
                '1.1',
                true
            );
            wp_localize_script(
                'rpg-admin-script',
                'rpg_admin_settings',
                array(
                    'nonce'    => wp_create_nonce( 'rpg_admin_ajax_nonce' ),
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'text'     => array(
                        'confirm_delete' => __( 'Вы уверены, что хотите удалить этот купон?', 'woodmart-child' ),
                        'confirm_reset'  => __( 'Вы уверены, что хотите сбросить кулдаун этой способности?', 'woodmart-child' ),
                    ),
                )
            );
        }
    }

    /**
     * Подключает скрипты и стили для страницы магазина продавца Dokan.
     *
     * Должно вызываться через add_action('wp_enqueue_scripts', ...)
     */
    public function enqueue_dokan_store_assets() {
        if ( function_exists('dokan_is_store_page') && dokan_is_store_page() ) {
            $user_id   = get_current_user_id();
            $user_race = $user_id ? $this->character_manager->get_race( $user_id ) : '';
            $rpg_common_data = array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'user_id'  => $user_id,
                'race'     => $user_race,
                'text'     => array(
                    'error_network'        => __( 'Ошибка сети.', 'woodmart-child' ),
                    'error_generic'        => __( 'Произошла ошибка.', 'woodmart-child' ),
                    'confirm_elf_select'   => __( 'Выберите хотя бы один товар.', 'woodmart-child' ),
                    'confirm_deactivate'   => __( 'Вы уверены, что хотите деактивировать этот купон?', 'woodmart-child' ),
                    'ability_activated'    => __( 'Способность активирована!', 'woodmart-child' ),
                    'ability_select_item'  => __( 'Выберите товар(ы) в корзине для применения способности.', 'woodmart-child' ),
                    'ability_already_used' => __( 'Способность уже активирована на этой неделе.', 'woodmart-child' ),
                    'ability_level_low'    => __( 'Способность доступна с более высокого уровня.', 'woodmart-child' ),
                ),
            );
            wp_enqueue_script(
                'rpg-account-script',
                WOODMART_CHILD_RPG_DIR_URI . 'assets/js/rpg-account.js',
                array( 'jquery' ),
                '1.2',
                true
            );
            $account_data          = $rpg_common_data;
            $account_data['nonce'] = wp_create_nonce( 'rpg_ajax_nonce' );
            wp_localize_script( 'rpg-account-script', 'rpg_settings', $account_data );
        }
    }
}

// В конструкторе или в functions.php нужно добавить хук:
// add_action('wp_enqueue_scripts', [$assetManager, 'enqueue_dokan_store_assets']);
