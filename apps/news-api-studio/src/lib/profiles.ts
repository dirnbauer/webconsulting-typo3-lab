import type { Profile } from "@/lib/types"
import { decryptToken, encryptToken } from "@/lib/electron-bridge"

const STORAGE_KEY = "typo3-news-api-studio:profiles"
const ACTIVE_KEY = "typo3-news-api-studio:active-profile"

type StoredProfile = Profile

function uuid(): string {
  if (typeof crypto !== "undefined" && "randomUUID" in crypto) return crypto.randomUUID()
  return `p-${Math.random().toString(36).slice(2)}-${Date.now()}`
}

function readStored(): StoredProfile[] {
  if (typeof window === "undefined") return []
  try {
    const raw = window.localStorage.getItem(STORAGE_KEY)
    if (!raw) return []
    const parsed = JSON.parse(raw)
    return Array.isArray(parsed) ? parsed : []
  } catch {
    return []
  }
}

function writeStored(profiles: StoredProfile[]): void {
  if (typeof window === "undefined") return
  window.localStorage.setItem(STORAGE_KEY, JSON.stringify(profiles))
}

export async function listProfiles(): Promise<Profile[]> {
  const stored = readStored()
  return Promise.all(
    stored.map(async (profile) => ({ ...profile, token: await decryptToken(profile.token) })),
  )
}

export async function getActiveProfileId(): Promise<string | null> {
  if (typeof window === "undefined") return null
  return window.localStorage.getItem(ACTIVE_KEY)
}

export async function setActiveProfileId(id: string | null): Promise<void> {
  if (typeof window === "undefined") return
  if (id) window.localStorage.setItem(ACTIVE_KEY, id)
  else window.localStorage.removeItem(ACTIVE_KEY)
}

export async function getActiveProfile(): Promise<Profile | null> {
  const id = await getActiveProfileId()
  if (!id) return null
  const profiles = await listProfiles()
  return profiles.find((profile) => profile.id === id) || null
}

export async function saveProfile(input: Omit<Profile, "id"> & { id?: string }): Promise<Profile> {
  const stored = readStored()
  const id = input.id || uuid()
  const token = (input.token || "").trim()
  const tokenEnc = await encryptToken(token)
  const profile: StoredProfile = {
    id,
    name: input.name.trim() || "Untitled",
    baseUrl: input.baseUrl.trim().replace(/\/+$/, ""),
    apiId: input.apiId.trim(),
    tenant: input.tenant.trim(),
    token: tokenEnc,
  }
  const index = stored.findIndex((entry) => entry.id === id)
  if (index >= 0) stored[index] = profile
  else stored.push(profile)
  writeStored(stored)
  return { ...profile, token }
}

export async function deleteProfile(id: string): Promise<void> {
  const stored = readStored().filter((profile) => profile.id !== id)
  writeStored(stored)
  const active = await getActiveProfileId()
  if (active === id) await setActiveProfileId(stored[0]?.id || null)
}

export function profileSummary(profile: Profile): string {
  try {
    return new URL(profile.baseUrl).host
  } catch {
    return profile.baseUrl
  }
}
