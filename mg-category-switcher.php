<?php
/**
 * Plugin Name: MG Category Switcher (Woo)
 * Description: Subcategory / sibling category switcher on WooCommerce product category archives, with admin settings.
 * Version: 1.1.0
 * Author: MG
 * Text Domain: mg-category-switcher
 */

if (!defined('ABSPATH')) exit;

class MG_Category_Switcher_Woo {
  const VERSION = '1.1.0';
  const OPTION_KEY = 'mg_cat_switcher_settings';

  public function __construct() {
    add_action('admin_menu', [$this, 'admin_menu']);
    add_action('admin_init', [$this, 'register_settings']);

    add_action('wp_enqueue_scripts', [$this, 'enqueue_styles']);
    // Place under the category description, above products
    add_action('woocommerce_archive_description', [$this, 'render_switcher'], 25);
    add_filter('woocommerce_get_price_html', [$this, 'append_from_label_to_price_html'], 20, 2);
  }

  /* =========================
   * Admin UI
   * ========================= */
  public function admin_menu() {
    // Put under WooCommerce menu for convenience
    add_submenu_page(
      'woocommerce',
      __('Kategória váltó', 'mg-category-switcher'),
      __('Kategória váltó', 'mg-category-switcher'),
      'manage_woocommerce',
      'mg-category-switcher',
      [$this, 'render_settings_page']
    );
  }

  public function register_settings() {
    register_setting('mg_cat_switcher_group', self::OPTION_KEY, [
      'type' => 'array',
      'sanitize_callback' => [$this, 'sanitize_settings'],
      'default' => $this->default_settings(),
    ]);

    add_settings_section(
      'mg_cat_switcher_section_display',
      __('Megjelenés', 'mg-category-switcher'),
      function() {
        echo '<p style="margin:0;">' . esc_html__('Állítsd be, hogyan jelenjenek meg az alkategória gombok a kategóriaoldal tetején.', 'mg-category-switcher') . '</p>';
      },
      'mg-category-switcher'
    );

    add_settings_field(
      'display_mode',
      __('Gombok elrendezése', 'mg-category-switcher'),
      [$this, 'field_display_mode'],
      'mg-category-switcher',
      'mg_cat_switcher_section_display'
    );

    add_settings_field(
      'hide_empty',
      __('Üres kategóriák', 'mg-category-switcher'),
      [$this, 'field_hide_empty'],
      'mg-category-switcher',
      'mg_cat_switcher_section_display'
    );

    add_settings_field(
      'show_counts',
      __('Darabszám megjelenítése', 'mg-category-switcher'),
      [$this, 'field_show_counts'],
      'mg-category-switcher',
      'mg_cat_switcher_section_display'
    );

    add_settings_field(
      'zoom_base_desktop',
      __('Fix kép nagyítás (asztali)', 'mg-category-switcher'),
      [$this, 'field_zoom_base_desktop'],
      'mg-category-switcher',
      'mg_cat_switcher_section_display'
    );

    add_settings_field(
      'zoom_base_mobile',
      __('Fix kép nagyítás (mobil)', 'mg-category-switcher'),
      [$this, 'field_zoom_base_mobile'],
      'mg-category-switcher',
      'mg_cat_switcher_section_display'
    );

    add_settings_field(
      'zoom_hover_intensity',
      __('Hover zoom effekt', 'mg-category-switcher'),
      [$this, 'field_zoom_hover_intensity'],
      'mg-category-switcher',
      'mg_cat_switcher_section_display'
    );
  }

  private function default_settings() {
    return [
      'display_mode'         => 'scroll', // scroll | wrap
      'hide_empty'           => 0,
      'show_counts'          => 1,
      'zoom_base_desktop'    => 0,
      'zoom_base_mobile'     => 0,
      'zoom_hover_intensity' => 5,
    ];
  }

  public function get_settings() {
    $saved = get_option(self::OPTION_KEY, []);
    if (!is_array($saved)) $saved = [];
    return array_merge($this->default_settings(), $saved);
  }

  public function sanitize_settings($input) {
    $out = $this->default_settings();
    if (!is_array($input)) return $out;

    $mode = isset($input['display_mode']) ? (string)$input['display_mode'] : 'scroll';
    $out['display_mode'] = in_array($mode, ['scroll','wrap'], true) ? $mode : 'scroll';

    $out['hide_empty'] = !empty($input['hide_empty']) ? 1 : 0;
    $out['show_counts'] = !empty($input['show_counts']) ? 1 : 0;
    $out['zoom_base_desktop'] = $this->sanitize_percent($input['zoom_base_desktop'] ?? 0);
    $out['zoom_base_mobile'] = $this->sanitize_percent($input['zoom_base_mobile'] ?? 0);
    $out['zoom_hover_intensity'] = $this->sanitize_percent($input['zoom_hover_intensity'] ?? 0);

    return $out;
  }

  private function sanitize_percent($value) {
    $value = is_numeric($value) ? (float) $value : 0;
    $value = max(0, min(100, $value));
    return (int) round($value);
  }

  private function format_scale($percent) {
    $percent = $this->sanitize_percent($percent);
    $scale = 1 + ($percent / 100);
    return number_format($scale, 3, '.', '');
  }

  public function render_settings_page() {
    if (!current_user_can('manage_woocommerce')) return;

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('MG – Kategória váltó', 'mg-category-switcher') . '</h1>';
    echo '<p style="max-width:900px;">' . esc_html__('Itt tudod állítani a termékkategória oldalak tetején megjelenő alkategória-váltó gombok megjelenését. Később ide jönnek a további funkciók is.', 'mg-category-switcher') . '</p>';

    echo '<form method="post" action="options.php">';
    settings_fields('mg_cat_switcher_group');
    do_settings_sections('mg-category-switcher');
    submit_button(__('Mentés', 'mg-category-switcher'));
    echo '</form>';

    echo '<hr/>';
    echo '<h2>' . esc_html__('Gyors infó', 'mg-category-switcher') . '</h2>';
    echo '<ul style="list-style:disc;padding-left:18px;">';
    echo '<li>' . esc_html__('Fő kategórián: a közvetlen alkategóriák jelennek meg.', 'mg-category-switcher') . '</li>';
    echo '<li>' . esc_html__('Alkategórián: testvér kategóriák + „összes” (szülő) link jelenik meg.', 'mg-category-switcher') . '</li>';
    echo '</ul>';

    echo '</div>';
  }

  public function field_display_mode() {
    $s = $this->get_settings();
    $mode = $s['display_mode'];

    echo '<label style="display:block;margin:6px 0;">';
    echo '<input type="radio" name="'.esc_attr(self::OPTION_KEY).'[display_mode]" value="scroll" '.checked($mode,'scroll',false).' /> ';
    echo esc_html__('Scrollos (egysoros, vízszintes görgetés mobilon / hosszúnál)', 'mg-category-switcher');
    echo '</label>';

    echo '<label style="display:block;margin:6px 0;">';
    echo '<input type="radio" name="'.esc_attr(self::OPTION_KEY).'[display_mode]" value="wrap" '.checked($mode,'wrap',false).' /> ';
    echo esc_html__('Több sor (wrap) – egyszerre látni az összes alkategória gombot', 'mg-category-switcher');
    echo '</label>';

    echo '<p class="description">'.esc_html__('Tipp: 40+ alkategóriánál desktopon a wrap nagyon átlátható, mobilon a scroll sokkal barátságosabb. Ha wrapot választasz, mobilon is wrap lesz.', 'mg-category-switcher').'</p>';
  }

  public function field_hide_empty() {
    $s = $this->get_settings();
    echo '<label>';
    echo '<input type="checkbox" name="'.esc_attr(self::OPTION_KEY).'[hide_empty]" value="1" '.checked(1, (int)$s['hide_empty'], false).' /> ';
    echo esc_html__('Ne mutassa az üres (0 termék) kategóriákat', 'mg-category-switcher');
    echo '</label>';
  }

  public function field_show_counts() {
    $s = $this->get_settings();
    echo '<label>';
    echo '<input type="checkbox" name="'.esc_attr(self::OPTION_KEY).'[show_counts]" value="1" '.checked(1, (int)$s['show_counts'], false).' /> ';
    echo esc_html__('Mutassa a termékszámot a gombokon', 'mg-category-switcher');
    echo '</label>';
  }

  public function field_zoom_base_desktop() {
    $s = $this->get_settings();
    $value = (int) $s['zoom_base_desktop'];
    echo '<label>';
    echo esc_html__('Fix kép nagyítás (asztali) %', 'mg-category-switcher') . ' ';
    echo '<input type="number" min="0" max="100" step="1" name="'.esc_attr(self::OPTION_KEY).'[zoom_base_desktop]" value="'.esc_attr($value).'" style="width:90px;" />';
    echo '</label>';
  }

  public function field_zoom_base_mobile() {
    $s = $this->get_settings();
    $value = (int) $s['zoom_base_mobile'];
    echo '<label>';
    echo esc_html__('Fix kép nagyítás (mobil) %', 'mg-category-switcher') . ' ';
    echo '<input type="number" min="0" max="100" step="1" name="'.esc_attr(self::OPTION_KEY).'[zoom_base_mobile]" value="'.esc_attr($value).'" style="width:90px;" />';
    echo '</label>';
  }

  public function field_zoom_hover_intensity() {
    $s = $this->get_settings();
    $value = (int) $s['zoom_hover_intensity'];
    echo '<label>';
    echo esc_html__('Hover zoom effekt erőssége %', 'mg-category-switcher') . ' ';
    echo '<input type="number" min="0" max="100" step="1" name="'.esc_attr(self::OPTION_KEY).'[zoom_hover_intensity]" value="'.esc_attr($value).'" style="width:90px;" />';
    echo '</label>';
  }

  /* =========================
   * Frontend
   * ========================= */
  public function enqueue_styles() {
    if (!function_exists('is_product_category') || !is_product_category()) return;

    $s = $this->get_settings();
    $mode = $s['display_mode'];

    // Base styles
    $css = "
    .mg-cat-switcher{margin:14px 0 18px}
    .mg-cat-switcher__title{font-weight:700;margin:0 0 10px;font-size:16px}
    .mg-cat-switcher__grid{display:flex;gap:10px;flex-wrap:wrap}
    .mg-cat-chip{display:inline-flex;align-items:center;gap:8px;padding:10px 12px;border:1px solid rgba(0,0,0,.12);border-radius:999px;text-decoration:none;line-height:1}
    .mg-cat-chip:hover{border-color:rgba(0,0,0,.28)}
    .mg-cat-chip.is-active{border-color:rgba(0,0,0,.55);font-weight:700}
    .mg-cat-chip__count{opacity:.65;font-size:12px}
    .mg-cat-switcher__meta{margin-top:10px;display:flex;gap:10px;flex-wrap:wrap}
    .mg-cat-back{display:inline-flex;align-items:center;gap:8px;text-decoration:none;font-weight:600}
    .woocommerce ul.products li.product a img{transition:transform .25s ease}
    .woocommerce ul.products li.product a:hover img{transform:scale(1.05)}";

    $base_desktop = $this->format_scale($s['zoom_base_desktop'] ?? 0);
    $base_mobile = $this->format_scale($s['zoom_base_mobile'] ?? 0);
    $hover_scale_desktop = $this->format_scale(($s['zoom_base_desktop'] ?? 0) + ($s['zoom_hover_intensity'] ?? 0));
    $hover_scale_mobile = $this->format_scale(($s['zoom_base_mobile'] ?? 0) + ($s['zoom_hover_intensity'] ?? 0));

    $css .= "
    .woocommerce ul.products li.product a img{transition:transform .25s ease;transform:scale({$base_desktop});transform-origin:center}
    .woocommerce ul.products li.product a:hover img{transform:scale({$hover_scale_desktop})}";

    $base_desktop = $this->format_scale($s['zoom_base_desktop'] ?? 0);
    $base_mobile = $this->format_scale($s['zoom_base_mobile'] ?? 0);
    $hover_scale_desktop = $this->format_scale(($s['zoom_base_desktop'] ?? 0) + ($s['zoom_hover_intensity'] ?? 0));
    $hover_scale_mobile = $this->format_scale(($s['zoom_base_mobile'] ?? 0) + ($s['zoom_hover_intensity'] ?? 0));

    $css .= "
    .woocommerce ul.products li.product a img{transition:transform .25s ease;transform:scale({$base_desktop});transform-origin:center}
    .woocommerce ul.products li.product a:hover img{transform:scale({$hover_scale_desktop})}";

    $base_desktop = $this->format_scale($s['zoom_base_desktop'] ?? 0);
    $base_mobile = $this->format_scale($s['zoom_base_mobile'] ?? 0);
    $hover_scale_desktop = $this->format_scale(($s['zoom_base_desktop'] ?? 0) + ($s['zoom_hover_intensity'] ?? 0));
    $hover_scale_mobile = $this->format_scale(($s['zoom_base_mobile'] ?? 0) + ($s['zoom_hover_intensity'] ?? 0));

    $css .= "
    .woocommerce ul.products li.product a img{transition:transform .25s ease;transform:scale({$base_desktop});transform-origin:center}
    .woocommerce ul.products li.product a:hover img{transform:scale({$hover_scale_desktop})}";

    $base_desktop = $this->format_scale($s['zoom_base_desktop'] ?? 0);
    $base_mobile = $this->format_scale($s['zoom_base_mobile'] ?? 0);
    $hover_scale_desktop = $this->format_scale(($s['zoom_base_desktop'] ?? 0) + ($s['zoom_hover_intensity'] ?? 0));
    $hover_scale_mobile = $this->format_scale(($s['zoom_base_mobile'] ?? 0) + ($s['zoom_hover_intensity'] ?? 0));

    $css .= "
    .woocommerce ul.products li.product a img{transition:transform .25s ease;transform:scale({$base_desktop});transform-origin:center}
    .woocommerce ul.products li.product a:hover img{transform:scale({$hover_scale_desktop})}";

    $base_desktop = $this->format_scale($s['zoom_base_desktop'] ?? 0);
    $base_mobile = $this->format_scale($s['zoom_base_mobile'] ?? 0);
    $hover_scale_desktop = $this->format_scale(($s['zoom_base_desktop'] ?? 0) + ($s['zoom_hover_intensity'] ?? 0));
    $hover_scale_mobile = $this->format_scale(($s['zoom_base_mobile'] ?? 0) + ($s['zoom_hover_intensity'] ?? 0));

    $css .= "
    .woocommerce ul.products li.product a img{transition:transform .25s ease;transform:scale({$base_desktop});transform-origin:center}
    .woocommerce ul.products li.product a:hover img{transform:scale({$hover_scale_desktop})}";

    $base_desktop = $this->format_scale($s['zoom_base_desktop'] ?? 0);
    $base_mobile = $this->format_scale($s['zoom_base_mobile'] ?? 0);
    $hover_scale_desktop = $this->format_scale(($s['zoom_base_desktop'] ?? 0) + ($s['zoom_hover_intensity'] ?? 0));
    $hover_scale_mobile = $this->format_scale(($s['zoom_base_mobile'] ?? 0) + ($s['zoom_hover_intensity'] ?? 0));

    $css .= "
    .woocommerce ul.products li.product a img{transition:transform .25s ease;transform:scale({$base_desktop});transform-origin:center}
    .woocommerce ul.products li.product a:hover img{transform:scale({$hover_scale_desktop})}";

    if ($mode === 'scroll') {
      // Scroll mode: always single-row scroll on small screens; allow wrap on desktop unless forced
      $css .= "
      @media (max-width: 920px){
        .mg-cat-switcher__grid{flex-wrap:nowrap;overflow:auto;padding-bottom:6px}
        .mg-cat-chip{white-space:nowrap}
      }";
    } else {
      // Wrap mode: show all, even on mobile (could be long, but user requested explicit option)
      $css .= "
      @media (max-width: 920px){
        .mg-cat-switcher__grid{flex-wrap:wrap;overflow:visible}
      }";
    }

    $css .= "
    @media (max-width: 768px){
      .woocommerce ul.products{display:flex;flex-wrap:wrap;gap:8px}
      .woocommerce ul.products li.product{width:calc(50% - 4px);margin:0 !important;padding:0 !important}
      .woocommerce ul.products li.product .astra-shop-summary-wrap{margin:6px 0 0 !important;padding:0 !important}
      .woocommerce ul.products li.product .woocommerce-loop-product__title{margin:0 0 4px !important}
      .woocommerce ul.products li.product .price{margin:0 0 4px !important}
      .woocommerce ul.products li.product .ast-woo-shop-product-description{display:none !important;margin:0 !important;padding:0 !important}
      .woocommerce ul.products li.product a img{width:100%;height:auto;transform:scale({$base_mobile})}
      .woocommerce ul.products li.product a:hover img{transform:scale({$hover_scale_mobile})}
    }";

    wp_register_style('mg-cat-switcher', false, [], self::VERSION);
    wp_enqueue_style('mg-cat-switcher');
    wp_add_inline_style('mg-cat-switcher', $css);
  }

  public function render_switcher() {
    if (!function_exists('is_product_category') || !is_product_category()) return;

    $term = get_queried_object();
    if (!$term || empty($term->term_id) || !isset($term->taxonomy) || $term->taxonomy !== 'product_cat') return;

    $s = $this->get_settings();

    $current_id = (int) $term->term_id;
    $parent_id  = (int) $term->parent;

    // If on a top-level category: list children.
    // If on a child category: list siblings + back link.
    if ($parent_id === 0) {
      $title = __('Alkategóriák', 'mg-category-switcher');
      $target_parent = $current_id;
      $show_back = false;
      $back_term = null;
    } else {
      $title = __('Válts alkategóriát', 'mg-category-switcher');
      $target_parent = $parent_id;
      $show_back = true;
      $back_term = get_term($parent_id, 'product_cat');
    }

    $children = get_terms([
      'taxonomy'   => 'product_cat',
      'hide_empty' => !empty($s['hide_empty']),
      'parent'     => $target_parent,
      'orderby'    => 'name',
      'order'      => 'ASC',
    ]);

    if (is_wp_error($children) || empty($children)) return;

    echo '<div class="mg-cat-switcher" role="navigation" aria-label="'.esc_attr__('Kategória váltó', 'mg-category-switcher').'">';
    echo '<div class="mg-cat-switcher__title">'.esc_html($title).'</div>';
    echo '<div class="mg-cat-switcher__grid">';

    foreach ($children as $child) {
      $url = get_term_link($child);
      if (is_wp_error($url)) continue;

      if ($show_back && (int) $child->term_id !== $current_id) {
        continue;
      }

      $active = ((int)$child->term_id === $current_id) ? ' is-active' : '';
      $count = isset($child->count) ? (int)$child->count : 0;

      echo '<a class="mg-cat-chip'.$active.'" href="'.esc_url($url).'">';
      echo esc_html($child->name);
      if (!empty($s['show_counts'])) {
        echo ' <span class="mg-cat-chip__count">('.$count.')</span>';
      }
      echo '</a>';
    }

    echo '</div>';

    if ($show_back && $back_term && !is_wp_error($back_term)) {
      $back_url = get_term_link($back_term);
      echo '<div class="mg-cat-switcher__meta">';
      echo '<a class="mg-cat-back" href="'.esc_url($back_url).'">← '.esc_html__('Vissza:', 'mg-category-switcher').' '.esc_html($back_term->name).'</a>';
      echo '</div>';
    }

    echo '</div>';
  }

  public function append_from_label_to_price_html($price_html, $product) {
    if (!function_exists('is_product_category') || !is_product_category()) {
      return $price_html;
    }

    if (!is_string($price_html) || $price_html === '') {
      return $price_html;
    }

    $suffix = ' ' . esc_html__('- tól', 'mg-category-switcher');

    if (strpos($price_html, '</bdi>') !== false) {
      return preg_replace('/<\/bdi>/', $suffix . '</bdi>', $price_html, 1);
    }

    return $price_html . $suffix;
  }
}

new MG_Category_Switcher_Woo();
