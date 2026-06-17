/**
 * Progressive enhancement for the Flue run form: trigger a flow via the AJAX
 * endpoint and stream its durable events (SSE) into the live log. Falls back to
 * the plain form POST (synchronous runAction) when these hooks are absent.
 */
const form = document.getElementById('flue-run-form');

if (form instanceof HTMLFormElement) {
  const triggerEndpoint = form.dataset.triggerEndpoint;
  const streamEndpoint = form.dataset.streamEndpoint;
  const live = document.getElementById('flue-live');
  const log = document.getElementById('flue-live-log');

  if (triggerEndpoint && streamEndpoint && live && log) {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      log.textContent = '';
      live.hidden = false;

      const body = new URLSearchParams(new FormData(form));
      let runUid = 0;
      try {
        const res = await fetch(triggerEndpoint, {
          method: 'POST',
          body,
          headers: { Accept: 'application/json' },
        });
        const json = await res.json();
        if (json.error) {
          log.textContent = 'Error: ' + json.error;
          return;
        }
        runUid = Number(json.runUid) || 0;
        log.textContent = 'Run #' + runUid + ' (' + (json.status || '') + ')\n';
      } catch (err) {
        log.textContent = 'Trigger failed: ' + err;
        return;
      }
      if (!runUid) {
        return;
      }

      const sep = streamEndpoint.includes('?') ? '&' : '?';
      const source = new EventSource(streamEndpoint + sep + 'runUid=' + runUid);
      const append = (text) => {
        log.textContent += text;
        log.scrollTop = log.scrollHeight;
      };
      source.addEventListener('event', (ev) => {
        try {
          const data = JSON.parse(ev.data);
          append('• ' + (data.type || 'event') + '\n');
        } catch {
          append(ev.data + '\n');
        }
      });
      source.addEventListener('done', () => {
        source.close();
        append('\nDone — reload to see the stored report.\n');
      });
      source.addEventListener('error', () => {
        source.close();
        append('\n[stream closed]\n');
      });
    });
  }
}
