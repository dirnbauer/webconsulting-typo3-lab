"use client"

import { Check, ChevronDown, Layers } from "lucide-react"

import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { Button } from "@/components/ui/button"
import type { WorkspaceItem } from "@/lib/types"

type Props = {
  workspaces: WorkspaceItem[]
  activeId: number | null
  onSelect: (id: number) => void
  disabled?: boolean
}

export function WorkspaceSwitcher({ workspaces, activeId, onSelect, disabled }: Props) {
  const active = workspaces.find((workspace) => workspace.uid === activeId) || workspaces[0]
  const label = active ? active.title : "Live"

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          type="button"
          variant="outline"
          size="sm"
          className="max-w-40 justify-between gap-2"
          disabled={disabled || workspaces.length === 0}
        >
          <span className="flex min-w-0 items-center gap-1.5">
            <Layers className="size-3.5 text-muted-foreground" />
            <span className="truncate">{label}</span>
          </span>
          <ChevronDown className="size-3.5 text-muted-foreground" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="min-w-52">
        <DropdownMenuLabel>Workspace</DropdownMenuLabel>
        {workspaces.length === 0 && (
          <div className="px-2 py-2 text-xs text-muted-foreground">No workspaces loaded.</div>
        )}
        {workspaces.map((workspace) => (
          <DropdownMenuItem key={workspace.uid} onSelect={() => onSelect(workspace.uid)}>
            <span className="flex flex-1 flex-col">
              <span className="text-sm">{workspace.title}</span>
              {workspace.access && (
                <span className="text-xs text-muted-foreground">{workspace.access}</span>
              )}
            </span>
            {workspace.uid === activeId && <Check className="size-4 text-primary" />}
          </DropdownMenuItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
