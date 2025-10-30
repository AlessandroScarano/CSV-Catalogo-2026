<?php
/**
 * Plugin Name: Glasscom – Catalog Builder (STAN URL + Cache + Edit) + Saved List (FIXED)
 * Description: Builder da CSV STAN fisso con cache, preview + modale edit, export modello finale e salvaggio DB. Include pagina “salvati” con filtri/edit/export. Aggiunte modalità Morsetti, Tubi e SenzaSeparatore.
 * Author: You
 * Version: 2.4.6 (JS Confirm Logic Refinement)
 */

if (defined('GCB_CATALOG_BUILDER_LOADED')) {
    return;
}
define('GCB_CATALOG_BUILDER_LOADED', true);

if (!defined('ABSPATH')) {
    exit;
}

/* ===================== CONFIG ===================== */
function gcb_cfg_stan_url() {
    return [
        'source_url'  => 'https://www.glasscom.it/Catalogo2026/STAN-DREAM.CSV',
        'sep'         => ';',
        'encoding'    => 'latin-1',
        'cache_hours' => 24,
    ];
}

/* === Colonne modello finale CLASSIC / SENZASEPARATORE === */
function gcb_target_cols() {
    return [
        'Prodotto','Categoria','@image_01','@image_scheda','Nome Articolo','Sottotitolo','Codice Articolo',
        'cod1','Prezzo_cod1','fin1','@image_fin1',
        'cod2','Prezzo_cod2','fin2','@image_fin2',
        'cod3','Prezzo_cod3','fin3','@image_fin3',
        'cod4','Prezzo_cod4','fin4','@image_fin4',
        'cod5','Prezzo_cod5','fin5','@image_fin5',
        'Dimensione','Per Vetro','Materiale','UM','@image_SchedeTecniche'
    ];
}

/* === Colonne modello finale base per MORSETTI/TUBI === */
function gcb_target_cols_variant_base() {
    return [
        'Prodotto','Categoria','@image_01','@image_scheda','Nome Articolo','Sottotitolo','Codice Articolo',
        'Dimensione','Per Vetro','Materiale','UM','@image_SchedeTecniche'
    ];
}

/* === Genera colonne dinamiche per MORSETTI/TUBI === */
function gcb_target_cols_variant_dynamic($n) {
    $base_start = ['Prodotto','Categoria','@image_01','@image_scheda','Nome Articolo','Sottotitolo','Codice Articolo'];
    $base_end   = ['Dimensione','Per Vetro','Materiale','UM','@image_SchedeTecniche'];
    $variant_cols = [];
    for ($i = 1; $i <= $n; $i++) {
        $variant_cols[] = 'cod'.$i;
        $variant_cols[] = 'Prezzo_cod'.$i;
        $variant_cols[] = 'var'.$i;
    }
    return array_merge($base_start, $variant_cols, $base_end);
}

/* === Parser Morsetti === */
function gcb_parent_and_variable_for_morsetti($full_code) {
    $code = strtoupper(trim((string) $full_code));
    if ($code === '') {
        return ['', ''];
    }
    $parts = explode('/', $code, 2);
    $parent  = trim($parts[0] ?? '');
    $variant = trim($parts[1] ?? '');
    return [$parent, $variant];
}

/* === Parser Tubi === */
function gcb_parent_and_variable_for_tubi($full_code) {
    $code = strtoupper(trim((string) $full_code));
    if ($code === '') {
        return ['', ''];
    }
    $parts = explode('-', $code, 2);
    $parent  = trim($parts[0] ?? '');
    $variant = trim($parts[1] ?? '');
    if (count($parts) === 1) {
        return [$parent, ''];
    }
    return [$parent, $variant];
}

/* === Finiture (usato da Classic e SenzaSeparatore) === */
function gcb_finish_options() {
    return [
        'Oro Satinato Antichizzato' => 'AO',
        'Cromo Lucido'              => 'CR',
        'Cromo Satinato'            => 'CS',
        'Cromo Opaco'               => 'CO',
        'Cromo Perla'               => 'CP',
        'Grigio Argento'            => 'GA',
        'Inox Lucido'               => 'IL',
        'Inox Satinato'             => 'IX',
        'Effetto Inox Satinato'     => 'EIX',
        'Nero Opaco'                => 'NO',
        'Nichel Lucido'             => 'NL',
        'Nichel Satinato'           => 'NS',
        'Nichelato Opaco'           => 'NP',
        'Alluminio Anodizzato'      => 'AN',
        'Brillantato'               => 'BA',
        'Ottone Lucido'             => 'OLC',
        'Ottone Cromo Lucido'       => 'OCL',
        'Ottone Cromo Satinato'     => 'OCO',
        'Ottone Bronzato'           => 'OBZ',
        'Ottone Spazzolato'         => 'OSP',
        'Zincato'                   => 'ZN',
    ];
}
function gcb_finish_label_by_code($code) {
    $code = strtoupper(trim((string) $code));
    if ($code === '') {
        return '';
    }
    $options = array_flip(gcb_finish_options());
    return $options[$code] ?? '';
}
function gcb_finish_code_from_variant($parent, $full_code) {
    $parent = trim((string) $parent);
    $full   = trim((string) $full_code);
    if ($parent === '' || $full === '') {
        return '';
    }
    if (stripos($full, $parent) === 0) {
        $rest = trim(substr($full, strlen($parent)));
    } else {
        $rest = $full;
    }
    $rest  = preg_replace('/^[\s\-_]+/', '', $rest);
    $parts = preg_split('/\s+/', $rest);
    $sigla = strtoupper(trim((string) end($parts)));
    return gcb_finish_label_by_code($sigla) !== '' ? $sigla : '';
}

/* === Nome file asset === */
function gcb_asset_filename_from_code($code) {
    $s = strtoupper(trim((string) $code));
    $s = preg_replace('/[^A-Z0-9]+/', '_', $s);
    $s = preg_replace('/_+/', '_', $s);
    return trim($s, '_');
}

/* === Parser Classic === */
function gcb_parent_and_variant($full_code) {
    $code = strtoupper(trim((string) $full_code));
    if ($code === '') {
        return ['', ''];
    }
    $letter_only_prefixes = ['SMF'];
    $tokens = preg_split('/[\s_]+/', $code, -1, PREG_SPLIT_NO_EMPTY);
    if (empty($tokens)) {
        return ['', ''];
    }
    $first_token      = $tokens[0];
    $remaining_tokens = array_slice($tokens, 1);
    if (isset($tokens[1]) && in_array($tokens[1], ['DX', 'SX'], true)) {
        $parent  = $first_token . $tokens[1];
        $variant = implode(' ', array_slice($tokens, 2));
        return [$parent, trim($variant)];
    }
    foreach ($letter_only_prefixes as $prefix) {
        $prefix_len = strlen($prefix);
        if (substr($first_token, 0, $prefix_len) === $prefix && strlen($first_token) > $prefix_len && ctype_alnum(substr($first_token, $prefix_len))) {
            if (preg_match('/^' . preg_quote($prefix, '/') . '([0-9][A-Z0-9]*)$/', $first_token, $match)) {
                $parent  = $prefix;
                $variant = $match[1] . (empty($remaining_tokens) ? '' : ' ' . implode(' ', $remaining_tokens));
                return [$parent, trim($variant)];
            }
        }
    }
    $parent  = $first_token;
    $variant = implode(' ', $remaining_tokens);
    return [$parent, trim($variant)];
}

/* =================== SHORTCODE: BUILDER STAN DA URL =================== */
add_shortcode('glasscom_catalog_builder_stan_url', 'glasscom_catalog_builder_stan_url_shortcode');

function glasscom_catalog_builder_stan_url_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>Devi essere autenticato per usare questo strumento.</p>';
    }

    $cfg   = gcb_cfg_stan_url();
    $msg   = '';
    $stage = 'form';
    $rows_preview = [];
    $existing_map = [];
    $not_found = [];
    $err = '';
    $TARGET_COLS = [];

    $upload = wp_upload_dir();
    if (!empty($upload['error'])) {
        return '<div class="gcb-alert gcb-error">Errore uploads: '.esc_html($upload['error']).'</div>';
    }
    $base_dir = trailingslashit($upload['basedir']) . 'glasscom-catalog/';
    $base_url = trailingslashit($upload['baseurl']) . 'glasscom-catalog/';
    if (!file_exists($base_dir)) {
        wp_mkdir_p($base_dir);
    }
    $cache_file = $base_dir . 'stan_cached.csv';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['gcb_action']) && $_POST['gcb_action'] === 'refresh_stan')) {
        if (empty($_POST['_gcb_refresh']) || !wp_verify_nonce($_POST['_gcb_refresh'], 'gcb_refresh_stan')) {
            return '<div class="gcb-alert gcb-error">Nonce non valido (refresh sorgente).</div>';
        }
        $ok = gcb_fetch_and_cache_stan($cfg, $cache_file, $err);
        $msg = $ok
            ? '<div class="gcb-alert gcb-success">Sorgente aggiornata.</div>'
            : '<div class="gcb-alert gcb-error">Errore aggiornando: '.esc_html($err).'</div>';
    }

    $need_fetch = !file_exists($cache_file);
    if (!$need_fetch) {
        $age = time() - @filemtime($cache_file);
        if ($age > ((int) $cfg['cache_hours'] * 3600)) {
            $need_fetch = true;
        }
    }
    if ($need_fetch) {
        gcb_fetch_and_cache_stan($cfg, $cache_file, $err);
        if (!file_exists($cache_file)) {
            return '<div class="gcb-alert gcb-error">Impossibile reperire sorgente: '.esc_html($err ?: 'file non disp.').'</div>';
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['gcb_action']) && $_POST['gcb_action'] === 'export')) {
        if (empty($_POST['_gcb_export']) || !wp_verify_nonce($_POST['_gcb_export'], 'gcb_export')) {
            return '<div class="gcb-alert gcb-error">Nonce non valido (export).</div>';
        }
        $mode = sanitize_text_field($_POST['_gcb_mode'] ?? 'classic');
        if (!in_array($mode, ['classic', 'morsetti', 'tubi', 'senza_separatore'], true)) {
            $mode = 'classic';
        }
        $rows = is_array($_POST['rows'] ?? null) ? array_map('wp_unslash', $_POST['rows']) : [];

        if (empty($rows)) {
            $msg = '<div class="gcb-alert gcb-error">Non ci sono righe da esportare.</div>';
        } else {
            if ($mode === 'morsetti' || $mode === 'tubi') {
                $maxN = 0;
                foreach ($rows as $rr) {
                    $maxN = max($maxN, gcb_max_variant_index($rr));
                }
                if ($maxN < 1) {
                    $maxN = 1;
                }
                $current_target_cols = gcb_target_cols_variant_dynamic($maxN);
            } else {
                $current_target_cols = gcb_target_cols();
            }

            $filename = 'modello_finale_stan_' . date('Ymd_His') . '.csv';
            gcb_write_csv_semicolon($base_dir . $filename, $current_target_cols, $rows);
            $url = $base_url . $filename;

            ob_start(); ?>
            <div class="gcb-card">
                <p>✅ CSV generato (Modalità: <?php echo esc_html(ucfirst(str_replace('_', '', $mode))); ?>).</p>
                <p><a class="gcb-btn" href="<?php echo esc_url($url); ?>" download>Scarica CSV</a></p>
                <form method="post" style="margin-top:10px;">
                    <?php wp_nonce_field('gcb_save_db', '_gcb_save_db'); ?>
                    <input type="hidden" name="gcb_action" value="save_db">
                    <input type="hidden" name="_gcb_mode" value="<?php echo esc_attr($mode); ?>">
                    <?php foreach ($rows as $i => $r) {
                        foreach ($current_target_cols as $col) {
                            printf('<input type="hidden" name="rows[%d][%s]" value="%s">', (int) $i, esc_attr($col), esc_attr($r[$col] ?? ''));
                        }
                    } ?>
                    <button class="gcb-btn secondary" type="submit">💽 Salva nel Database</button>
                    <a class="gcb-btn secondary" href="<?php echo esc_url(get_permalink()); ?>">↩ Torna all’inizio</a>
                </form>
            </div>
            <?php
            return ob_get_clean();
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['gcb_action']) && $_POST['gcb_action'] === 'save_db')) {
        if (empty($_POST['_gcb_save_db']) || !wp_verify_nonce($_POST['_gcb_save_db'], 'gcb_save_db')) {
            return '<div class="gcb-alert gcb-error">Nonce non valido (salvataggio).</div>';
        }
        gcb_ensure_table();
        $rows = is_array($_POST['rows'] ?? null) ? $_POST['rows'] : [];
        $updated = 0;
        foreach ($rows as $r) {
            $parent = sanitize_text_field($r['Codice Articolo'] ?? '');
            if ($parent === '') {
                continue;
            }
            if (!isset($r['_gcb_mode'])) {
                $r['_gcb_mode'] = sanitize_text_field($_POST['_gcb_mode'] ?? 'classic');
            }
            if (gcb_db_upsert_row($parent, $r)) {
                $updated++;
            }
        }
        $msg = '<div class="gcb-alert gcb-success">💾 Salvate/aggiornate '.$updated.' righe nel database. Gestisci da <b>[glasscom_catalog_saved]</b>.</div>';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['gcb_action']) && $_POST['gcb_action'] === 'build')) {
        if (empty($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'gcb_build_stan_url')) {
            return '<div class="gcb-alert gcb-error">Nonce non valido (build).</div>';
        }
        $parents_txt = trim((string) ($_POST['parents'] ?? ''));
        $mode = sanitize_text_field($_POST['gcb_mode'] ?? 'classic');
        if (!in_array($mode, ['classic', 'morsetti', 'tubi', 'senza_separatore'], true)) {
            $mode = 'classic';
        }
        if ($mode === 'morsetti' || $mode === 'tubi') {
            $TARGET_COLS = gcb_target_cols_variant_base();
        } else {
            $TARGET_COLS = gcb_target_cols();
        }
        if ($parents_txt === '') {
            $msg = '<div class="gcb-alert gcb-error">Inserisci almeno un codice padre.</div>';
        } else {
            try {
                list($headers, $data) = gcb_read_csv_assoc($cache_file, $cfg['sep'], $cfg['encoding']);
                gcb_ensure_table();
                global $wpdb;
                $table = $wpdb->prefix . 'glasscom_catalog';
                $existing_map = [];
                $existing_rows = $wpdb->get_results("SELECT id, parent_sku FROM {$table}", ARRAY_A);
                if (is_array($existing_rows)) {
                    foreach ($existing_rows as $row) {
                        $ps = strtoupper(trim((string) $row['parent_sku']));
                        if ($ps !== '') {
                            $existing_map[$ps] = (int) $row['id'];
                        }
                    }
                }
                $key_code  = gcb_guess_col($headers, ['codice dream','codice','sku','articolo','code'], 0);
                $key_desc  = gcb_guess_col($headers, ['descrizione'], 3);
                $key_catm  = gcb_guess_col($headers, ['categoria merc','categoria','cat merc','merc'], 2);
                $key_um    = gcb_guess_col($headers, ['u.m.','um','unita','unità','unità di misura','u m'], 4);
                $key_price = gcb_guess_col($headers, ['listino','prezzo','price','€','eur'], 6);

                $parents = array_values(array_filter(array_map('trim', preg_split('/\R+/', $parents_txt))));
                foreach ($parents as $parent_code_input) {
                    $items = [];
                    $parent_code_actual = strtoupper($parent_code_input);
                    $found_match_for_derived_parent = false;

                    if ($mode === 'senza_separatore') {
                        foreach ($data as $row) {
                            $full_code = trim((string) ($row[$key_code] ?? ''));
                            if ($full_code !== '' && stripos($full_code, $parent_code_input) === 0) {
                                $variant_part = trim(substr($full_code, strlen($parent_code_input)));
                                $item = $row;
                                $item['_parent'] = $parent_code_actual;
                                $item['_variant'] = $variant_part;
                                $items[] = $item;
                            }
                        }
                    } else {
                        foreach ($data as $row) {
                            $full = trim((string) ($row[$key_code] ?? ''));
                            if ($full === '') {
                                continue;
                            }
                            $parent = '';
                            $variant = '';
                            if ($mode === 'morsetti') {
                                list($parent, $variant) = gcb_parent_and_variable_for_morsetti($full);
                            } elseif ($mode === 'tubi') {
                                list($parent, $variant) = gcb_parent_and_variable_for_tubi($full);
                            } else {
                                list($parent, $variant) = gcb_parent_and_variant($full);
                            }
                            if ($parent === '') {
                                continue;
                            }
                            if (strtoupper($parent) === strtoupper($parent_code_input)) {
                                $item = $row;
                                $item['_parent'] = $parent;
                                $item['_variant'] = $variant;
                                $items[] = $item;
                                if (!$found_match_for_derived_parent) {
                                    $parent_code_actual = strtoupper($parent);
                                    $found_match_for_derived_parent = true;
                                }
                            }
                        }
                    }

                    if (empty($items)) {
                        $not_found[] = $parent_code_input;
                        $ghost = array_fill_keys($TARGET_COLS, '');
                        $ghost['Codice Articolo'] = $parent_code_actual;
                        $ordered = [];
                        foreach ($TARGET_COLS as $col) {
                            $ordered[$col] = $ghost[$col] ?? '';
                        }
                        $ordered['_already_saved_id'] = $existing_map[$parent_code_actual] ?? 0;
                        $ordered['_not_found'] = 1;
                        $ordered['_gcb_mode'] = $mode;
                        $rows_preview[] = $ordered;
                        continue;
                    }

                    usort($items, function ($a, $b) {
                        return strnatcasecmp($a['_variant'], $b['_variant']);
                    });

                    $out = array_fill_keys($TARGET_COLS, '');
                    $out['Categoria'] = gcb_most_common_field($items, $key_catm);
                    $asset_file = gcb_asset_filename_from_code($parent_code_actual);
                    $out['@image_01'] = 'singoli-componenti/ALLimages/' . $asset_file . '.jpg';
                    $out['@image_scheda'] = 'singoli-componenti/ALLpdf/' . $asset_file . '.pdf';
                    $out['Nome Articolo'] = isset($items[0][$key_desc]) ? trim($items[0][$key_desc]) : '';
                    $out['Codice Articolo'] = $parent_code_actual;
                    $out['UM'] = gcb_most_common_field($items, $key_um);
                    $out['@image_SchedeTecniche'] = $out['@image_scheda'];

                    if ($mode === 'morsetti' || $mode === 'tubi') {
                        $idx = 1;
                        foreach ($items as $item) {
                            $full = trim((string) ($item[$key_code] ?? ''));
                            $variant_part = $item['_variant'] ?? '';
                            $pre_raw = trim((string) ($item[$key_price] ?? ''));
                            $pre = $pre_raw;
                            if ($pre_raw !== '' && is_numeric(str_replace(',', '.', $pre_raw))) {
                                $price_float = floatval(str_replace(',', '.', $pre_raw));
                                $pre = number_format($price_float, 2, ',', '');
                            }
                            $out['cod'.$idx] = $full;
                            $out['Prezzo_cod'.$idx] = $pre;
                            $out['var'.$idx] = $variant_part;
                            $idx++;
                        }
                        $out['_var_count'] = $idx - 1;
                    } else {
                        for ($i = 0; $i < 5; $i++) {
                            $idx = $i + 1;
                            $item = $items[$i] ?? null;
                            if (!$item) {
                                continue;
                            }
                            $full = trim((string) ($item[$key_code] ?? ''));
                            $sigla = gcb_finish_code_from_variant($parent_code_actual, $full);
                            $label = $sigla ? gcb_finish_label_by_code($sigla) : '';
                            $pre_raw = trim((string) ($item[$key_price] ?? ''));
                            $pre = $pre_raw;
                            if ($pre_raw !== '' && is_numeric(str_replace(',', '.', $pre_raw))) {
                                $price_float = floatval(str_replace(',', '.', $pre_raw));
                                $pre = number_format($price_float, 2, ',', '');
                            }
                            $out['cod'.$idx] = $full;
                            $out['Prezzo_cod'.$idx] = $pre;
                            $out['fin'.$idx] = $label;
                            $out['@image_fin'.$idx] = $sigla ? 'finiture/' . $sigla . '.jpg' : '';
                        }
                    }

                    $ordered = [];
                    $actual_cols = array_keys($out);
                    $preview_base_cols = ($mode === 'morsetti' || $mode === 'tubi') ? gcb_target_cols_variant_base() : gcb_target_cols();
                    $preview_cols_set = array_unique(array_merge($preview_base_cols, $actual_cols));
                    foreach ($preview_cols_set as $col) {
                        if (substr($col, 0, 1) === '_' && !in_array($col, ['_var_count'], true)) {
                            continue;
                        }
                        $ordered[$col] = $out[$col] ?? '';
                    }

                    $ordered['_already_saved_id'] = $existing_map[$parent_code_actual] ?? 0;
                    $ordered['_gcb_mode'] = $mode;
                    if ($mode === 'morsetti' || $mode === 'tubi') {
                        $ordered['_var_count'] = $out['_var_count'] ?? 0;
                    }
                    $rows_preview[] = $ordered;
                }

                $stage = !empty($rows_preview) ? 'preview' : 'form';
                if (empty($rows_preview)) {
                    $list = !empty($not_found) ? ' (Non trovati: '.esc_html(implode(', ', $not_found)).')' : '';
                    $msg = '<div class="gcb-alert gcb-warning">Nessun risultato per i codici indicati'.$list.'.</div>';
                }
            } catch (Exception $e) {
                $msg = '<div class="gcb-alert gcb-error">Errore: '.esc_html($e->getMessage()).'</div>';
            }
        }
    }

    ob_start(); ?>
    <style>
      .gcb-wrap { font-family: system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif; max-width: 1200px; }
      .gcb-card { padding:16px; border:1px solid #ddd; border-radius:12px; background:#fff; margin:10px 0; }
      .gcb-btn { padding:10px 14px; background:#0d6efd; color:#fff; border:0; border-radius:6px; font-weight:700; cursor:pointer; text-decoration:none; display:inline-block; }
      .gcb-btn.secondary { background:#6c757d; }
      .gcb-alert { padding:10px; border-radius:8px; margin:10px 0; }
      .gcb-error { background:#fdecec; border:1px solid #f5b5b5; }
      .gcb-success { background:#e9f9ee; border:1px solid #b6efc6; }
      .gcb-summary .item{ padding:18px 0; border-bottom:1px solid #eaeaea; display:grid; grid-template-columns:1fr 200px 160px; gap:24px; align-items:start; }
      .gcb-summary .item:last-child { border-bottom: none; }
      .gcb-summary .sku{font-weight:800;font-size:22px;margin-bottom:8px;}
      .gcb-summary .sku.red{color:#b00020;}
      .gcb-tag{display:inline-block;background:#ffe8e9;border:1px solid #ffb3b8;color:#b00020;padding:2px 8px;border-radius:999px;font-size:12px;margin-left:8px;}
      .gcb-finish{display:grid;grid-template-columns:minmax(220px,1fr) minmax(100px,150px);column-gap:40px;row-gap:8px;font-size:16px; margin-top: 10px;}
      .gcb-finish div{white-space:nowrap; overflow: hidden; text-overflow: ellipsis;}
      .gcb-summary .thumb{justify-self:end;}
      .gcb-summary .item-actions { display: flex; flex-direction: column; gap: 8px; align-items: flex-start;}
      .gcb-modal{position:fixed;inset:0;background:rgba(0,0,0,.35);display:none;align-items:center;justify-content:center;z-index:9999;}
      .gcb-modal .box{background:#fff;padding:18px;border-radius:12px;width:min(94vw,800px); max-height: 90vh; overflow-y: auto;}
      .gcb-grid-4{display:grid;grid-template-columns:1.1fr 1fr 1fr 1.6fr;gap:8px;}
      .gcb-grid-3{display:grid;grid-template-columns:1.5fr 1fr 1fr; gap:8px;}
      .gcb-actions{display:flex;gap:8px;justify-content:flex-end;margin-top:12px;}
      .gcb-input,.gcb-select{width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;}
    </style>
    <div class="gcb-wrap">
        <h2>Glasscom Catalog Builder (STAN da URL fisso + cache)</h2>
        <?php echo $msg; ?>

        <?php if ($stage === 'form'): ?>
            <div class="gcb-card">
                <form method="post" style="display:flex;gap:10px;align-items:center; flex-wrap: wrap;">
                    <?php wp_nonce_field('gcb_refresh_stan', '_gcb_refresh'); ?>
                    <input type="hidden" name="gcb_action" value="refresh_stan">
                    <span>Sorgente: <code><?php echo esc_html($cfg['source_url']); ?></code></span>
                    <button class="gcb-btn secondary" type="submit">↻ Aggiorna</button>
                    <?php if (file_exists($cache_file)): ?>
                        <span style="font-size:12px;color:#555;">Cache: <?php echo esc_html(date('Y-m-d H:i', @filemtime($cache_file))); ?></span>
                    <?php endif; ?>
                </form>
            </div>

            <div class="gcb-card">
                <form method="post">
                    <?php wp_nonce_field('gcb_build_stan_url'); ?>
                    <input type="hidden" name="gcb_action" value="build">
                    <label>Codici Padre (uno per riga)</label>
                    <textarea name="parents" style="width:100%;min-height:140px" placeholder="PB261&#10;SVPB&#10;TUCO-01.304&#10;MB2.6565/17.52" required></textarea>
                    <hr style="border:none;border-top:1px solid #eee;margin:14px 0;">
                    <div style="margin: 10px 0;">
                        <label for="gcb_mode_select" style="display: block; margin-bottom: 5px; font-weight: 600;">Modalità di elaborazione:</label>
                        <select name="gcb_mode" id="gcb_mode_select" class="gcb-select" style="width: 100%; max-width: 400px;">
                            <option value="classic">CLASSIC (Finiture es. IL, CR)</option>
                            <option value="morsetti">SEPARATORE / (es. MB2/17.52)</option>
                            <option value="tubi">SEPARATORE - (es. TUCO-01.304)</option>
                            <option value="senza_separatore">SENZASEPARATORE (es. SVPB -> SVPB2 AN)</option>
                        </select>
                    </div>
                    <div style="margin-top:10px;display:flex;gap:8px;">
                        <button class="gcb-btn" type="submit">Elabora</button>
                        <button class="gcb-btn secondary" type="button" onclick="location.reload()">Ricomincia</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($stage === 'preview'): ?>
            <form method="post" class="gcb-card" id="gcbExportForm" style="padding: 0;">
                <?php wp_nonce_field('gcb_export', '_gcb_export'); ?>
                <input type="hidden" name="gcb_action" value="export">
                <?php $preview_mode = $rows_preview[0]['_gcb_mode'] ?? 'classic'; ?>
                <input type="hidden" name="_gcb_mode" value="<?php echo esc_attr($preview_mode); ?>">

                <div style="padding: 16px; border-bottom: 1px solid #eee;">
                    <p><b>NUMERO DI RIGHE:</b> <?php echo count($rows_preview); ?> (Modalità: <?php echo esc_html(ucfirst(str_replace('_', '', $preview_mode))); ?>)</p>
                    <?php if (!empty($not_found)): ?>
                        <div class="gcb-alert gcb-error"><b>Codici non trovati (verranno esportati come righe vuote):</b> <?php echo esc_html(implode(', ', $not_found)); ?></div>
                    <?php endif; ?>
                </div>

                <div class="gcb-summary">
                    <?php foreach ($rows_preview as $i => $r): ?>
                        <?php
                            $current_mode = $r['_gcb_mode'] ?? 'classic';
                            $already_id   = (int)($r['_already_saved_id'] ?? 0);
                            $parentUpper  = strtoupper(trim((string)($r['Codice Articolo'] ?? '')));
                            $assetFile    = gcb_asset_filename_from_code($parentUpper);
                            $hero_url     = $parentUpper ? sprintf('https://www.glasscom.it/Catalogo2026/FotoCatalogo2026/%s.jpg', $assetFile) : '';
                            $is_not_found = !empty($r['_not_found']);
                            $is_variant_mode_row = ($current_mode === 'morsetti' || $current_mode === 'tubi');
                        ?>
                        <div class="item" data-row="<?php echo (int)$i; ?>" style="<?php echo $is_not_found ? 'background-color: #fff5f5;' : ''; ?>">
                            <div>
                                <div class="sku <?php echo $already_id ? 'red' : ''; ?>">
                                    <?php echo esc_html($parentUpper ?: 'N/D'); ?>
                                    <?php if ($already_id): ?><span class="gcb-tag">già salvato (ID #<?php echo (int)$already_id; ?>)</span><?php endif; ?>
                                    <?php if ($is_not_found): ?><span class="gcb-tag" style="background-color: #fdd; border-color: #fbb;">NON TROVATO</span><?php endif; ?>
                                    <div style="font-size:14px;font-weight:normal;color:#555;">&nbsp;<?php echo esc_html($r['Nome Articolo'] ?? ''); ?></div>
                                </div>

                                <div class="gcb-finish" id="gcbView<?php echo (int)$i; ?>">
                                    <?php
                                    $maxIdx = $is_variant_mode_row ? gcb_max_variant_index($r) : 5;
                                    if ($maxIdx < 1 && !$is_not_found && $is_variant_mode_row) {
                                        $maxIdx = 1;
                                    }
                                    $variant_count_display = 0;
                                    for ($j = 1; $j <= $maxIdx; $j++):
                                        $cod_val = $r['cod'.$j] ?? '';
                                        if (empty($cod_val) && !$is_not_found && !($is_variant_mode_row && $j === 1 && $maxIdx === 1)) {
                                            continue;
                                        }
                                        $variant_count_display++;
                                        if ($is_variant_mode_row) {
                                            $left = 'Var: '.($r['var'.$j] ?? '');
                                        } else {
                                            $left = $r['fin'.$j] ?? '';
                                        }
                                        $right = $r['Prezzo_cod'.$j] ?? '';
                                    ?>
                                        <div><?php echo esc_html($left . ' (' . $cod_val . ')'); ?></div>
                                        <div><?php echo esc_html($right); ?></div>
                                    <?php endfor; ?>
                                    <?php if ($variant_count_display === 0 && !$is_not_found) echo '<div>Nessuna variante trovata/definita.</div>'; ?>
                                </div>
                            </div>

                            <div class="item-actions">
                                <?php if (!$is_not_found): ?>
                                    <button class="gcb-btn" type="button" onclick="gcbOpenEdit(<?php echo (int)$i; ?>)">MODIFICA</button>
                                    <button class="gcb-btn secondary" type="button" onclick="gcbOpenAdd(<?php echo (int)$i; ?>)">AGGIUNGI</button>
                                <?php endif; ?>
                            </div>

                            <div class="thumb">
                                <?php if (!empty($hero_url) && !$is_not_found): ?>
                                    <img src="<?php echo esc_url($hero_url); ?>" alt="Foto <?php echo esc_attr($parentUpper); ?>" style="width:150px;max-width:100%;height:auto;border:1px solid #e5e5e5;border-radius:10px;background:#fff;object-fit:contain;padding:4px;" onerror="this.style.display='none';">
                                <?php endif; ?>
                            </div>

                            <?php
                            $cols_to_save = [];
                            if ($is_variant_mode_row) {
                                $var_count = gcb_max_variant_index($r);
                                $cols_to_save = gcb_target_cols_variant_dynamic($var_count > 0 ? $var_count : 1);
                            } else {
                                $cols_to_save = gcb_target_cols();
                            }
                            foreach ($cols_to_save as $col) {
                                printf('<input type="hidden" name="rows[%1$d][%2$s]" id="gcbField_%1$d_%3$s" value="%4$s">', (int) $i, esc_attr($col), esc_attr(preg_replace('/[^a-zA-Z0-9_@]/', '_', $col)), esc_attr($r[$col] ?? ''));
                            }
                            printf('<input type="hidden" name="rows[%1$d][_gcb_mode]" id="gcbField_%1$d__gcb_mode" value="%2$s">', (int) $i, esc_attr($current_mode));
                            printf('<input type="hidden" name="rows[%1$d][_already_saved_id]" id="gcbField_%1$d__already_saved_id" value="%2$s">', (int) $i, esc_attr($already_id));
                            printf('<input type="hidden" name="rows[%1$d][_not_found]" id="gcbField_%1$d__not_found" value="%2$s">', (int) $i, $is_not_found ? '1' : '0');
                            if ($is_variant_mode_row) {
                                printf('<input type="hidden" name="rows[%1$d][_var_count]" id="gcbField_%1$d__var_count" value="%2$d">', (int) $i, (int) gcb_max_variant_index($r));
                            }
                            ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="padding: 16px; border-top: 1px solid #eee; display:flex;gap:8px;flex-wrap:wrap;">
                    <button class="gcb-btn" type="submit">💾 Esporta CSV</button>
                    <?php wp_nonce_field('gcb_save_db', '_gcb_save_db'); ?>
                    <button class="gcb-btn secondary" name="gcb_action" value="save_db" type="submit">💽 Salva su Database</button>
                    <a class="gcb-btn secondary" href="<?php echo esc_url(get_permalink()); ?>">↩ Ricomincia</a>
                </div>
            </form>

            <div class="gcb-modal" id="gcbModal">
                <div class="box">
                    <h3 id="gcbModalTitle">Modifica Varianti</h3>
                    <div id="gcbModalBody"></div>
                    <div class="gcb-actions">
                        <button type="button" class="gcb-btn secondary" onclick="gcbCloseModal()">Annulla</button>
                        <button type="button" class="gcb-btn" id="gcbModalConfirm">Conferma</button>
                    </div>
                </div>
            </div>
            <script>
                const FINISH_OPTIONS = <?php
                    $fin = gcb_finish_options();
                    echo wp_json_encode(array_map(function ($label, $code) {
                        return ['label' => $label, 'code' => $code];
                    }, array_keys($fin), array_values($fin)), JSON_UNESCAPED_UNICODE);
                ?>;
                const CODE_BY_LABEL = Object.fromEntries(FINISH_OPTIONS.map(f => [f.label, f.code]));
                const LABEL_BY_CODE = Object.fromEntries(FINISH_OPTIONS.map(f => [f.code, f.label]));
                const SELECT_OPTIONS_HTML = ['<option value="">— finitura —</option>']
                    .concat(FINISH_OPTIONS.map(f => `<option value="${f.label}">${f.label}</option>`)).join('');

                function gcbGetField(rowIndex, col) {
                    const id = 'gcbField_' + rowIndex + '_' + col.replace(/[^a-zA-Z0-9_@]/g, '_');
                    const el = document.getElementById(id);
                    return el ? el.value : '';
                }
                function gcbSetField(rowIndex, col, val) {
                    const id = 'gcbField_' + rowIndex + '_' + col.replace(/[^a-zA-Z0-9_@]/g, '_');
                    const el = document.getElementById(id);
                    if (el) {
                        el.value = val;
                    }
                }

                function gcbRefreshView(rowIndex) {
                    const view = document.getElementById('gcbView' + rowIndex);
                    if (!view) {
                        return;
                    }
                    view.innerHTML = '';
                    const mode = gcbGetField(rowIndex, '_gcb_mode') || 'classic';
                    const isVariantMode = mode === 'morsetti' || mode === 'tubi';
                    let maxSlots = 5;
                    if (isVariantMode) {
                        maxSlots = parseInt(gcbGetField(rowIndex, '_var_count') || '1', 10);
                        if (maxSlots < 1) {
                            maxSlots = 1;
                        }
                    }
                    let displayedCount = 0;
                    for (let j = 1; j <= maxSlots; j++) {
                        const cod = gcbGetField(rowIndex, 'cod' + j);
                        if (!cod) {
                            continue;
                        }
                        displayedCount++;
                        const pre = gcbGetField(rowIndex, 'Prezzo_cod' + j);
                        let left;
                        if (isVariantMode) {
                            const vr = gcbGetField(rowIndex, 'var' + j);
                            left = `Var: ${vr || ''} (${cod})`;
                        } else {
                            const fin = gcbGetField(rowIndex, 'fin' + j);
                            left = `${fin || ''} (${cod})`;
                        }
                        const right = pre || '';
                        const finDiv = document.createElement('div');
                        finDiv.textContent = left;
                        const preDiv = document.createElement('div');
                        preDiv.textContent = right;
                        view.appendChild(finDiv);
                        view.appendChild(preDiv);
                    }
                    if (displayedCount === 0) {
                        const noVarDiv = document.createElement('div');
                        noVarDiv.textContent = 'Nessuna variante definita.';
                        view.appendChild(noVarDiv);
                    }
                }

                function gcbCompactVariants(rowIndex) {
                    const mode = gcbGetField(rowIndex, '_gcb_mode') || 'classic';
                    const isVariantMode = mode === 'morsetti' || mode === 'tubi';
                    const maxCheckSlots = isVariantMode ? 99 : 5;
                    const keep = [];
                    let lastUsedSlot = 0;
                    let currentMaxDeclared = isVariantMode ? parseInt(gcbGetField(rowIndex, '_var_count') || '0', 10) : 5;
                    if (currentMaxDeclared < 1 && isVariantMode) {
                        currentMaxDeclared = 1;
                    }
                    for (let j = 1; j <= maxCheckSlots; j++) {
                        const cod = gcbGetField(rowIndex, 'cod' + j);
                        const pre = gcbGetField(rowIndex, 'Prezzo_cod' + j);
                        const fin = gcbGetField(rowIndex, 'fin' + j);
                        const img = gcbGetField(rowIndex, '@image_fin' + j);
                        const v = gcbGetField(rowIndex, 'var' + j);
                        const isFull = isVariantMode ? (cod || pre || v) : (cod || pre || fin || img);
                        if (isFull) {
                            keep.push({ cod, pre, fin, img, v });
                            lastUsedSlot = j;
                        } else if (isVariantMode && j > currentMaxDeclared && gcbGetField(rowIndex, 'cod' + j) === '') {
                            break;
                        }
                    }
                    const finalSlotCount = keep.length;
                    const slotsToWrite = isVariantMode ? finalSlotCount : 5;
                    for (let j = 1; j <= slotsToWrite; j++) {
                        const s = keep[j - 1] || { cod: '', pre: '', fin: '', img: '', v: '' };
                        gcbSetField(rowIndex, 'cod' + j, s.cod);
                        gcbSetField(rowIndex, 'Prezzo_cod' + j, s.pre);
                        gcbSetField(rowIndex, 'fin' + j, isVariantMode ? '' : s.fin);
                        gcbSetField(rowIndex, '@image_fin' + j, isVariantMode ? '' : s.img);
                        gcbSetField(rowIndex, 'var' + j, isVariantMode ? s.v : '');
                    }
                    const maxSlotToClean = isVariantMode ? Math.max(lastUsedSlot, currentMaxDeclared) : 5;
                    for (let j = finalSlotCount + 1; j <= maxSlotToClean; j++) {
                        gcbSetField(rowIndex, 'cod' + j, '');
                        gcbSetField(rowIndex, 'Prezzo_cod' + j, '');
                        gcbSetField(rowIndex, 'fin' + j, '');
                        gcbSetField(rowIndex, '@image_fin' + j, '');
                        gcbSetField(rowIndex, 'var' + j, '');
                    }
                    if (isVariantMode) {
                        gcbSetField(rowIndex, '_var_count', finalSlotCount > 0 ? finalSlotCount : 0);
                    }
                }

                function guessSiglaFromVariant(parent, variant) {
                    parent = (parent || '').trim().toUpperCase();
                    let full = (variant || '').trim().toUpperCase();
                    let rest = full;
                    if (parent && full.startsWith(parent)) {
                        rest = full.slice(parent.length);
                    }
                    rest = rest.replace(/^[\s\-_]+/, '').trim();
                    if (!rest) {
                        return '';
                    }
                    const parts = rest.split(/\s+/);
                    const last = parts.length > 0 ? parts[parts.length - 1] : '';
                    return last && LABEL_BY_CODE[last] ? last : '';
                }
                function syncFromCode(rowIndex, j) {
                    const parent = gcbGetField(rowIndex, 'Codice_Articolo');
                    const codEl = document.getElementById('edit_cod' + j);
                    const imgEl = document.getElementById('edit_img' + j);
                    const sel = document.getElementById('edit_fin' + j);
                    if (!codEl || !sel) {
                        return;
                    }
                    const code = codEl.value.trim();
                    const sigla = guessSiglaFromVariant(parent, code);
                    if (sigla) {
                        const label = LABEL_BY_CODE[sigla];
                        sel.value = label || '';
                        if (imgEl && !imgEl.value.trim()) {
                            imgEl.value = 'finiture/' + sigla + '.jpg';
                        }
                    }
                }
                function onFinishChange(rowIndex, j) {
                    const sel = document.getElementById('edit_fin' + j);
                    const imgEl = document.getElementById('edit_img' + j);
                    const label = sel ? sel.value : '';
                    const sigla = CODE_BY_LABEL[label] || '';
                    if (sigla) {
                        if (imgEl && !imgEl.value.trim()) {
                            imgEl.value = 'finiture/' + sigla + '.jpg';
                        }
                    } else if (imgEl) {
                        imgEl.value = '';
                    }
                }

                function gcbOpenEdit(rowIndex) {
                    const body = document.getElementById('gcbModalBody');
                    const title = document.getElementById('gcbModalTitle');
                    title.textContent = 'Modifica Varianti: ' + gcbGetField(rowIndex, 'Codice_Articolo');
                    const mode = gcbGetField(rowIndex, '_gcb_mode') || 'classic';
                    const isVariantMode = mode === 'morsetti' || mode === 'tubi';
                    let maxSlots = 5;
                    if (isVariantMode) {
                        maxSlots = parseInt(gcbGetField(rowIndex, '_var_count') || '1', 10);
                        if (maxSlots < 1) {
                            maxSlots = 1;
                        }
                    }
                    let allHtml = '';
                    for (let j = 1; j <= maxSlots; j++) {
                        const codVal = gcbGetField(rowIndex, 'cod' + j);
                        const preVal = gcbGetField(rowIndex, 'Prezzo_cod' + j);
                        if (isVariantMode) {
                            const varVal = gcbGetField(rowIndex, 'var' + j);
                            allHtml += `
                            <div class="gcb-grid-3" style="align-items:center;gap:8px;margin-bottom:6px;" data-slot="${j}">
                                <input class="gcb-input" id="edit_cod${j}" placeholder="cod${j}" value="${codVal.replace(/"/g,'&quot;')}">
                                <input class="gcb-input" id="edit_pre${j}" placeholder="Prezzo_cod${j}" value="${preVal.replace(/"/g,'&quot;')}">
                                <div style="display:flex;gap:6px;align-items:center;">
                                    <input class="gcb-input" id="edit_var${j}" placeholder="var${j}" value="${varVal.replace(/"/g,'&quot;')}" style="flex:1;">
                                    <button type="button" class="gcb-btn secondary" onclick="gcbClearSlot(${j}, '${mode}')" title="Svuota slot">X</button>
                                </div>
                            </div>`;
                        } else {
                            const finVal = gcbGetField(rowIndex, 'fin' + j);
                            const imgVal = gcbGetField(rowIndex, '@image_fin' + j);
                            allHtml += `
                            <div class="gcb-grid-4" style="align-items:center;gap:8px;margin-bottom:6px;" data-slot="${j}">
                                <input class="gcb-input" id="edit_cod${j}" placeholder="cod${j}" value="${codVal.replace(/"/g,'&quot;')}">
                                <select class="gcb-select" id="edit_fin${j}">${SELECT_OPTIONS_HTML}</select>
                                <input class="gcb-input" id="edit_pre${j}" placeholder="Prezzo_cod${j}" value="${preVal.replace(/"/g,'&quot;')}">
                                <div style="display:flex;gap:6px;align-items:center;">
                                    <input class="gcb-input" id="edit_img${j}" placeholder="@image_fin${j}" value="${imgVal.replace(/"/g,'&quot;')}" style="flex:1;">
                                    <button type="button" class="gcb-btn secondary" onclick="gcbClearSlot(${j}, '${mode}')" title="Svuota slot">X</button>
                                </div>
                            </div>`;
                        }
                    }
                    body.innerHTML = allHtml;

                    for (let j = 1; j <= maxSlots; j++) {
                        if (isVariantMode) {
                            continue;
                        }
                        const sel = document.getElementById('edit_fin' + j);
                        const cod = document.getElementById('edit_cod' + j);
                        const finVal = gcbGetField(rowIndex, 'fin' + j);
                        const codVal = gcbGetField(rowIndex, 'cod' + j);
                        if (sel) {
                            sel.value = finVal || '';
                            sel.addEventListener('change', () => onFinishChange(rowIndex, j));
                        }
                        if (cod) {
                            cod.addEventListener('blur', () => syncFromCode(rowIndex, j));
                            if (!finVal && codVal) {
                                syncFromCode(rowIndex, j);
                            }
                        }
                    }

                    document.getElementById('gcbModalConfirm').onclick = function () {
                        const currentMaxInModal = body.querySelectorAll('[data-slot]').length;
                        for (let j = 1; j <= currentMaxInModal; j++) {
                            const codEl = document.getElementById('edit_cod' + j);
                            const preEl = document.getElementById('edit_pre' + j);
                            gcbSetField(rowIndex, 'cod' + j, codEl ? codEl.value.trim() : '');
                            gcbSetField(rowIndex, 'Prezzo_cod' + j, preEl ? preEl.value.trim() : '');
                            if (isVariantMode) {
                                const varEl = document.getElementById('edit_var' + j);
                                gcbSetField(rowIndex, 'var' + j, varEl ? varEl.value.trim() : '');
                            } else {
                                const sel = document.getElementById('edit_fin' + j);
                                const imgEl = document.getElementById('edit_img' + j);
                                const label = sel ? sel.value : '';
                                let sigla = CODE_BY_LABEL[label] || '';
                                if (!label && codEl && codEl.value.trim()) {
                                    const inferredSigla = guessSiglaFromVariant(gcbGetField(rowIndex, 'Codice_Articolo'), codEl.value.trim());
                                    if (inferredSigla) {
                                        sigla = inferredSigla;
                                        const inferredLabel = LABEL_BY_CODE[sigla];
                                        if (inferredLabel) {
                                            gcbSetField(rowIndex, 'fin' + j, inferredLabel);
                                        }
                                        if (sel && inferredLabel) {
                                            sel.value = inferredLabel;
                                        }
                                    }
                                } else {
                                    gcbSetField(rowIndex, 'fin' + j, label);
                                }
                                let img = imgEl ? imgEl.value.trim() : '';
                                if (!img && sigla) {
                                    img = 'finiture/' + sigla + '.jpg';
                                }
                                gcbSetField(rowIndex, '@image_fin' + j, img);
                            }
                        }
                        gcbCompactVariants(rowIndex);
                        gcbRefreshView(rowIndex);
                        gcbCloseModal();
                    };
                    document.getElementById('gcbModal').style.display = 'flex';
                }

                function gcbClearSlot(j, mode) {
                    const codEl = document.getElementById('edit_cod' + j);
                    if (codEl) {
                        codEl.value = '';
                    }
                    const preEl = document.getElementById('edit_pre' + j);
                    if (preEl) {
                        preEl.value = '';
                    }
                    if (mode === 'morsetti' || mode === 'tubi') {
                        const varEl = document.getElementById('edit_var' + j);
                        if (varEl) {
                            varEl.value = '';
                        }
                    } else {
                        const finEl = document.getElementById('edit_fin' + j);
                        if (finEl) {
                            finEl.value = '';
                        }
                        const imgEl = document.getElementById('edit_img' + j);
                        if (imgEl) {
                            imgEl.value = '';
                        }
                    }
                }

                function gcbOpenAdd(rowIndex) {
                    const mode = gcbGetField(rowIndex, '_gcb_mode') || 'classic';
                    const isVariantMode = mode === 'morsetti' || mode === 'tubi';
                    let slot = 0;
                    const maxCheck = isVariantMode ? 99 : 5;
                    let currentMax = 0;
                    for (let j = 1; j <= maxCheck; j++) {
                        const cod = gcbGetField(rowIndex, 'cod' + j);
                        const pre = gcbGetField(rowIndex, 'Prezzo_cod' + j);
                        const relevantField = isVariantMode ? gcbGetField(rowIndex, 'var' + j) : gcbGetField(rowIndex, 'fin' + j);
                        if (cod || pre || relevantField) {
                            currentMax = j;
                        } else if (j <= (isVariantMode ? currentMax + 1 : 5)) {
                            slot = j;
                            break;
                        }
                        if (!isVariantMode && j >= 5) {
                            slot = 0;
                            break;
                        }
                    }
                    if (isVariantMode && slot === 0) {
                        slot = currentMax + 1;
                    }
                    if (slot === 0 && !isVariantMode) {
                        alert('Hai già 5 finiture.');
                        return;
                    }
                    const body = document.getElementById('gcbModalBody');
                    const title = document.getElementById('gcbModalTitle');
                    title.textContent = `Aggiungi Variante (slot ${slot})`;
                    let html = '';
                    if (isVariantMode) {
                        html = `
                        <div>
                            <input class="gcb-input" id="add_cod" placeholder="Codice Variante (es. MIOCOD${mode === 'morsetti' ? '/' : '-'}VAR)">
                            <input class="gcb-input" id="add_pre" placeholder="Prezzo" style="margin-top:8px;">
                            <input class="gcb-input" id="add_var" placeholder="Variabile (es. ${mode === 'morsetti' ? '17.52' : '01.304'})" style="margin-top:8px;">
                            <button type="button" class="gcb-btn secondary" id="add_clear" style="margin-top:8px;">Svuota</button>
                        </div>`;
                    } else {
                        html = `
                        <div>
                            <select class="gcb-select" id="add_fin">${SELECT_OPTIONS_HTML}</select>
                            <input class="gcb-input" id="add_cod" placeholder="Codice variante" style="margin-top:8px;">
                            <input class="gcb-input" id="add_pre" placeholder="Prezzo variante" style="margin-top:8px;">
                            <div style="display:flex;gap:6px;margin-top:8px;">
                                <input class="gcb-input" id="add_img" placeholder="URL immagine finitura" style="flex:1;">
                                <button type="button" class="gcb-btn secondary" id="add_clear">Svuota</button>
                            </div>
                        </div>`;
                    }
                    body.innerHTML = html;
                    if (!isVariantMode) {
                        const finSel = document.getElementById('add_fin');
                        if (finSel) {
                            finSel.addEventListener('change', function () {
                                const parent = gcbGetField(rowIndex, 'Codice_Articolo');
                                const label = this.value || '';
                                const sigla = CODE_BY_LABEL[label] || '';
                                if (sigla) {
                                    const codEl = document.getElementById('add_cod');
                                    if (codEl) {
                                        codEl.value = (parent + ' ' + sigla).trim();
                                    }
                                    const imgEl = document.getElementById('add_img');
                                    if (imgEl && !imgEl.value.trim()) {
                                        imgEl.value = 'finiture/' + sigla + '.jpg';
                                    }
                                } else {
                                    const imgEl = document.getElementById('add_img');
                                    if (imgEl) {
                                        imgEl.value = '';
                                    }
                                }
                            });
                        }
                    }
                    const clearBtn = document.getElementById('add_clear');
                    if (clearBtn) {
                        clearBtn.onclick = function () {
                            const codEl = document.getElementById('add_cod');
                            if (codEl) {
                                codEl.value = '';
                            }
                            const preEl = document.getElementById('add_pre');
                            if (preEl) {
                                preEl.value = '';
                            }
                            if (isVariantMode) {
                                const varEl = document.getElementById('add_var');
                                if (varEl) {
                                    varEl.value = '';
                                }
                            } else {
                                const finEl = document.getElementById('add_fin');
                                if (finEl) {
                                    finEl.value = '';
                                }
                                const imgEl = document.getElementById('add_img');
                                if (imgEl) {
                                    imgEl.value = '';
                                }
                            }
                        };
                    }
                    document.getElementById('gcbModalConfirm').onclick = function () {
                        gcbSetField(rowIndex, 'cod' + slot, (document.getElementById('add_cod')?.value || '').trim());
                        gcbSetField(rowIndex, 'Prezzo_cod' + slot, (document.getElementById('add_pre')?.value || '').trim());
                        if (isVariantMode) {
                            gcbSetField(rowIndex, 'var' + slot, (document.getElementById('add_var')?.value || '').trim());
                            const currentMax = parseInt(gcbGetField(rowIndex, '_var_count') || '0', 10);
                            if (slot > currentMax) {
                                gcbSetField(rowIndex, '_var_count', slot);
                            }
                        } else {
                            const label = document.getElementById('add_fin')?.value;
                            const sigla = CODE_BY_LABEL[label] || '';
                            gcbSetField(rowIndex, 'fin' + slot, label);
                            let img = (document.getElementById('add_img')?.value || '').trim();
                            if (!img && sigla) {
                                img = 'finiture/' + sigla + '.jpg';
                            }
                            gcbSetField(rowIndex, '@image_fin' + slot, img);
                        }
                        gcbCompactVariants(rowIndex);
                        gcbRefreshView(rowIndex);
                        gcbCloseModal();
                    };
                    document.getElementById('gcbModal').style.display = 'flex';
                }
                function gcbCloseModal() {
                    document.getElementById('gcbModal').style.display = 'none';
                }
            </script>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/* =================== SHORTCODE: SAVED LIST =================== */
add_shortcode('glasscom_catalog_saved', 'glasscom_catalog_saved_shortcode');

function glasscom_catalog_saved_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>Devi essere autenticato.</p>';
    }
    gcb_ensure_table();
    global $wpdb;
    $table = $wpdb->prefix . 'glasscom_catalog';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['gcb_action']) && $_POST['gcb_action'] === 'bulk_delete')) {
        if (empty($_POST['_gcb_bulkdel']) || !wp_verify_nonce($_POST['_gcb_bulkdel'], 'gcb_bulkdel')) {
            return '<div class="gcb-alert gcb-error">Nonce non valido (eliminazione multipla).</div>';
        }
        $ids = array_values(array_filter(array_map('intval', $_POST['ids'] ?? [])));
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE id IN ($placeholders)", $ids));
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['gcb_action']) && $_POST['gcb_action'] === 'update_row')) {
        if (empty($_POST['_gcb_update']) || !wp_verify_nonce($_POST['_gcb_update'], 'gcb_update')) {
            return '<div class="gcb-alert gcb-error">Nonce non valido (modifica).</div>';
        }
        $id = (int) ($_POST['id'] ?? 0);
        $data = [];
        foreach (($_POST['row'] ?? []) as $key => $value) {
            $data[$key] = is_array($value) ? '' : wp_unslash($value);
        }
        if ($id > 0) {
            $wpdb->update(
                $table,
                [
                    'record'     => wp_json_encode($data, JSON_UNESCAPED_UNICODE),
                    'created_at' => current_time('mysql'),
                ],
                ['id' => $id],
                ['%s', '%s'],
                ['%d']
            );
        }
    }

    $edit_id = isset($_GET['gcb_edit_id']) ? (int) $_GET['gcb_edit_id'] : 0;
    if ($edit_id > 0) {
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $edit_id), ARRAY_A);
        if (!$row) {
            return '<p>Record non trovato.</p>';
        }
        $data = json_decode($row['record'], true);
        if (!is_array($data)) {
            $data = [];
        }
        $base_fields = [
            'Prodotto','Categoria','@image_01','@image_scheda',
            'Nome Articolo','Sottotitolo','Codice Articolo',
            'Dimensione','Per Vetro','Materiale','UM','@image_SchedeTecniche'
        ];
        $finish_opts = gcb_finish_options();
        $edit_mode = $data['_gcb_mode'] ?? 'classic';
        $is_variant_mode_edit = $edit_mode === 'morsetti' || $edit_mode === 'tubi';
        $max_variants_edit = $is_variant_mode_edit ? gcb_max_variant_index($data) : 5;
        if ($max_variants_edit < 1 && $is_variant_mode_edit) {
            $max_variants_edit = 1;
        }
        ob_start(); ?>
        <style>
            .gcb-edit { font-family: system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif; max-width: 1100px; margin: 0 auto; }
            .gcb-card { padding:16px; border:1px solid #e7e7e7; border-radius:14px; background:#fff; margin:12px 0; }
            .gcb-h2 { margin:0 0 12px; font-size:24px; font-weight:800; }
            .gcb-grid-2 { display:grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap:12px; }
            .gcb-grid-1 { display:grid; grid-template-columns: 1fr; gap:12px; }
            .gcb-input, .gcb-select { width:100%; padding:10px; border:1px solid #ccc; border-radius:8px; }
            .gcb-label { font-size:12px; color:#666; display:block; margin-bottom:6px; }
            .gcb-btn { padding:10px 14px; background:#0d6efd; color:#fff; border:0; border-radius:8px; font-weight:700; cursor:pointer; text-decoration:none; }
            .gcb-btn.secondary { background:#6c757d; }
            .gcb-variant { border:1px dashed #e1e1e1; border-radius:12px; padding:12px; background:#fafafa; }
            .gcb-variant h3 { margin:0 0 10px; font-size:16px; font-weight:800; display:flex; align-items:center; gap:8px; }
            .gcb-actions { display:flex; gap:8px; margin-top:14px; }
            .gcb-grid-var-classic { display:grid; grid-template-columns:1.5fr 1fr 1fr 1.5fr; gap:8px; }
            .gcb-grid-var-morsetti { display:grid; grid-template-columns:1.5fr 1fr 1fr; gap:8px; }
            @media (max-width: 820px) {
                .gcb-grid-2, .gcb-grid-var-classic, .gcb-grid-var-morsetti { grid-template-columns:1fr; }
            }
        </style>
        <div class="gcb-edit">
            <div class="gcb-card">
                <h2 class="gcb-h2">Modifica record #<?php echo (int) $row['id']; ?> — <?php echo esc_html($row['parent_sku']); ?> (Modalità: <?php echo esc_html(ucfirst(str_replace('_', '', $edit_mode))); ?>)</h2>
                <form method="post">
                    <?php wp_nonce_field('gcb_update', '_gcb_update'); ?>
                    <input type="hidden" name="gcb_action" value="update_row">
                    <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                    <input type="hidden" name="row[_gcb_mode]" value="<?php echo esc_attr($edit_mode); ?>">

                    <div class="gcb-card" style="margin-top:0;">
                        <h3 class="gcb-h2" style="font-size:18px;">Dati generali</h3>
                        <div class="gcb-grid-2">
                            <?php foreach ($base_fields as $field): ?>
                                <label class="gcb-grid-1">
                                    <span class="gcb-label"><?php echo esc_html($field); ?></span>
                                    <input class="gcb-input" type="text" name="row[<?php echo esc_attr($field); ?>]" value="<?php echo esc_attr($data[$field] ?? ''); ?>">
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="gcb-card">
                        <h3 class="gcb-h2" style="font-size:18px;">Varianti di prodotto</h3>
                        <?php for ($i = 1; $i <= $max_variants_edit; $i++):
                            $cod = 'cod'.$i;
                            $pre = 'Prezzo_cod'.$i;
                        ?>
                            <div class="gcb-variant" style="margin-bottom:12px;">
                                <h3>Variante <?php echo $i; ?></h3>
                                <?php if ($is_variant_mode_edit):
                                    $var = 'var'.$i;
                                ?>
                                    <div class="gcb-grid-var-morsetti">
                                        <label class="gcb-grid-1"><span class="gcb-label"><?php echo esc_html(strtoupper($cod)); ?></span><input class="gcb-input" type="text" name="row[<?php echo esc_attr($cod); ?>]" value="<?php echo esc_attr($data[$cod] ?? ''); ?>"></label>
                                        <label class="gcb-grid-1"><span class="gcb-label"><?php echo esc_html($pre); ?></span><input class="gcb-input" type="text" name="row[<?php echo esc_attr($pre); ?>]" value="<?php echo esc_attr($data[$pre] ?? ''); ?>"></label>
                                        <label class="gcb-grid-1"><span class="gcb-label"><?php echo esc_html(strtoupper($var)); ?></span><input class="gcb-input" type="text" name="row[<?php echo esc_attr($var); ?>]" value="<?php echo esc_attr($data[$var] ?? ''); ?>"></label>
                                    </div>
                                <?php else:
                                    $fin = 'fin'.$i;
                                    $img = '@image_fin'.$i;
                                ?>
                                    <div class="gcb-grid-var-classic">
                                        <label class="gcb-grid-1"><span class="gcb-label"><?php echo esc_html(strtoupper($cod)); ?></span><input class="gcb-input" type="text" name="row[<?php echo esc_attr($cod); ?>]" value="<?php echo esc_attr($data[$cod] ?? ''); ?>"></label>
                                        <label class="gcb-grid-1"><span class="gcb-label"><?php echo esc_html($fin); ?></span><select class="gcb-select" name="row[<?php echo esc_attr($fin); ?>]"><option value="">— seleziona —</option><?php foreach ($finish_opts as $label => $sigla): ?><option value="<?php echo esc_attr($label); ?>" <?php selected(($data[$fin] ?? ''), $label); ?>><?php echo esc_html($label . ' (' . $sigla . ')'); ?></option><?php endforeach; ?></select></label>
                                        <label class="gcb-grid-1"><span class="gcb-label"><?php echo esc_html($pre); ?></span><input class="gcb-input" type="text" name="row[<?php echo esc_attr($pre); ?>]" value="<?php echo esc_attr($data[$pre] ?? ''); ?>"></label>
                                        <label class="gcb-grid-1"><span class="gcb-label"><?php echo esc_html($img); ?></span><input class="gcb-input" type="text" name="row[<?php echo esc_attr($img); ?>]" value="<?php echo esc_attr($data[$img] ?? ''); ?>"></label>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endfor; ?>
                    </div>

                    <?php
                        $parent_raw = (string) ($row['parent_sku'] ?? '');
                        $parent_upper = strtoupper($parent_raw);
                        $asset_file = gcb_asset_filename_from_code($parent_upper);
                        $hero_url_safe = sprintf('https://www.glasscom.it/Catalogo2026/FotoCatalogo2026/%s.jpg', $asset_file);
                        $hero_url_raw = sprintf('https://www.glasscom.it/Catalogo2026/FotoCatalogo2026/%s.jpg', $parent_upper);
                        $scheda_url = sprintf('https://www.glasscom.it/fotodreamnet/%s.pdf', $asset_file);
                    ?>
                    <div style="margin:10px 0 16px;display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap;">
                        <div style="flex:0 0 240px;">
                            <img src="<?php echo esc_url($hero_url_safe); ?>" alt="Foto <?php echo esc_attr($parent_upper); ?>" style="width:100%;max-width:240px;height:auto;border:1px solid #e5e5e5;border-radius:12px;background:#fff;object-fit:contain;padding:6px;" onerror="this.onerror=null;this.src='<?php echo esc_js($hero_url_raw); ?>'; this.style.border='1px solid #fcc';">
                            <div style="font-size:12px;color:#777;margin-top:6px;">Foto <?php echo esc_html($asset_file); ?>.jpg</div>
                        </div>
                        <div style="font-size:14px;color:#555;line-height:1.5;flex:1;">
                            <div><strong>Codice Padre:</strong> <?php echo esc_html($parent_upper); ?></div>
                            <div style="margin:6px 0;"><code><?php echo esc_html($hero_url_safe); ?></code></div>
                            <a href="<?php echo esc_url($scheda_url); ?>" target="_blank" class="gcb-btn" style="margin-top:8px;"> 📄 Scarica la scheda tecnica </a>
                        </div>
                    </div>

                    <div class="gcb-actions">
                        <button class="gcb-btn" type="submit">💾 Salva modifiche</button>
                        <a class="gcb-btn secondary" href="<?php echo esc_url(remove_query_arg('gcb_edit_id')); ?>">↩ Indietro</a>
                    </div>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    $f_cat = trim((string) ($_GET['cat'] ?? ''));
    $f_sub = trim((string) ($_GET['sub'] ?? ''));
    $q     = trim((string) ($_GET['q'] ?? ''));
    $sort  = trim((string) ($_GET['sort'] ?? 'created_at'));
    $dir   = strtolower((string) ($_GET['dir'] ?? 'desc'));
    $per_page = (int) ($_GET['pp'] ?? 25);
    if (!in_array($per_page, [25, 50, 100], true)) {
        $per_page = 25;
    }
    $page = max(1, (int) ($_GET['gpg'] ?? 1));

    $all = $wpdb->get_results("SELECT id, parent_sku, created_at, record FROM {$table} ORDER BY created_at DESC", ARRAY_A);
    $cat_set = [];
    $sub_by_cat = [];
    foreach ($all as $row) {
        $data = json_decode($row['record'], true);
        if (!is_array($data)) {
            continue;
        }
        list($cat, $sub) = gcb_extract_cat_sub($data['Categoria'] ?? '', $data['Sottotitolo'] ?? '');
        if ($cat !== '') {
            $cat_set[$cat] = true;
        }
        if ($cat !== '' && $sub !== '') {
            $sub_by_cat[$cat][$sub] = true;
        }
    }
    $cats = array_keys($cat_set);
    sort($cats, SORT_NATURAL | SORT_FLAG_CASE);
    $subs = [];
    if ($f_cat !== '' && isset($sub_by_cat[strtoupper($f_cat)])) {
        $subs = array_keys($sub_by_cat[strtoupper($f_cat)]);
        sort($subs, SORT_NATURAL | SORT_FLAG_CASE);
    }

    $rows = [];
    foreach ($all as $row) {
        $data = json_decode($row['record'], true);
        if (!is_array($data)) {
            $data = [];
        }
        list($cat, $sub) = gcb_extract_cat_sub($data['Categoria'] ?? '', $data['Sottotitolo'] ?? '');
        if ($f_cat !== '' && strtoupper($f_cat) !== $cat) {
            continue;
        }
        if ($f_sub !== '' && strtoupper($f_sub) !== $sub) {
            continue;
        }
        $parent = (string) $row['parent_sku'];
        $nome_art = (string) ($data['Nome Articolo'] ?? '');
        $fin_count = gcb_count_finishes($data);
        if ($q !== '') {
            $qnorm = mb_strtolower($q);
            if (mb_stripos($parent, $qnorm) === false && mb_stripos($nome_art, $qnorm) === false) {
                continue;
            }
        }
        $rows[] = [
            'id'            => (int) $row['id'],
            'parent_sku'    => $parent,
            'created_at'    => (string) $row['created_at'],
            'cat'           => $cat,
            'nome_articolo' => $nome_art,
            'fin_count'     => $fin_count,
            '_data'         => $data,
        ];
    }
    foreach ($rows as &$r) {
        $r['_has_zp'] = gcb_row_has_zero_price($r['_data']);
    }
    unset($r);

    $validSort = ['id', 'parent_sku', 'cat', 'nome_articolo', 'fin_count', 'created_at'];
    if (!in_array($sort, $validSort, true)) {
        $sort = 'created_at';
    }
    $dirMul = ($dir === 'asc') ? 1 : -1;
    usort($rows, function ($a, $b) use ($sort, $dirMul) {
        $va = $a[$sort] ?? '';
        $vb = $b[$sort] ?? '';
        if ($sort === 'id' || $sort === 'fin_count') {
            return (($va <=> $vb)) * $dirMul;
        }
        return strnatcasecmp((string) $va, (string) $vb) * $dirMul;
    });

    $total = count($rows);
    $total_pages = max(1, (int) ceil($total / $per_page));
    if ($page > $total_pages) {
        $page = $total_pages;
    }
    $offset = ($page - 1) * $per_page;
    $page_rows = array_slice($rows, $offset, $per_page);

    $page_url = get_permalink(get_queried_object_id());
    $base_url = remove_query_arg(['gpg', 'pp', 'gcb_export_saved', '_wpnonce'], $page_url);
    $qs = $_GET;
    unset($qs['gpg'], $qs['pp'], $qs['gcb_export_saved'], $qs['_wpnonce']);

    $export_url = add_query_arg(
        array_merge($qs, [
            'gcb_export_saved' => 1,
            '_wpnonce'         => wp_create_nonce('gcb_export_saved'),
        ]),
        $base_url
    );

    $th_link = function ($field, $label) use ($qs, $sort, $dir, $base_url) {
        $nextDir = ($sort === $field && $dir === 'asc') ? 'desc' : 'asc';
        $url = add_query_arg(array_merge($qs, ['sort' => $field, 'dir' => $nextDir]), $base_url);
        $arrow = ($sort === $field) ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';
        return '<a href="'.esc_url($url).'" style="color:inherit;text-decoration:none;">'.esc_html($label.$arrow).'</a>';
    };

    ob_start(); ?>
    <style>
        .gcb-list { font-family: system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif; max-width: 1100px; }
        .gcb-list table { width:100%; border-collapse: collapse; margin-top: 10px; }
        .gcb-list th, .gcb-list td { border:1px solid #e5e5e5; padding:8px; text-align:left; vertical-align: middle; }
        .gcb-list th { background-color: #f9f9f9; }
        .gcb-btn { padding:8px 12px; background:#0d6efd; color:#fff; border:0; border-radius:6px; font-weight:700; cursor:pointer; text-decoration:none; display:inline-block; line-height:1; }
        .gcb-btn.secondary { background:#6c757d; }
        .gcb-topbar { display:flex; justify-content:space-between; align-items:center; margin:8px 0 12px; gap:10px; flex-wrap:wrap; }
        form.inline { display:inline; margin:0; }
        .gcb-filters { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
        .gcb-select, .gcb-input { padding:8px; border:1px solid #ccc; border-radius:6px; }
        .row-nf { background:#fff5f5 !important; }
        .row-zp { background:#fffbeb !important; }
        .gcb-pager { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-top:15px; }
        .gcb-pager a, .gcb-pager span { padding:6px 10px; border:1px solid #ddd; border-radius:6px; text-decoration:none; background:#fff; color:#444; }
        .gcb-pager a:hover { background:#f0f0f0; }
        .gcb-pager .active { background:#0d6efd; color:#fff; border-color:#0d6efd; font-weight:bold; }
    </style>
    <div class="gcb-list">
        <div class="gcb-topbar">
            <div class="gcb-filters">
                <form method="get" id="gcbFilterForm" class="inline" style="display:flex; gap:8px; align-items:center;">
                    <label>Cat</label>
                    <select name="cat" class="gcb-select" onchange="this.form.submit()">
                        <option value="">Tutte</option>
                        <?php foreach ($cats as $cat): ?>
                            <option value="<?php echo esc_attr($cat); ?>" <?php selected($f_cat, $cat); ?>><?php echo esc_html($cat); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label>Sub</label>
                    <select name="sub" class="gcb-select" onchange="this.form.submit()">
                        <option value="">Tutte</option>
                        <?php foreach ($subs as $sub): ?>
                            <option value="<?php echo esc_attr($sub); ?>" <?php selected($f_sub, $sub); ?>><?php echo esc_html($sub); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="q" class="gcb-input" placeholder="Cerca..." value="<?php echo esc_attr($q); ?>" style="width: 150px;" />
                    <select name="pp" class="gcb-select" onchange="this.form.submit()" title="Record per pagina">
                        <option value="25" <?php selected($per_page, 25); ?>>25</option>
                        <option value="50" <?php selected($per_page, 50); ?>>50</option>
                        <option value="100" <?php selected($per_page, 100); ?>>100</option>
                    </select>
                    <?php foreach ($_GET as $k => $v) {
                        if (in_array($k, ['cat', 'sub', 'q', 'pp', 'gpg'], true)) {
                            continue;
                        }
                        printf('<input type="hidden" name="%s" value="%s">', esc_attr($k), esc_attr($v));
                    } ?>
                    <button class="gcb-btn secondary" type="submit" style="padding: 8px 10px;">🔍</button>
                </form>
            </div>
            <div>
                <a class="gcb-btn" href="<?php echo esc_url($export_url); ?>">⬇️ Esporta</a>
                <a class="gcb-btn secondary" href="<?php echo esc_url($base_url); ?>">❌ Filtri</a>
            </div>
        </div>

        <?php if (empty($page_rows)): ?>
            <p>Nessun record salvato con i filtri correnti.</p>
        <?php else: ?>
            <form method="post" onsubmit="return confirm('Eliminare i record selezionati?');">
                <?php wp_nonce_field('gcb_bulkdel', '_gcb_bulkdel'); ?>
                <input type="hidden" name="gcb_action" value="bulk_delete">
                <div style="margin: 0 0 8px; display: flex; justify-content: space-between; align-items: center;">
                    <button class="gcb-btn secondary" type="submit">🗑️ Elimina selezionati</button>
                    <span style="font-size: 12px; color: #555;">Trovati <?php echo $total; ?> record. Pagina <?php echo $page; ?> di <?php echo $total_pages; ?>.</span>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th style="width: 20px;"><input type="checkbox" onclick="document.querySelectorAll('.gcb-check').forEach(cb => cb.checked = this.checked)"></th>
                            <th style="width: 50px;"><?php echo $th_link('id', 'ID'); ?></th>
                            <th style="width: 80px;">Foto</th>
                            <th><?php echo $th_link('parent_sku', 'Codice Padre'); ?></th>
                            <th><?php echo $th_link('cat', 'Categoria'); ?></th>
                            <th><?php echo $th_link('nome_articolo', 'Nome Articolo'); ?></th>
                            <th style="width: 60px; text-align: center;"><?php echo $th_link('fin_count', 'Var'); ?></th>
                            <th style="width: 140px;"><?php echo $th_link('created_at', 'Data'); ?></th>
                            <th style="width: 100px;">Azione</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($page_rows as $row):
                            $rowClass = '';
                            if (isset($row['fin_count']) && (int) $row['fin_count'] === 0) {
                                $rowClass = 'row-nf';
                            } elseif (!empty($row['_has_zp'])) {
                                $rowClass = 'row-zp';
                            }
                            $parentUpper = strtoupper(trim((string) $row['parent_sku']));
                            $assetFile = gcb_asset_filename_from_code($parentUpper);
                            $thumb_url = $parentUpper ? sprintf('https://www.glasscom.it/Catalogo2026/FotoCatalogo2026/%s.jpg', $assetFile) : '';
                            $thumb_url_fallback = $parentUpper ? sprintf('https://www.glasscom.it/Catalogo2026/FotoCatalogo2026/%s.jpg', $parentUpper) : '';
                        ?>
                            <tr class="<?php echo esc_attr($rowClass); ?>">
                                <td><input class="gcb-check" type="checkbox" name="ids[]" value="<?php echo (int) $row['id']; ?>"></td>
                                <td><?php echo (int) $row['id']; ?></td>
                                <td style="text-align: center;">
                                    <?php if ($thumb_url): ?>
                                        <img src="<?php echo esc_url($thumb_url); ?>" alt="<?php echo esc_attr($parentUpper); ?>" style="width:60px;height:auto;border:1px solid #ddd;border-radius:6px;background:#fff;object-fit:contain;padding:2px;" onerror="this.onerror=null; this.src='<?php echo esc_js($thumb_url_fallback); ?>'; this.style.border='1px solid #fcc';">
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($row['parent_sku']); ?></td>
                                <td><?php echo esc_html($row['cat']); ?></td>
                                <td><?php echo esc_html($row['nome_articolo']); ?></td>
                                <td style="text-align:center;"><?php echo (int) $row['fin_count']; ?></td>
                                <td><?php echo esc_html(date('d/m/y H:i', strtotime($row['created_at']))); ?></td>
                                <td><a class="gcb-btn" href="<?php echo esc_url(add_query_arg('gcb_edit_id', (int) $row['id'], $base_url)); ?>">✏️ Mod</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>

            <div class="gcb-pager">
                <?php if ($page > 1) {
                    $prev_url = add_query_arg(array_merge($qs, ['gpg' => $page - 1, 'pp' => $per_page]), $base_url);
                    echo '<a href="'.esc_url($prev_url).'">« Prec</a>';
                } else {
                    echo '<span style="color: #ccc; border-color: #eee;">« Prec</span>';
                }
                $links_to_show = 5;
                $start = max(1, $page - floor($links_to_show / 2));
                $end = min($total_pages, $start + $links_to_show - 1);
                if ($end - $start + 1 < $links_to_show) {
                    $start = max(1, $end - $links_to_show + 1);
                }
                if ($start > 1) {
                    $url = add_query_arg(array_merge($qs, ['gpg' => 1, 'pp' => $per_page]), $base_url);
                    echo '<a href="'.esc_url($url).'">1</a>';
                    if ($start > 2) {
                        echo '<span>...</span>';
                    }
                }
                for ($p = $start; $p <= $end; $p++) {
                    $url = add_query_arg(array_merge($qs, ['gpg' => $p, 'pp' => $per_page]), $base_url);
                    if ($p === $page) {
                        echo '<span class="active">'.(int) $p.'</span>';
                    } else {
                        echo '<a href="'.esc_url($url).'">'.(int) $p.'</a>';
                    }
                }
                if ($end < $total_pages) {
                    if ($end < $total_pages - 1) {
                        echo '<span>...</span>';
                    }
                    $url = add_query_arg(array_merge($qs, ['gpg' => $total_pages, 'pp' => $per_page]), $base_url);
                    echo '<a href="'.esc_url($url).'">'.(int) $total_pages.'</a>';
                }
                if ($page < $total_pages) {
                    $next_url = add_query_arg(array_merge($qs, ['gpg' => $page + 1, 'pp' => $per_page]), $base_url);
                    echo '<a href="'.esc_url($next_url).'">Succ »</a>';
                } else {
                    echo '<span style="color: #ccc; border-color: #eee;">Succ »</span>';
                }
                ?>
                <span style="margin-left:auto; color:#555; font-size: 12px;">Totale: <?php echo (int) $total; ?></span>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/* === Handler export salvati (Aggiornato per 4 modalità) === */
add_action('template_redirect', 'gcb_handle_export_saved');
function gcb_handle_export_saved() {
    if (empty($_GET['gcb_export_saved'])) {
        return;
    }
    if (!is_user_logged_in()) {
        wp_die('Non autorizzato.');
    }
    if (empty($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'gcb_export_saved')) {
        wp_die('Nonce non valido (export salvati).');
    }

    gcb_ensure_table();
    global $wpdb;
    $table = $wpdb->prefix . 'glasscom_catalog';
    $all_records = $wpdb->get_results("SELECT id, parent_sku, created_at, record FROM {$table} ORDER BY id ASC", ARRAY_A);

    $f_cat = trim((string) ($_GET['cat'] ?? ''));
    $f_sub = trim((string) ($_GET['sub'] ?? ''));
    $q     = trim((string) ($_GET['q'] ?? ''));
    $sort  = trim((string) ($_GET['sort'] ?? 'id'));
    $dir   = strtolower((string) ($_GET['dir'] ?? 'asc'));

    $rows_to_export_data = [];
    $max_variants_needed = 0;
    $detected_mode = 'classic';
    $first_row_processed = false;

    foreach ($all_records as $record) {
        $data = json_decode($record['record'], true);
        if (!is_array($data)) {
            continue;
        }
        list($cat, $sub) = gcb_extract_cat_sub($data['Categoria'] ?? '', $data['Sottotitolo'] ?? '');
        if ($f_cat !== '' && strtoupper($f_cat) !== $cat) {
            continue;
        }
        if ($f_sub !== '' && strtoupper($f_sub) !== $sub) {
            continue;
        }
        $parent = $data['Codice Articolo'] ?? '';
        $nome_art = $data['Nome Articolo'] ?? '';
        if ($q !== '') {
            $qnorm = mb_strtolower($q);
            if (mb_stripos($parent, $qnorm) === false && mb_stripos($nome_art, $qnorm) === false) {
                continue;
            }
        }
        $row_mode = $data['_gcb_mode'] ?? 'classic';
        if (!$first_row_processed) {
            if (!in_array($row_mode, ['classic', 'morsetti', 'tubi', 'senza_separatore'], true)) {
                $row_mode = 'classic';
            }
            $detected_mode = $row_mode;
            $first_row_processed = true;
        }
        if ($row_mode === 'morsetti' || $row_mode === 'tubi') {
            $current_max_variants = gcb_max_variant_index($data);
            if ($current_max_variants > $max_variants_needed) {
                $max_variants_needed = $current_max_variants;
            }
        }
        $data['_sort_cat'] = $cat;
        $data['_sort_fin_count'] = gcb_count_finishes($data);
        $data['_sort_id'] = (int) $record['id'];
        $data['_sort_created_at'] = (string) $record['created_at'];
        $rows_to_export_data[] = $data;
    }

    $sortKeyMap = [
        'id'            => '_sort_id',
        'parent_sku'    => 'Codice Articolo',
        'cat'           => '_sort_cat',
        'nome_articolo' => 'Nome Articolo',
        'fin_count'     => '_sort_fin_count',
        'created_at'    => '_sort_created_at',
    ];
    $sortKey = $sortKeyMap[$sort] ?? '_sort_id';
    $dirMul = ($dir === 'asc') ? 1 : -1;
    usort($rows_to_export_data, function ($a, $b) use ($sortKey, $dirMul) {
        $va = $a[$sortKey] ?? null;
        $vb = $b[$sortKey] ?? null;
        if ($sortKey === '_sort_id' || $sortKey === '_sort_fin_count') {
            return (($va <=> $vb)) * $dirMul;
        }
        return strnatcasecmp((string) $va, (string) $vb) * $dirMul;
    });

    if ($detected_mode === 'morsetti' || $detected_mode === 'tubi') {
        $final_cols = gcb_target_cols_variant_dynamic(empty($rows_to_export_data) ? 1 : $max_variants_needed);
    } else {
        $final_cols = gcb_target_cols();
    }

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="glasscom_records_' . date('Ymd_His') . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, $final_cols, ';');
    foreach ($rows_to_export_data as $data) {
        $line = [];
        foreach ($final_cols as $col) {
            $line[] = $data[$col] ?? '';
        }
        fputcsv($out, $line, ';');
    }
    fclose($out);
    exit;
}

/* =================== DB =================== */
function gcb_ensure_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'glasscom_catalog';
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $sql = "CREATE TABLE $table (
              id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              parent_sku VARCHAR(120) NOT NULL,
              record LONGTEXT NOT NULL,
              created_at DATETIME NOT NULL,
              PRIMARY KEY (id),
              KEY parent_sku (parent_sku)
            ) $charset;";
    dbDelta($sql);
}

function gcb_db_upsert_row($parent_sku, $row_assoc) {
    global $wpdb;
    $table = $wpdb->prefix . 'glasscom_catalog';
    $existing_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE parent_sku=%s LIMIT 1", strtoupper($parent_sku)));
    $payload = wp_json_encode($row_assoc, JSON_UNESCAPED_UNICODE);
    if ($existing_id) {
        return (bool) $wpdb->update(
            $table,
            ['record' => $payload, 'created_at' => current_time('mysql')],
            ['id' => (int) $existing_id],
            ['%s', '%s'],
            ['%d']
        );
    }
    return (bool) $wpdb->insert($table, [
        'parent_sku' => strtoupper($parent_sku),
        'record'     => $payload,
        'created_at' => current_time('mysql'),
    ], ['%s', '%s', '%s']);
}

/* =================== UTIL =================== */
function gcb_write_csv_semicolon($path, $headers, $rows) {
    $fh = fopen($path, 'w');
    if (!$fh) {
        return false;
    }
    fputcsv($fh, $headers, ';');
    foreach ($rows as $row) {
        $line = [];
        if (is_array($row)) {
            foreach ($headers as $header) {
                $line[] = $row[$header] ?? '';
            }
        } else {
            foreach ($headers as $header) {
                $line[] = '';
            }
        }
        fputcsv($fh, $line, ';');
    }
    fclose($fh);
    return true;
}

function gcb_norm($string) {
    $string = strtolower(trim((string) $string));
    if (function_exists('iconv')) {
        $tmp = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $string);
        if ($tmp !== false) {
            $string = $tmp;
        }
    }
    $string = preg_replace('/[^a-z0-9]+/', ' ', $string);
    return trim($string);
}

function gcb_read_csv_assoc($path, $sep = ';', $encoding = 'utf-8') {
    if (!file_exists($path) || !is_readable($path)) {
        throw new Exception('Impossibile leggere la sorgente cache.');
    }
    $stream = fopen($path, 'r');
    if ($stream === false) {
        throw new Exception('Impossibile aprire lo stream del file cache.');
    }
    $encoding_lower = strtolower($encoding);
    if ($encoding_lower !== 'utf-8' && $encoding_lower !== 'utf8') {
        if (function_exists('stream_filter_append')) {
            @stream_filter_append($stream, 'convert.iconv.' . $encoding . '/UTF-8//IGNORE');
        } else {
            fclose($stream);
            throw new Exception('Funzione stream_filter_append non disponibile.');
        }
    }
    $headers = fgetcsv($stream, 0, $sep);
    if ($headers === false) {
        fclose($stream);
        throw new Exception('CSV senza intestazioni o illeggibile.');
    }
    if (isset($headers[0])) {
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
    }
    $rows = [];
    while (($data = fgetcsv($stream, 0, $sep)) !== false) {
        $assoc = [];
        $data_count = count($data);
        foreach ($headers as $i => $key) {
            $clean_key = trim((string) $key);
            if ($clean_key === '') {
                continue;
            }
            $assoc[$clean_key] = ($i < $data_count) ? trim((string) $data[$i]) : '';
        }
        if (count(array_filter($assoc)) > 0) {
            $rows[] = $assoc;
        }
    }
    fclose($stream);
    return [$headers, $rows];
}

function gcb_fetch_and_cache_stan($cfg, $cache_file, &$err = null) {
    $err = '';
    $resp = wp_remote_get($cfg['source_url'], ['timeout' => 30, 'stream' => true, 'filename' => $cache_file]);
    if (is_wp_error($resp)) {
        $err = $resp->get_error_message();
        if ($resp->get_error_code() === 'http_request_failed' && strpos($err, 'cURL error 60') !== false) {
            $resp_no_ssl = wp_remote_get($cfg['source_url'], ['timeout' => 30, 'stream' => true, 'filename' => $cache_file, 'sslverify' => false]);
            if (!is_wp_error($resp_no_ssl)) {
                $resp = $resp_no_ssl;
                $err = '';
            } else {
                $err .= ' (Anche fallback SSL fallito: ' . $resp_no_ssl->get_error_message() . ')';
                return false;
            }
        } else {
            return false;
        }
    }
    $http_code = wp_remote_retrieve_response_code($resp);
    if ($http_code !== 200) {
        $err = 'Errore HTTP ' . $http_code;
        $body = wp_remote_retrieve_body($resp);
        if (!empty($body) && strlen($body) < 500) {
            $err .= ' - Risposta: ' . esc_html(substr($body, 0, 100));
        }
        if (file_exists($cache_file)) {
            @unlink($cache_file);
        }
        return false;
    }
    if (!file_exists($cache_file) || filesize($cache_file) === 0) {
        $err = 'Scrittura cache fallita o file vuoto.';
        if (file_exists($cache_file)) {
            @unlink($cache_file);
        }
        return false;
    }
    return true;
}

function gcb_guess_col($headers, $candidates, $fallback_index = 0) {
    $clean_headers = array_map('trim', $headers);
    $normHeaders = array_map('gcb_norm', $clean_headers);
    $headerMap = array_flip($normHeaders);
    foreach ($candidates as $candidate) {
        $norm = gcb_norm($candidate);
        if (isset($headerMap[$norm])) {
            return $clean_headers[$headerMap[$norm]];
        }
    }
    foreach ($candidates as $candidate) {
        $norm = gcb_norm($candidate);
        foreach ($normHeaders as $i => $header) {
            if ($header !== '' && $norm !== '' && strpos($header, $norm) !== false) {
                return $clean_headers[$i];
            }
        }
    }
    return $clean_headers[$fallback_index] ?? ($clean_headers[0] ?? '');
}

function gcb_extract_cat_sub($categoria, $sottotitolo = '') {
    $cat = strtoupper(trim((string) $categoria));
    $sub = '';
    if ($cat !== '') {
        $parts = preg_split('/\s*(?:>|\||\/)\s*/', $cat, 2);
        $cat = strtoupper(trim((string) ($parts[0] ?? '')));
        if (isset($parts[1])) {
            $sub = strtoupper(trim((string) $parts[1]));
        }
    }
    if ($sub === '' && !empty($sottotitolo)) {
        $sub = strtoupper(trim((string) $sottotitolo));
    }
    return [$cat, $sub];
}

function gcb_most_common_field($items, $key) {
    $count = [];
    foreach ($items as $item) {
        $val = trim((string) ($item[$key] ?? ''));
        if ($val === '') {
            continue;
        }
        $count[$val] = ($count[$val] ?? 0) + 1;
    }
    if (empty($count)) {
        return '';
    }
    arsort($count);
    return (string) key($count);
}

function gcb_row_is_not_found($data) {
    return !empty($data['_not_found']);
}

function gcb_row_has_zero_price($data) {
    if (!is_array($data)) {
        return false;
    }
    $max_check = gcb_max_variant_index($data);
    if ($max_check < 1) {
        $max_check = 5;
    }
    for ($i = 1; $i <= $max_check; $i++) {
        if (empty(trim((string) ($data['cod'.$i] ?? '')))) {
            continue;
        }
        $pre = trim((string) ($data['Prezzo_cod'.$i] ?? ''));
        if ($pre === '') {
            continue;
        }
        if (in_array($pre, ['0', '0.0', '0,0', '0.00', '0,00'], true)) {
            return true;
        }
        $num = str_replace(',', '.', $pre);
        if (is_numeric($num) && (float) $num == 0.0) {
            return true;
        }
    }
    return false;
}

function gcb_count_finishes($data) {
    if (!is_array($data)) {
        return 0;
    }
    $count = 0;
    $index = 1;
    while (isset($data['cod' . $index])) {
        if (trim((string) ($data['cod' . $index] ?? '')) !== '') {
            $count++;
        }
        $index++;
        if ($index > 100) {
            break;
        }
    }
    if ($count === 0 && ($data['_gcb_mode'] ?? 'classic') === 'classic') {
        for ($i = 1; $i <= 5; $i++) {
            if (!empty(trim((string) ($data['cod' . $i] ?? '')))) {
                $count++;
            }
        }
    }
    return $count;
}

function gcb_max_variant_index($row) {
    if (!is_array($row)) {
        return 0;
    }
    $max = 0;
    foreach ($row as $key => $value) {
        if (preg_match('/^cod(\d+)$/', $key, $match) && trim((string) $value) !== '') {
            $index = (int) $match[1];
            if ($index > $max) {
                $max = $index;
            }
        }
    }
    return $max;
}
