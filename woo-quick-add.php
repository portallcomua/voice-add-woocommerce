<?php
/**
 * Plugin Name: Voice ADD for WooCommerce PRO
 * Plugin URI:  https://uaserver.pp.ua/voice-add-woocommerce/
 * Description: Голосове додавання товарів у WooCommerce з фото, атрибутами та варіаціями.
 * Version:     2.3.1
 * Author:      UAServer
 * Author URI:  https://uaserver.pp.ua/
 * License:     GPLv2 or later
 * Text Domain: voice-add-wc
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 9.5
 */

if (!defined('ABSPATH')) exit;

define('VAWC_VERSION',      '2.3.1');
define('VAWC_FILE',         __FILE__);
define('VAWC_SERVICE_SLUG', 'voice-add-woocommerce');
define('VAWC_GITHUB',       'portallcomua/voice-add-woocommerce');
define('VAWC_FREE_LIMIT',   25);

// WooCommerce HPOS
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', VAWC_FILE, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', VAWC_FILE, true);
    }
});

// =====================================================================
// АВТООНОВЛЕННЯ З GITHUB
// =====================================================================
class VAWC_Updater {
    private $file, $slug, $repo;
    public function __construct($file, $repo) {
        $this->file = $file;
        $this->slug = dirname(plugin_basename($file));
        $this->repo = $repo;
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check']);
        add_filter('plugins_api', [$this, 'info'], 10, 3);
    }
    public function check($t) {
        if (empty($t->checked)) return $t;
        $r = $this->remote();
        if ($r && version_compare(VAWC_VERSION, $r->version, '<')) {
            $t->response[plugin_basename($this->file)] = (object)[
                'slug' => $this->slug, 'new_version' => $r->version,
                'package' => $r->zip, 'url' => $r->url, 'tested' => '7.1',
            ];
        }
        return $t;
    }
    public function info($res, $action, $args) {
        if ($action !== 'plugin_information' || $args->slug !== $this->slug) return $res;
        $r = $this->remote();
        if (!$r) return $res;
        return (object)['name' => 'Voice ADD for WooCommerce PRO', 'slug' => $this->slug,
            'version' => $r->version, 'download_link' => $r->zip,
            'tested' => '7.1', 'requires_php' => '7.4',
            'sections' => ['description' => 'Голосове додавання товарів у WooCommerce.']];
    }
    private function remote() {
        $resp = wp_remote_get("https://api.github.com/repos/{$this->repo}/releases/latest",
            ['headers' => ['Accept' => 'application/json'], 'timeout' => 5]);
        if (is_wp_error($resp)) return null;
        $d = json_decode(wp_remote_retrieve_body($resp));
        if (!$d || !isset($d->tag_name)) return null;
        return (object)['version' => ltrim($d->tag_name, 'v'), 'zip' => $d->zipball_url, 'url' => $d->html_url];
    }
}
new VAWC_Updater(VAWC_FILE, VAWC_GITHUB);

// =====================================================================
// 1. ІНТЕГРАЦІЯ З ЯДРОМ
// =====================================================================
function vawc_core_active() { return class_exists('UM_Client') && class_exists('UM_Helpers'); }

function vawc_get_service_id() {
    if (!class_exists('UM_Client')) return 0;
    $service = UM_Client::get_service(VAWC_SERVICE_SLUG);
    if (!$service) return 0;
    if (is_object($service) && isset($service->id)) return (int) $service->id;
    if (is_array($service) && isset($service['id']))  return (int) $service['id'];
    return 0;
}

function vawc_pro() {
    if (!function_exists('wcc_check_user_premium')) return false;
    $sid = vawc_get_service_id();
    return $sid ? (bool) wcc_check_user_premium(get_current_user_id(), $sid) : false;
}

// =====================================================================
// 2. АКТИВАЦІЯ
// =====================================================================
register_activation_hook(__FILE__, function () {
    add_option('vawc_ops', 0);
    add_option('vawc_draft', '1');
    add_option('vawc_instructions', '');
    add_option('vawc_rbg_api', '');
    add_option('vawc_legacy_migrated', '0');

    if (class_exists('UM_Client')) {
        UM_Client::register([
            'slug' => VAWC_SERVICE_SLUG,
            'name' => 'Voice ADD for WooCommerce PRO',
        ]);
    }
});

// =====================================================================
// 3. LEGACY
// =====================================================================
function wcc_add_premium_access_wc_legacy($user_id, $pid_old, $type = 'trial', $email = '', $domain = '') {
    $sid = vawc_get_service_id();
    if (!$sid || !function_exists('wcc_add_premium_access')) return false;
    return wcc_add_premium_access($user_id, $sid, ['type' => $type, 'email' => $email, 'domain' => $domain]);
}
function wcc_block_premium_access_wc_legacy($user_id, $pid_old) {
    $sid = vawc_get_service_id();
    if (!$sid || !function_exists('wcc_block_premium_access')) return false;
    return wcc_block_premium_access($user_id, $sid);
}

// =====================================================================
// 4. МІГРАЦІЯ
// =====================================================================
add_action('admin_init', function () {
    if (get_option('vawc_legacy_migrated') === '1') return;
    if (!vawc_core_active() || !function_exists('wcc_add_premium_access')) return;

    global $wpdb;
    $old_table = $wpdb->prefix . 'wcc_premium_access';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$old_table}'") !== $old_table) {
        update_option('vawc_legacy_migrated', '1');
        return;
    }
    $sid = vawc_get_service_id();
    if (!$sid) return;

    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$old_table} WHERE product_id = %d", 3));
    if ($rows) {
        foreach ($rows as $row) {
            wcc_add_premium_access($row->user_id, $sid, [
                'type' => $row->type ?? 'legacy',
                'email' => $row->email ?? '',
                'domain' => $row->domain ?? '',
            ]);
        }
    }
    update_option('vawc_legacy_migrated', '1');
});

// =====================================================================
// 5. ХЕЛПЕРИ
// =====================================================================
function vawc_en() { return in_array(get_locale(), ['en_US','en_GB','en_CA','en_AU','en']); }
function vawc_t($ua, $en) { return vawc_en() ? $en : $ua; }
function vawc_count() { return (int) get_option('vawc_ops', 0); }
function vawc_inc() { update_option('vawc_ops', vawc_count() + 1); }
function vawc_user_can_use() { return is_user_logged_in() && current_user_can('manage_woocommerce'); }

// =====================================================================
// 6. МЕНЮ
// =====================================================================
add_action('admin_menu', function () {
    add_menu_page('Voice ADD WC', 'Voice ADD WC', 'manage_woocommerce', 'vawc_main', 'vawc_page_main', 'dashicons-microphone', 30);
    add_submenu_page('vawc_main', vawc_t('Налаштування','Settings'), '⚙️ ' . vawc_t('Налаштування','Settings'), 'manage_woocommerce', 'vawc_settings', 'vawc_page_settings');
});

// =====================================================================
// 7. НАЛАШТУВАННЯ
// =====================================================================
function vawc_page_settings() {
    if (isset($_POST['vawc_save']) && check_admin_referer('vawc_cfg')) {
        update_option('vawc_rbg_api', sanitize_text_field($_POST['rbg_api']));
        update_option('vawc_draft', isset($_POST['vawc_draft']) ? '1' : '0');
        update_option('vawc_instructions', wp_kses_post($_POST['vawc_instructions']));
        echo '<div class="notice notice-success"><p>✅ Збережено!</p></div>';
    }
    $rbg = get_option('vawc_rbg_api', '');
    $draft = get_option('vawc_draft', '1');
    $instr = get_option('vawc_instructions', '');
    ?>
    <div class="wrap">
        <h1>⚙️ Налаштування Voice ADD WC</h1>
        <?php if (!vawc_core_active()): ?>
            <div class="notice notice-error"><p>⚠️ Ядро не активне. PRO-функції недоступні.</p></div>
        <?php endif ?>
        <form method="post"><?php wp_nonce_field('vawc_cfg') ?>
        <table class="form-table">
            <tr><th>Remove.bg API Key</th><td><input type="text" name="rbg_api" value="<?php echo esc_attr($rbg) ?>" style="width:340px"></td></tr>
            <tr><th>Статус</th><td><label><input type="checkbox" name="vawc_draft" value="1" <?php checked($draft, '1') ?>> Зберігати як чернетку</label></td></tr>
            <tr><th>Інструкція</th><td><textarea name="vawc_instructions" rows="4" style="width:100%"><?php echo esc_textarea($instr) ?></textarea></td></tr>
        </table>
        <input type="hidden" name="vawc_save" value="1">
        <?php submit_button('Зберегти') ?>
        </form>
    </div>
    <?php
}

// =====================================================================
// 8. ГОЛОВНА СТОРІНКА
// =====================================================================
function vawc_page_main() {
    if (!vawc_user_can_use()) wp_die('Доступ заборонено');

    $pro = vawc_pro();
    $can = $pro || vawc_count() < VAWC_FREE_LIMIT;
    $has_rbg = !empty(get_option('vawc_rbg_api', ''));
    $draft = get_option('vawc_draft', '1') === '1';
    $sid = vawc_get_service_id();

    // Динамічний шаблон для диктування
    $fields = vawc_en()
        ? ['NAME','BRAND','PRICE','SALE','SKU','CATEGORY','TAGS','DESCRIPTION']
        : ['НАЗВА','БРЕНД','ЦІНА','АКЦІЯ','АРТИКУЛ','КАТЕГОРІЯ','МІТКИ','ОПИС'];
    $template = implode("\n", array_map(fn($f) => $f.': ', $fields));

    $js_config = [
        'EN' => vawc_en(), 'PRO' => $pro, 'CAN' => $can, 'DRAFT' => $draft, 'HAS_RBG' => $has_rbg,
        'AJAX' => admin_url('admin-ajax.php'),
        'NS' => wp_create_nonce('vawc_save'),
        'NE' => wp_create_nonce('vawc_export'),
        'TPL' => $template,
    ];
    ?>
    <style>@keyframes vawc_pulse {0%,100%{opacity:1} 50%{opacity:.5}}</style>
    <div id="vawc_w" style="max-width:800px;margin:auto;padding:20px 14px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
            <h1>🎤 Voice ADD WC <sup style="font-size:11px;color:#aaa">v<?php echo esc_html(VAWC_VERSION) ?></sup></h1>
            <button id="btn_export" style="background:#0d6efd;color:#fff;border:none;padding:6px 14px;border-radius:6px;cursor:pointer">📤 CSV</button>
        </div>

        <?php if (!$pro): ?>
            <div style="background:#fff3cd;padding:12px;border-radius:8px;margin-bottom:15px">
                📊 Залишилось: <strong><?php echo max(0, VAWC_FREE_LIMIT - vawc_count()) ?></strong>/<?php echo VAWC_FREE_LIMIT ?>
                <?php
                if (vawc_core_active() && $sid && method_exists('UM_Helpers', 'buy_button')) {
                    echo UM_Helpers::buy_button($sid, '💰 Купити PRO');
                } else {
                    echo '<span style="color:#c00;font-weight:bold">⚠️ Ядро не активне</span>';
                }
                ?>
            </div>
        <?php endif ?>

        <video id="vawc_cam" autoplay playsinline muted style="width:100%;background:#111;border-radius:10px;margin-bottom:6px;max-height:280px;object-fit:cover"></video>
        <div id="vawc_gal" style="display:flex;gap:4px;flex-wrap:wrap;margin-bottom:8px"></div>

        <?php if ($has_rbg): ?>
        <div id="vawc_bg_wrap" style="background:#f8f9fa;border-radius:10px;padding:10px 14px;margin-bottom:10px;display:none">
            <div style="font-size:13px;font-weight:bold;margin-bottom:8px">🖼️ Фон:</div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
                <label><input type="radio" name="vawc_bg" value="original" checked> Оригінал</label>
                <label><input type="radio" name="vawc_bg" value="transparent"> Прозорий</label>
                <label><input type="radio" name="vawc_bg" value="ffffff"> Білий</label>
                <label><input type="radio" name="vawc_bg" value="000000"> Чорний</label>
                <label><input type="radio" name="vawc_bg" value="custom"> Колір: <input type="color" id="vawc_cc" value="#ff0000" style="width:32px;height:22px;padding:0;border:1px solid #ccc;cursor:pointer"></label>
            </div>
            <div id="vawc_bg_st" style="margin-top:6px;font-size:12px;color:#555;display:none"></div>
        </div>
        <?php endif ?>

        <div style="display:flex;gap:8px;margin-bottom:10px">
            <button id="btn_photo" style="flex:3;background:#2271b1;color:#fff;border:none;padding:12px;border-radius:8px;cursor:pointer">📷 ФОТО</button>
            <button id="btn_clr" style="flex:1;background:#6c757d;color:#fff;border:none;padding:12px;border-radius:8px;cursor:pointer">❌</button>
        </div>

        <div id="vawc_bar" style="display:none;padding:12px;text-align:center;color:#fff;border-radius:9px;margin-bottom:8px"></div>

        <textarea id="vawc_ta" rows="10" style="width:100%;font-family:monospace;font-size:14px;padding:10px;border:2px solid #2271b1;border-radius:8px;"><?php echo esc_textarea($template); ?></textarea>

        <div style="margin-top:10px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
                <label style="font-weight:bold">🏷️ АТРИБУТИ</label>
                <button id="btn_add_attr" style="background:#6f42c1;color:#fff;border:none;padding:7px 13px;border-radius:6px;cursor:pointer">+ Додати</button>
            </div>
            <div id="vawc_attrs"></div>
        </div>

        <button id="btn_mic" style="width:100%;margin-top:10px;padding:14px;background:#1877F2;color:#fff;border:none;border-radius:8px;font-size:16px;font-weight:bold;cursor:pointer">🎤 ДИКТУВАТИ</button>

        <?php if ($pro): ?>
        <div style="margin-top:12px;padding:14px;background:#f8f0ff;border-radius:10px;border:2px solid #6f42c1">
            <label style="font-weight:bold">Тип:</label>
            <select id="vawc_ptype" style="padding:7px 12px;border-radius:7px;border:2px solid #6f42c1">
                <option value="simple">Простий</option>
                <option value="variable">Варіативний</option>
            </select>
            <div id="vawc_vwrap" style="display:none;margin-top:10px">
                <div id="vawc_var_rows"></div>
                <button id="btn_add_var" style="background:#6f42c1;color:#fff;border:none;padding:7px 13px;border-radius:6px;cursor:pointer;margin-top:6px">+ Варіацію</button>
            </div>
        </div>
        <?php endif ?>

        <button id="btn_save" style="width:100%;margin-top:10px;padding:16px;background:#00a32a;color:#fff;border:none;border-radius:8px;font-size:18px;font-weight:bold;cursor:pointer">
            <?php echo $draft ? '➕ ЗБЕРЕГТИ ЯК ЧЕРНЕТКУ' : '➕ ОПУБЛІКУВАТИ'; ?>
        </button>
        <div id="vawc_msg" style="margin-top:15px;"></div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var C = <?php echo json_encode($js_config, JSON_UNESCAPED_UNICODE) ?>;
        var photos = [], listening = false;

        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
            .then(function (stream) { document.getElementById('vawc_cam').srcObject = stream; }).catch(function (e) { console.log(e); });

        document.getElementById('btn_photo').addEventListener('click', function () {
            var v = document.getElementById('vawc_cam');
            if (!v.videoWidth) return;
            var c = document.createElement('canvas');
            c.width = v.videoWidth; c.height = v.videoHeight;
            c.getContext('2d').drawImage(v, 0, 0);
            var d = c.toDataURL('image/jpeg', 0.82);
            photos.push(d);
            var img = document.createElement('img');
            img.src = d;
            img.style.cssText = 'width:66px;height:66px;object-fit:cover;border-radius:7px;border:2px solid #2271b1;cursor:pointer';
            img.addEventListener('click', function () { photos.splice(photos.indexOf(d), 1); img.remove(); });
            document.getElementById('vawc_gal').prepend(img);
            var w = document.getElementById('vawc_bg_wrap'); if (w) w.style.display = 'block';
        });

        document.getElementById('btn_clr').addEventListener('click', function () {
            photos = []; document.getElementById('vawc_gal').innerHTML = '';
            var w = document.getElementById('vawc_bg_wrap'); if (w) w.style.display = 'none';
        });

        function getBg() {
            var radios = document.querySelectorAll('input[name="vawc_bg"]');
            for (var i = 0; i < radios.length; i++) { if (radios[i].checked) return radios[i].value; }
            return 'original';
        }

        function processPhotos(list, cb) {
            if (!C.HAS_RBG || getBg() === 'original') { cb(list); return; }
            var bg = getBg();
            if (bg === 'custom') { var cc = document.getElementById('vawc_cc'); bg = cc ? cc.value.replace('#', '') : 'ffffff'; }
            var st = document.getElementById('vawc_bg_st'); if (st) st.style.display = 'block';
            var res = [], i = 0;
            function next() {
                if (i >= list.length) { if (st) { st.textContent = '✅ Готово!'; setTimeout(function () { st.style.display = 'none'; }, 2000); } cb(res); return; }
                if (st) st.textContent = '⏳ Фото ' + (i + 1) + '/' + list.length + '…';
                var fd = new FormData();
                fd.append('action', 'vawc_remove_bg'); fd.append('nonce', C.NS); fd.append('image', list[i]); fd.append('bg', bg);
                fetch(C.AJAX, { method: 'POST', body: fd }).then(r => r.json()).then(d => {
                    res.push(d.success && d.data && d.data.image ? d.data.image : list[i]);
                    if (d.success && d.data && d.data.image) {
                        var imgs = document.getElementById('vawc_gal').querySelectorAll('img');
                        var idx = list.length - 1 - i;
                        if (imgs[idx]) imgs[idx].src = d.data.image;
                    }
                    i++; next();
                }).catch(function () { res.push(list[i]); i++; next(); });
            }
            next();
        }

        function attrAdd(name, value) {
            var list = document.getElementById('vawc_attrs');
            var row = document.createElement('div');
            row.className = 'vawc_ar';
            row.style.cssText = 'display:flex;gap:6px;align-items:center;margin-bottom:5px';
            var i0 = document.createElement('input'); i0.type = 'text'; i0.value = name || ''; i0.placeholder = 'Назва (Колір)'; i0.style.cssText = 'flex:1;padding:7px;border:1px solid #ccc;border-radius:6px';
            var i1 = document.createElement('input'); i1.type = 'text'; i1.value = value || ''; i1.placeholder = 'Значення (Червоний)'; i1.style.cssText = 'flex:2;padding:7px;border:1px solid #ccc;border-radius:6px';
            var del = document.createElement('button'); del.textContent = '✕'; del.style.cssText = 'background:#dc3545;color:#fff;border:none;padding:7px 10px;border-radius:6px;cursor:pointer';
            del.addEventListener('click', function () { row.remove(); });
            row.appendChild(i0); row.appendChild(i1); row.appendChild(del); list.appendChild(row);
        }
        document.getElementById('btn_add_attr').addEventListener('click', function () { attrAdd('', ''); });

        if (C.PRO) {
            document.getElementById('vawc_ptype').addEventListener('change', function () {
                document.getElementById('vawc_vwrap').style.display = this.value === 'variable' ? 'block' : 'none';
            });
            document.getElementById('btn_add_var').addEventListener('click', function () { varAdd('', [], []); });
            window.varAdd = function (name, vals, prices) {
                var rows = document.getElementById('vawc_var_rows');
                var row = document.createElement('div'); row.className = 'vawc_vr';
                row.style.cssText = 'background:#fff;border:1px solid #d4b0ff;border-radius:8px;padding:10px;margin-bottom:8px';
                var head = document.createElement('div'); head.style.cssText = 'display:flex;gap:6px;margin-bottom:8px';
                var nameInp = document.createElement('input'); nameInp.type = 'text'; nameInp.value = name || ''; nameInp.placeholder = 'Назва (Розмір)'; nameInp.style.cssText = 'flex:1;padding:7px;border:1.5px solid #6f42c1;border-radius:6px';
                var del = document.createElement('button'); del.textContent = '✕'; del.style.cssText = 'background:#dc3545;color:#fff;border:none;padding:6px 10px;border-radius:6px;cursor:pointer';
                del.addEventListener('click', function () { row.remove(); });
                head.appendChild(nameInp); head.appendChild(del); row.appendChild(head);
                var valList = document.createElement('div'); valList.className = 'vawc_vvals'; row.appendChild(valList);
                function addVal(v, p) {
                    var vr = document.createElement('div'); vr.style.cssText = 'display:flex;gap:6px;margin-bottom:5px';
                    var vi = document.createElement('input'); vi.type = 'text'; vi.value = v || ''; vi.placeholder = 'Значення (S)'; vi.style.cssText = 'flex:2;padding:6px;border:1px solid #ccc;border-radius:6px';
                    var pi = document.createElement('input'); pi.type = 'number'; pi.value = p || ''; pi.placeholder = 'Ціна'; pi.style.cssText = 'flex:1;padding:6px;border:1px solid #ccc;border-radius:6px';
                    var vd = document.createElement('button'); vd.textContent = '✕'; vd.style.cssText = 'background:#6c757d;color:#fff;border:none;padding:5px 9px;border-radius:6px;cursor:pointer';
                    vd.addEventListener('click', function () { vr.remove(); });
                    vr.appendChild(vi); vr.appendChild(pi); vr.appendChild(vd); valList.appendChild(vr);
                }
                (vals || []).forEach(function (v, i) { addVal(v, (prices || [])[i] || ''); });
                if (!vals || !vals.length) addVal('', '');
                var addBtn = document.createElement('button'); addBtn.textContent = '+ Значення'; addBtn.style.cssText = 'background:#6f42c1;color:#fff;border:none;padding:5px 10px;border-radius:6px;cursor:pointer;margin-top:2px';
                addBtn.addEventListener('click', function () { addVal('', ''); });
                row.appendChild(addBtn); rows.appendChild(row);
            };
            window.collectVariations = function () {
                var result = [];
                document.querySelectorAll('.vawc_vr').forEach(function (row) {
                    var name = row.querySelector('input').value.trim();
                    if (!name) return;
                    var vals = [], prices = [];
                    row.querySelectorAll('.vawc_vvals > div').forEach(function (vr) {
                        var inp = vr.querySelectorAll('input');
                        var v = inp[0].value.trim(), p = inp[1].value.trim();
                        if (v) { vals.push(v); prices.push(p); }
                    });
                    if (vals.length) result.push({ name: name, values: vals, prices: prices });
                });
                return result;
            };
        }

        if (window.SpeechRecognition || window.webkitSpeechRecognition) {
            var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
            var rec = new SR();
            rec.lang = C.EN ? 'en-US' : 'uk-UA';
            rec.continuous = true; rec.interimResults = false;
            var mb = document.getElementById('btn_mic');

            mb.addEventListener('click', function () {
                if (!listening) {
                    rec.start(); listening = true; mb.textContent = '🛑 ЗУПИНИТИ'; mb.style.background = '#dc3545';
                } else {
                    listening = false; rec.stop(); mb.textContent = '🎤 ДИКТУВАТИ'; mb.style.background = '#1877F2';
                }
            });

            rec.onresult = function (e) {
                var spoken = e.results[e.results.length - 1][0].transcript.trim();
                document.getElementById('vawc_ta').value += "\n" + spoken;
            };
            rec.onend = function () { if (listening) setTimeout(function () { try { rec.start(); } catch (x) {} }, 100); };
        }

        document.getElementById('btn_save').addEventListener('click', function () {
            var self = this;
            if (!C.CAN) { alert('Ліміт вичерпано!'); return; }
            self.disabled = true; self.textContent = '⏳ ЗБЕРІГАЮ...';

            var attrs = [];
            document.querySelectorAll('.vawc_ar').forEach(function (row) {
                var inp = row.querySelectorAll('input');
                var k = inp[0].value.trim(), v = inp[1].value.trim();
                if (k && v) attrs.push({ name: k, value: v });
            });

            var ptype = 'simple', vars = [];
            if (C.PRO) {
                var pt = document.getElementById('vawc_ptype');
                if (pt) ptype = pt.value;
                if (ptype === 'variable' && window.collectVariations) vars = window.collectVariations();
            }

            function doSave(finalPhotos) {
                var fd = new FormData();
                fd.append('action', 'vawc_save'); fd.append('nonce', C.NS);
                fd.append('text', document.getElementById('vawc_ta').value);
                fd.append('images', JSON.stringify(finalPhotos));
                fd.append('attrs', JSON.stringify(attrs));
                fd.append('type', ptype);
                fd.append('variations', JSON.stringify(vars));

                fetch(C.AJAX, { method: 'POST', body: fd }).then(r => r.json()).then(data => {
                    if (data.success) {
                        document.getElementById('vawc_msg').innerHTML = '<span style="color:green">✅ Створено! ID: ' + data.data.id + '</span>';
                        photos = []; document.getElementById('vawc_gal').innerHTML = '';
                        document.getElementById('vawc_attrs').innerHTML = '';
                        document.getElementById('vawc_ta').value = C.TPL;
                        var vr = document.getElementById('vawc_var_rows'); if (vr) vr.innerHTML = '';
                    } else {
                        document.getElementById('vawc_msg').innerHTML = '<span style="color:red">❌ ' + data.data + '</span>';
                    }
                }).finally(() => {
                    self.disabled = false; self.textContent = C.DRAFT ? '➕ ЗБЕРЕГТИ ЯК ЧЕРНЕТКУ' : '➕ ОПУБЛІКУВАТИ';
                });
            }

            if (C.HAS_RBG && getBg() !== 'original' && photos.length > 0) processPhotos(photos.slice(), doSave);
            else doSave(photos.slice());
        });

        var btnExp = document.getElementById('btn_export');
        if (btnExp) {
            btnExp.addEventListener('click', function () {
                var self = this; self.textContent = '⏳'; self.disabled = true;
                var fd = new FormData(); fd.append('action', 'vawc_export'); fd.append('nonce', C.NE);
                fetch(C.AJAX, { method: 'POST', body: fd }).then(r => r.blob()).then(b => {
                    var u = URL.createObjectURL(b); var a = document.createElement('a'); a.href = u;
                    a.download = 'wc-products-' + new Date().toISOString().split('T')[0] + '.csv';
                    document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(u);
                });
                self.textContent = '📤 CSV'; self.disabled = false;
            });
        }
    });
    </script>
    <?php
}

// =====================================================================
// 9. AJAX: ЗБЕРЕЖЕННЯ
// =====================================================================
add_action('wp_ajax_vawc_save', function () {
    if (!check_ajax_referer('vawc_save', 'nonce', false)) wp_send_json_error('Nonce');
    if (!current_user_can('manage_woocommerce')) wp_send_json_error('Permission');

    $raw = sanitize_textarea_field($_POST['text'] ?? '');
    $imgs = json_decode(stripslashes($_POST['images'] ?? '[]'), true) ?: [];
    $attrs = json_decode(stripslashes($_POST['attrs'] ?? '[]'), true) ?: [];
    $ptype = sanitize_text_field($_POST['type'] ?? 'simple');
    $vars = json_decode(stripslashes($_POST['variations'] ?? '[]'), true) ?: [];
    $draft = get_option('vawc_draft', '1') === '1';

    $data = [];
    foreach (explode("\n", $raw) as $l) {
        if (strpos($l, ':') !== false) { [$k, $v] = array_map('trim', explode(':', $l, 2)); if ($k && $v) $data[strtoupper($k)] = $v; }
    }

    $product = ($ptype === 'variable' && vawc_pro()) ? new WC_Product_Variable() : new WC_Product_Simple();
    $product->set_status($draft ? 'draft' : 'publish');
    $product->set_name($data['NAME'] ?? $data['НАЗВА'] ?? 'Товар ' . gmdate('H:i:s'));
    if (!empty($data['PRICE'])) $product->set_regular_price(preg_replace('/[^0-9.]/', '', $data['PRICE']));
    if (!empty($data['SALE'])) $product->set_sale_price(preg_replace('/[^0-9.]/', '', $data['SALE']));
    if (!empty($data['DESCRIPTION'])) $product->set_description($data['DESCRIPTION']);
    $pid = $product->save();

    if (!empty($data['CATEGORY'])) {
        $term = term_exists($data['CATEGORY'], 'product_cat') ?: wp_insert_term($data['CATEGORY'], 'product_cat');
        if (!is_wp_error($term)) wp_set_object_terms($pid, (int)$term['term_id'], 'product_cat');
    }
    if (!empty($data['BRAND'])) {
        foreach (['pwb-brand','product_brand','pa_brand'] as $tax) {
            if (taxonomy_exists($tax)) {
                $term = term_exists($data['BRAND'], $tax) ?: wp_insert_term($data['BRAND'], $tax);
                if (!is_wp_error($term)) { wp_set_object_terms($pid, (int)$term['term_id'], $tax); break; }
            }
        }
    }
    if (!empty($data['TAGS'])) {
        $ids = [];
        foreach (array_filter(array_map('trim', explode(',', $data['TAGS']))) as $tn) {
            $term = term_exists($tn, 'product_tag') ?: wp_insert_term($tn, 'product_tag');
            if (!is_wp_error($term)) $ids[] = (int)$term['term_id'];
        }
        if ($ids) wp_set_object_terms($pid, $ids, 'product_tag');
    }

    $wca = [];
    foreach ($attrs as $a) {
        if (empty($a['name']) || empty($a['value'])) continue;
        $attr_name = wc_clean($a['name']);
        $taxonomy = wc_attribute_taxonomy_name($attr_name);
        if (!taxonomy_exists($taxonomy)) {
            $aid = wc_create_attribute(['name' => $attr_name, 'slug' => sanitize_title($attr_name), 'type' => 'select', 'order_by' => 'menu_order']);
            if (!is_wp_error($aid)) { delete_transient('wc_attribute_taxonomies'); register_taxonomy($taxonomy, ['product'], []); }
        }
        $values = array_filter(array_map('trim', explode(',', $a['value'])));
        foreach ($values as $val) { if (!term_exists($val, $taxonomy)) wp_insert_term($val, $taxonomy); }
        $attr = new WC_Product_Attribute();
        $attr->set_id(wc_attribute_taxonomy_id_by_name($attr_name));
        $attr->set_name($taxonomy);
        $attr->set_options($values);
        $attr->set_visible(true);
        $wca[] = $attr;
    }

    if ($ptype === 'variable' && vawc_pro() && !empty($vars)) {
        $varmap = []; $price_map = [];
        foreach ($vars as $var) {
            $vn = $var['name'] ?? ''; $vvals = $var['values'] ?? []; $vprices = $var['prices'] ?? [];
            if (!$vn || empty($vvals)) continue;
            $attr_name = wc_clean($vn);
            $taxonomy = wc_attribute_taxonomy_name($attr_name);
            if (!taxonomy_exists($taxonomy)) {
                $aid = wc_create_attribute(['name' => $attr_name, 'slug' => sanitize_title($attr_name), 'type' => 'select', 'order_by' => 'menu_order']);
                if (!is_wp_error($aid)) { delete_transient('wc_attribute_taxonomies'); register_taxonomy($taxonomy, ['product'], []); }
            }
            foreach ($vvals as $val) { if (!term_exists(trim($val), $taxonomy)) wp_insert_term(trim($val), $taxonomy); }
            $va = new WC_Product_Attribute();
            $va->set_id(wc_attribute_taxonomy_id_by_name($attr_name));
            $va->set_name($taxonomy);
            $va->set_options(array_filter(array_map('trim', $vvals)));
            $va->set_visible(true);
            $va->set_variation(true);
            $wca[] = $va;
            $varmap[$vn] = array_values(array_filter(array_map('trim', $vvals)));
            foreach ($vvals as $i => $vv) { $vv = trim($vv); $p = trim($vprices[$i] ?? ''); if ($vv && $p) $price_map[$vn.'|'.$vv] = $p; }
        }
        if ($wca) { $product->set_attributes($wca); $product->save(); }
        $combos = [[]];
        foreach ($varmap as $vn => $opts) {
            $new = [];
            foreach ($combos as $c) foreach ($opts as $o) $new[] = array_merge($c, [$vn => $o]);
            $combos = $new;
        }
        foreach ($combos as $combo) {
            $v = new WC_Product_Variation();
            $v->set_parent_id($pid); $v->set_status('publish');
            $v_price = $data['PRICE'] ?? '';
            foreach ($combo as $cn => $cv) { if (isset($price_map[$cn.'|'.$cv])) { $v_price = $price_map[$cn.'|'.$cv]; break; } }
            if ($v_price) $v->set_regular_price(preg_replace('/[^0-9.]/', '', $v_price));
            $va2 = [];
            foreach ($combo as $n => $val) $va2[wc_attribute_taxonomy_name($n)] = $val;
            $v->set_attributes($va2); $v->save();
        }
    } else {
        if ($wca) { $product->set_attributes($wca); $product->save(); }
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    foreach ($imgs as $i => $b64) {
        $is_png = strpos($b64, 'data:image/png') !== false;
        $img = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $b64));
        $ext = $is_png ? 'png' : 'jpg'; $mime = $is_png ? 'image/png' : 'image/jpeg';
        $file = wp_upload_bits('vawc_' . $pid . '_' . time() . '_' . $i . '.' . $ext, null, $img);
        if ($file['error']) continue;
        $aid = wp_insert_attachment(['post_mime_type' => $mime, 'post_status' => 'inherit'], $file['file'], $pid);
        wp_update_attachment_metadata($aid, wp_generate_attachment_metadata($aid, $file['file']));
        if ($i === 0) set_post_thumbnail($pid, $aid);
    }

    vawc_inc();
    wp_send_json_success(['id' => $pid]);
});

// =====================================================================
// 10. AJAX: REMOVE.BG
// =====================================================================
add_action('wp_ajax_vawc_remove_bg', function () {
    if (!check_ajax_referer('vawc_save', 'nonce', false)) wp_send_json_error('Nonce');
    $api = get_option('vawc_rbg_api', '');
    if (!$api) wp_send_json_error('No API key');
    $b64 = $_POST['image'] ?? ''; $bg = sanitize_text_field($_POST['bg'] ?? 'transparent');
    if (!$b64) wp_send_json_error('No image');
    $img_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', str_replace(' ', '+', $b64)));
    $r = wp_remote_post('https://api.remove.bg/v1.0/removebg', [
        'headers' => ['X-Api-Key' => $api],
        'body'    => ['image_file_b64' => base64_encode($img_data), 'size' => 'auto', 'format' => 'png', 'bg_color' => ($bg === 'transparent' ? '' : $bg)],
        'timeout' => 30,
    ]);
    if (is_wp_error($r)) wp_send_json_error($r->get_error_message());
    $code = wp_remote_retrieve_response_code($r);
    if ($code !== 200) { $b = json_decode(wp_remote_retrieve_body($r), true); wp_send_json_error($b['errors'][0]['title'] ?? 'HTTP '.$code); }
    wp_send_json_success(['image' => 'data:image/png;base64,'.base64_encode(wp_remote_retrieve_body($r))]);
});

// =====================================================================
// 11. AJAX: ЕКСПОРТ CSV
// =====================================================================
add_action('wp_ajax_vawc_export', function () {
    if (!check_ajax_referer('vawc_export', 'nonce', false)) wp_die('Nonce');
    if (!current_user_can('manage_woocommerce')) wp_die('Permission');

    $products = wc_get_products(['limit' => -1, 'status' => 'any', 'return' => 'objects']);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="wc-import-'.gmdate('Y-m-d').'.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

    $headers = [
        'ID', 'Type', 'SKU', 'Name', 'Published', 'Is featured?', 'Visibility in catalog',
        'Short description', 'Description', 'Date sale price starts', 'Date sale price ends',
        'Tax status', 'Tax class', 'In stock?', 'Stock', 'Low stock amount', 'Backorders allowed?',
        'Sold individually?', 'Weight (kg)', 'Length (cm)', 'Width (cm)', 'Height (cm)',
        'Allow customer reviews?', 'Purchase note', 'Sale price', 'Regular price',
        'Categories', 'Tags', 'Shipping class', 'Images', 'Download limit', 'Download expiry',
        'Parent', 'Grouped products', 'Upsells', 'Cross-sells', 'External URL', 'Button text', 'Position',
        'Attribute 1 name', 'Attribute 1 value(s)', 'Attribute 1 visible', 'Attribute 1 global',
        'Attribute 2 name', 'Attribute 2 value(s)', 'Attribute 2 visible', 'Attribute 2 global',
        'Attribute 3 name', 'Attribute 3 value(s)', 'Attribute 3 visible', 'Attribute 3 global',
    ];
    fputcsv($out, $headers);

    foreach ($products as $p) {
        $pid = $p->get_id();
        $cats = implode(', ', wp_get_object_terms($pid, 'product_cat', ['fields' => 'names']));
        $tgs  = implode(', ', wp_get_object_terms($pid, 'product_tag', ['fields' => 'names']));
        $imgs = [];
        if ($p->get_image_id()) $imgs[] = wp_get_attachment_url($p->get_image_id());
        foreach ($p->get_gallery_image_ids() as $gi) if ($gi) $imgs[] = wp_get_attachment_url($gi);

        $as = array_values($p->get_attributes());
        $attr_cols = [];
        for ($i = 0; $i < 3; $i++) {
            if (isset($as[$i])) {
                $attr_cols[] = $as[$i]->get_name();
                $attr_cols[] = implode(', ', $as[$i]->get_options());
                $attr_cols[] = $as[$i]->get_visible() ? 1 : 0;
                $attr_cols[] = $as[$i]->get_id() ? 1 : 0;
            } else {
                $attr_cols[] = ''; $attr_cols[] = ''; $attr_cols[] = 1; $attr_cols[] = 0;
            }
        }

        $row = [
            $pid, $p->get_type(), $p->get_sku(), $p->get_name(), $p->get_status() === 'publish' ? 1 : 0,
            $p->is_featured() ? 1 : 0, 'visible', $p->get_short_description(), $p->get_description(),
            '', '', $p->get_tax_status(), $p->get_tax_class(), $p->is_in_stock() ? 1 : 0,
            $p->get_stock_quantity() ?? '', $p->get_low_stock_amount() ?? '', $p->get_backorders(),
            $p->get_sold_individually() ? 1 : 0, $p->get_weight(), $p->get_length(), $p->get_width(), $p->get_height(),
            $p->get_reviews_allowed() ? 1 : 0, $p->get_purchase_note(), $p->get_sale_price(), $p->get_regular_price(),
            $cats, $tgs, $p->get_shipping_class(), implode(', ', $imgs), $p->get_download_limit(), $p->get_download_expiry(),
            $p->get_parent_id(), '', implode(', ', $p->get_upsell_ids()), implode(', ', $p->get_cross_sell_ids()),
            $p->get_product_url(), $p->get_button_text(), ''
        ];
        $row = array_merge($row, $attr_cols);
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
});
