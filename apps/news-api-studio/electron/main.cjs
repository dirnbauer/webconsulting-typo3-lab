const { app, BrowserWindow, Menu, ipcMain, nativeTheme, safeStorage, shell } = require("electron")
const fs = require("node:fs")
const path = require("node:path")

const { buildMenu } = require("./menu.cjs")

const isDev = !app.isPackaged

const stateFile = path.join(app.getPath("userData"), "window-state.json")
const defaultBounds = { width: 1280, height: 860 }

let mainWindow = null

// ---------- single instance lock ----------
const gotLock = app.requestSingleInstanceLock()
if (!gotLock) {
  app.quit()
} else {
  app.on("second-instance", () => {
    if (mainWindow) {
      if (mainWindow.isMinimized()) mainWindow.restore()
      mainWindow.focus()
    }
  })
}

// ---------- window state ----------
function loadWindowState() {
  try {
    const raw = fs.readFileSync(stateFile, "utf8")
    const parsed = JSON.parse(raw)
    if (typeof parsed?.width === "number" && typeof parsed?.height === "number") {
      return { ...defaultBounds, ...parsed }
    }
  } catch {
    // ignore — first launch or corrupt file
  }
  return defaultBounds
}

function persistWindowState(window) {
  if (!window || window.isDestroyed()) return
  const bounds = window.getBounds()
  try {
    fs.writeFileSync(stateFile, JSON.stringify(bounds))
  } catch {
    // best-effort persistence
  }
}

// ---------- IPC bridge ----------
ipcMain.handle("studio:set-native-theme", (_event, theme) => {
  if (theme === "system" || theme === "light" || theme === "dark") {
    nativeTheme.themeSource = theme
  }
})

ipcMain.handle("studio:encrypt-string", (_event, plaintext) => {
  if (!safeStorage.isEncryptionAvailable()) return null
  return safeStorage.encryptString(String(plaintext || "")).toString("base64")
})

ipcMain.handle("studio:decrypt-string", (_event, ciphertext) => {
  if (!safeStorage.isEncryptionAvailable()) return ""
  try {
    return safeStorage.decryptString(Buffer.from(String(ciphertext || ""), "base64"))
  } catch {
    return ""
  }
})

ipcMain.handle("studio:is-encryption-available", () => safeStorage.isEncryptionAvailable())

ipcMain.handle("studio:open-external", (_event, url) => {
  if (typeof url !== "string") return false
  if (!/^https?:\/\//i.test(url)) return false
  return shell.openExternal(url).then(() => true).catch(() => false)
})

// ---------- create window ----------
function createWindow() {
  // Expose version to the preload via env so the renderer can show it.
  process.env.STUDIO_APP_VERSION = app.getVersion()

  const state = loadWindowState()

  mainWindow = new BrowserWindow({
    width: state.width,
    height: state.height,
    x: state.x,
    y: state.y,
    minWidth: 980,
    minHeight: 700,
    title: "TYPO3 News API Studio",
    backgroundColor: nativeTheme.shouldUseDarkColors ? "#0a0a0a" : "#fafafa",
    titleBarStyle: process.platform === "darwin" ? "hiddenInset" : "default",
    trafficLightPosition: process.platform === "darwin" ? { x: 18, y: 18 } : undefined,
    webPreferences: {
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: true,
      // sg_apicore is hosted on a different origin than the packaged Electron app.
      // We disable webSecurity for the Electron renderer only; the long-term fix is
      // serving the renderer through a custom `app://` protocol with TYPO3 CORS.
      webSecurity: false,
      preload: path.join(__dirname, "preload.cjs"),
    },
  })

  mainWindow.webContents.setWindowOpenHandler(({ url }) => {
    if (/^https?:\/\//i.test(url)) shell.openExternal(url)
    return { action: "deny" }
  })

  mainWindow.webContents.on("will-navigate", (event, navigationUrl) => {
    const current = new URL(mainWindow.webContents.getURL())
    const next = new URL(navigationUrl)
    if (current.origin !== next.origin) {
      event.preventDefault()
      shell.openExternal(navigationUrl)
    }
  })

  mainWindow.on("close", () => persistWindowState(mainWindow))
  mainWindow.on("resized", () => persistWindowState(mainWindow))
  mainWindow.on("moved", () => persistWindowState(mainWindow))

  if (isDev) {
    mainWindow.loadURL("http://localhost:3000")
  } else {
    mainWindow.loadFile(path.join(__dirname, "../out/index.html"))
  }

  Menu.setApplicationMenu(
    buildMenu({
      getFocusedWebContents: () => (mainWindow && !mainWindow.isDestroyed() ? mainWindow.webContents : null),
    }),
  )
}

app.whenReady().then(() => {
  createWindow()
  app.on("activate", () => {
    if (BrowserWindow.getAllWindows().length === 0) createWindow()
  })
})

app.on("window-all-closed", () => {
  if (process.platform !== "darwin") app.quit()
})
