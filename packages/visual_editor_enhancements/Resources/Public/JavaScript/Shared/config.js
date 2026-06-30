export function enhancementConfig() {
  return window.visualEditorEnhancements || {};
}

export function isElementLibraryEnabled() {
  return !!enhancementConfig().elementLibraryEnabled;
}

export function isEditableLinksEnabled() {
  const config = enhancementConfig();
  return !!(config.editableLinksEnabled ?? config.elementLibraryLinks);
}

export function elementLibraryColumns() {
  return enhancementConfig().elementLibraryColumns || 3;
}

export function contentAddedFeedback() {
  return enhancementConfig().contentAddedFeedback || null;
}
