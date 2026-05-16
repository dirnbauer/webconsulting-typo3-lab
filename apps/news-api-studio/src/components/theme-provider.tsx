"use client"

import { ThemeProvider as NextThemesProvider, useTheme as useNextTheme } from "next-themes"
import { useEffect, type ComponentProps } from "react"

import { setNativeTheme } from "@/lib/electron-bridge"

export function ThemeProvider({ children, ...props }: ComponentProps<typeof NextThemesProvider>) {
  return (
    <NextThemesProvider attribute="class" defaultTheme="system" enableSystem disableTransitionOnChange {...props}>
      <NativeThemeBridge />
      {children}
    </NextThemesProvider>
  )
}

/** Mirrors `next-themes` state into Electron's nativeTheme so the title bar matches. */
function NativeThemeBridge() {
  const { theme } = useNextTheme()
  useEffect(() => {
    if (!theme) return
    const next = theme === "light" || theme === "dark" ? theme : "system"
    void setNativeTheme(next)
  }, [theme])
  return null
}

export { useNextTheme as useTheme }
