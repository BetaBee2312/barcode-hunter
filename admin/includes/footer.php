<?php
$adminBase = $adminBase ?? bh_admin_base();
$flash = $flash ?? bh_take_flash();
?>
<?php bh_render_flash_script($flash); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= bh_h($adminBase) ?>/assets/admin.js"></script>
<?php if (!empty($pageScript)): ?><script src="<?= bh_h($adminBase) ?>/assets/<?= bh_h($pageScript) ?>"></script><?php endif; ?>
</body>
</html>
