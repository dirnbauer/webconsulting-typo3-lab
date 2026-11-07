(() => {
    'use strict';

    const url = new URL(window.location.href);
    if (url.origin !== 'https://typo3-lab.webconsulting.at'
        || window.labStatisticsInitialized
        || navigator.doNotTrack === '1'
        || navigator.globalPrivacyControl
        || window.self !== window.top) {
        return;
    }

    // Never include authentication, editor previews, searches or form submissions.
    if (/(?:^|\/)(?:typo3|login|logout|account|profile|workos|oauth|callback|reset-password|search|suche)(?:\/|$)/i.test(url.pathname)
        || [...url.searchParams.keys()].some((key) => /^(?:ADMCMD_|tx_|eID$|q$|query$|search$|token$|secret$|email$|code$|key$|password$|auth$|access_token$|preview$)/i.test(key))) {
        return;
    }

    let referrer = '';
    try {
        const previous = new URL(document.referrer);
        if (/^https?:$/.test(previous.protocol)) referrer = previous.origin;
    } catch {
        // An absent/invalid referrer is deliberately left empty.
    }

    const queue = window._paq = window._paq || [];
    queue.push(
        ['disableCookies'],
        ['setDoNotTrack', true],
        ['disableBrowserFeatureDetection'],
        ['setTrackerUrl', 'https://statistics.webconsulting.at/matomo.php'],
        ['setSiteId', '11'],
        ['setRequestMethod', 'POST'],
        ['setReferrerUrl', referrer],
        ['setCustomUrl', url.origin + url.pathname],
        ['setDocumentTitle', url.pathname],
        ['trackPageView'],
    );
    window.labStatisticsInitialized = true;

    const script = document.createElement('script');
    script.src = 'https://statistics.webconsulting.at/matomo.js';
    script.async = true;
    script.referrerPolicy = 'no-referrer';
    document.head.appendChild(script);
})();
