<?php
/**
 * Управляет логикой и отображением страницы "Персонаж" в "Моем аккаунте".
 *
 * @package WoodmartChildRPG\Pages
 */

namespace WoodmartChildRPG\Pages;

use WoodmartChildRPG\RPG\Character as RPGCharacter;
use WoodmartChildRPG\RPG\RaceFactory;
use WoodmartChildRPG\RPG\LevelManager;
use WoodmartChildRPG\Integration\Dokan\DokanUserCouponDB;
use WoodmartChildRPG\Core\Utils;
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Запрещаем прямой доступ.
}

class CharacterPage {
    /** @var RPGCharacter */
    private $character_manager;
    /** @var DokanUserCouponDB */
    private $dokan_coupon_db;

    /**
     * CharacterPage constructor.
     *
     * @param RPGCharacter       $character_manager  Менеджер данных персонажа.
     * @param DokanUserCouponDB  $dokan_coupon_db    Работа с БД купонов Dokan.
     */
    public function __construct( RPGCharacter $character_manager, DokanUserCouponDB $dokan_coupon_db ) {
        $this->character_manager = $character_manager;
        $this->dokan_coupon_db   = $dokan_coupon_db;
    }

    /**
     * Выводит контент страницы "Персонаж".
     *
     * @return void
     */
    public function render_page_content() {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            echo '<p class="woocommerce-info">' . esc_html__( 'Пожалуй, вам нужно войти, чтобы посмотреть информацию о персонаже.', 'woodmart-child' ) . '</p>';
            return;
        }

        $data_for_template = $this->prepare_data_for_template( $user_id );

        $template_path = WOODMART_CHILD_RPG_DIR_PATH . 'templates/myaccount/character-page-content.php';
        if ( file_exists( $template_path ) ) {
            // Делаем данные доступными в шаблоне
            extract( $data_for_template ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
            include $template_path;
        } else {
            echo '<p class="woocommerce-error">' . esc_html__( 'Шаблон страницы персонажа не найден.', 'woodmart-child' ) . '</p>';
        }
    }

    /**
     * Собирает все данные для шаблона "Персонаж".
     *
     * @param int $user_id
     * @return array
     */
    private function prepare_data_for_template( $user_id ) {
        $data = [];

        // Основные параметры
        $data['user_id']           = $user_id;
        $data['user_race_slug']    = $this->character_manager->get_meta( $user_id, 'race' );
        $data['user_level']        = $this->character_manager->get_level( $user_id );
        $data['total_spent']       = $this->character_manager->get_total_spent( $user_id );
        $data['experience_points'] = $this->character_manager->get_experience( $user_id );
        $data['user_gold']         = $this->character_manager->get_gold( $user_id );

        // Информация о расе и бонусах
        $race_object = RaceFactory::create_race( $data['user_race_slug'] );
        $data['user_race_name'] = $race_object
            ? $race_object->get_name()
            : __( 'Не выбрана', 'woodmart-child' );
        $data['race_bonuses_description'] = $race_object
            ? $race_object->get_description()
            : __( 'Описание бонусов не найдено.', 'woodmart-child' );

        // Прогресс-бар уровня
        if ( 'dwarf' === $data['user_race_slug'] ) {
            $data['max_level']         = LevelManager::get_max_dwarf_level();
            $data['xp_for_next_level'] = ( $data['user_level'] < $data['max_level'] )
                ? LevelManager::get_xp_for_dwarf_level( $data['user_level'] + 1 )
                : $data['experience_points'];
            $xp_for_current_level = ( $data['user_level'] > 0 )
                ? LevelManager::get_xp_for_dwarf_level( $data['user_level'] )
                : 0;
        } else {
            $data['max_level']         = LevelManager::get_max_level();
            $data['xp_for_next_level'] = ( $data['user_level'] < $data['max_level'] )
                ? LevelManager::get_xp_for_level( $data['user_level'] + 1 )
                : $data['experience_points'];
            $xp_for_current_level = ( $data['user_level'] > 0 )
                ? LevelManager::get_xp_for_level( $data['user_level'] )
                : 0;
        }
        $xp_needed = $data['xp_for_next_level'] - $xp_for_current_level;
        $xp_gained = $data['experience_points'] - $xp_for_current_level;
        if ( $data['user_level'] >= $data['max_level'] ) {
            $data['progress_percent'] = 100;
        } elseif ( $xp_needed > 0 ) {
            $data['progress_percent'] = round( ( $xp_gained / $xp_needed ) * 100, 2 );
            $data['progress_percent'] = max( 0, min( $data['progress_percent'], 100 ) );
        } else {
            $data['progress_percent'] = ( $data['experience_points'] > $xp_for_current_level && $xp_for_current_level > 0 ) ? 100 : 0;
            if ( $data['user_level'] === 1 && $data['xp_for_next_level'] === 0 ) {
                $data['progress_percent'] = 0;
            }
        }

        // RPG купоны
        $data['rpg_coupons']            = $this->character_manager->get_coupon_inventory( $user_id );
        $data['active_rpg_item_coupon'] = WC()->session ? WC()->session->get( 'active_item_coupon' ) : null;
        $data['active_rpg_cart_coupon'] = WC()->session ? WC()->session->get( 'active_cart_coupon' ) : null;

        // Купоны продавцов Dokan
        $data['dokan_coupons_per_page']   = apply_filters( 'wcrpg_dokan_coupons_per_page_char', 10 );
        $data['current_dokan_page']       = isset( $_GET['dokan_coupon_page'] )
            ? max( 1, intval( $_GET['dokan_coupon_page'] ) )
            : 1;
        $data['filter_vendor_id']         = isset( $_GET['filter_vendor_id'] )
            ? intval( $_GET['filter_vendor_id'] )
            : 0;
        $data['dokan_coupon_entries']     = $this->dokan_coupon_db->get_user_coupons(
            $user_id,
            $data['filter_vendor_id'],
            $data['dokan_coupons_per_page'],
            $data['current_dokan_page']
        );
        $data['total_dokan_coupons']      = $this->dokan_coupon_db->get_user_coupons_count(
            $user_id,
            $data['filter_vendor_id']
        );
        $data['active_dokan_vendor_coupon_code'] = WC()->session
            ? WC()->session->get( 'active_rpg_dokan_coupon_code' )
            : null;

        // Способности рас
        $data['elf_sense_pending']    = ( 'elf' === $data['user_race_slug'] )
            ? (bool) $this->character_manager->get_meta( $user_id, 'elf_sense_pending' )
            : false;
        $data['can_activate_elf_sense'] = ( 'elf' === $data['user_race_slug'] )
            ? Utils::can_activate_weekly_ability( $user_id, 'last_elf_activation' )
            : false;
        $data['rage_pending']         = ( 'orc' === $data['user_race_slug'] )
            ? (bool) $this->character_manager->get_meta( $user_id, 'rage_pending' )
            : false;
        $data['can_activate_orc_rage'] = ( 'orc' === $data['user_race_slug'] )
            ? Utils::can_activate_weekly_ability( $user_id, 'last_rage_activation' )
            : false;
        if ( 'elf' === $data['user_race_slug'] && $data['user_level'] >= 3 ) {
            $sense_map = [3 => 1, 4 => 2, 5 => 3];
            $data['elf_sense_max_items'] = $sense_map[ $data['user_level'] ] ?? 1;
        } else {
            $data['elf_sense_max_items'] = 0;
        }

        return $data;
    }

    /**
     * Проверяет, можно ли активировать еженедельную способность.
     *
     * @param int    $user_id
     * @param string $meta_key_suffix_without_prefix
     * @return bool
     */
    
}
