"use client"

import { useEffect, useState } from "react"

/**
 * Standard matchMedia hook. Returns false during SSR / initial render
 * to avoid hydration mismatches; flips to the real value on mount.
 */
export function useMediaQuery(query: string): boolean {
  const [matches, setMatches] = useState(false)

  useEffect(() => {
    if (typeof window === "undefined" || !window.matchMedia) return
    const list = window.matchMedia(query)
    setMatches(list.matches)
    const handler = (event: MediaQueryListEvent) => setMatches(event.matches)
    list.addEventListener("change", handler)
    return () => list.removeEventListener("change", handler)
  }, [query])

  return matches
}
