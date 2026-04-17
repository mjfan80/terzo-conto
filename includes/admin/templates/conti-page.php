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
    <h1><?php echo esc_html__('Conti', 'terzo-conto'); ?></h1>
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

    <h2><?php echo esc_html($is_edit ? __('Modifica conto', 'terzo-conto') : __('Nuovo conto', 'terzo-conto')); ?></h2>
    <p class="terzoconto-conti-help"><?php echo esc_html__("Un conto rappresenta il metodo o lo strumento con cui viene gestito il denaro dell’associazione (es. contanti, conto corrente, PayPal, Satispay). Serve per tracciare entrate e uscite. I conti possono essere tracciabili (bonifico, PayPal, ecc.) o non tracciabili (contanti).", 'terzo-conto'); ?></p>

    <form method="post">
        <?php wp_nonce_field('terzoconto_action_nonce'); ?>
        <input type="hidden" name="terzoconto_action" value="<?php echo esc_attr($is_edit ? 'update_conto' : 'add_conto'); ?>" />
        <?php if ($is_edit) : ?>
            <input type="hidden" name="id" value="<?php echo esc_attr((string) $conto_id); ?>" />
        <?php endif; ?>

        <div class="terzoconto-conti-form-row">
            <p><input type="text" name="nome" required placeholder="<?php echo esc_attr__('Nome conto', 'terzo-conto'); ?>" value="<?php echo esc_attr($conto_nome); ?>" /></p>
            <p><input type="text" name="descrizione" placeholder="<?php echo esc_attr__('Descrizione', 'terzo-conto'); ?>" value="<?php echo esc_attr($conto_descrizione); ?>" /></p>
        </div>

        <div class="terzoconto-conti-check-row">
            <label title="<?php echo esc_attr__('Indica se il metodo di pagamento consente la tracciabilità fiscale (es. bonifico, carta, PayPal). Necessario per le erogazioni liberali detraibili.', 'terzo-conto'); ?>"><input type="checkbox" name="tracciabile" value="1" <?php echo checked($conto_tracciabile, 1, false); ?> /> <?php echo esc_html__('Tracciabile', 'terzo-conto'); ?></label>
            <label><input type="checkbox" name="attivo" value="1" <?php echo checked($conto_attivo, 1, false); ?> /> <?php echo esc_html__('Attivo', 'terzo-conto'); ?></label>
        </div>

        <?php submit_button($is_edit ? esc_html__('Aggiorna conto', 'terzo-conto') : esc_html__('Aggiungi conto', 'terzo-conto')); ?>
    </form>

    <hr />

    <h2><?php echo esc_html__('Elenco conti', 'terzo-conto'); ?></h2>
    <table class="widefat fixed striped">
        <thead><tr>
            <th><?php echo esc_html__('Nome', 'terzo-conto'); ?></th>
            <th><?php echo esc_html__('Descrizione', 'terzo-conto'); ?></th>
            <th><?php echo esc_html__('Stato', 'terzo-conto'); ?></th>
            <th><?php echo esc_html__('Tracciabile', 'terzo-conto'); ?></th>
            <th><?php echo esc_html__('Azioni', 'terzo-conto'); ?></th>
        </tr></thead>
        <tbody>
        <?php if ($conti === []) : ?>
            <tr><td colspan="5"><?php echo esc_html__('Nessun conto presente.', 'terzo-conto'); ?></td></tr>
        <?php endif; ?>
        <?php foreach ($conti as $row) :
            $row_id = absint($row['id'] ?? 0);
            $row_nome = sanitize_text_field((string) ($row['nome'] ?? ''));
            $row_descrizione = sanitize_text_field((string) ($row['descrizione'] ?? ''));
            $row_attivo = absint($row['attivo'] ?? 0);
            $row_tracciabile = absint($row['tracciabile'] ?? 0);
            $edit_url = add_query_arg(['page' => 'terzoconto-conti', 'edit_conto_id' => $row_id], admin_url('admin.php'));
            $is_attivo = 1 === $row_attivo;
            $tracciabile_label = $row_tracciabile ? __('Sì', 'terzo-conto') : __('No', 'terzo-conto');
            $cannot_delete = ! $admin->get_conti_repository()->can_delete($row_id);
        ?>
            <tr>
                <td><a href="<?php echo esc_url($edit_url); ?>"><?php echo esc_html($row_nome); ?></a></td>
                <td><?php echo esc_html($row_descrizione); ?></td>
                <td>
                    <?php
                    if ($is_attivo) {
                        echo '<span class="terzoconto-conti-status-badge">' . esc_html__('Attivo', 'terzo-conto') . '</span>';
                    } else {
                        echo esc_html__('—', 'terzo-conto');
                    }
                    ?>
                </td>
                <td><?php echo esc_html($tracciabile_label); ?></td>
                <td>
                    <a class="button button-secondary" href="<?php echo esc_url($edit_url); ?>"><?php echo esc_html__('Modifica', 'terzo-conto'); ?></a>
                    <form method="post" style="display:inline-block;margin-left:6px;">
                        <?php wp_nonce_field('terzoconto_action_nonce'); ?>
                        <input type="hidden" name="terzoconto_action" value="<?php echo esc_attr('delete_conto'); ?>" />
                        <input type="hidden" name="id" value="<?php echo esc_attr((string) $row_id); ?>" />
                        <?php if ($cannot_delete) : ?>
                            <button type="submit" class="button button-link-delete" disabled="disabled" title="<?php echo esc_attr__('Il conto è associato a movimenti e non può essere eliminato.', 'terzo-conto'); ?>"><?php echo esc_html__('Elimina', 'terzo-conto'); ?></button>
                        <?php else : ?>
                            <button type="submit" class="button button-link-delete" onclick="return confirm('<?php echo esc_js(__('Vuoi davvero eliminare questo conto?', 'terzo-conto')); ?>');"><?php echo esc_html__('Elimina', 'terzo-conto'); ?></button>
                        <?php endif; ?>
                    </form>
                    <?php if ($cannot_delete) : ?><br /><small><?php echo esc_html__('Non eliminabile: conto associato a movimenti.', 'terzo-conto'); ?></small><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
