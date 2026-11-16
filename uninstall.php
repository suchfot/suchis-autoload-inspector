<?php
if (!defined('WP_UNINSTALL_PLUGIN')) { exit; }
delete_option('sai_change_history');
delete_option('sai_change_journal');
