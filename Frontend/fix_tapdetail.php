<?php
$file = __DIR__ . '/lobby_pvp.php';
$content = file_get_contents($file);

$old = '<span style="display:block;font-size:.46rem;letter-spacing:.14em;color:rgba(238,240,255,.28);margin-top:5px;font-family:\'Rajdhani\',sans-serif;font-weight:700">TAP DETAIL ▼</span>';
$new = '<span class="id-rank-tap">TAP DETAIL ▼</span>';

if (strpos($content, $old) !== false) {
    $content = str_replace($old, $new, $content);
    file_put_contents($file, $content);
    echo "SUCCESS: TAP DETAIL inline style replaced with class.\n";
} else {
    // Try alternate quote style
    $old2 = '<span style="display:block;font-size:.46rem;letter-spacing:.14em;color:rgba(238,240,255,.28);margin-top:5px;font-family:\'Rajdhani\',sans-serif;font-weight:700">TAP DETAIL ▼</span>';
    echo "Pattern not found. Searching for partial match...\n";
    if (strpos($content, 'TAP DETAIL') !== false) {
        echo "Found 'TAP DETAIL' in file.\n";
        // Show context
        $pos = strpos($content, 'TAP DETAIL');
        echo "Context: " . substr($content, max(0,$pos-200), 400) . "\n";
    } else {
        echo "ERROR: 'TAP DETAIL' not found in file.\n";
    }
}
