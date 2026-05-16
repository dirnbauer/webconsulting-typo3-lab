import type { FieldSchema, FileItem, FileReferenceValue } from "@/lib/types"
import { toArray } from "@/lib/format"

export function valueToIds(value: unknown): number[] {
  if (Array.isArray(value)) {
    return value
      .map((item) => (typeof item === "object" && item !== null ? Number((item as { uid?: number }).uid) : Number(item)))
      .filter((item) => Number.isFinite(item) && item > 0)
  }
  if (typeof value === "string") {
    return value
      .split(",")
      .map((item) => Number(item.trim()))
      .filter((item) => Number.isFinite(item) && item > 0)
  }
  const numeric = Number(value)
  return Number.isFinite(numeric) && numeric > 0 ? [numeric] : []
}

export function fileReferenceMatches(reference: FileReferenceValue, file: FileItem): boolean {
  const fileUid = Number(file.uid || 0)
  const referenceFileUid = Number(reference.uid_local || reference.fileUid || 0)
  if (fileUid > 0 && referenceFileUid === fileUid) return true

  const fileUrl = String(file.publicUrl || "").replace(/^\//, "")
  const referenceUrl = String(reference.publicUrl || reference.url || "").replace(/^\//, "")
  if (fileUrl && referenceUrl && fileUrl === referenceUrl) return true

  return Boolean(file.name && reference.name === file.name)
}

export function normalizeFileReferences(value: unknown): FileReferenceValue[] | undefined {
  const references = toArray<FileReferenceValue>(value)
  if (references.length === 0) return undefined

  const normalized = references
    .map((reference) => {
      const uid = Number(reference.uid || 0)
      const uidLocal = Number(reference.uid_local || reference.fileUid || 0)
      if (!uid && !uidLocal) return null

      const result: FileReferenceValue = {}
      if (uid > 0) result.uid = uid
      if (uidLocal > 0) result.uid_local = uidLocal
      if (reference.title || reference.name) result.title = String(reference.title || reference.name)
      if (reference.alternative) result.alternative = String(reference.alternative)
      if (reference.description) result.description = String(reference.description)
      if (reference.crop !== undefined) result.crop = reference.crop
      return result
    })
    .filter((reference): reference is FileReferenceValue => Boolean(reference))

  return normalized.length > 0 ? normalized : undefined
}

export function normalizeSaveValue(field: FieldSchema, value: unknown): unknown {
  if (value === undefined) return undefined
  if (field.type === "file") return normalizeFileReferences(value)
  if (field.type === "relation" && field.relation) {
    const ids = valueToIds(value)
    if (field.relation.multiple) return ids.join(",")
    return ids[0] || 0
  }
  if ((field.type === "relation" || field.type === "inline") && Array.isArray(value)) {
    return valueToIds(value).join(",")
  }
  if (field.type === "boolean") return Number(Boolean(value))
  if (Array.isArray(value) && value.length === 0) return undefined
  return value
}

export function normalizeDefaultValue(value: unknown): unknown {
  if (Array.isArray(value)) return value.length > 0 ? value : undefined
  return value
}

export function isRichTextField(field: FieldSchema): boolean {
  return field.type === "richtext" || field.richtext === true || field.renderType === "rte" || field.renderType === "richtext"
}

/**
 * Validate that a relation searchUrl points inside the API root.
 * Returns the URL when safe, null when it is suspicious (absolute external URL or tries to escape).
 */
export function validateRelationSearchUrl(searchUrl: string, apiRoot: string): string | null {
  if (!searchUrl) return null
  if (searchUrl.includes("..")) return null
  if (/^https?:\/\//i.test(searchUrl)) {
    try {
      const url = new URL(searchUrl)
      const root = new URL(apiRoot)
      if (url.origin !== root.origin) return null
      if (!url.pathname.startsWith(root.pathname.replace(/\/$/, ""))) return null
      return searchUrl
    } catch {
      return null
    }
  }
  return searchUrl.startsWith("/") ? searchUrl : `/${searchUrl}`
}
