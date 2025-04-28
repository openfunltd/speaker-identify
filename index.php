<table border="1">
<?php foreach (glob(__DIR__ . "/ivod-data/diff/*.json") as $diff_file) {
$ivod_id = basename($diff_file, ".json");
if (filesize($diff_file) === 0) {
    continue;
}
if (filesize(__DIR__ . "/ivod-data/txt/{$ivod_id}.txt") === 0) {
    continue;
}
?>
<tr>
    <td><?= $ivod_id ?></td>
    <td><a href="parse-diff.php?ivod_id=<?= $ivod_id ?>">Parse</a></td>
</tr>
<?php } ?>
