<?php

declare(strict_types=1);

/**
 * Live regression for server rejection feedback with the client summary disabled.
 * Run inside the lab web container, where Basic Auth credentials are environment
 * variables. This submits hidden form state only: required fields and CAPTCHA
 * remain empty, so the request must be rejected without saving or sending mail.
 *
 * Usage: php check-powermail-server-errors.php CONTACT_URL EXPECTED_CAPTCHA_ERROR
 */
$url = $argv[1] ?? '';
$expected = $argv[2] ?? '';
$base = 'https://typo3-lab.webconsulting.at';
if (!str_starts_with($url, $base . '/') || $expected === '') {
    fwrite(STDERR, "Provide a lab contact-form URL and its expected localized CAPTCHA error.\n");
    exit(2);
}

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 40,
    CURLOPT_COOKIEFILE => '',
    CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
    CURLOPT_USERPWD => getenv('BASIC_AUTH_USER') . ':' . getenv('BASIC_AUTH_PASSWORD'),
]);
$request = static function (string $requestUrl, ?array $data = null) use ($curl): array {
    curl_setopt($curl, CURLOPT_URL, $requestUrl);
    if ($data !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    $html = curl_exec($curl);
    if (!is_string($html)) {
        throw new RuntimeException('HTTP request failed.');
    }
    $document = new DOMDocument();
    @$document->loadHTML('<?xml encoding="UTF-8">' . $html);
    return [new DOMXPath($document), curl_getinfo($curl, CURLINFO_HTTP_CODE)];
};

try {
    [$xpath, $status] = $request($url);
    $form = $xpath->query('//form[contains(@class,"powermail_form")]')->item(0);
    if ($status !== 200 || !$form instanceof DOMElement) {
        throw new RuntimeException('The initial form did not render.');
    }
    if ($form->getAttribute('data-powermail-error-summary-mode') !== 'off'
        || $xpath->query('.//*[contains(@class,"frc-captcha")]', $form)->length !== 1
        || $xpath->query('.//*[@id="powermail_field_email"]', $form)->length !== 1) {
        throw new RuntimeException('Expected a CAPTCHA-protected contact form with the client summary disabled.');
    }
    if ($xpath->query('//*[@data-powermail-server-error-summary]')->length !== 0) {
        throw new RuntimeException('An untouched form already shows server errors.');
    }
    $action = $form->getAttribute('action');
    if (str_starts_with($action, '/') && !str_starts_with($action, '//')) {
        $action = $base . $action;
    }
    if (!str_starts_with($action, $base . '/')) {
        throw new RuntimeException('The form action is outside the lab.');
    }
    $data = [];
    foreach ($xpath->query('.//input[@type="hidden"][@name]', $form) as $input) {
        $data[$input->getAttribute('name')] = $input->getAttribute('value');
    }
    // Intentionally leave required fields and the real CAPTCHA response empty.
    $data['frc-captcha-response'] = '';
    [$xpath, $status] = $request($action, $data);
    $summary = $xpath->query('//*[@data-powermail-server-error-summary]')->item(0);
    $text = $summary ? preg_replace('/\s+/u', ' ', trim($summary->textContent)) : '';
    $passed = $status === 200 && $summary instanceof DOMElement
        && $summary->getAttribute('role') === 'alert'
        && !$summary->hasAttribute('hidden')
        && str_contains($text, $expected);
    echo json_encode([
        'url' => $url,
        'status' => $status,
        'server_summary_present' => $summary !== null,
        'expected_captcha_error_present' => str_contains($text, $expected),
        'summary_text' => $text,
        'passed' => $passed,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    exit($passed ? 0 : 1);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
