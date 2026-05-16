"use client"

import { type FormEvent, useEffect, useState } from "react"

import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import type { Profile } from "@/lib/types"

type Props = {
  profile?: Profile | null
  onSubmit: (profile: Omit<Profile, "id"> & { id?: string }) => Promise<void>
  onCancel?: () => void
  submitLabel?: string
  hideCancel?: boolean
}

const empty: Omit<Profile, "id"> = {
  name: "",
  baseUrl: "",
  apiId: "news",
  tenant: "",
  token: "",
}

export function ProfileForm({ profile, onSubmit, onCancel, submitLabel = "Save profile", hideCancel }: Props) {
  const [draft, setDraft] = useState<Omit<Profile, "id"> & { id?: string }>(empty)
  const [submitting, setSubmitting] = useState(false)

  useEffect(() => {
    if (profile) setDraft({ ...profile })
    else setDraft(empty)
  }, [profile?.id])

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    setSubmitting(true)
    try {
      await onSubmit(draft)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <div className="grid gap-2">
        <Label htmlFor="profile-name">Profile name</Label>
        <Input
          id="profile-name"
          required
          placeholder="Production / Staging / Local"
          value={draft.name}
          onChange={(event) => setDraft((state) => ({ ...state, name: event.target.value }))}
        />
      </div>
      <div className="grid gap-2">
        <Label htmlFor="profile-baseUrl">TYPO3 base URL</Label>
        <Input
          id="profile-baseUrl"
          required
          placeholder="https://www.example.com"
          value={draft.baseUrl}
          onChange={(event) => setDraft((state) => ({ ...state, baseUrl: event.target.value }))}
        />
      </div>
      <div className="grid grid-cols-2 gap-3">
        <div className="grid gap-2">
          <Label htmlFor="profile-apiId">API id</Label>
          <Input
            id="profile-apiId"
            required
            placeholder="news"
            value={draft.apiId}
            onChange={(event) => setDraft((state) => ({ ...state, apiId: event.target.value }))}
          />
        </div>
        <div className="grid gap-2">
          <Label htmlFor="profile-tenant">Tenant</Label>
          <Input
            id="profile-tenant"
            placeholder="optional"
            value={draft.tenant}
            onChange={(event) => setDraft((state) => ({ ...state, tenant: event.target.value }))}
          />
        </div>
      </div>
      <div className="grid gap-2">
        <Label htmlFor="profile-token">Personal BE-user token</Label>
        <Input
          id="profile-token"
          type="password"
          required
          value={draft.token}
          onChange={(event) => setDraft((state) => ({ ...state, token: event.target.value }))}
          placeholder="paste token"
        />
        <p className="text-xs text-muted-foreground">
          Stored encrypted via the OS keychain when running in the Electron app, plain in localStorage when running in a browser.
        </p>
      </div>
      <div className="flex items-center justify-end gap-2 pt-1">
        {!hideCancel && onCancel && (
          <Button type="button" variant="ghost" size="sm" onClick={onCancel}>
            Cancel
          </Button>
        )}
        <Button type="submit" size="sm" disabled={submitting}>
          {submitting ? "Saving…" : submitLabel}
        </Button>
      </div>
    </form>
  )
}
