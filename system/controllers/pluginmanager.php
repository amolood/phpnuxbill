<?php

/**
 *  PHP Mikrotik Billing (https://github.com/hotspotbilling/phpnuxbill/)
 *  by https://t.me/ibnux
 **/

_admin();
$ui->assign('_title', 'Plugin Manager');
$ui->assign('_system_menu', 'settings');

$plugin_repository = 'https://hotspotbilling.github.io/Plugin-Repository/repository.json';

$action = $routes['1'];
$ui->assign('_admin', $admin);


if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
    _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
}

$cache = $CACHE_PATH . File::pathFixer('/plugin_repository.json');
if (file_exists($cache) && time() - filemtime($cache) < (24 * 60 * 60)) {
    $txt = file_get_contents($cache);
    $json = json_decode($txt, true);
    if (empty($json['plugins']) && empty($json['payment_gateway'])) {
        unlink($cache);
        r2(U . 'pluginmanager');
    }
} else {
    $data = Http::getData($plugin_repository);
    file_put_contents($cache, $data);
    $json = json_decode($data, true);
}
switch ($action) {
    case 'refresh':
        if (file_exists($cache)) unlink($cache);
        r2(U . "pluginmanager", 's', 'Refresh success');
        break;
    case 'enable':
        // Get the plugin directory from the route
        $plugin_dir = $routes[2];
        $plugins_json_path = File::pathFixer($PLUGIN_PATH . DIRECTORY_SEPARATOR . 'plugins.json');
        $existing_plugins = [];

        // Load the existing plugins from plugins.json
        if (file_exists($plugins_json_path)) {
            $existing_plugins = json_decode(file_get_contents($plugins_json_path), true);
        }

        // Update the status of the plugin to 'active'
        $plugin_found = false;
        foreach ($existing_plugins as $key => $existing_plugin) {
            if ($existing_plugin['dir'] === $plugin_dir) {
                $existing_plugins[$key]['status'] = 'active';
                $plugin_found = true;
                break;
            }
        }

        // Save the updated plugins back to plugins.json
        if ($plugin_found) {
            file_put_contents($plugins_json_path, json_encode($existing_plugins, JSON_PRETTY_PRINT));
            r2(U . "pluginmanager", 's', 'Plugin enabled successfully');
        } else {
            r2(U . "pluginmanager", 'e', 'Plugin not found');
        }
        break;
    case 'disable':
        // Get the plugin directory from the route
        $plugin_dir = $routes[2];
        $plugins_json_path = File::pathFixer($PLUGIN_PATH . DIRECTORY_SEPARATOR . 'plugins.json');
        $existing_plugins = [];

        // Load the existing plugins from plugins.json
        if (file_exists($plugins_json_path)) {
            $existing_plugins = json_decode(file_get_contents($plugins_json_path), true);
        }

        // Update the status of the plugin to 'inactive'
        $plugin_found = false;
        foreach ($existing_plugins as $key => $existing_plugin) {
            if ($existing_plugin['dir'] === $plugin_dir) {
                $existing_plugins[$key]['status'] = 'inactive';
                $plugin_found = true;
                break;
            }
        }

        // Save the updated plugins back to plugins.json
        if ($plugin_found) {
            file_put_contents($plugins_json_path, json_encode($existing_plugins, JSON_PRETTY_PRINT));
            r2(U . "pluginmanager", 's', 'Plugin disabled successfully');
        } else {
            r2(U . "pluginmanager", 'e', 'Plugin not found');
        }
        break;
    case 'update':
        // update the plugin


        // update the plugin
        break;
    case 'dlinstall':
        if ($_app_stage == 'demo') {
            r2(U . "pluginmanager", 'e', 'Demo Mode cannot install as it Security risk');
        }
        if (!is_writeable($CACHE_PATH)) {
            r2(U . "pluginmanager", 'e', 'Folder cache/ is not writable');
        }
        if (!is_writeable($PLUGIN_PATH)) {
            r2(U . "pluginmanager", 'e', 'Folder plugin/ is not writable');
        }
        if (!is_writeable($DEVICE_PATH)) {
            r2(U . "pluginmanager", 'e', 'Folder devices/ is not writable');
        }
        if (!is_writeable($UI_PATH . DIRECTORY_SEPARATOR . 'themes')) {
            r2(U . "pluginmanager", 'e', 'Folder themes/ is not writable');
        }
        $cache = $CACHE_PATH . DIRECTORY_SEPARATOR . 'installer' . DIRECTORY_SEPARATOR;
        if (!file_exists($cache)) {
            mkdir($cache);
        }
        if (file_exists($_FILES['zip_plugin']['tmp_name'])) {
            $zip = new ZipArchive();
            $zip->open($_FILES['zip_plugin']['tmp_name']);
            $zip->extractTo($cache);
            $zip->close();
            $plugin = basename($_FILES['zip_plugin']['name']);
            unlink($_FILES['zip_plugin']['tmp_name']);
            $success = 0;
            //moving
            if (file_exists($cache . 'plugin')) {
                File::copyFolder($cache . 'plugin' . DIRECTORY_SEPARATOR, $PLUGIN_PATH . DIRECTORY_SEPARATOR);
                $success++;
            }
            if (file_exists($cache . 'paymentgateway')) {
                File::copyFolder($cache . 'paymentgateway' . DIRECTORY_SEPARATOR, $PAYMENTGATEWAY_PATH . DIRECTORY_SEPARATOR);
                $success++;
            }
            if (file_exists($cache . 'theme')) {
                File::copyFolder($cache . 'theme' . DIRECTORY_SEPARATOR, $UI_PATH . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR);
                $success++;
            }
            if (file_exists($cache . 'device')) {
                File::copyFolder($cache . 'device' . DIRECTORY_SEPARATOR, $DEVICE_PATH . DIRECTORY_SEPARATOR);
                $success++;
            }
            if ($success == 0) {
                // old plugin and theme using this
                $check = strtolower($ghUrl);
                if (strpos($check, 'plugin') !== false) {
                    File::copyFolder($folder, $PLUGIN_PATH . DIRECTORY_SEPARATOR);
                } else if (strpos($check, 'payment') !== false) {
                    File::copyFolder($folder, $PAYMENTGATEWAY_PATH . DIRECTORY_SEPARATOR);
                } else if (strpos($check, 'theme') !== false) {
                    rename($folder, $UI_PATH . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . $plugin);
                } else if (strpos($check, 'device') !== false) {
                    File::copyFolder($folder, $DEVICE_PATH . DIRECTORY_SEPARATOR);
                }
            }
            //Cleaning
            File::deleteFolder($cache);
            r2(U . "pluginmanager", 's', 'Installation success');
        } else if (_post('gh_url', '') != '') {
            $ghUrl = _post('gh_url', '');
            if (!empty($config['github_token']) && !empty($config['github_username'])) {
                $ghUrl = str_replace('https://github.com', 'https://' . $config['github_username'] . ':' . $config['github_token'] . '@github.com', $ghUrl);
            }
            $plugin = basename($ghUrl);
            $file = $cache . $plugin . '.zip';
            $fp = fopen($file, 'w+');
            $ch = curl_init($ghUrl . '/archive/refs/heads/master.zip');
            curl_setopt($ch, CURLOPT_POST, 0);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_exec($ch);
            curl_close($ch);
            fclose($fp);
            $zip = new ZipArchive();
            $zip->open($file);
            $zip->extractTo($cache);
            $zip->close();
            $folder = $cache . DIRECTORY_SEPARATOR . $plugin . '-main' . DIRECTORY_SEPARATOR;
            if (!file_exists($folder)) {
                $folder = $cache . DIRECTORY_SEPARATOR . $plugin . '-master' . DIRECTORY_SEPARATOR;
            }
            $success = 0;
            if (file_exists($folder . 'plugin')) {
                File::copyFolder($folder . 'plugin' . DIRECTORY_SEPARATOR, $PLUGIN_PATH . DIRECTORY_SEPARATOR);
                $success++;
            }
            if (file_exists($folder . 'paymentgateway')) {
                File::copyFolder($folder . 'paymentgateway' . DIRECTORY_SEPARATOR, $PAYMENTGATEWAY_PATH . DIRECTORY_SEPARATOR);
                $success++;
            }
            if (file_exists($folder . 'theme')) {
                File::copyFolder($folder . 'theme' . DIRECTORY_SEPARATOR, $UI_PATH . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR);
                $success++;
            }
            if (file_exists($folder . 'device')) {
                File::copyFolder($folder . 'device' . DIRECTORY_SEPARATOR, $DEVICE_PATH . DIRECTORY_SEPARATOR);
                $success++;
            }
            if ($success == 0) {
                // old plugin and theme using this
                $check = strtolower($ghUrl);
                if (strpos($check, 'plugin') !== false) {
                    File::copyFolder($folder, $PLUGIN_PATH . DIRECTORY_SEPARATOR);
                } else if (strpos($check, 'payment') !== false) {
                    File::copyFolder($folder, $PAYMENTGATEWAY_PATH . DIRECTORY_SEPARATOR);
                } else if (strpos($check, 'theme') !== false) {
                    rename($folder, $UI_PATH . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . $plugin);
                } else if (strpos($check, 'device') !== false) {
                    File::copyFolder($folder, $DEVICE_PATH . DIRECTORY_SEPARATOR);
                }
            }
            File::deleteFolder($cache);
            r2(U . "pluginmanager", 's', 'Installation success');
        } else {
            r2(U . 'pluginmanager', 'e', 'Nothing Installed');
        }
        break;
    case 'delete':
        // function to delete plugin from the plugins.json and delete the plugin folder
        $plugin_dir = $routes[2];
        $plugins_json_path = File::pathFixer($PLUGIN_PATH . DIRECTORY_SEPARATOR . 'plugins.json');
        $existing_plugins = [];

        // Load the existing plugins from plugins.json
        if (file_exists($plugins_json_path)) {
            $existing_plugins = json_decode(file_get_contents($plugins_json_path), true);
        }

        // Remove the plugin from the plugins.json
        $plugin_found = false;
        foreach ($existing_plugins as $key => $existing_plugin) {
            if ($existing_plugin['dir'] === $plugin_dir) {
                unset($existing_plugins[$key]);
                $plugin_found = true;
                break;
            }
        }

        // Save the updated plugins back to plugins.json
        if ($plugin_found) {
            file_put_contents($plugins_json_path, json_encode($existing_plugins, JSON_PRETTY_PRINT));
        } else {
            r2(U . "pluginmanager", 'e', 'Plugin not found');
        }

        // Delete the plugin folder
        $plugin_folder = File::pathFixer($PLUGIN_PATH . DIRECTORY_SEPARATOR . $plugin_dir);
        if (file_exists($plugin_folder)) {
            scanAndRemovePath($plugin_folder, $plugin_folder);
        }

        r2(U . "pluginmanager", 's', 'Plugin deleted successfully');

        break;
    case 'install':
        if (!is_writeable($CACHE_PATH)) {
            r2(U . "pluginmanager", 'e', 'Folder cache/ is not writable');
        }
        if (!is_writeable($PLUGIN_PATH)) {
            r2(U . "pluginmanager", 'e', 'Folder plugin/ is not writable');
        }
        set_time_limit(-1);
        $tipe = $routes['2'];
        $plugin = $routes['3'];
        $file = $CACHE_PATH . DIRECTORY_SEPARATOR . $plugin . '.zip';
        if (file_exists($file)) unlink($file);
        if ($tipe == 'plugin') {
            foreach ($json['plugins'] as $plg) {
                if ($plg['id'] == $plugin) {
                    if (!empty($config['github_token']) && !empty($config['github_username'])) {
                        $plg['github'] = str_replace('https://github.com', 'https://' . $config['github_username'] . ':' . $config['github_token'] . '@github.com', $plg['github']);
                    }
                    $fp = fopen($file, 'w+');
                    $ch = curl_init($plg['github'] . '/archive/refs/heads/master.zip');
                    curl_setopt($ch, CURLOPT_POST, 0);
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_FILE, $fp);
                    curl_exec($ch);
                    curl_close($ch);
                    fclose($fp);

                    $zip = new ZipArchive();
                    $zip->open($file);
                    $zip->extractTo($CACHE_PATH);
                    $zip->close();
                    $folder = $CACHE_PATH . File::pathFixer('/' . $plugin . '-main/');
                    if (!file_exists($folder)) {
                        $folder = $CACHE_PATH . File::pathFixer('/' . $plugin . '-master/');
                    }
                    if (!file_exists($folder)) {
                        r2(U . "pluginmanager", 'e', 'Extracted Folder is unknown');
                    }
                    File::copyFolder($folder, $PLUGIN_PATH . DIRECTORY_SEPARATOR, ['README.md', 'LICENSE']);
                    File::deleteFolder($folder);
                    unlink($file);
                    r2(U . "pluginmanager", 's', 'Plugin ' . $plugin . ' has been installed');
                    break;
                }
            }
            break;
        } else if ($tipe == 'payment') {
            foreach ($json['payment_gateway'] as $plg) {
                if ($plg['id'] == $plugin) {
                    if (!empty($config['github_token']) && !empty($config['github_username'])) {
                        $plg['github'] = str_replace('https://github.com', 'https://' . $config['github_username'] . ':' . $config['github_token'] . '@github.com', $plg['github']);
                    }
                    $fp = fopen($file, 'w+');
                    $ch = curl_init($plg['github'] . '/archive/refs/heads/master.zip');
                    curl_setopt($ch, CURLOPT_POST, 0);
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_FILE, $fp);
                    curl_exec($ch);
                    curl_close($ch);
                    fclose($fp);

                    $zip = new ZipArchive();
                    $zip->open($file);
                    $zip->extractTo($CACHE_PATH);
                    $zip->close();
                    $folder = $CACHE_PATH . File::pathFixer('/' . $plugin . '-main/');
                    if (!file_exists($folder)) {
                        $folder = $CACHE_PATH . File::pathFixer('/' . $plugin . '-master/');
                    }
                    if (!file_exists($folder)) {
                        r2(U . "pluginmanager", 'e', 'Extracted Folder is unknown');
                    }
                    File::copyFolder($folder, $PAYMENTGATEWAY_PATH . DIRECTORY_SEPARATOR, ['README.md', 'LICENSE']);
                    File::deleteFolder($folder);
                    unlink($file);
                    r2(U . "paymentgateway", 's', 'Payment Gateway ' . $plugin . ' has been installed');
                    break;
                }
            }
            break;
        } else if ($tipe == 'device') {
            foreach ($json['devices'] as $d) {
                if ($d['id'] == $plugin) {
                    if (!empty($config['github_token']) && !empty($config['github_username'])) {
                        $d['github'] = str_replace('https://github.com', 'https://' . $config['github_username'] . ':' . $config['github_token'] . '@github.com', $d['github']);
                    }
                    $fp = fopen($file, 'w+');
                    $ch = curl_init($d['github'] . '/archive/refs/heads/master.zip');
                    curl_setopt($ch, CURLOPT_POST, 0);
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_FILE, $fp);
                    curl_exec($ch);
                    curl_close($ch);
                    fclose($fp);

                    $zip = new ZipArchive();
                    $zip->open($file);
                    $zip->extractTo($CACHE_PATH);
                    $zip->close();
                    $folder = $CACHE_PATH . File::pathFixer('/' . $plugin . '-main/');
                    if (!file_exists($folder)) {
                        $folder = $CACHE_PATH . File::pathFixer('/' . $plugin . '-master/');
                    }
                    if (!file_exists($folder)) {
                        r2(U . "pluginmanager", 'e', 'Extracted Folder is unknown');
                    }
                    File::copyFolder($folder, $DEVICE_PATH . DIRECTORY_SEPARATOR, ['README.md', 'LICENSE']);
                    File::deleteFolder($folder);
                    unlink($file);
                    r2(U . "settings/devices", 's', 'Device ' . $plugin . ' has been installed');
                    break;
                }
            }
            break;
        }
    default:
        if (class_exists('ZipArchive')) {
            $zipExt = true;
        } else {
            $zipExt = false;
        }
        $InstalledPlugin = [];
        // list all installed plugin
        $InstalledPlugin = get_plugin_files($PLUGIN_PATH);
        $ui->assign('InstalledPlugin', $InstalledPlugin);
        $ui->assign('zipExt', $zipExt);
        $ui->assign('plugins', $json['plugins']);
        $ui->assign('pgs', $json['payment_gateway']);
        $ui->assign('dvcs', $json['devices']);
        $ui->display('plugin-manager.tpl');
}

function get_plugin_files($directory)
{
    $plugin_files = [];

    // Load existing plugins.json from the main directory
    $plugins_json_path = "$directory/plugins.json";
    $existing_plugins = [];

    if (file_exists($plugins_json_path)) {
        $existing_plugins = json_decode(file_get_contents($plugins_json_path), true);
    }

    // Iterate over the plugins listed in plugins.json
    foreach ($existing_plugins as $existing_plugin) {
        // Only process plugins with status 'active'
        if (isset($existing_plugin['status'])) {
            $dir = $existing_plugin['dir'];
            $plugin_dir = "$directory/$dir";

            // Check if the directory exists and contains a register.json file
            if (is_dir($plugin_dir) && file_exists("$plugin_dir/register.json")) {
                // Add the plugin to the list based on the information from register.json
                $plugin_files[] = [
                    'dir' => $dir,
                    'name' => isset($existing_plugin['name']) ? $existing_plugin['name'] : 'Unknown Plugin',
                    'version' => isset($existing_plugin['version']) ? $existing_plugin['version'] : 'Unknown',
                    'author' => isset($existing_plugin['author']) ? $existing_plugin['author'] : 'Unknown',
                    'author_url' => isset($existing_plugin['authorUrl']) ? $existing_plugin['authorUrl'] : 'Unknown',
                    'description' => isset($existing_plugin['description']) ? $existing_plugin['description'] : 'No description',
                    'plugin_url' => isset($existing_plugin['pluginUrl']) ? $existing_plugin['pluginUrl'] : 'No URI',
                    'last_update' => isset($existing_plugin['lastUpdate']) ? $existing_plugin['lastUpdate'] : 'Unknown',
                    'latest_version' => isset($existing_plugin['latest_version']) ? $existing_plugin['latest_version'] : 'Unknown',
                    'status' => $existing_plugin['status'],  // This will be 'active'
                ];
            }
        }
    }

    // Return the list of active plugins as an array
    return $plugin_files;
}


function scanAndRemovePath($source, $target)
{
    $files = scandir($source);
    foreach ($files as $file) {
        if (is_file($source . $file)) {
            if (file_exists($target . $file)) {
                unlink($target . $file);
            }
        } else if (is_dir($source . $file) && !in_array($file, ['.', '..'])) {
            scanAndRemovePath($source . $file . DIRECTORY_SEPARATOR, $target . $file . DIRECTORY_SEPARATOR);
            if (file_exists($target . $file)) {
                rmdir($target . $file);
            }
        }
    }
    if (file_exists($target)) {
        rmdir($target);
    }
}
