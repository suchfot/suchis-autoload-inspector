<?php
if (!defined('ABSPATH')) { exit; }
final class SAI_View {
    public static function render(array $items, array $history): void {
        $catalog = new SAI_Catalog();
        $get = static function ($key) { return isset($_GET[$key]) && is_string($_GET[$key]) ? sanitize_text_field(wp_unslash($_GET[$key])) : ''; };
        $search = $get('sai_search'); $type = $get('sai_type'); $owner = $get('sai_owner');
        $status = $get('sai_status'); $autoload = $get('sai_autoload') ?: 'on';
        $sort = $get('sai_sort'); $min = max(0, (int)$get('sai_min'));
        $types = ['core'=>'WordPress-Core','plugin'=>'Plugin','theme'=>'Theme','unknown'=>'Unbekannt'];
        $owners = []; $statuses = []; $suggestions = []; $total = 0; $count = 0;
        foreach ($items as &$item) {
            $item = array_merge($item, $catalog->owner($item['option_name']));
            $item['on'] = in_array($item['autoload'], wp_autoload_values_to_autoload(), true);
            $item['protected'] = $item['type'] === 'core' || in_array($item['option_name'], ['sai_change_history','sai_change_journal'], true);
            $owners[$item['owner']] = $item['owner']; $statuses[$item['status']] = $item['status'];
            $suggestions[$item['option_name']] = true; $suggestions[$item['owner']] = true;
            if ($item['on']) { $total += (int)$item['bytes']; $count++; }
        }
        unset($item); natcasesort($owners); natcasesort($statuses);
        $filtered = array_values(array_filter($items, static function ($item) use ($search,$type,$owner,$status,$autoload,$min,$types) {
            $text = implode(' ', [$item['option_name'], $types[$item['type']], $item['owner'], $item['status'], $item['confidence'], $item['autoload'], (string)$item['bytes']]);
            foreach (preg_split('/\s+/u', trim($search), -1, PREG_SPLIT_NO_EMPTY) as $term) { if (stripos($text, $term) === false) { return false; } }
            return (!$type || $item['type'] === $type) && (!$owner || $item['owner'] === $owner)
                && (!$status || $item['status'] === $status) && (int)$item['bytes'] >= $min
                && ($autoload === 'all' || ($autoload === 'off' ? !$item['on'] : $item['on']));
        }));
        usort($filtered, static function ($a,$b) use ($sort) { return $sort === 'name' ? strcmp($a['option_name'],$b['option_name'])  : (((int)$b['bytes'] <=> (int)$a['bytes']) ?: strcmp($a['option_name'],$b['option_name'])); });
        $sum = array_sum(array_column($filtered, 'bytes')); $groups = [];
        foreach ($filtered as $item) {
            $key = $types[$item['type']] . ': ' . $item['owner'];
            if (!isset($groups[$key])) { $groups[$key] = ['bytes'=>0,'count'=>0]; }
            $groups[$key]['bytes'] += (int)$item['bytes']; $groups[$key]['count']++;
        }
        uasort($groups, static function ($a,$b) { return $b['bytes'] <=> $a['bytes']; });
        $pages = max(1, (int)ceil(count($filtered)/50)); $page = min($pages, max(1, (int)$get('paged')));
        $select = static function ($name,$label,$choices,$value) {
            echo '<label>'.esc_html($label).' <select name="'.esc_attr($name).'">';
            foreach ($choices as $key=>$text) { echo '<option value="'.esc_attr($key).'"'.selected($value,(string)$key,false).'>'.esc_html($text).'</option>'; }
            echo '</select></label> ';
        };
        echo '<div class="wrap"><h1>Suchis Autoload Inspector</h1>';
        if ($get('sai_notice')) { echo '<div class="notice notice-info"><p>'.esc_html($get('sai_notice')).'</p></div>'; }
        echo '<div class="notice notice-warning"><p>Vor Änderungen vollständiges Website-Backup erstellen. Eine Zuordnung ist kein Beweis für Eigentum oder Entbehrlichkeit. Inaktive Plugins können weiterhin benötigte Optionen besitzen.</p></div>';
        echo '<p><strong>'.esc_html(number_format_i18n($count)).' automatisch geladene Optionen · '.esc_html(size_format($total)).'</strong></p>';
        echo '<p><a class="button" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=sai_export'),'sai_export')).'">Optionstabelle als SQL exportieren</a> Enthält möglicherweise Zugangsdaten! Kein Ersatz für ein vollständiges Backup.</p>';
        if (!empty($history['changes'])) {
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'; wp_nonce_field('sai_undo');
            echo '<input type="hidden" name="action" value="sai_undo"><button class="button">Letzte Änderung rückgängig machen</button></form>';
        }
        echo '<form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin:16px 0"><input type="hidden" name="page" value="suchis-autoload-inspector"><label>Suche <input type="search" name="sai_search" list="sai-suggestions" placeholder="Option, Plugin, Theme, Herkunft …" value="'.esc_attr($search).'"></label><datalist id="sai-suggestions">';
        foreach (array_keys($suggestions) as $suggestion) { echo '<option value="'.esc_attr($suggestion).'"></option>'; }
        echo '</datalist>';
        $select('sai_type','Herkunft',[''=>'Alle']+$types,$type);
        $select('sai_owner','Anbieter',[''=>'Alle']+$owners,$owner);
        $select('sai_status','Status',[''=>'Alle']+$statuses,$status);
        $select('sai_autoload','Autoload',['on'=>'An','off'=>'Aus','all'=>'Alle'],$autoload);
        $select('sai_sort','Sortierung',['size'=>'Größe absteigend','name'=>'Name aufsteigend'],$sort ?: 'size');
        echo '<label>Ab Bytes <input style="width:100px" type="number" min="0" name="sai_min" value="'.esc_attr((string)$min).'"></label><button class="button button-primary">Filtern</button><a href="'.esc_url(admin_url('tools.php?page=suchis-autoload-inspector')).'">Zurücksetzen</a></form>';
        echo '<p>Suche über alle Optionsnamen und Metadaten, nicht über geheime Optionswerte. Mehrere Suchwörter werden mit UND verknüpft; Vorschläge beziehen sich auf den gesamten Bestand.</p>';
        echo '<p><strong>Auswahl: '.esc_html(number_format_i18n(count($filtered))).' Optionen · '.esc_html(size_format($sum)).'</strong> (Optionswerte, ohne Speicher-Overhead)</p>';
        echo '<details><summary>Gruppensummen der aktuellen Auswahl</summary><table class="widefat striped"><thead><tr><th>Herkunft / Anbieter</th><th>Anzahl</th><th>Größe</th><th>Anteil der Auswahl</th></tr></thead><tbody>';
        foreach ($groups as $label=>$group) { echo '<tr><td>'.esc_html($label).'</td><td>'.esc_html((string)$group['count']).'</td><td>'.esc_html(size_format($group['bytes'])).'</td><td>'.esc_html(number_format_i18n($sum ? 100*$group['bytes']/$sum : 0,1)).' %</td></tr>'; }
        echo '</tbody></table></details><table class="widefat striped" style="margin-top:16px"><thead><tr><th>Option</th><th>Herkunft / Anbieter</th><th>Zuordnung</th><th>Status</th><th>Größe</th><th>Autoload</th><th>Aktion</th></tr></thead><tbody>';
        foreach (array_slice($filtered, ($page-1)*50,50) as $item) {
            echo '<tr><td><code>'.esc_html($item['option_name']).'</code></td><td>'.esc_html($types[$item['type']]).'<br>'.esc_html($item['owner']).'</td><td>'.esc_html($item['confidence']).'</td><td>'.esc_html($item['status']).'</td><td title="'.esc_attr($item['bytes'].' Bytes').'">'.esc_html(size_format((int)$item['bytes'])).'</td><td>'.esc_html($item['autoload']).'</td><td>';
            if ($item['protected']) { echo 'Geschützt'; }
            else {
                echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" onsubmit="return confirm(\'Autoload ändern? Backup erstellen und Website anschließend testen.\')">'; wp_nonce_field('sai_change');
                echo '<input type="hidden" name="action" value="sai_change"><input type="hidden" name="option_name" value="'.esc_attr($item['option_name']).'"><input type="hidden" name="enable" value="'.($item['on'] ? '0' : '1').'"><input type="hidden" name="expected" value="'.esc_attr($item['autoload']).'"><button class="button button-small">Autoload '.($item['on'] ? 'aus' : 'an').'</button></form>';
            }
            echo '</td></tr>';
        }
        if (!$filtered) { echo '<tr><td colspan="7">Keine Optionen gefunden.</td></tr>'; }
        echo '</tbody></table><p>Seite '.esc_html((string)$page).' von '.esc_html((string)$pages).'</p>';
        $args = ['page'=>'suchis-autoload-inspector','sai_search'=>$search,'sai_type'=>$type,'sai_owner'=>$owner,'sai_status'=>$status,'sai_autoload'=>$autoload,'sai_min'=>$min,'sai_sort'=>$sort];
        foreach (['Erste'=>1,'Zurück'=>max(1,$page-1),'Weiter'=>min($pages,$page+1),'Letzte'=>$pages] as $label=>$target) { echo '<a class="button" style="margin-right:8px" href="'.esc_url(add_query_arg($args+['paged'=>$target],admin_url('tools.php'))).'">'.esc_html($label).'</a>'; }
        echo '</div>';
    }
}
