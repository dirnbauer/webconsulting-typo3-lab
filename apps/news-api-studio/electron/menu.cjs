const { Menu, app, shell } = require("electron")

/**
 * Builds a standard application menu and dispatches custom actions
 * (save / new / settings / search) to the focused renderer via
 * `studio:menu-action`. The renderer wires those into the same handlers
 * its in-renderer keyboard shortcuts use.
 */
function buildMenu({ getFocusedWebContents }) {
  const isMac = process.platform === "darwin"

  function send(action) {
    const wc = getFocusedWebContents()
    if (wc) wc.send("studio:menu-action", action)
  }

  const template = [
    ...(isMac
      ? [
          {
            label: app.name,
            submenu: [
              { role: "about" },
              { type: "separator" },
              {
                label: "Settings…",
                accelerator: "Cmd+,",
                click: () => send("settings"),
              },
              { type: "separator" },
              { role: "services" },
              { type: "separator" },
              { role: "hide" },
              { role: "hideOthers" },
              { role: "unhide" },
              { type: "separator" },
              { role: "quit" },
            ],
          },
        ]
      : []),
    {
      label: "File",
      submenu: [
        {
          label: "New record",
          accelerator: isMac ? "Cmd+N" : "Ctrl+N",
          click: () => send("new"),
        },
        {
          label: "Save record",
          accelerator: isMac ? "Cmd+S" : "Ctrl+S",
          click: () => send("save"),
        },
        { type: "separator" },
        ...(isMac
          ? [{ role: "close" }]
          : [
              {
                label: "Settings…",
                accelerator: "Ctrl+,",
                click: () => send("settings"),
              },
              { type: "separator" },
              { role: "quit" },
            ]),
      ],
    },
    {
      label: "Edit",
      submenu: [
        { role: "undo" },
        { role: "redo" },
        { type: "separator" },
        { role: "cut" },
        { role: "copy" },
        { role: "paste" },
        { role: "selectAll" },
        { type: "separator" },
        {
          label: "Find records…",
          accelerator: isMac ? "Cmd+K" : "Ctrl+K",
          click: () => send("search"),
        },
      ],
    },
    {
      label: "View",
      submenu: [
        { role: "reload" },
        { role: "forceReload" },
        { role: "toggleDevTools" },
        { type: "separator" },
        { role: "resetZoom" },
        { role: "zoomIn" },
        { role: "zoomOut" },
        { type: "separator" },
        { role: "togglefullscreen" },
      ],
    },
    {
      label: "Window",
      submenu: [
        { role: "minimize" },
        { role: "zoom" },
        ...(isMac
          ? [{ type: "separator" }, { role: "front" }, { type: "separator" }, { role: "window" }]
          : [{ role: "close" }]),
      ],
    },
    {
      role: "help",
      submenu: [
        {
          label: "Open webconsulting.at",
          click: () => shell.openExternal("https://webconsulting.at"),
        },
      ],
    },
  ]

  return Menu.buildFromTemplate(template)
}

module.exports = { buildMenu }
