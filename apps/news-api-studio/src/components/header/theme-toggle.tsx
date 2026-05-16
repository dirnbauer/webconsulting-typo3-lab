"use client"

import { useEffect, useState } from "react"
import { Monitor, Moon, Sun } from "lucide-react"

import { Button } from "@/components/ui/button"
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip"
import { useTheme } from "@/components/theme-provider"

const order = ["system", "light", "dark"] as const

export function ThemeToggle() {
  const { theme, setTheme } = useTheme()
  const [mounted, setMounted] = useState(false)
  useEffect(() => setMounted(true), [])

  const current = mounted ? (theme as (typeof order)[number] | undefined) || "system" : "system"
  const next = order[(order.indexOf(current) + 1) % order.length]

  const Icon = current === "light" ? Sun : current === "dark" ? Moon : Monitor
  const label = current === "light" ? "Light" : current === "dark" ? "Dark" : "System"

  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <Button
          type="button"
          variant="ghost"
          size="icon-sm"
          aria-label={`Theme: ${label} — click to switch`}
          onClick={() => setTheme(next)}
        >
          <Icon className="size-4" />
        </Button>
      </TooltipTrigger>
      <TooltipContent>Theme: {label}</TooltipContent>
    </Tooltip>
  )
}
