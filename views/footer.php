<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<?php $appJsPath = __DIR__ . '/../public/assets/app.js'; ?>
<script src="<?= url('assets/app.js?v=' . (is_file($appJsPath) ? filemtime($appJsPath) : time())) ?>"></script>
</body>
</html>
