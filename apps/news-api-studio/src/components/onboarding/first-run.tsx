"use client"

import { Sparkles } from "lucide-react"

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { ProfileForm } from "@/components/settings/profile-form"
import type { Profile } from "@/lib/types"

type Props = {
  onCreate: (profile: Omit<Profile, "id">) => Promise<void>
}

export function FirstRun({ onCreate }: Props) {
  return (
    <div className="flex min-h-screen items-center justify-center bg-background p-6">
      <Card className="w-full max-w-md">
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Sparkles className="size-4 text-primary" />
            Welcome to the News API Studio
          </CardTitle>
          <CardDescription>
            Add your first connection profile to start editing TYPO3 news records. You can add more profiles later from the settings drawer.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <ProfileForm
            submitLabel="Connect"
            hideCancel
            onSubmit={async (input) => {
              await onCreate(input)
            }}
          />
        </CardContent>
      </Card>
    </div>
  )
}
