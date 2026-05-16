"use client"

import { useCallback, useEffect, useRef, useState } from "react"

const STORAGE_KEY = "typo3-news-api-studio:records-column-width"
const MIN = 280
const MAX = 560

/**
 * Records column width persisted to localStorage.
 * Returns the current width and a `startResize` callback to wire
 * onPointerDown of the drag handle.
 */
export function useResizableColumn(defaultWidth = 380) {
  const [width, setWidth] = useState<number>(() => {
    if (typeof window === "undefined") return defaultWidth
    const stored = window.localStorage.getItem(STORAGE_KEY)
    const parsed = stored ? Number(stored) : NaN
    return Number.isFinite(parsed) && parsed >= MIN && parsed <= MAX ? parsed : defaultWidth
  })

  const widthRef = useRef(width)
  widthRef.current = width

  useEffect(() => {
    if (typeof window === "undefined") return
    window.localStorage.setItem(STORAGE_KEY, String(width))
  }, [width])

  const startResize = useCallback((event: React.PointerEvent) => {
    event.preventDefault()
    const startX = event.clientX
    const startWidth = widthRef.current
    const onMove = (move: PointerEvent) => {
      const next = Math.min(MAX, Math.max(MIN, startWidth + (move.clientX - startX)))
      setWidth(next)
    }
    const onUp = () => {
      window.removeEventListener("pointermove", onMove)
      window.removeEventListener("pointerup", onUp)
      document.body.style.userSelect = ""
      document.body.style.cursor = ""
    }
    document.body.style.userSelect = "none"
    document.body.style.cursor = "col-resize"
    window.addEventListener("pointermove", onMove)
    window.addEventListener("pointerup", onUp)
  }, [])

  return { width, startResize }
}
