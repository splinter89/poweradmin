<?php

require_once "inc/toolkit.inc.php";

require "inc/header.inc.php";
echo "<script type=\"text/javascript\" src=\"inc/helper.js\"></script>";

$zone_master_add = (bool)do_hook('verify_permission', 'zone_master_add');

if (isset($_POST['submit_btn']) && $zone_master_add) {
    $record_name = $_POST['record_name'];
    if (empty($_POST['ips'])) {
        $_POST['ips'] = [];
    }
    $new_ips = array_filter(array_filter(array_map('trim',
        explode("\n", str_replace(["\r\n", "\r", "\n"], "\n", $_POST['new_ips']))
    )), function ($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP);
    });
    $ips = array_unique(array_merge($_POST['ips'], $new_ips));
    $priority = (int)$_POST['priority'];
    $ttl = (int)$_POST['ttl'];

    // try to find domain_id by record_name
    list($records_by_id) = get_info_for_bulk_edit();
    $domain_id = '';
    foreach ($records_by_id as $record) {
        if ($record['name'] != $record_name) continue;

        if (!empty($domain_id) && ($domain_id != $record['domain_id'])) {
            error('Found records with the given name that belong to multiple zones');
            die;
        }

        $domain_id = $record['domain_id'];
    }
    if (empty($domain_id)) {
        error('No records found with the given name');
        die;
    }
    $zones_by_name = get_zones('all');
    $zones_by_id = array_column($zones_by_name, null, 'id');
    if (empty($zones_by_id[$domain_id])) {
        error('Zone not found');
        die;
    }
    $zone = $zones_by_id[$domain_id];
    unset($zones_by_name);
    unset($zones_by_id);

    $type = 'A';
    $records = array_filter($records_by_id, function ($record) use ($domain_id, $record_name) {
        return ($record['domain_id'] == $domain_id)
            && ($record['name'] == $record_name);
    });
    $existing_ips = array_unique(array_column($records, 'content'));
    $name = substr($record_name, 0, strpos($record_name, '.'.$zone['name']));

    $ips_to_add = array_diff($ips, $existing_ips);
    $ips_to_remove = array_diff($existing_ips, $ips);

    foreach ($ips_to_add as $ip) {
        if (add_record($zone['id'], $name, $type, $ip, $ttl, $priority)) {
            success(" <a href=\"edit.php?id=".$zone['id']."\"> "._('The record was successfully added.')." ($ip)</a>");
            log_info(sprintf('client_ip:%s user:%s operation:add_record record_type:%s record:%s.%s content:%s ttl:%s priority:%s',
                $_SERVER['REMOTE_ADDR'], $_SESSION["userlogin"],
                $type, $name, $zone['name'], $ip, $ttl, $priority));
        }
    }

    foreach ($ips_to_remove as $ip) {
        foreach ($records as $record) {
            if ($record['content'] != $ip) continue;

            $record_info = get_record_from_id($record['id']);
            if (delete_record($record['id'])) {
                success("<a href=\"edit.php?id=".$zone['id']."\">".SUC_RECORD_DEL." ($ip)</a>");
                if (isset($record_info['prio'])) {
                    log_info(sprintf('client_ip:%s user:%s operation:delete_record record_type:%s record:%s content:%s ttl:%s priority:%s',
                        $_SERVER['REMOTE_ADDR'], $_SESSION["userlogin"],
                        $record_info['type'], $record_info['name'], $record_info['content'], $record_info['ttl'], $record_info['prio']));
                } else {
                    log_info(sprintf('client_ip:%s user:%s operation:delete_record record_type:%s record:%s content:%s ttl:%s',
                        $_SERVER['REMOTE_ADDR'], $_SESSION["userlogin"],
                        $record_info['type'], $record_info['name'], $record_info['content'], $record_info['ttl']));
                }

                delete_record_zone_templ($record['id']);
            }
        }
    }

    update_soa_serial($zone['id']);
} else {
    $_POST['selected_domain_id'] = (!empty($_POST['selected_domain_id'])) ? (int)$_POST['selected_domain_id'] : 0;
    if (empty($_POST['selected_ip'])) {
        $_POST['selected_ip'] = '';
    }
    if (empty($_POST['record_name'])) {
        $_POST['record_name'] = '';
    }
    if (empty($_POST['new_ips'])) {
        $_POST['new_ips'] = '';
    }
    $_POST['priority'] = (!empty($_POST['priority'])) ? (int)$_POST['priority'] : 0;
    $_POST['ttl'] = (!empty($_POST['ttl'])) ? (int)$_POST['ttl'] : 300;
}

if (!$zone_master_add) {
    error(ERR_PERM_EDIT_RECORD);
} else {
    echo "     <h2>"._('Bulk edit')."</h2>";

    $zones_by_name = get_zones('all');
    $zone_names_by_id = array_column($zones_by_name, 'name', 'id');
    unset($zones_by_name);
    asort($zone_names_by_id);

    list($records_by_id, $records_by_domain_id, $records_by_ip) = get_info_for_bulk_edit();

    if (!empty($_POST['selected_domain_id']) && isset($records_by_domain_id[$_POST['selected_domain_id']])) {
        $selected_records = $records_by_domain_id[$_POST['selected_domain_id']];
    } elseif (!empty($_POST['selected_ip']) && isset($records_by_ip[$_POST['selected_ip']])) {
        $selected_records = $records_by_ip[$_POST['selected_ip']];
    } else {
        $selected_records = $records_by_id; // all
    }

    $record_names = array_unique(array_column($selected_records, 'name'));
    sort($record_names);

    $ips_by_record_name = array_fill_keys($record_names, []);
    foreach ($records_by_ip as $ip => $records) {
        foreach ($records as $record) {
            $ips_by_record_name[$record['name']][] = $ip;
        }
    }
    $ips_by_record_name = array_map('array_unique', $ips_by_record_name);

    echo "     <form method=\"post\" action=\"bulk_edit.php\">";
    echo "      <table>";
    echo "       <tr>";
    echo "        <td class=\"n\" width=\"100\">"._('Filter').":</td>";
    echo "        <td class=\"n\">";
    echo "         <select name=\"selected_domain_id\" onchange=\"this.form.selected_ip.value=''; this.form.record_name.value=''; this.form.submit();\">";
    echo "          <option value=\"\">all zones</option>";
    foreach ($zone_names_by_id as $domain_id => $name) {
        $cnt = count(array_unique(array_column($records_by_domain_id[$domain_id], 'name')));
        echo "          <option value=\"$domain_id\" ".($domain_id == $_POST['selected_domain_id'] ? 'selected' : '').">$name ($cnt)</option>";
    }
    echo "         </select>";
    echo " and ";
    echo "         <select name=\"selected_ip\" onchange=\"this.form.selected_domain_id.value=''; this.form.record_name.value=''; this.form.submit();\">";
    echo "          <option value=\"\">all IPs</option>";
    foreach ($records_by_ip as $ip => $records) {
        $cnt = count(array_unique(array_column($records, 'name')));
        echo "          <option value=\"$ip\" ".($ip == $_POST['selected_ip'] ? 'selected' : '').">$ip ($cnt)</option>";
    }
    echo "         </select>";
    echo "        </td>";
    echo "       </tr>";

    echo "       <tr>";
    echo "        <td class=\"n\" width=\"100\">"._('Record name').":</td>";
    echo "        <td class=\"n\">";
    echo "         <select name=\"record_name\" onchange=\"this.form.submit();\">";
    echo "          <option value=\"\">none</option>";
    foreach ($record_names as $record_name) {
        echo "          <option value=\"".$record_name."\" ".($record_name == $_POST['record_name'] ? 'selected' : '').">".$record_name."</option>";
    }
    echo "         </select>";
    echo "        </td>";
    echo "       </tr>";

    echo "       <tr>";
    echo "        <td class=\"n\">"._('IPs').":</td>";
    echo "        <td class=\"n\">";
    foreach (array_keys($records_by_ip) as $ip) {
        echo '<label><input type="checkbox" name="ips[]" value="'.$ip.'" '
            .(empty($_POST['record_name']) ? 'disabled="disabled"' : '')
            .(!empty($_POST['record_name']) && !empty($ips_by_record_name[$_POST['record_name']]) && in_array($ip, $ips_by_record_name[$_POST['record_name']]) ? 'checked="checked"' : '')
            .'>'.$ip.'</label><br>';
    }
    echo "<input type=\"button\" onclick=\"checkBulkEditIPs(true);\" value=\"select all\""
        .(empty($_POST['record_name']) ? 'disabled="disabled"' : '')
        ."/>";
    echo "<input type=\"button\" onclick=\"checkBulkEditIPs(false);\" value=\"none\" "
        .(empty($_POST['record_name']) ? 'disabled="disabled"' : '')
        ."/>";
    echo "        </td>";
    echo "       </tr>";

    echo "       <tr>";
    echo "        <td class=\"n\">"._('New IPs').":</td>";
    echo "        <td class=\"n\">";
    echo "         <div>"._('Type one IP per line')."</div>";
    echo "          <textarea class=\"input\" name=\"new_ips\" rows=\"10\" cols=\"30\" style=\"width: 500px;\">";
    if (!empty($_POST['new_ips'])) {
        echo $_POST['new_ips'];
    }
    echo "</textarea>";
    echo "        </td>";
    echo "       </tr>";

    echo "       <tr>";
    echo "        <td class=\"n\">"._('Priority').":</td>";
    echo "        <td class=\"n\">";
    echo '         <input type="text" name="priority" value="'.$_POST['priority'].'">';
    echo "        </td>";
    echo "       </tr>";

    echo "       <tr>";
    echo "        <td class=\"n\">"._('TTL').":</td>";
    echo "        <td class=\"n\">";
    echo '         <input type="text" name="ttl" value="'.$_POST['ttl'].'">';
    echo "        </td>";
    echo "       </tr>";

    echo "       <tr>";
    echo "        <td class=\"n\">&nbsp;</td>";
    echo "        <td class=\"n\">";
    echo "         <input type=\"submit\" class=\"button\" name=\"submit_btn\" value=\""._('Submit')."\" ".(empty($_POST['record_name']) ? 'disabled="disabled"' : '').">";
    echo "        </td>";
    echo "       </tr>";
    echo "      </table>";
    echo "     </form>";
}

require "inc/footer.inc.php";
