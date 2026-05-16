export type ApiMeta = { schemaHash?: string }
export type ApiEnvelope<T> = { data?: T; meta?: ApiMeta } | T

export type FieldSchema = {
  name: string
  label: string
  description?: string
  type: string
  tcaType: string
  renderType?: string
  required?: boolean
  readOnly?: boolean
  richtext?: boolean
  writeable?: boolean
  relation?: { table: string; multiple: boolean; searchUrl: string } | null
  items?: Array<{ label: string; value: string | number }>
  maxitems?: number | null
  rows?: number | null
}

export type FormSchema = {
  table: string
  label: string
  fields: Record<string, FieldSchema>
  tabs: Array<{ id: string; label: string; fields: string[] }>
  defaultValues: Record<string, unknown>
}

export type FileReferenceValue = {
  uid?: number
  uid_local?: number
  fileUid?: number
  title?: string
  alternative?: string
  description?: string
  url?: string
  publicUrl?: string
  name?: string
  crop?: unknown
}

export type NewsRecord = Record<string, unknown> & {
  uid?: number
  pid?: number
  title?: string
  hidden?: number
  fal_media?: FileReferenceValue[]
}

export type FileItem = {
  uid: number | null
  name: string
  combinedIdentifier: string | null
  publicUrl: string | null
  mimeType: string
  size: number
  extension: string
  permissions: { read: boolean; write: boolean; delete: boolean }
}

export type FolderItem = {
  name: string
  combinedIdentifier: string
  publicUrl: string | null
  permissions: { read: boolean; write: boolean; delete: boolean }
}

export type FileListing = {
  current: FolderItem
  folders: FolderItem[]
  files: FileItem[]
}

export type WorkspaceItem = {
  uid: number
  title: string
  access: string
  current: boolean
  publishAllowed: boolean
}

export type MeResponse = {
  user: { uid: number; username: string; realName: string; email: string; admin: boolean }
  token: { uid: number | string | null; scopes: string[] }
  workspace: { uid: number; title: string; access: string }
  workspaces: WorkspaceItem[]
  permissions: Record<string, boolean>
}

export type RecordOption = { uid: number; pid: number; label: string; table: string }

export type Profile = {
  id: string
  name: string
  baseUrl: string
  apiId: string
  tenant: string
  /** Stored token (encrypted in Electron, plain in browser fallback). Marker prefix indicates encryption. */
  token: string
}

export type ConnectionStatus = "idle" | "connecting" | "connected" | "error"

export type ConnectionState = {
  status: ConnectionStatus
  message?: string
  user?: MeResponse["user"]
  schemaHash?: string
}

export type RecordFilter = "all" | "visible" | "hidden"
