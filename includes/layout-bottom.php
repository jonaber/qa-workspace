<?php

$pageScripts = $pageScripts ?? [];
$pageExternalScripts = $pageExternalScripts ?? [];
?>
        </main>
    </div>

    <?php foreach ($pageExternalScripts as $externalScript): ?>
        <script src="<?php echo htmlspecialchars((string)$externalScript, ENT_QUOTES, 'UTF-8'); ?>"></script>
    <?php endforeach; ?>

    <?php foreach ($pageScripts as $script): ?>
        <?php
            $scriptPath = __DIR__ . '/../' . ltrim((string)$script, '/\\');
            $version = file_exists($scriptPath) ? (string)filemtime($scriptPath) : (string)time();
            $scriptUrl = $script . '?v=' . rawurlencode($version);
        ?>
        <script src="<?php echo htmlspecialchars($scriptUrl, ENT_QUOTES, 'UTF-8'); ?>" defer></script>
    <?php endforeach; ?>
</body>
</html>
