<?php
// Standalone metadata/UI regression tests; no WordPress database needed.
define('ABSPATH', __DIR__ . '/fixtures/');
function get_plugins() { return ['woocommerce/woocommerce.php'=>['Name'=>'WooCommerce'], 'example-tool/example-tool.php'=>['Name'=>'Example Tool']]; }
function get_mu_plugins() { return []; }
function is_plugin_active($file) { return $file === 'woocommerce/woocommerce.php'; }
function wp_get_themes() { return []; }
function get_stylesheet() { return 'demo'; }
function get_template() { return 'demo'; }
function apply_filters($hook,$value) { return $value; }
function wp_autoload_values_to_autoload() { return ['yes','on','auto','auto-on']; }
function sanitize_text_field($value) { return strip_tags($value); }
function wp_unslash($value) { return stripslashes($value); }
function esc_html($value) { return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_url($value) { return esc_attr($value); }
function selected($a,$b,$echo=false) { return (string)$a === (string)$b ? ' selected' : ''; }
function number_format_i18n($value,$decimals=0) { return number_format($value,$decimals); }
function size_format($value) { return $value.' B'; }
function admin_url($value) { return '/wp-admin/'.$value; }
function wp_nonce_url($url,$action) { return $url; }
function wp_nonce_field($action) { echo '<input name="_wpnonce" value="test">'; }
function add_query_arg($args,$url) { return $url.'?'.http_build_query($args); }
$wpdb = (object)['prefix'=>'custom_'];
require dirname(__DIR__).'/includes/class-sai-catalog.php';
require dirname(__DIR__).'/includes/class-sai-view.php';
function check($condition,$label) { if (!$condition) { throw new RuntimeException($label); } echo "OK: $label\n"; }
$catalog = new SAI_Catalog();
check($catalog->owner('blogname')['type'] === 'core','Core name');
check($catalog->owner('custom_user_roles')['type'] === 'core','Custom table prefix');
check($catalog->owner('wp_unknown')['type'] === 'unknown','wp_ is not proof of Core ownership');
check($catalog->owner('woocommerce_settings')['status'] === 'aktiv','Installed active plugin');
check($catalog->owner('example_tool_settings')['status'] === 'inaktiv','Plugin slug heuristic');
check($catalog->owner('theme_mods_missing')['status'] === 'nicht installiert','Missing theme');
$items = [
    ['option_name'=>'blogname','bytes'=>10,'autoload'=>'yes'],
    ['option_name'=>'woocommerce_settings','bytes'=>200,'autoload'=>'on'],
    ['option_name'=>'woocommerce_off','bytes'=>300,'autoload'=>'off'],
    ['option_name'=>'<script>alert(1)</script>','bytes'=>1,'autoload'=>'yes'],
];
function render_test($items,$query) { $_GET=$query; ob_start(); SAI_View::render($items,[]); return ob_get_clean(); }
$html=render_test($items,[]);
check(strpos($html,'<script>') === false,'HTML escaping');
check(strpos($html,'211 B') !== false,'Autoload total excludes off');
$html=render_test($items,['sai_owner'=>'WooCommerce','sai_autoload'=>'all']);
check(strpos($html,'Auswahl: 2 Optionen') !== false && strpos($html,'500 B') !== false,'Combined owner and autoload filters');
$html=render_test($items,['sai_search'=>'WooCommerce aktiv','sai_autoload'=>'all']);
check(strpos($html,'Auswahl: 2 Optionen') !== false,'Search across owner and status');
$html=render_test($items,['sai_type'=>'core']);
check(strpos($html,'Auswahl: 1 Optionen') !== false,'Core filter');
$html=render_test($items,['sai_min'=>'250','sai_autoload'=>'all']);
check(strpos($html,'Auswahl: 1 Optionen') !== false,'Minimum size');
// Exercise mutation guards and compare-and-swap using an in-memory DB double.
class Redirected extends RuntimeException {}
function add_action($hook,$callback) {}
function current_user_can($capability) { return $GLOBALS['allowed'] ?? true; }
function check_admin_referer($action) { if (!($GLOBALS['nonce_ok'] ?? true)) { throw new RuntimeException('nonce'); } }
function wp_die($message,$title='', $args=[]) { throw new RuntimeException($message); }
function get_option($key,$default=[]) { return $GLOBALS['stored'][$key] ?? $default; }
function update_option($key,$value,$autoload=false) { $GLOBALS['stored'][$key]=$value; return true; }
function delete_option($key) { unset($GLOBALS['stored'][$key]); }
function get_current_user_id() { return 7; }
function wp_cache_delete($key,$group) { $GLOBALS['invalidated'][]=$key; }
function wp_safe_redirect($url) { throw new Redirected($url); }
class FakeDB {
    public $prefix='custom_'; public $options='custom_options'; public $state='yes'; public $queries=0;
    public function prepare($sql,...$args) { return $args; }
    public function query($args) {
        $this->queries++;
        [$new,$name,$expected]=$args;
        if ($this->state !== $expected) { return 0; }
        $this->state=$new; return 1;
    }
}
require dirname(__DIR__).'/includes/class-sai-admin.php';
$_SERVER['REQUEST_METHOD']='POST';
$wpdb=new FakeDB(); $admin=new SAI_Admin();
$_POST=['option_name'=>'example_tool_settings','expected'=>'yes','enable'=>'0'];
try { $admin->change(); } catch (Redirected $e) {}
check($wpdb->state === 'off','Autoload mutation');
check(get_option('sai_change_history')['changes'][0]['autoload'] === 'yes','Exact original recorded');
check(in_array('alloptions',$GLOBALS['invalidated'],true) && in_array('example_tool_settings',$GLOBALS['invalidated'],true),'Cache invalidation');
try { $admin->undo(); } catch (Redirected $e) {}
check($wpdb->state === 'yes','Undo exact original');
try { $admin->change(); } catch (Redirected $e) {}
$wpdb->state='auto-on';
try { $admin->undo(); throw new LogicException('Expected conflict'); } catch (RuntimeException $e) { check($wpdb->state === 'auto-on','Undo conflict preserves intervening value'); }
$_POST=['option_name'=>'blogname','expected'=>'yes','enable'=>'0'];
$before=$wpdb->queries;
try { $admin->change(); } catch (RuntimeException $e) {}
check($wpdb->queries === $before,'Core POST protection');
$_POST=['option_name'=>'sai_change_journal','expected'=>'yes','enable'=>'0'];
try { $admin->change(); } catch (RuntimeException $e) {}
check($wpdb->queries === $before,'Own journal POST protection');
$GLOBALS['allowed']=false;
try { $admin->change(); } catch (RuntimeException $e) {}
check($wpdb->queries === $before,'Capability guard');
$GLOBALS['allowed']=true; $GLOBALS['nonce_ok']=false;
try { $admin->change(); } catch (RuntimeException $e) {}
check($wpdb->queries === $before,'Nonce guard');
echo "Alle Metadaten-/UI-/Änderungslogik-Tests erfolgreich.\n";
