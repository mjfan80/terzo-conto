<?php
if (! defined('ABSPATH')) {
    exit;
}

/** @var array $context */
/** @var TerzoConto_Admin $admin */
$is_edit = (bool) ($context['is_edit'] ?? false);
$conto = (array) ($context['conto'] ?? []);
$conti = (array) ($context['conti'] ?? []);

$conto_id = absint($conto['id'] ?? 0);
$conto_nome = sanitize_text_field((string) ($conto['nome'] ?? ''));
$conto_descrizione = sanitize_text_field((string) ($conto['descrizione'] ?? ''));
$conto_tracciabile = absint($conto['tracciabile'] ?? 0);
$conto_attivo = absint($conto['attivo'] ?? 1);
?>
<div class="wrap">
    <h1><?php echo esc_html__('Conti', 'terzoconto-rendiconto-ets'); ?></h1>
    <style>
        .terzoconto-conti-form-row{display:flex;flex-wrap:wrap;gap:12px;max-width:980px;align-items:flex-end;margin-bottom:8px}
        .terzoconto-conti-form-row p{margin:0;flex:1 1 280px}
        .terzoconto-conti-form-row input[type="text"]{width:100%}
        .terzoconto-conti-check-row{display:flex;flex-wrap:wrap;gap:16px;margin:8px 0 0}
        .terzoconto-conti-help{max-width:980px;margin:8px 0 14px}
        .terzoconto-conti-status-badge{display:inline-block;padding:2px 10px;border-radius:999px;font-size:12px;font-weight:600;line-height:1.8;background:#e6f6eb;color:#176a32}
    </style>

    <?php $admin->render_conti_notice(); ?>
    <?php settings_errors('terzoconto_conti'); ?>

    <h2><?php echo esc_html($is_edit ? __('Modifica conto', 'terzoconto-rendiconto-ets') : __('Nuovo conto', 'terzoconto-rendiconto-ets')); ?></h2>
    <p class="terzoconto-conti-help"><?php echo esc_html__("Un conto rappresenta il metodo o lo strumento con cui viene gestito il denaro dell’associazione (es. contanti, conto corrente, PayPal, Satispay). Serve per tracciare entrate e uscite. I conti possono essere tracciabili (bonifico, PayPal, ecc.) o non tracciabili (contanti).", 'terzoconto-rendiconto-ets'); ?></p>

    <form method="post">
        <?php wp_nonce_field('terzoconto_action_nonce'); ?>
        <input type="hidden" name="terzoconto_action" value="<?php echo esc_attr($is_edit ? 'update_conto' : 'add_conto'); ?>" />
        <?php if ($is_edit) : ?>
            <input type="hidden" name="id" value="<?php echo esc_attr((string) $conto_id); ?>" />
        <?php endif; ?>

        <div class="terzoconto-conti-form-row">
            <p><input type="text" name="nome" required placeholder="<?php echo esc_attr__('Nome conto', 'terzoconto-rendiconto-ets'); ?>" value="<?php echo esc_attr($conto_nome); ?>" /></p>
            <p><input type="text" name="descrizione" placeholder="<?php echo esc_attr__('Descrizione', 'terzoconto-rendiconto-ets'); ?>" value="<?php echo esc_attr($conto_descrizione); ?>" /></p>
        </div>

        <div class="terzoconto-conti-check-row">
            <label title="<?php echo esc_attr__('Indica se il metodo di pagamento consente la tracciabilità fiscale (es. bonifico, carta, PayPal). Necessario per le erogazioni liberali detraibili.', 'terzoconto-rendiconto-ets'); ?>"><input type="checkbox" name="tracciabile" value="1" <?php checked($conto_tracciabile, 1); ?> /> <?php echo esc_html__('Tracciabile', 'terzoconto-rendiconto-ets'); ?></label>
            <label><input type="checkbox" name="attivo" value="1" <?php checked($conto_attivo, 1); ?> /> <?php echo esc_html__('Attivo', 'terzoconto-rendiconto-ets'); ?></label>
        </div>

        <?php submit_button($is_edit ? esc_html__('Aggiorna conto', 'terzoconto-rendiconto-ets') : esc_html__('Aggiungi conto', 'terzoconto-rendiconto-ets')); ?>
    </form>

    <hr />

    <h2><?php echo esc_html__('Elenco conti', 'terzoconto-rendiconto-ets'); ?></h2>
    <table class="widefat fixed striped">
        <thead><tr>
            <th><?php echo esc_html__('Nome', 'terzoconto-rendiconto-ets'); ?></th>
            <th><?php echo esc_html__('Descrizione', 'terzoconto-rendiconto-ets'); ?></th>
            <th><?php echo esc_html__('Stato', 'terzoconto-rendiconto-ets'); ?></th>
            <th><?php echo esc_html__('Tracciabile', 'terzoconto-rendiconto-ets'); ?></th>
            <th><?php echo esc_html__('Azioni', 'terzoconto-rendiconto-ets'); ?></th>
        </tr></thead>
        <tbody>
        <?php if ($conti === []) : ?>
            <tr><td colspan="5"><?php echo esc_html__('Nessun conto presente.', 'terzoconto-rendiconto-ets'); ?></td></tr>
        <?php endif; ?>
        <?php foreach ($conti as $row) :
            $row_id = absint($row['id'] ?? 0);
            $row_nome = sanitize_text_field((string) ($row['nome'] ?? ''));
            $row_descrizione = sanitize_text_field((string) ($row['descrizione'] ?? ''));
            $row_attivo = absint($row['attivo'] ?? 0);
            $row_tracciabile = absint($row['tracciabile'] ?? 0);
            $edit_url = add_query_arg(['page' => 'terzoconto-conti', 'edit_conto_id' => $row_id], admin_url('admin.php'));
            $is_attivo = 1 === $row_attivo;
            $tracciabile_label = $row_tracciabile ? __('Sì', 'terzoconto-rendiconto-ets') : __('No', 'terzoconto-rendiconto-ets');
            $cannot_delete = ! $admin->get_conti_repository()->can_delete($row_id);
        ?>
            <tr>
                <td><a href="<?php echo esc_url($edit_url); ?>"><?php echo esc_html($row_nome); ?></a></td>
                <td><?php echo esc_html($row_descrizione); ?></td>
                <td>
                    <?php
                    if ($is_attivo) {
                        echo '<span class="terzoconto-conti-status-badge">' . esc_html__('Attivo', 'terzoconto-rendiconto-ets') . '</span>';
                    } else {
                        echo esc_html__('—', 'terzoconto-rendiconto-ets');
                    }
                    ?>
                </td>
                <td><?php echo esc_html($tracciabile_label); ?></td>
                <td>
                    <a class="button button-secondary" href="<?php echo esc_url($edit_url); ?>"><?php echo esc_html__('Modifica', 'terzoconto-rendiconto-ets'); ?></a>
                    <form method="post" style="display:inline-block;margin-left:6px;">
                        <?php wp_nonce_field('terzoconto_action_nonce'); ?>
                        <input type="hidden" name="terzoconto_action" value="<?php echo esc_attr('delete_conto'); ?>" />
                        <input type="hidden" name="id" value="<?php echo esc_attr((string) $row_id); ?>" />
                        <?php if ($cannot_delete) : ?>
                            <button type="submit" class="button button-link-delete" disabled="disabled" title="<?php echo esc_attr__('Il conto è associato a movimenti e non può essere eliminato.', 'terzoconto-rendiconto-ets'); ?>"><?php echo esc_html__('Elimina', 'terzoconto-rendiconto-ets'); ?></button>
                        <?php else : ?>
                            <button type="submit" class="button button-link-delete" onclick="return confirm('<?php echo esc_js(__('Vuoi davvero eliminare questo conto?', 'terzoconto-rendiconto-ets')); ?>');"><?php echo esc_html__('Elimina', 'terzoconto-rendiconto-ets'); ?></button>
                        <?php endif; ?>
                    </form>
                    <?php if ($cannot_delete) : ?><br /><small><?php echo esc_html__('Non eliminabile: conto associato a movimenti.', 'terzoconto-rendiconto-ets'); ?></small><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
