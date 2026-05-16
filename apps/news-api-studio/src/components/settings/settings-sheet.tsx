"use client"

import { useEffect, useState } from "react"
import { Pencil, Plus, Trash2 } from "lucide-react"

import { Sheet, SheetBody, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from "@/components/ui/sheet"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { Card, CardContent } from "@/components/ui/card"
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog"
import { ProfileForm } from "@/components/settings/profile-form"
import type { Profile } from "@/lib/types"
import { profileSummary } from "@/lib/profiles"
import { cn } from "@/lib/utils"

type Mode = { kind: "list" } | { kind: "edit"; profile: Profile | null } | { kind: "create" }

type Props = {
  open: boolean
  onOpenChange: (open: boolean) => void
  profiles: Profile[]
  activeProfileId: string | null
  onSetActive: (id: string) => void
  onSave: (profile: Omit<Profile, "id"> & { id?: string }) => Promise<void>
  onDelete: (id: string) => Promise<void>
  appVersion: string | null
}

export function SettingsSheet({
  open,
  onOpenChange,
  profiles,
  activeProfileId,
  onSetActive,
  onSave,
  onDelete,
  appVersion,
}: Props) {
  const [mode, setMode] = useState<Mode>({ kind: "list" })
  const [pendingDelete, setPendingDelete] = useState<Profile | null>(null)

  useEffect(() => {
    if (!open) setMode({ kind: "list" })
  }, [open])

  return (
    <>
      <Sheet open={open} onOpenChange={onOpenChange}>
        <SheetContent side="right">
          <SheetHeader>
            <SheetTitle>
              {mode.kind === "list" ? "Settings" : mode.kind === "create" ? "New profile" : `Edit “${mode.profile?.name || "profile"}”`}
            </SheetTitle>
            <SheetDescription>
              {mode.kind === "list"
                ? "Manage credentials and connection profiles. Tokens are encrypted with your OS keychain inside the Electron app."
                : "Connection details for one TYPO3 instance."}
            </SheetDescription>
          </SheetHeader>

          <SheetBody>
            {mode.kind === "list" && (
              <div className="space-y-5">
                <section>
                  <div className="mb-3 flex items-center justify-between">
                    <h3 className="text-sm font-semibold">Credentials</h3>
                    <Button size="sm" variant="outline" onClick={() => setMode({ kind: "create" })}>
                      <Plus className="size-3.5" />
                      New profile
                    </Button>
                  </div>

                  {profiles.length === 0 ? (
                    <Card className="border-dashed">
                      <CardContent className="py-8 text-center text-sm text-muted-foreground">
                        No profiles yet. Add one to connect to a TYPO3 instance.
                      </CardContent>
                    </Card>
                  ) : (
                    <ul className="space-y-2">
                      {profiles.map((profile) => {
                        const active = profile.id === activeProfileId
                        return (
                          <li key={profile.id}>
                            <Card className={cn("py-3", active && "border-primary/40")} size="sm">
                              <CardContent className="flex items-start gap-2 px-3">
                                <button
                                  type="button"
                                  onClick={() => onSetActive(profile.id)}
                                  className="flex min-w-0 flex-1 flex-col text-left"
                                >
                                  <div className="flex items-center gap-2">
                                    <span className="truncate text-sm font-medium">{profile.name}</span>
                                    {active && (
                                      <Badge variant="outline" className="border-primary/40 text-primary">
                                        Active
                                      </Badge>
                                    )}
                                  </div>
                                  <span className="mt-0.5 truncate text-xs text-muted-foreground">
                                    {profileSummary(profile)} · {profile.apiId}
                                    {profile.tenant ? ` · ${profile.tenant}` : ""}
                                  </span>
                                </button>
                                <div className="flex items-center gap-0.5">
                                  <Button size="icon-sm" variant="ghost" onClick={() => setMode({ kind: "edit", profile })} aria-label={`Edit ${profile.name}`}>
                                    <Pencil className="size-3.5" />
                                  </Button>
                                  <Button
                                    size="icon-sm"
                                    variant="ghost"
                                    onClick={() => setPendingDelete(profile)}
                                    aria-label={`Delete ${profile.name}`}
                                    className="text-muted-foreground hover:text-destructive"
                                  >
                                    <Trash2 className="size-3.5" />
                                  </Button>
                                </div>
                              </CardContent>
                            </Card>
                          </li>
                        )
                      })}
                    </ul>
                  )}
                </section>

                <Card size="sm">
                  <CardContent className="flex items-center justify-between px-3 text-xs text-muted-foreground">
                    <span>Version</span>
                    <span className="font-mono">{appVersion || "dev"}</span>
                  </CardContent>
                </Card>
              </div>
            )}

            {mode.kind === "create" && (
              <ProfileForm
                profile={null}
                onCancel={() => setMode({ kind: "list" })}
                submitLabel="Create profile"
                onSubmit={async (input) => {
                  await onSave(input)
                  setMode({ kind: "list" })
                }}
              />
            )}

            {mode.kind === "edit" && (
              <ProfileForm
                profile={mode.profile}
                onCancel={() => setMode({ kind: "list" })}
                submitLabel="Save changes"
                onSubmit={async (input) => {
                  await onSave(input)
                  setMode({ kind: "list" })
                }}
              />
            )}
          </SheetBody>

          {mode.kind === "list" && (
            <SheetFooter>
              <Button variant="ghost" size="sm" onClick={() => onOpenChange(false)}>
                Close
              </Button>
            </SheetFooter>
          )}
        </SheetContent>
      </Sheet>

      <AlertDialog open={pendingDelete !== null} onOpenChange={(open) => !open && setPendingDelete(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete profile?</AlertDialogTitle>
            <AlertDialogDescription>
              Remove the profile <strong>{pendingDelete?.name}</strong>. This only deletes the local credentials — your TYPO3 user is unaffected.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              onClick={async () => {
                if (pendingDelete) await onDelete(pendingDelete.id)
                setPendingDelete(null)
              }}
            >
              Delete
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  )
}
