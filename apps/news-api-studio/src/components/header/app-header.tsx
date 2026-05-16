"use client"

import { useEffect, useState } from "react"
import { Settings } from "lucide-react"

import { Button } from "@/components/ui/button"
import { Separator } from "@/components/ui/separator"
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip"
import { ThemeToggle } from "@/components/header/theme-toggle"
import { ProfileSwitcher } from "@/components/header/profile-switcher"
import { WorkspaceSwitcher } from "@/components/header/workspace-switcher"
import { ConnectionIndicator } from "@/components/header/connection-indicator"
import type { ConnectionState, Profile, WorkspaceItem } from "@/lib/types"
import { getPlatform } from "@/lib/electron-bridge"

type Props = {
  profiles: Profile[]
  activeProfileId: string | null
  workspaces: WorkspaceItem[]
  activeWorkspaceId: number | null
  connection: ConnectionState
  onSelectProfile: (id: string) => void
  onSelectWorkspace: (id: number) => void
  onOpenSettings: () => void
}

const brandBasePath = process.env.NODE_ENV === "production" ? "./brand" : "/brand"
const logoLightSrc = `${brandBasePath}/webconsulting-logo-light.svg`
const logoDarkSrc = `${brandBasePath}/webconsulting-logo-dark.svg`

export function AppHeader({
  profiles,
  activeProfileId,
  workspaces,
  activeWorkspaceId,
  connection,
  onSelectProfile,
  onSelectWorkspace,
  onOpenSettings,
}: Props) {
  const [isMac, setIsMac] = useState(false)
  useEffect(() => setIsMac(getPlatform() === "darwin"), [])

  const activeWorkspace = workspaces.find((workspace) => workspace.uid === activeWorkspaceId)

  return (
    <header
      data-electron-drag={isMac ? "true" : undefined}
      className="sticky top-0 z-40 flex h-14 items-center gap-3 border-b bg-background px-4"
      style={isMac ? { paddingLeft: 86, WebkitAppRegion: "drag" } as React.CSSProperties & { WebkitAppRegion?: string } : undefined}
    >
      <div className="flex min-w-0 items-center gap-3">
        <div
          className="flex h-7 items-center px-1"
          aria-label="webconsulting"
          style={{ WebkitAppRegion: "no-drag" } as React.CSSProperties}
        >
          <img src={logoLightSrc} alt="webconsulting" className="h-4 w-auto object-contain dark:hidden" />
          <img src={logoDarkSrc} alt="webconsulting" className="hidden h-4 w-auto object-contain dark:block" />
        </div>
        <Separator orientation="vertical" className="hidden h-5 sm:block" />
        <h1 className="hidden truncate text-sm font-semibold sm:block">TYPO3 News API Studio</h1>
      </div>

      <div
        className="flex flex-1 items-center justify-end gap-1.5"
        style={{ WebkitAppRegion: "no-drag" } as React.CSSProperties}
      >
        <ProfileSwitcher
          profiles={profiles}
          activeId={activeProfileId}
          onSelect={onSelectProfile}
          onManage={onOpenSettings}
        />
        <WorkspaceSwitcher
          workspaces={workspaces}
          activeId={activeWorkspaceId}
          onSelect={onSelectWorkspace}
          disabled={connection.status !== "connected"}
        />
        <ConnectionIndicator state={connection} workspaceTitle={activeWorkspace?.title} />
        <ThemeToggle />
        <Tooltip>
          <TooltipTrigger asChild>
            <Button type="button" variant="ghost" size="icon-sm" onClick={onOpenSettings} aria-label="Open settings">
              <Settings className="size-4" />
            </Button>
          </TooltipTrigger>
          <TooltipContent>Settings (⌘,)</TooltipContent>
        </Tooltip>
      </div>
    </header>
  )
}
