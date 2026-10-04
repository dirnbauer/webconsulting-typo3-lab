<?php
// Checks a published snapshot for credentials and personal data, inside the
// web container (publish-snapshot runs it after every publish). Prints counts,
// tables and masked samples only - never a full secret, address or IP.
//
//   php scan-snapshot.php <file with SNAPSHOT_SENSITIVE_PATTERN> <download dir>
//
// Exit 1 on a leak: rows in a table the pattern empties, a credential-shaped
// string (beyond the one documented demo administrator's hash), more than one
// account, or a key file in the files archive. E-mail domains and IPv4-like
// values are reported for review and do not fail the run.
$sensitive = trim((string)@file_get_contents($argv[1] ?? ''));
$dir = rtrim($argv[2] ?? '/var/www/html/public/fileadmin/_downloads', '/');
if ($sensitive === '' || !is_file("$dir/db-public.zip")) {
    fwrite(STDERR, "usage: php scan-snapshot.php <pattern file> <download dir>\n");
    exit(1);
}
$leaks = [];

$secretPatterns = [
    'openai_key'    => '/\bsk-(?:proj-)?[A-Za-z0-9_-]{20,}/',
    'anthropic_key' => '/\bsk-ant-[A-Za-z0-9_-]{20,}/',
    'slack_token'   => '/\bxox[abposr]-[A-Za-z0-9-]{10,}/',
    'github_token'  => '/\b(?:ghp|gho|ghu|ghs|ghr)_[A-Za-z0-9]{30,}|github_pat_[A-Za-z0-9_]{30,}/',
    'gitlab_token'  => '/\bglpat-[A-Za-z0-9_-]{20,}/',
    'aws_key'       => '/\bAKIA[0-9A-Z]{16}\b/',
    'private_key'   => '/-----BEGIN (?:RSA |EC |OPENSSH |DSA )?PRIVATE KEY-----/',
    'jwt'           => '/\beyJ[A-Za-z0-9_-]{10,}\.eyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}/',
    'bearer'        => '/Bearer\s+[A-Za-z0-9._-]{20,}/',
    'password_hash' => '/\$(?:argon2id?|2y|2a)\$[^\s\'"]{20,}/',
    'fal_key'       => '/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}:[0-9a-f]{32}\b/',
];
// Business, product and placeholder domains that may appear in published content.
$allowedDomains = '/(^|\.)(example\.(com|org|net)|webconsulting\.at|typo3\.(com|org)|test|localhost|invalid|vienna\.at)$/i';
$emailRe = '/\b[A-Za-z0-9._%+-]+@([A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,})\b/';
$ipRe = '/(?<![\d.])((?:25[0-5]|2[0-4]\d|1?\d?\d)\.(?:25[0-5]|2[0-4]\d|1?\d?\d)\.(?:25[0-5]|2[0-4]\d|1?\d?\d)\.(?:25[0-5]|2[0-4]\d|1?\d?\d))(?![\d.])/';

function maskEmail(string $e): string { [$l, $d] = explode('@', $e, 2) + [1 => '']; return substr($l, 0, 2) . '…@' . $d; }
function maskIp(string $ip): string { $p = explode('.', $ip); return $p[0] . '.' . $p[1] . '.x.x'; }
function redact(string $s): string { return (string)preg_replace('/([A-Za-z0-9_\-\.\/+]{6})[A-Za-z0-9_\-\.\/+]{6,}/', '$1…', $s); }

$zip = new ZipArchive();
$zip->open("$dir/db-public.zip");
$sql = (string)$zip->getFromName('db-public.sql');
$zip->close();
echo "== DATABASE (" . number_format(strlen($sql)) . " bytes)\n";

// Split into INSERT statements per table. mariadb-dump writes "VALUES" and
// every row on lines of their own; a newline inside data is escaped (\n), so
// ");" at the end of a real line ends the statement.
$byTable = [];
$offset = 0;
while (($start = strpos($sql, 'INSERT INTO `', $offset)) !== false) {
    $nameEnd = strpos($sql, '`', $start + 13);
    $table = substr($sql, $start + 13, $nameEnd - $start - 13);
    $end = strpos($sql, ");\n", $nameEnd);
    $end = $end === false ? strlen($sql) : $end + 2;
    $byTable[$table][] = substr($sql, $nameEnd + 1, $end - $nameEnd - 1);
    $offset = $end;
}
echo "  tables with rows: " . count($byTable) . "\n";
// be_users is emptied like every account table, then gets the one demo
// administrator back; the account check below covers it.
$leakingTables = array_values(array_filter(array_keys($byTable), fn ($t) => $t !== 'be_users' && preg_match('/' . $sensitive . '/i', $t) === 1));
echo "  sensitive tables with rows: " . ($leakingTables ? implode(', ', $leakingTables) : 'none') . "\n";
if ($leakingTables) { $leaks[] = 'rows in ' . implode(', ', $leakingTables); }
// Rows, not statements: mariadb-dump puts each row on a line of its own
// ("\n("); the demo administrator is one INSERT with "VALUES (" on one line.
$accounts = 0;
foreach ($byTable['be_users'] ?? [] as $statement) { $rows = substr_count($statement, "\n("); $accounts += $rows > 0 ? $rows : 1; }
echo "  be_users rows: $accounts (the demo administrator)\n";
if ($accounts > 1) { $leaks[] = "$accounts backend accounts"; }

foreach ($secretPatterns as $name => $re) {
    $hits = [];
    foreach ($byTable as $t => $values) {
        $n = preg_match_all($re, implode("\n", $values), $mm);
        if ($n) { $hits[$t] = [$n, $mm[0][0]]; }
    }
    if ($hits === []) { echo "  $name: 0\n"; continue; }
    echo "  $name: " . implode(', ', array_map(fn ($t) => "$t(" . $hits[$t][0] . ")", array_keys($hits))) . "\n";
    $expected = $name === 'password_hash' && array_keys($hits) === ['be_users'] && $hits['be_users'][0] === 1;
    if (!$expected) { $leaks[] = "$name in " . implode(', ', array_keys($hits)); }
    foreach (array_slice($hits, 0, 4, true) as $t => [$n, $sample]) { echo "      e.g. $t: " . redact(substr($sample, 0, 60)) . "\n"; }
}

// E-mail addresses by domain and table.
$domains = [];
foreach ($byTable as $t => $values) {
    if (!preg_match_all($emailRe, implode("\n", $values), $mm, PREG_SET_ORDER)) continue;
    foreach ($mm as $m) { $d = strtolower($m[1]); $domains[$d]['n'] = ($domains[$d]['n'] ?? 0) + 1; $domains[$d]['t'][$t] = true; $domains[$d]['s'] ??= maskEmail($m[0]); }
}
uasort($domains, fn ($a, $b) => $b['n'] <=> $a['n']);
echo "  e-mail domains: " . count($domains) . "\n";
foreach ($domains as $d => $info) {
    $flag = preg_match($allowedDomains, $d) ? '   ' : ' ! ';
    if ($flag === ' ! ' || $info['n'] > 50) {
        echo "   $flag$d: {$info['n']} in " . implode(', ', array_slice(array_keys($info['t']), 0, 5)) . (count($info['t']) > 5 ? ', …' : '') . " (e.g. {$info['s']})\n";
    }
}

// IPv4 addresses by table, ignoring version-like and documentation values.
$ips = [];
foreach ($byTable as $t => $values) {
    if (!preg_match_all($ipRe, implode("\n", $values), $mm)) continue;
    foreach ($mm[1] as $ip) {
        if (preg_match('/^(0\.|127\.|255\.|192\.0\.2\.|198\.51\.100\.|203\.0\.113\.)/', $ip)) continue;
        $ips[$t][$ip] = true;
    }
}
echo "  tables with IPv4-like values: " . count($ips) . "\n";
foreach ($ips as $t => $set) { echo "     $t: " . count($set) . " distinct (e.g. " . implode(', ', array_map('maskIp', array_slice(array_keys($set), 0, 3))) . ")\n"; }

echo "== FILES\n";
$zip = new ZipArchive();
$zip->open("$dir/fileadmin-public.zip");
$scanned = 0; $names = []; $fileHits = []; $fileDomains = [];
for ($i = 0; $i < $zip->numFiles; $i++) {
    $st = $zip->statIndex($i); $n = $st['name'];
    if (preg_match('/(^|\/)(\.env|\.htpasswd|id_rsa|id_ed25519)|\.(pem|key|p12|pfx|kdbx|sql|sqlite|db)$/i', $n)) { $leaks[] = "key or database file $n"; }
    if (preg_match('/secret|credential|token|password|lebenslauf|rechnung|invoice|steuer/i', $n)) { $names[] = $n; }
    if ($st['size'] > 0 && $st['size'] < 3000000 && preg_match('/\.(txt|md|json|ya?ml|xml|csv|html?|js|css|php|ts|ini|conf|log|env|svg|mdx|xlf|vcf|ics)$/i', $n)) {
        $c = (string)$zip->getFromIndex($i); $scanned++;
        foreach ($secretPatterns as $name => $re) {
            if ($name === 'password_hash') continue;
            if (preg_match($re, $c, $mm)) { $fileHits[] = "$name in $n: " . redact(substr($mm[0], 0, 60)); }
        }
        if (preg_match_all($emailRe, $c, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $m) { $d = strtolower($m[1]); if (!preg_match($allowedDomains, $d)) { $fileDomains[$d][$n] = maskEmail($m[0]); } }
        }
    }
}
$zip->close();
echo "  files: scanned $scanned text files\n";
echo "  suspicious names: " . (count($names) ? implode(', ', array_slice($names, 0, 10)) : 'none') . "\n";
echo "  secret hits: " . count($fileHits) . "\n";
foreach (array_slice($fileHits, 0, 20) as $h) echo "     $h\n";
if ($fileHits) { $leaks[] = count($fileHits) . ' credential-shaped strings in text files'; }
echo "  e-mail domains outside the allow list: " . count($fileDomains) . "\n";
foreach (array_slice($fileDomains, 0, 30, true) as $d => $files) { echo "     $d: " . count($files) . " file(s), e.g. " . array_key_first($files) . " (" . reset($files) . ")\n"; }

echo "== RESULT\n";
if ($leaks) {
    foreach ($leaks as $leak) { echo "  LEAK: $leak\n"; }
    exit(1);
}
echo "  clean (review the e-mail domains and IPv4-like values above)\n";
