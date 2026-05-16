"use client"

import { useCallback, useMemo, useRef } from "react"

import type { ApiEnvelope, ApiMeta, Profile } from "@/lib/types"
import { unwrap } from "@/lib/format"

export type ApiClient = {
  apiRoot: string
  fetch: <T>(path: string, init?: RequestInit) => Promise<{ data: T; meta?: ApiMeta }>
  abort: () => void
}

type Options = {
  profile: Profile | null
  workspace: number | null
}

/**
 * Single source of truth for HTTP calls against sg_apicore.
 *
 * - All in-flight requests share an AbortController; a profile/workspace
 *   switch (or `abort()`) cancels them so stale responses cannot overwrite
 *   fresh state.
 * - Adds tenant + bearer headers, no-cache GET, JSON-or-FormData detection,
 *   and parses the standard `{ data, meta }` envelope into a plain shape.
 */
export function useApi({ profile, workspace }: Options): ApiClient {
  const apiRoot = useMemo(() => {
    if (!profile) return ""
    return `${profile.baseUrl.replace(/\/$/, "")}/api/${profile.apiId}/v1`
  }, [profile])

  const controllerRef = useRef<AbortController | null>(null)

  const abort = useCallback(() => {
    controllerRef.current?.abort()
    controllerRef.current = null
  }, [])

  const fetcher = useCallback(
    async <T,>(path: string, init: RequestInit = {}) => {
      if (!profile) throw new Error("No active connection profile")
      if (!profile.token) throw new Error("Profile has no token")
      if (!controllerRef.current || controllerRef.current.signal.aborted) {
        controllerRef.current = new AbortController()
      }
      const signal = init.signal ?? controllerRef.current.signal

      const url = new URL(`${apiRoot}${path.startsWith("/") ? path : `/${path}`}`)
      if (workspace !== null && !url.searchParams.has("workspace")) {
        url.searchParams.set("workspace", String(workspace))
      }
      const method = String(init.method || "GET").toUpperCase()
      if (method === "GET" && !url.searchParams.has("_")) {
        url.searchParams.set("_", String(Date.now()))
      }
      const isForm = init.body instanceof FormData
      const response = await fetch(url.toString(), {
        ...init,
        signal,
        cache: method === "GET" ? "no-store" : init.cache,
        headers: {
          Accept: "application/json",
          ...(profile.tenant ? { "X-Tenant-ID": profile.tenant } : {}),
          ...(profile.token ? { Authorization: `Bearer ${profile.token}` } : {}),
          ...(isForm || !init.body ? {} : { "Content-Type": "application/json" }),
          ...(init.headers || {}),
        },
      })
      const text = await response.text()
      const parsed = text ? JSON.parse(text) : null
      if (!response.ok) {
        const detail = parsed?.detail || parsed?.message || parsed?.title || `${response.status} ${response.statusText}`
        const errors = Array.isArray(parsed?.errors) ? ` ${parsed.errors.join("; ")}` : ""
        throw new Error(`${detail}${errors}`)
      }
      return unwrap<T>(parsed as ApiEnvelope<T>)
    },
    [apiRoot, profile, workspace],
  )

  return { apiRoot, fetch: fetcher, abort }
}
