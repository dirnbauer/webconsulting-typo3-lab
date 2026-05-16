"use client"

import { cn } from "@/lib/utils"
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover"
import { Separator } from "@/components/ui/separator"
import type { ConnectionState } from "@/lib/types"

type Props = {
  state: ConnectionState
  workspaceTitle?: string
}

export function ConnectionIndicator({ state, workspaceTitle }: Props) {
  const dotClass =
    state.status === "connected"
      ? "bg-emerald-500"
      : state.status === "connecting"
        ? "bg-amber-400 animate-pulse"
        : state.status === "error"
          ? "bg-destructive"
          : "bg-muted-foreground/40"

  const label =
    state.status === "connected"
      ? state.user
        ? `Connected as ${state.user.realName || state.user.username}`
        : "Connected"
      : state.status === "connecting"
        ? "Connecting…"
        : state.status === "error"
          ? "Connection error"
          : "Idle"

  return (
    <Popover>
      <PopoverTrigger asChild>
        <button
          type="button"
          aria-label={`Connection: ${label}`}
          className="inline-flex h-7 w-7 items-center justify-center rounded-md transition-colors hover:bg-muted"
        >
          <span className={cn("size-2 rounded-full", dotClass)} />
        </button>
      </PopoverTrigger>
      <PopoverContent align="end" className="w-72">
        <div className="space-y-2">
          <div className="flex items-center gap-2">
            <span className={cn("size-2 rounded-full", dotClass)} />
            <span className="text-sm font-medium">{label}</span>
          </div>
          {state.message && (
            <p className="break-words text-xs text-muted-foreground">{state.message}</p>
          )}
          {state.user && (
            <>
              <Separator />
              <dl className="grid grid-cols-[88px_1fr] gap-x-3 gap-y-1 text-xs">
                <dt className="text-muted-foreground">User</dt>
                <dd className="font-mono">{state.user.username}</dd>
                <dt className="text-muted-foreground">Email</dt>
                <dd className="break-all">{state.user.email || "—"}</dd>
                <dt className="text-muted-foreground">Workspace</dt>
                <dd>{workspaceTitle || "Live"}</dd>
                {state.schemaHash && (
                  <>
                    <dt className="text-muted-foreground">Schema</dt>
                    <dd className="font-mono text-[10px]">{state.schemaHash.slice(0, 16)}</dd>
                  </>
                )}
              </dl>
            </>
          )}
        </div>
      </PopoverContent>
    </Popover>
  )
}
