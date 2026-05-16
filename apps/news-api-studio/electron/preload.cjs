/**
 * Sandbox-safe surface exposed to the renderer as `window.studioBridge`.
 *
 * The bridge intentionally exposes only the narrow set of operations the
 * renderer needs (theme sync, OS keychain, external link, version). Anything
 * else stays in the main process. All renderer/main IPC channels are
 * namespaced under `studio:*` so they don't collide with Electron defaults.
 */
const { contextBridge, ipcRenderer } = require("electron")

const channels = {
  setNativeTheme: "studio:set-native-theme",
  encryptString: "studio:encrypt-string",
  decryptString: "studio:decrypt-string",
  isEncryptionAvailable: "studio:is-encryption-available",
  openExternal: "studio:open-external",
  menuAction: "studio:menu-action",
}

contextBridge.exposeInMainWorld("studioBridge", {
  isElectron: true,
  platform: process.platform,
  appVersion: process.env.STUDIO_APP_VERSION || "dev",

  setNativeTheme(theme) {
    return ipcRenderer.invoke(channels.setNativeTheme, theme)
  },

  encryptString(plaintext) {
    return ipcRenderer.invoke(channels.encryptString, plaintext)
  },

  decryptString(ciphertext) {
    return ipcRenderer.invoke(channels.decryptString, ciphertext)
  },

  isEncryptionAvailable() {
    return ipcRenderer.invoke(channels.isEncryptionAvailable)
  },

  openExternal(url) {
    return ipcRenderer.invoke(channels.openExternal, url)
  },

  /**
   * Subscribe to native menu actions dispatched from the main process.
   * Returns an unsubscribe function.
   */
  onMenuAction(handler) {
    const listener = (_event, action) => handler(action)
    ipcRenderer.on(channels.menuAction, listener)
    return () => ipcRenderer.removeListener(channels.menuAction, listener)
  },
})
