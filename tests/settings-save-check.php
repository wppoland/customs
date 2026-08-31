<?php
/**
 * Every checkbox on the settings form must survive a save.
 *
 * 1.0.12 shipped the subcategory checkbox without adding its key to the array
 * maybeSave() builds, so it could never be ticked: normalize() reads that array
 * and not $_POST, and a missing key reads as false and is written back over
 * whatever was stored. The unit test for the counter passed the whole time,
 * because it injected the flag straight into the repository and never went near
 * the form. This walks the form's own list instead.
 *
 * Run: php tests/settings-save-check.php
 *
 * @package plogins-customs
 */

$src = file_get_contents(__DIR__ . '/../src/Admin/Settings.php');

// The checkboxes and inputs the form actually renders.
preg_match_all('/name="([a-z_]+)"/', $src, $m);
$rendered = array_values(array_unique(array_filter(
    $m[1],
    static fn (string $n): bool => ! in_array($n, ['customs_settings_nonce'], true),
)));

// The keys maybeSave() hands to normalize().
$start = strpos($src, '$raw = [');
$saved = [];
if (false !== $start) {
    $block = substr($src, $start, strpos($src, '];', $start) - $start);
    preg_match_all("/'([a-z_]+)'\s*=>/", $block, $s);
    $saved = $s[1];
}

$missing = array_values(array_diff($rendered, $saved));

printf("form fields : %s\n", implode(', ', $rendered));
printf("saved keys  : %s\n", implode(', ', $saved));

if ([] !== $missing) {
    fwrite(STDERR, sprintf("FAIL these fields are rendered but never saved: %s\n", implode(', ', $missing)));
    exit(1);
}

echo "ok   every rendered field is in the save path\n";
