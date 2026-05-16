"use client"

import { useEffect } from "react"

type ShortcutHandlers = {
  onSave?: () => void
  onNew?: () => void
  onSettings?: () => void
  onSearch?: () => void
  onEscape?: () => void
}

/**
 * Wires the global save / new / settings / search / escape shortcuts.
 * macOS uses Cmd, Windows/Linux uses Ctrl. The Electron native menu
 * dispatches the same actions via the studio bridge in main.cjs, so
 * shortcuts work even when the renderer doesn't have keyboard focus.
 */
export function useKeyboardShortcuts(handlers: ShortcutHandlers) {
  useEffect(() => {
    function onKeyDown(event: KeyboardEvent) {
      const meta = event.metaKey || event.ctrlKey
      const target = event.target as HTMLElement | null
      const editingShortcut = target && (target.tagName === "INPUT" || target.tagName === "TEXTAREA" || target.isContentEditable)

      if (event.key === "Escape" && handlers.onEscape) {
        handlers.onEscape()
        return
      }

      if (!meta) return

      if (event.key === "s" || event.key === "S") {
        event.preventDefault()
        handlers.onSave?.()
        return
      }

      if (event.key === "n" || event.key === "N") {
        if (editingShortcut) return
        event.preventDefault()
        handlers.onNew?.()
        return
      }

      if (event.key === ",") {
        event.preventDefault()
        handlers.onSettings?.()
        return
      }

      if (event.key === "k" || event.key === "K") {
        if (editingShortcut) return
        event.preventDefault()
        handlers.onSearch?.()
        return
      }
    }

    window.addEventListener("keydown", onKeyDown)
    return () => window.removeEventListener("keydown", onKeyDown)
  }, [handlers])
}
