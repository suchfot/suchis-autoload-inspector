<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__ . '/class-sai-catalog.php';
require_once __DIR__ . '/class-sai-view.php';
final class SAI_Admin {
    private const SLUG = 'suchis-autoload-inspector';
    private const HISTORY_KEY = 'sai_change_history';
    private const JOURNAL_KEY = 'sai_change_journal';
    private const VALUES = ['yes','no','on','off','auto','auto-on','auto-off'];
    public function __construct() {
        add_action('admin_menu', [$this,'menu']);
        foreach (['export','change','undo'] as $action) { add_action('admin_post_sai_'.$action, [$this,$action]); }
    }
    public function menu(): void { add_management_page('Autoload Inspector','Autoload Inspector','manage_options',self::SLUG,[$this,'page']); }
    private function guard(string $action): void {
        if (!current_user_can('manage_options')) { wp_die('Keine Berechtigung.','',['response'=>403]); }
        if ($action !== 'sai_export' && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { wp_die('Nur POST-Anfragen sind erlaubt.', '', ['response'=>405]); }
        check_admin_referer($action);
    }
    private function redirect(string $notice): void {
        wp_safe_redirect(add_query_arg(['page'=>self::SLUG,'sai_notice'=>$notice],admin_url('tools.php'))); exit;
    }
    private function protected(string $name): bool {
        $owner = (new SAI_Catalog())->owner($name);
        return $owner['type'] === 'core' || in_array($name,[self::HISTORY_KEY,self::JOURNAL_KEY],true);
    }
    private function invalidate(string $name): void {
        wp_cache_delete($name,'options'); wp_cache_delete('alloptions','options'); wp_cache_delete('notoptions','options');
    }
    private function journal(string $name,string $old,string $new,string $action): void {
        $entries = get_option(self::JOURNAL_KEY,[]);
        if (!is_array($entries)) { $entries = []; }
        $entries[] = ['name'=>$name,'old'=>$old,'new'=>$new,'action'=>$action,'time'=>time(),'user'=>get_current_user_id()];
        update_option(self::JOURNAL_KEY,array_slice($entries,-100),false);
    }
    public function page(): void {
        if (!current_user_can('manage_options')) { wp_die('Keine Berechtigung.'); }
        global $wpdb;
        $items = $wpdb->get_results("SELECT option_name, OCTET_LENGTH(option_value) AS bytes, autoload FROM {$wpdb->options}", ARRAY_A);
        if (!is_array($items)) { wp_die('Options-Metadaten konnten nicht gelesen werden.'); }
        $history = get_option(self::HISTORY_KEY,[]);
        SAI_View::render($items,is_array($history) ? $history : []);
        $entries = get_option(self::JOURNAL_KEY,[]);
        if (is_array($entries) && $entries) {
            echo '<div class="wrap"><details><summary>Änderungsverlauf (letzte 100 Änderungen)</summary><table class="widefat striped"><thead><tr><th>Zeit</th><th>Benutzer-ID</th><th>Option</th><th>Vorher → Nachher</th><th>Aktion</th></tr></thead><tbody>';
            foreach (array_reverse($entries) as $entry) {
                echo '<tr><td>'.esc_html(wp_date('d.m.Y H:i:s',$entry['time'])).'</td><td>'.esc_html((string)$entry['user']).'</td><td><code>'.esc_html($entry['name']).'</code></td><td>'.esc_html($entry['old'].' → '.$entry['new']).'</td><td>'.esc_html($entry['action']).'</td></tr>';
            }
            echo '</tbody></table></details></div>';
        }
    }
    public function change(): void {
        $this->guard('sai_change');
        $name = isset($_POST['option_name']) && is_string($_POST['option_name']) ? wp_unslash($_POST['option_name']) : '';
        $expected = isset($_POST['expected']) && is_string($_POST['expected']) ? wp_unslash($_POST['expected']) : '';
        if (!isset($_POST['enable']) || !in_array($_POST['enable'], ['0','1'], true)) { wp_die('Ungültiger Zielzustand.'); }
        $enable = $_POST['enable'] === '1';
        if (!$name || $this->protected($name) || !in_array($expected,self::VALUES,true)) { wp_die('Option geschützt oder ungültig.'); }
        global $wpdb;
        // Compare-and-swap: never overwrite an intervening autoload change.
        $new = $enable ? 'on' : 'off';
        if ($expected === $new) { wp_die('Keine Änderung erforderlich.'); }
        $result = $wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET autoload=%s WHERE option_name=%s AND autoload=%s",$new,$name,$expected));
        if ($result !== 1) { wp_die('Option wurde zwischenzeitlich geändert oder ist nicht vorhanden. Ansicht neu laden.'); }
        $this->invalidate($name);
        update_option(self::HISTORY_KEY,['changes'=>[['name'=>$name,'autoload'=>$expected,'new'=>$new]],'time'=>time()],false);
        $this->journal($name,$expected,$new,'Änderung');
        $this->redirect('Autoload geändert. Bitte Website und zeitversetzte Funktionen testen.');
    }
    public function undo(): void {
        $this->guard('sai_undo'); $history = get_option(self::HISTORY_KEY,[]);
        $entry = $history['changes'][0] ?? []; $name = $entry['name'] ?? ''; $old = $entry['autoload'] ?? ''; $new = $entry['new'] ?? '';
        if (!$name || $this->protected($name) || !in_array($old,self::VALUES,true) || !in_array($new,self::VALUES,true)) { wp_die('Kein gültiger Rückgängig-Schritt vorhanden. Ältere Protokolle werden nicht automatisch zurückgespielt.'); }
        global $wpdb;
        $result = $wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET autoload=%s WHERE option_name=%s AND autoload=%s",$old,$name,$new));
        if ($result !== 1) { wp_die('Rückgängig abgebrochen: Option wurde inzwischen geändert oder entfernt.'); }
        $this->invalidate($name); delete_option(self::HISTORY_KEY); $this->journal($name,$new,$old,'Rückgängig');
        $this->redirect('Letzte Änderung zurückgesetzt.');
    }
    public function export(): void {
        $this->guard('sai_export'); global $wpdb;
        nocache_headers(); header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="wordpress-options-'.gmdate('Ymd-His').'.sql"');
        header('X-Content-Type-Options: nosniff');
        echo "-- Confidential WordPress options snapshot; contains secrets.\n-- Restore only to matching database/table. Does not delete extra rows.\n";
        $table = str_replace('`','``',$wpdb->options); $last = 0;
        do {
            $rows = $wpdb->get_results($wpdb->prepare("SELECT option_id,option_name,option_value,autoload FROM {$wpdb->options} WHERE option_id > %d ORDER BY option_id LIMIT 200",$last),ARRAY_A);
            if (!is_array($rows)) { echo "-- ERROR: export incomplete\n"; break; }
            foreach ($rows as $row) {
                // Hex literals preserve arbitrary bytes without SQL-mode-dependent escaping.
                $hex = static function ($value) { return "X'".bin2hex((string)$value)."'"; };
                echo 'INSERT INTO `'.$table.'` (`option_id`,`option_name`,`option_value`,`autoload`) VALUES ('.(int)$row['option_id'].','.$hex($row['option_name']).','.$hex($row['option_value']).','.$hex($row['autoload']).') ON DUPLICATE KEY UPDATE `option_name`=VALUES(`option_name`),`option_value`=VALUES(`option_value`),`autoload`=VALUES(`autoload`);'."\n";
                $last = (int)$row['option_id'];
            }
        } while (count($rows) === 200);
        exit;
    }
}
