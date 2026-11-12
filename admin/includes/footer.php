<?php
$adminBase = $adminBase ?? bh_admin_base();
$flash = $flash ?? bh_take_flash();
?>
<?php bh_render_flash_script($flash); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php
$adminJsPath = __DIR__ . '/../assets/admin.js';
$adminJsVersion = is_file($adminJsPath) ? (string) filemtime($adminJsPath) : (string) time();
?>
<script src="<?= bh_h($adminBase) ?>/assets/admin.js?v=<?= rawurlencode($adminJsVersion) ?>"></script>
<?php
if (!empty($pageScript)):
    $pageScriptPath = __DIR__ . '/../assets/' . basename((string) $pageScript);
    $pageScriptVersion = is_file($pageScriptPath) ? (string) filemtime($pageScriptPath) : (string) time();
?>
<script src="<?= bh_h($adminBase) ?>/assets/<?= bh_h($pageScript) ?>?v=<?= rawurlencode($pageScriptVersion) ?>"></script>
<?php endif; ?>
</body>
</html>
