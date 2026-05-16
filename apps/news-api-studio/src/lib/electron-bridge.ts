/**
 * Renderer-side wrapper for the preload-bridged Electron APIs.
 *
 * The preload script exposes a small, sandboxed surface as `window.studioBridge`.
 * Outside of Electron the wrapper falls back to localStorage / no-op equivalents
 * so the UI can run in a browser unchanged.
 */

export type StudioBridge = {
  isElectron: true
  platform: NodeJS.Platform
  appVersion: string
  setNativeTheme: (theme: "system" | "light" | "dark") => Promise<void>
  encryptString: (plaintext: string) => Promise<string>
  decryptString: (ciphertext: string) => Promise<string>
  isEncryptionAvailable: () => Promise<boolean>
  openExternal: (url: string) => Promise<void>
  onMenuAction: (handler: (action: string) => void) => () => void
}

declare global {
  interface Window {
    studioBridge?: StudioBridge
  }
}

export function getBridge(): StudioBridge | null {
  if (typeof window === "undefined") return null
  return window.studioBridge && window.studioBridge.isElectron ? window.studioBridge : null
}

export function isElectron(): boolean {
  return getBridge() !== null
}

const ENCRYPTED_PREFIX = "enc:v1:"

export async function encryptToken(plaintext: string): Promise<string> {
  if (!plaintext) return ""
  const bridge = getBridge()
  if (!bridge) return plaintext
  try {
    const available = await bridge.isEncryptionAvailable()
    if (!available) return plaintext
    const ciphertext = await bridge.encryptString(plaintext)
    return `${ENCRYPTED_PREFIX}${ciphertext}`
  } catch {
    return plaintext
  }
}

export async function decryptToken(stored: string): Promise<string> {
  if (!stored) return ""
  if (!stored.startsWith(ENCRYPTED_PREFIX)) return stored
  const bridge = getBridge()
  if (!bridge) return ""
  try {
    return await bridge.decryptString(stored.slice(ENCRYPTED_PREFIX.length))
  } catch {
    return ""
  }
}

export async function setNativeTheme(theme: "system" | "light" | "dark"): Promise<void> {
  const bridge = getBridge()
  if (!bridge) return
  try {
    await bridge.setNativeTheme(theme)
  } catch {
    // ignore — UI theme still applies
  }
}

export async function openExternal(url: string): Promise<void> {
  const bridge = getBridge()
  if (bridge) {
    try {
      await bridge.openExternal(url)
      return
    } catch {
      // fall through to browser open
    }
  }
  if (typeof window !== "undefined") {
    window.open(url, "_blank", "noopener,noreferrer")
  }
}

export function onMenuAction(handler: (action: string) => void): () => void {
  const bridge = getBridge()
  if (!bridge) return () => undefined
  return bridge.onMenuAction(handler)
}

export function getPlatform(): NodeJS.Platform | "browser" {
  const bridge = getBridge()
  if (bridge) return bridge.platform
  return "browser"
}
