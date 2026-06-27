import {onMessage, sendMessage} from '@typo3/visual-editor/Shared/iframe-messaging';
import {elementLibraryOpen} from '@webconsulting/visual-editor-enhancements/Shared/local-stores';
import {isEditableLinksEnabled, isElementLibraryEnabled} from '@webconsulting/visual-editor-enhancements/Shared/config';
import '@webconsulting/visual-editor-enhancements/Frontend/components/ve-editable-link';

function initializeAccentBridge() {
  if (!isElementLibraryEnabled() && !isEditableLinksEnabled()) {
    return;
  }
  onMessage('veAccent', ({color}) => {
    if (color) {
      document.documentElement.style.setProperty('--ve-accent-color', color);
    }
  });
  sendMessage('requestAccent', null, 'parent');
}

async function initializeElementLibrary() {
  if (!isElementLibraryEnabled()) {
    return;
  }
  const libraryModule = await import('@webconsulting/visual-editor-enhancements/Frontend/components/ve-element-library');
  await import('@webconsulting/visual-editor-enhancements/Frontend/components/ve-element-library-button');
  document.body.appendChild(document.createElement('ve-element-library-button'));
  if (elementLibraryOpen.get()) {
    libraryModule.getElementLibrary().openPanel();
  }
  initializeContentElementActions(libraryModule);
}

function initializeContentElementActions(libraryModule) {
  const injectAll = () => document.querySelectorAll('ve-content-element').forEach((element) => injectAction(element, libraryModule));
  injectAll();
  new MutationObserver(injectAll).observe(document.documentElement, {childList: true, subtree: true});
  customElements.whenDefined('ve-content-element').then(async () => {
    const {VeContentElement} = await import('@typo3/visual-editor/Frontend/components/ve-content-element');
    const originalUpdated = VeContentElement.prototype.updated;
    if (originalUpdated?.visualEditorEnhancementsWrapped) {
      return;
    }
    const wrappedUpdated = function (changedProperties) {
      originalUpdated?.call(this, changedProperties);
      queueMicrotask(() => injectAction(this, libraryModule));
    };
    wrappedUpdated.visualEditorEnhancementsWrapped = true;
    VeContentElement.prototype.updated = wrappedUpdated;
    injectAll();
  });
}

function injectAction(contentElement, libraryModule) {
  if (!window.veInfo?.allowNewContent) {
    return;
  }
  const actionBar = contentElement.shadowRoot?.querySelector('.action-bar');
  if (!actionBar || actionBar.querySelector('[data-ve-enhancement="element-library"]')) {
    return;
  }
  const button = document.createElement('button');
  button.className = 'button';
  button.type = 'button';
  button.dataset.veEnhancement = 'element-library';
  const label = window.TYPO3?.lang?.['frontend.library.fromLibrary'] || 'Add from library';
  button.title = label;
  button.setAttribute('aria-label', label);
  button.innerHTML = '<ve-icon name="actions-menu-alternative"></ve-icon>';
  button.addEventListener('click', () => libraryModule.getElementLibrary().openPanel());
  actionBar.appendChild(button);
}

initializeAccentBridge();
initializeElementLibrary();
