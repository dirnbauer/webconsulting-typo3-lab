"use client"

import { Check, ChevronDown, Plug } from "lucide-react"

import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { Button } from "@/components/ui/button"
import type { Profile } from "@/lib/types"
import { profileSummary } from "@/lib/profiles"

type Props = {
  profiles: Profile[]
  activeId: string | null
  onSelect: (id: string) => void
  onManage: () => void
}

export function ProfileSwitcher({ profiles, activeId, onSelect, onManage }: Props) {
  const active = profiles.find((profile) => profile.id === activeId)

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button type="button" variant="outline" size="sm" className="max-w-44 justify-between gap-2">
          <span className="flex min-w-0 items-center gap-1.5">
            <Plug className="size-3.5 text-muted-foreground" />
            <span className="truncate">{active ? active.name : "No profile"}</span>
          </span>
          <ChevronDown className="size-3.5 text-muted-foreground" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="min-w-56">
        <DropdownMenuLabel>Connection profile</DropdownMenuLabel>
        {profiles.length === 0 && (
          <div className="px-2 py-2 text-xs text-muted-foreground">No profiles configured.</div>
        )}
        {profiles.map((profile) => (
          <DropdownMenuItem key={profile.id} onSelect={() => onSelect(profile.id)}>
            <div className="flex min-w-0 flex-1 flex-col">
              <span className="truncate text-sm">{profile.name}</span>
              <span className="truncate text-xs text-muted-foreground">{profileSummary(profile)}</span>
            </div>
            {profile.id === activeId && <Check className="ml-2 size-4 text-primary" />}
          </DropdownMenuItem>
        ))}
        <DropdownMenuSeparator />
        <DropdownMenuItem onSelect={onManage}>Manage profiles…</DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
