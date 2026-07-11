(() => {
  'use strict';

  const errorContainerSelector = '[data-powermail-field-error]';

  const configureErrorContainer = container => {
    container.setAttribute('aria-live', 'polite');
    container.setAttribute('aria-atomic', 'true');
  };

  const configureErrorContainersWithin = root => {
    if (root.matches?.(errorContainerSelector)) {
      configureErrorContainer(root);
    }

    root.querySelectorAll?.(errorContainerSelector).forEach(configureErrorContainer);
  };

  document.addEventListener('DOMContentLoaded', () => {
    configureErrorContainersWithin(document);

    const observer = new MutationObserver(records => {
      records.forEach(record => {
        record.addedNodes.forEach(node => {
          if (node instanceof Element) {
            configureErrorContainersWithin(node);
          }
        });
      });
    });

    observer.observe(document.body, { childList: true, subtree: true });
  });
})();
