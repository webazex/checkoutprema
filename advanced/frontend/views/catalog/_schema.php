<?php

/** @var string|null $schemaJson */
/** @var string|null $breadcrumbSchemaJson */
?>

<?php if (!empty($breadcrumbSchemaJson)): ?>
    <script type="application/ld+json"><?= $breadcrumbSchemaJson ?></script>
<?php endif; ?>

<?php if (!empty($schemaJson)): ?>
    <script type="application/ld+json"><?= $schemaJson ?></script>
<?php endif; ?>