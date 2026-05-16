import type { ApiEnvelope, ApiMeta } from "@/lib/types"

export function unwrap<T>(payload: ApiEnvelope<T>): { data: T; meta?: ApiMeta } {
  if (payload && typeof payload === "object" && "data" in payload) {
    const envelope = payload as { data: T; meta?: ApiMeta }
    return { data: envelope.data, meta: envelope.meta }
  }
  return { data: payload as T }
}

export function toArray<T>(value: unknown): T[] {
  return Array.isArray(value) ? (value as T[]) : []
}

export function displayDateTime(value: unknown): string {
  const timestamp = typeof value === "number" ? value : Number(value || 0)
  if (!timestamp) return ""
  const date = new Date(timestamp * 1000)
  const offsetDate = new Date(date.getTime() - date.getTimezoneOffset() * 60000)
  return offsetDate.toISOString().slice(0, 16)
}

export function fromDateTime(value: string): number {
  return value ? Math.floor(new Date(value).getTime() / 1000) : 0
}

export function assetUrl(baseUrl: string, value?: string | null): string {
  if (!value) return ""
  if (/^https?:\/\//i.test(value)) return value
  return `${baseUrl.replace(/\/$/, "")}/${value.replace(/^\//, "")}`
}
