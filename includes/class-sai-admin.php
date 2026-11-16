<?php
if (!defined('ABSPATH')) { exit; }
final class SAI_Admin {
    private const SLUG = 'suchis-autoload-inspector';
    private const HISTORY_KEY = 'sai_change_history';
    private const AUTOLOAD_VALUES = ['yes', 'on', 'auto-on', 'auto'];
    private const PROTECTED = ['siteurl','home','active_plugins','template','stylesheet','cron','rewrite_rules','wp_user_roles','blogname','blogdescription','admin_email','users_can_register','default_role','permalink_structure'];
    public function __construct() {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_post_sai_export', [$this, 'export']);
        add_action('admin_post_sai_change', [$this, 'change']);
        add_action('admin_post_sai_undo', [$this, 'undo']);
    }
    public function menu(): void { add_management_page('Autoload Inspector', 'Autoload Inspector', 'manage_options', self::SLUG, [$this, 'page']); }
    private function guard(string $action): void {
        if (!current_user_can('manage_options')) { wp_die('Keine Berechtigung.', '', ['response'=>403]); }
        check_admin_referer($action);
    }
    private function url(array $args=[]): string { return add_query_arg($args, admin_url('tools.php?page='.self::SLUG)); }
    private function options(): array {
        global $wpdb;
        // Only autoloaded entries: use actual database state rather than inferred plugin ownership.
        $placeholders = implode(',', array_fill(0, count(self::AUTOLOAD_VALUES), '%s'));
        return $wpdb->get_results($wpdb->prepare("SELECT option_name, OCTET_LENGTH(option_value) AS bytes, autoload FROM {$wpdb->options} WHERE autoload IN ($placeholders) ORDER BY bytes DESC", ...self::AUTOLOAD_VALUES), ARRAY_A);
    }
    private function group(string $name): string {
        if (in_array($name, self::PROTECTED, true) || strpos($name, 'wp_') === 0) { return 'WordPress / prüfen'; }
        if (strpos($name, '_transient_') === 0 || strpos($name, '_site_transient_') === 0) { return 'Transient'; }
        if (strpos($name, 'theme_mods_') === 0) { return 'Theme'; }
        $parts = explode('_', ltrim($name, '_'));
        return ($parts[0] ?? '') !== '' ? 'Präfix: '.$parts[0] : 'Nicht zugeordnet';
    }
    public function page(): void {
        if (!current_user_can('manage_options')) { wp_die('Keine Berechtigung.'); }
        $items = $this->options(); $count = count($items); $bytes = array_sum(array_map(static function ($x) { return (int)$x['bytes']; }, $items));
        $search = isset($_GET['sai_search']) ? sanitize_text_field(wp_unslash($_GET['sai_search'])) : '';
        $sort = isset($_GET['sai_sort']) && $_GET['sai_sort'] === 'name' ? 'name' : 'size';
        if ($sort === 'name') { usort($items, static function ($a,$b) { return strcmp($a['option_name'],$b['option_name']); }); }
        if ($search !== '') { $items = array_values(array_filter($items, static function ($x) use ($search) { return stripos($x['option_name'], $search) !== false; })); }
        $page = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1); $per = 50; $pages = max(1, (int)ceil(count($items)/$per)); $page = min($page,$pages);
        $visible = array_slice($items, ($page-1)*$per, $per);
        $history = get_option(self::HISTORY_KEY, []);
        echo '<div class="wrap"><h1>Suchis Autoload Inspector</h1>';
        if (isset($_GET['sai_notice'])) { echo '<div class="notice notice-info"><p>'.esc_html(sanitize_text_field(wp_unslash($_GET['sai_notice']))).'</p></div>'; }
        echo '<div class="notice notice-warning"><p><strong>Achtung: Dieses Werkzeug richtet sich an erfahrene WordPress-Administratoren.</strong> Änderungen können Funktionen der Website beeinträchtigen.</p></div>';
        echo '<details style="max-width:1000px;background:#fff;padding:12px 16px;border:1px solid #c3c4c7;margin-bottom:20px"><summary><strong>Sicherheitshinweise und Vorbereitung anzeigen</strong></summary><p>Erstellen Sie vor Änderungen ein vollständiges Backup von Dateien und Datenbank. Der Export unten sichert nur die Optionstabelle und ersetzt kein Website-Backup. Prüfen Sie nicht mehr benötigte Plugins und Themes; behalten Sie ein WordPress-Standardtheme als Reserve. Deaktivierte Plugins können weiterhin gespeicherte Einstellungen besitzen. Ändern Sie nur Optionen, deren Zweck Sie kennen. Testen Sie auch zeitversetzte Funktionen wie Cronjobs und Bestellungen. Die Zuordnung nach Namenspräfix ist nur eine Heuristik.</p></details>';
        echo '<p><strong>'.esc_html(number_format_i18n($count)).'</strong> automatisch geladene Optionen mit <strong>'.esc_html(size_format($bytes)).'</strong> Gesamtgröße.</p>';
        echo '<p><a class="button button-secondary" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=sai_export'),'sai_export')).'">Vollständige Optionstabelle als SQL exportieren</a> <em>Enthält möglicherweise Zugangsdaten und API-Schlüssel. Sicher aufbewahren!</em></p>';
        if (is_array($history) && !empty($history['changes'])) {
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
            wp_nonce_field('sai_undo'); echo '<input type="hidden" name="action" value="sai_undo"><button class="button">Letzte Autoload-Änderung rückgängig machen</button></form>';
        }
        echo '<form method="get"><input type="hidden" name="page" value="'.esc_attr(self::SLUG).'"><input type="search" name="sai_search" placeholder="Optionsname suchen" value="'.esc_attr($search).'"> <select name="sai_sort"><option value="size"'.selected($sort,'size',false).'>Größe absteigend</option><option value="name"'.selected($sort,'name',false).'>Name aufsteigend</option></select> <button class="button">Filtern</button></form>';
        echo '<table class="widefat striped" style="margin-top:12px"><thead><tr><th>Option</th><th>Vermutete Gruppe</th><th>Größe</th><th>Autoload</th><th>Aktion</th></tr></thead><tbody>';
        foreach ($visible as $item) {
            $name = $item['option_name']; $protected = in_array($name, self::PROTECTED, true);
            echo '<tr><td><code>'.esc_html($name).'</code></td><td>'.esc_html($this->group($name)).'</td><td>'.esc_html(size_format((int)$item['bytes'])).'</td><td>'.esc_html($item['autoload']).'</td><td>';
            if ($protected) { echo '<span title="Geschützte Kernoption">Geschützt</span>'; }
            else { echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" onsubmit="return confirm(\'Autoload für diese Option deaktivieren? Vorher Backup erstellen und Auswirkungen prüfen.\')">';
                wp_nonce_field('sai_change'); echo '<input type="hidden" name="action" value="sai_change"><input type="hidden" name="option_name" value="'.esc_attr($name).'"><button class="button button-small">Autoload aus</button></form>'; }
            echo '</td></tr>';
        }
        if (!$visible) { echo '<tr><td colspan="5">Keine Optionen gefunden.</td></tr>'; }
        echo '</tbody></table><p>Seite '.esc_html((string)$page).' von '.esc_html((string)$pages).' – maximal 50 Einträge pro Seite.</p>';
        for ($i=1;$i<=$pages;$i++) { if ($i === $page) { echo '<strong style="margin-right:8px">'.esc_html((string)$i).'</strong>'; } else { echo '<a style="margin-right:8px" href="'.esc_url($this->url(['paged'=>$i,'sai_search'=>$search,'sai_sort'=>$sort])).'">'.esc_html((string)$i).'</a>'; } }
        echo '<p><em>Hinweis: Diese Ansicht listet nur aktuell automatisch geladene Optionen. Zum Wiedereinschalten verwenden Sie die Rückgängig-Funktion. Erweiterte Filter und Einzelansicht sind für die nächste Version vorgesehen.</em></p></div>';
    }
    public function change(): void {
        $this->guard('sai_change');
        $name = isset($_POST['option_name']) ? sanitize_text_field(wp_unslash($_POST['option_name'])) : '';
        if (!$name || in_array($name, self::PROTECTED, true) || $name === self::HISTORY_KEY) { wp_die('Option ist geschützt oder ungültig.'); }
        global $wpdb;
        $old = $wpdb->get_var($wpdb->prepare("SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $name));
        if ($old === null || !in_array($old, self::AUTOLOAD_VALUES, true)) { wp_die('Option nicht automatisch geladen oder nicht vorhanden.'); }
        // Keep a single-step history. A failed update must not be recorded as a success.
        if (!function_exists('wp_set_option_autoload') || !wp_set_option_autoload($name, false)) { wp_die('Änderung nicht möglich.'); }
        update_option(self::HISTORY_KEY, ['changes'=>[['name'=>$name,'autoload'=>$old]], 'time'=>time()], false);
        wp_safe_redirect($this->url(['sai_notice'=>'Autoload deaktiviert. Bitte Website testen.'])); exit;
    }
    public function undo(): void {
        $this->guard('sai_undo'); $history = get_option(self::HISTORY_KEY, []);
        if (empty($history['changes']) || !is_array($history['changes'])) { wp_die('Kein Änderungsprotokoll vorhanden.'); }
        global $wpdb; $ok = true;
        foreach ($history['changes'] as $entry) {
            $name = $entry['name'] ?? ''; $value = $entry['autoload'] ?? '';
            if (!$name || !in_array($value, self::AUTOLOAD_VALUES, true)) { $ok=false; continue; }
            $exists = $wpdb->get_var($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name=%s",$name));
            if ($exists === null) { $ok=false; continue; }
            // Restore exact original autoload value, including legacy 'yes'.
            $result = $wpdb->update($wpdb->options, ['autoload'=>$value], ['option_name'=>$name], ['%s'], ['%s']);
            if ($result === false) { $ok=false; }
        }
        if ($ok) { delete_option(self::HISTORY_KEY); wp_cache_delete('alloptions','options'); wp_cache_delete('notoptions','options'); }
        wp_safe_redirect($this->url(['sai_notice'=>$ok?'Letzte Änderung zurückgesetzt.':'Rückgängig nicht vollständig möglich. Bitte manuell prüfen.'])); exit;
    }
    public function export(): void {
        $this->guard('sai_export'); global $wpdb;
        $table = $wpdb->options;
        $filename = 'wordpress-options-'.gmdate('Ymd-His').'.sql';
        nocache_headers(); header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$filename.'"'); header('X-Content-Type-Options: nosniff');
        echo '-- WordPress options backup; confidential: may include secrets'."\n";
        echo '-- Table: '.str_replace('`','``',$table)."\n";
        echo '-- Restore only into a matching WordPress database and prefix'."\n";
        $offset=0; $limit=200;
        do {
            $rows = $wpdb->get_results($wpdb->prepare("SELECT option_id, option_name, option_value, autoload FROM {$table} ORDER BY option_id ASC LIMIT %d OFFSET %d",$limit,$offset), ARRAY_A);
            if (!is_array($rows)) { break; }
            foreach ($rows as $r) {
                $quote = static function ($v) use ($wpdb) { return "'".esc_sql((string)$v)."'"; };
                echo 'INSERT INTO `'.str_replace('`','``',$table).'` (`option_id`,`option_name`,`option_value`,`autoload`) VALUES ('.(int)$r['option_id'].','.$quote($r['option_name']).','.$quote($r['option_value']).','.$quote($r['autoload']).') ON DUPLICATE KEY UPDATE `option_name`=VALUES(`option_name`),`option_value`=VALUES(`option_value`),`autoload`=VALUES(`autoload`);'."\n";
            }
            $offset += count($rows);
        } while (count($rows)===$limit);
        exit;
    }
}
