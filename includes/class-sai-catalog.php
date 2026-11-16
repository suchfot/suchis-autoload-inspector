<?php
if (!defined('ABSPATH')) { exit; }

/** Metadata only: ownership is never inferred from option values. */
final class SAI_Catalog {
    private $plugins = [];
    private $themes = [];
    public function __construct() {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        foreach (get_plugins() as $file => $data) {
            $slug = dirname($file) === '.' ? basename($file, '.php') : dirname($file);
            $this->plugins[$slug] = ['name' => $data['Name'], 'status' => is_plugin_active($file) ? 'aktiv' : 'inaktiv'];
        }
        foreach (get_mu_plugins() as $file => $data) {
            $this->plugins[basename($file, '.php')] = ['name' => $data['Name'], 'status' => 'MU-Plugin'];
        }
        foreach (wp_get_themes() as $slug => $theme) {
            $this->themes[$slug] = ['name' => $theme->get('Name'), 'status' => in_array($slug, [get_stylesheet(), get_template()], true) ? 'aktiv' : 'inaktiv'];
        }
    }
    public function owner(string $name): array {
        $core = ['siteurl','home','blogname','blogdescription','admin_email','new_admin_email','users_can_register','default_role','active_plugins','template','stylesheet','current_theme','cron','rewrite_rules','permalink_structure','category_base','tag_base','upload_path','upload_url_path','uploads_use_yearmonth_folders','db_version','initial_db_version','WPLANG','timezone_string','gmt_offset','date_format','time_format','start_of_week','show_on_front','page_on_front','page_for_posts','posts_per_page','posts_per_rss','rss_use_excerpt','default_category','default_comment_status','default_ping_status','default_pingback_flag','comment_registration','comment_moderation','moderation_keys','disallowed_keys','thread_comments','thread_comments_depth','page_comments','comments_per_page','comment_order','sticky_posts','sidebars_widgets','recently_activated','uninstall_plugins','auto_update_plugins','auto_update_themes','wp_page_for_privacy_policy','blog_public','avatar_default','avatar_rating','show_avatars'];
        global $wpdb;
        if (in_array($name, $core, true) || $name === $wpdb->prefix . 'user_roles') {
            return ['type'=>'core','owner'=>'WordPress-Core','status'=>'Core','confidence'=>'Bekannter Optionsname'];
        }
        if (strpos($name, 'theme_mods_') === 0) {
            $slug = substr($name, 11); $theme = $this->themes[$slug] ?? null;
            return ['type'=>'theme','owner'=>$theme ? $theme['name'] : $slug,'status'=>$theme ? $theme['status'] : 'nicht installiert','confidence'=>'Theme-Mods-Namensschema'];
        }
        // Deliberately small, reviewable rules; users may extend via this filter.
        $rules = apply_filters('sai_owner_rules', [
            'woocommerce_' => 'woocommerce', 'wc_' => 'woocommerce',
            'wpseo' => 'wordpress-seo', 'yoast_' => 'wordpress-seo',
            'elementor_' => 'elementor', '_elementor_' => 'elementor',
            'sai_' => 'suchis-autoload-inspector',
        ]);
        foreach ($rules as $prefix => $slug) {
            if (strpos($name, (string)$prefix) === 0) {
                $plugin = $this->plugins[$slug] ?? null;
                return ['type'=>'plugin','owner'=>$plugin ? $plugin['name'] : $slug,'status'=>$plugin ? $plugin['status'] : 'nicht installiert / prüfen','confidence'=>'Präfixregel (keine Eigentumsgarantie)'];
            }
        }
        $matches = [];
        foreach ($this->plugins as $slug => $plugin) {
            foreach (array_unique([$slug, str_replace('-', '_', $slug)]) as $prefix) {
                if (strpos(ltrim($name, '_'), $prefix . '_') === 0 || $name === $prefix) { $matches[$slug] = $plugin; }
            }
        }
        if (count($matches) === 1) {
            $plugin = reset($matches);
            return ['type'=>'plugin','owner'=>$plugin['name'],'status'=>$plugin['status'],'confidence'=>'Vermutung anhand Plugin-Slug'];
        }
        return ['type'=>'unknown','owner'=>'Unbekannt','status'=>'unklar','confidence'=>strpos($name, '_transient_') === 0 || strpos($name, '_site_transient_') === 0 ? 'Transient; Eigentümer unbekannt' : 'Keine eindeutige Zuordnung'];
    }
}
