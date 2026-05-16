"use client"

import { type ChangeEvent, type FormEvent, useCallback, useEffect, useMemo, useRef, useState } from "react"
import { toast } from "sonner"

import { AppHeader } from "@/components/header/app-header"
import { RecordsPanel } from "@/components/records/records-panel"
import { EditorPanel } from "@/components/editor/editor-panel"
import { FileBrowserSheet } from "@/components/files/file-browser-sheet"
import { SettingsSheet } from "@/components/settings/settings-sheet"
import { FirstRun } from "@/components/onboarding/first-run"
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
import { Sheet, SheetBody, SheetContent, SheetDescription, SheetHeader, SheetTitle } from "@/components/ui/sheet"
import { useApi } from "@/hooks/use-api"
import { useKeyboardShortcuts } from "@/hooks/use-keyboard-shortcuts"
import { useMediaQuery } from "@/hooks/use-media-query"
import {
  deleteProfile as removeProfile,
  getActiveProfileId,
  listProfiles,
  saveProfile as persistProfile,
  setActiveProfileId,
} from "@/lib/profiles"
import { getBridge, onMenuAction, openExternal } from "@/lib/electron-bridge"
import { fileReferenceMatches, normalizeDefaultValue, normalizeSaveValue, validateRelationSearchUrl, valueToIds } from "@/lib/normalize"
import { toArray } from "@/lib/format"
import type {
  ConnectionState,
  FieldSchema,
  FileItem,
  FileListing,
  FileReferenceValue,
  FormSchema,
  MeResponse,
  NewsRecord,
  Profile,
  RecordFilter,
  RecordOption,
  WorkspaceItem,
} from "@/lib/types"

const DRAFT_KEY = "typo3-news-api-studio:draft"
const RECORDS_PINNED_KEY = "typo3-news-api-studio:records-pinned"
const PAGE_SIZE = 50

type DraftState = { dirtyFields?: string[]; record?: NewsRecord; profileId?: string; updatedAt?: number }

export default function Home() {
  const [profiles, setProfiles] = useState<Profile[]>([])
  const [activeProfileId, setActiveId] = useState<string | null>(null)
  const [appVersion, setAppVersion] = useState<string | null>(null)

  const [me, setMe] = useState<MeResponse | null>(null)
  const [workspace, setWorkspace] = useState<number | null>(null)
  const [schema, setSchema] = useState<FormSchema | null>(null)
  const [schemaHash, setSchemaHash] = useState("")
  const [files, setFiles] = useState<FileListing | null>(null)
  const [records, setRecords] = useState<NewsRecord[]>([])
  const [page, setPage] = useState(1)
  const [hasMore, setHasMore] = useState(false)
  const [loadingMore, setLoadingMore] = useState(false)

  const [selected, setSelected] = useState<NewsRecord>({})
  const [dirty, setDirty] = useState(false)
  const [hasDraft, setHasDraft] = useState(false)
  const [dirtyFields, setDirtyFields] = useState<Set<string>>(() => new Set())
  const [activeTab, setActiveTab] = useState("main")
  const [relationResults, setRelationResults] = useState<Record<string, RecordOption[]>>({})
  const [relationSearch, setRelationSearch] = useState<Record<string, string>>({})

  const [search, setSearch] = useState("")
  const [filter, setFilter] = useState<RecordFilter>("all")
  const [loading, setLoading] = useState(false)
  const [saving, setSaving] = useState(false)

  const [settingsOpen, setSettingsOpen] = useState(false)
  const [recordsOpen, setRecordsOpen] = useState(false)
  const [recordsPinned, setRecordsPinned] = useState(false)
  const [fileBrowserField, setFileBrowserField] = useState<string | null>(null)
  const [pendingDelete, setPendingDelete] = useState(false)
  const [pendingSwitch, setPendingSwitch] = useState<NewsRecord | null>(null)

  // The "pin records" switch only meaningful at lg+ (≥1024px). Below that the
  // viewport is too narrow for two columns, so we always fall back to the
  // floating sheet regardless of the user's preference.
  const canPin = useMediaQuery("(min-width: 1024px)")
  const isRecordsPinned = recordsPinned && canPin

  const [connection, setConnection] = useState<ConnectionState>({ status: "idle" })

  const searchInputRef = useRef<HTMLInputElement | null>(null)

  const activeProfile = useMemo(() => profiles.find((profile) => profile.id === activeProfileId) || null, [profiles, activeProfileId])
  const activeProfileConnectionKey = activeProfile
    ? [
        activeProfile.id,
        activeProfile.baseUrl,
        activeProfile.apiId,
        activeProfile.tenant,
        activeProfile.token,
      ].join("\n")
    : ""
  const { fetch: apiFetch, abort, apiRoot } = useApi({ profile: activeProfile, workspace })

  // ---------- pinned records preference ----------
  useEffect(() => {
    if (typeof window === "undefined") return
    const stored = window.localStorage.getItem(RECORDS_PINNED_KEY)
    if (stored === "1") setRecordsPinned(true)
  }, [])

  useEffect(() => {
    if (typeof window === "undefined") return
    window.localStorage.setItem(RECORDS_PINNED_KEY, recordsPinned ? "1" : "0")
  }, [recordsPinned])

  // ---------- profile bootstrap ----------
  useEffect(() => {
    void (async () => {
      const bridge = getBridge()
      if (bridge) setAppVersion(bridge.appVersion)
      const list = await listProfiles()
      const id = await getActiveProfileId()
      setProfiles(list)
      setActiveId(id || list[0]?.id || null)
    })()
  }, [])

  // ---------- draft restore ----------
  useEffect(() => {
    if (typeof window === "undefined") return
    const raw = window.localStorage.getItem(DRAFT_KEY)
    if (!raw) return
    try {
      const parsed = JSON.parse(raw) as DraftState
      if (parsed.record && parsed.profileId === activeProfileId) {
        setSelected(parsed.record)
        setDirtyFields(new Set(parsed.dirtyFields || []))
        setDirty(true)
        setHasDraft(true)
      }
    } catch {
      window.localStorage.removeItem(DRAFT_KEY)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [activeProfileId])

  // ---------- draft persist ----------
  useEffect(() => {
    if (typeof window === "undefined") return
    if (!dirty || !activeProfileId) return
    const draft: DraftState = {
      profileId: activeProfileId,
      dirtyFields: Array.from(dirtyFields),
      record: selected,
      updatedAt: Date.now(),
    }
    window.localStorage.setItem(DRAFT_KEY, JSON.stringify(draft))
  }, [dirty, dirtyFields, selected, activeProfileId])

  // ---------- connect on profile change ----------
  useEffect(() => {
    if (!activeProfile) return
    abort()
    void connect()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [activeProfileConnectionKey])

  // ---------- workspace switching reloads schema/records ----------
  useEffect(() => {
    if (!activeProfile || !me) return
    void reloadAfterWorkspaceChange()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [workspace])

  // ---------- electron menu shortcuts ----------
  useEffect(() => {
    return onMenuAction((action) => {
      if (action === "save") void saveRecord()
      else if (action === "new") newRecord()
      else if (action === "settings") setSettingsOpen(true)
      else if (action === "search") searchInputRef.current?.focus()
    })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  useKeyboardShortcuts({
    onSave: () => void saveRecord(),
    onNew: () => newRecord(),
    onSettings: () => setSettingsOpen((prev) => !prev),
    onSearch: () => {
      // Open the records sheet and focus its search input next tick
      setRecordsOpen(true)
      setTimeout(() => searchInputRef.current?.focus(), 60)
    },
    onEscape: () => {
      if (fileBrowserField) setFileBrowserField(null)
      else if (settingsOpen) setSettingsOpen(false)
      else if (recordsOpen) setRecordsOpen(false)
    },
  })

  async function connect() {
    if (!activeProfile) return
    setLoading(true)
    setConnection({ status: "connecting", message: "Connecting…" })
    try {
      const meResponse = await apiFetch<MeResponse>("/studio/me")
      const nextWorkspace = meResponse.data.workspace.uid
      setMe(meResponse.data)
      setWorkspace(nextWorkspace)
      const [schemaResponse, fileResponse, recordsResponse] = await Promise.all([
        apiFetch<FormSchema>(`/studio/schema/news?workspace=${nextWorkspace}`),
        apiFetch<FileListing>(`/studio/files?workspace=${nextWorkspace}`),
        apiFetch<NewsRecord[] | { data?: NewsRecord[] }>(`/news?page=1&limit=${PAGE_SIZE}&sort=-uid&workspace=${nextWorkspace}`),
      ])
      setSchema(schemaResponse.data)
      setSchemaHash(schemaResponse.meta?.schemaHash || "")
      setFiles(fileResponse.data)
      const list = extractRecords(recordsResponse.data)
      setRecords(list)
      setPage(1)
      setHasMore(list.length >= PAGE_SIZE)
      if (!dirty && list[0]) {
        setSelected(list[0])
        setDirtyFields(new Set())
      }
      setActiveTab(schemaResponse.data.tabs[0]?.id || "main")
      setConnection({
        status: "connected",
        message: `Connected as ${meResponse.data.user.username}`,
        user: meResponse.data.user,
        schemaHash: schemaResponse.meta?.schemaHash,
      })
    } catch (error) {
      const message = errorMessage(error)
      setConnection({ status: "error", message })
      toast.error("Connection failed", { description: message })
    } finally {
      setLoading(false)
    }
  }

  async function reloadAfterWorkspaceChange() {
    if (!activeProfile || workspace === null) return
    setLoading(true)
    try {
      const [schemaResponse, fileResponse, recordsResponse] = await Promise.all([
        apiFetch<FormSchema>(`/studio/schema/news?workspace=${workspace}`),
        apiFetch<FileListing>(`/studio/files?workspace=${workspace}`),
        apiFetch<NewsRecord[] | { data?: NewsRecord[] }>(`/news?page=1&limit=${PAGE_SIZE}&sort=-uid&workspace=${workspace}`),
      ])
      setSchema(schemaResponse.data)
      setSchemaHash(schemaResponse.meta?.schemaHash || "")
      setFiles(fileResponse.data)
      const list = extractRecords(recordsResponse.data)
      setRecords(list)
      setPage(1)
      setHasMore(list.length >= PAGE_SIZE)
    } catch (error) {
      toast.error("Workspace reload failed", { description: errorMessage(error) })
    } finally {
      setLoading(false)
    }
  }

  function selectRecord(record: NewsRecord) {
    if (dirty) {
      setPendingSwitch(record)
      return
    }
    setSelected(record)
    setDirtyFields(new Set())
    setHasDraft(false)
  }

  function applyPendingSwitch(discard: boolean) {
    const target = pendingSwitch
    setPendingSwitch(null)
    if (discard && target) {
      setSelected(target)
      setDirty(false)
      setDirtyFields(new Set())
      setHasDraft(false)
      if (typeof window !== "undefined") window.localStorage.removeItem(DRAFT_KEY)
    }
  }

  function dismissDraft() {
    setHasDraft(false)
    setDirty(false)
    setDirtyFields(new Set())
    if (typeof window !== "undefined") window.localStorage.removeItem(DRAFT_KEY)
    if (records[0]) setSelected(records[0])
    else setSelected({})
  }

  function discardChanges() {
    if (!selected.uid) {
      newRecord()
      return
    }
    const original = records.find((record) => record.uid === selected.uid)
    if (original) setSelected(original)
    setDirty(false)
    setDirtyFields(new Set())
    setHasDraft(false)
    if (typeof window !== "undefined") window.localStorage.removeItem(DRAFT_KEY)
  }

  function updateField(field: string, value: unknown) {
    setSelected((record) => ({ ...record, [field]: value }))
    setDirtyFields((fields) => new Set(fields).add(field))
    setDirty(true)
  }

  function newRecord() {
    const defaults = schema?.defaultValues || { pid: 16, hidden: 0, datetime: Math.floor(Date.now() / 1000) }
    setSelected({ ...defaults, title: "" })
    // Only mark fields dirty when the user actually edits — avoids creating empty rows by accident.
    setDirtyFields(new Set())
    setDirty(false)
    setHasDraft(false)
    if (schema?.tabs[0]) setActiveTab(schema.tabs[0].id)
  }

  async function saveRecord(event?: FormEvent) {
    event?.preventDefault()
    if (!schema) return
    if (selected.uid && dirtyFields.size === 0) {
      toast.info("No changes to save")
      return
    }
    setSaving(true)
    const creating = !selected.uid
    const payload: NewsRecord = {}
    if (creating) {
      for (const [field, value] of Object.entries(schema.defaultValues || {})) {
        const normalized = normalizeDefaultValue(value)
        if (normalized !== undefined) payload[field] = normalized
      }
    }
    const requiredMissing: string[] = []
    for (const field of Object.values(schema.fields)) {
      if (field.name === "uid" || field.readOnly || field.writeable === false) continue
      if (!creating && !dirtyFields.has(field.name)) continue
      const value = normalizeSaveValue(field, selected[field.name])
      if (value !== undefined) payload[field.name] = value
      if (field.required && (value === undefined || value === "" || value === 0)) {
        requiredMissing.push(field.label || field.name)
      }
    }
    if (creating && requiredMissing.length > 0) {
      toast.error("Missing required fields", { description: requiredMissing.join(", ") })
      setSaving(false)
      return
    }
    try {
      const method = creating ? "POST" : "PATCH"
      const path = creating ? "/news" : `/news/${selected.uid}`
      const response = await apiFetch<NewsRecord>(path, { method, body: JSON.stringify(payload) })
      const savedUid = Number(response.data.uid || selected.uid || 0)
      setDirty(false)
      setDirtyFields(new Set())
      setHasDraft(false)
      if (typeof window !== "undefined") window.localStorage.removeItem(DRAFT_KEY)
      await reloadRecords()
      if (savedUid > 0) {
        const savedRecord = await apiFetch<NewsRecord>(`/news/${savedUid}`)
        setSelected(savedRecord.data)
      } else {
        setSelected(response.data)
      }
      toast.success(creating ? `Created news #${savedUid}` : `Saved news #${selected.uid}`)
    } catch (error) {
      toast.error("Save failed", { description: errorMessage(error) })
    } finally {
      setSaving(false)
    }
  }

  async function reloadRecords() {
    if (!activeProfile) return
    const response = await apiFetch<NewsRecord[] | { data?: NewsRecord[] }>(`/news?page=1&limit=${PAGE_SIZE}&sort=-uid`)
    const list = extractRecords(response.data)
    setRecords(list)
    setPage(1)
    setHasMore(list.length >= PAGE_SIZE)
  }

  async function loadMore() {
    if (!activeProfile || loadingMore) return
    setLoadingMore(true)
    try {
      const next = page + 1
      const response = await apiFetch<NewsRecord[] | { data?: NewsRecord[] }>(`/news?page=${next}&limit=${PAGE_SIZE}&sort=-uid`)
      const list = extractRecords(response.data)
      setRecords((prev) => [...prev, ...list])
      setPage(next)
      setHasMore(list.length >= PAGE_SIZE)
    } catch (error) {
      toast.error("Failed to load more", { description: errorMessage(error) })
    } finally {
      setLoadingMore(false)
    }
  }

  async function confirmDelete() {
    if (!selected.uid) return
    setPendingDelete(false)
    setLoading(true)
    try {
      await apiFetch(`/news/${selected.uid}`, { method: "DELETE" })
      toast.success(`Deleted news #${selected.uid}`)
      setSelected({})
      setDirty(false)
      setDirtyFields(new Set())
      await reloadRecords()
    } catch (error) {
      toast.error("Delete failed", { description: errorMessage(error) })
    } finally {
      setLoading(false)
    }
  }

  async function searchRelation(field: FieldSchema) {
    if (!field.relation) return
    const safe = validateRelationSearchUrl(field.relation.searchUrl, apiRoot)
    if (!safe) {
      toast.error("Refused suspicious relation search URL", { description: field.relation.searchUrl })
      return
    }
    try {
      const q = relationSearch[field.name] || ""
      const response = await apiFetch<RecordOption[]>(`${safe}?q=${encodeURIComponent(q)}&limit=20`)
      setRelationResults((state) => ({ ...state, [field.name]: response.data }))
    } catch (error) {
      toast.error("Relation search failed", { description: errorMessage(error) })
    }
  }

  function setRelationValue(field: FieldSchema, option: RecordOption) {
    const ids = valueToIds(selected[field.name])
    const next = field.relation?.multiple ? Array.from(new Set([...ids, option.uid])) : [option.uid]
    updateField(field.name, field.relation?.multiple ? next : next[0])
  }

  function removeRelationValue(field: FieldSchema, uid: number) {
    const next = valueToIds(selected[field.name]).filter((id) => id !== uid)
    updateField(field.name, field.relation?.multiple ? next : next[0] || 0)
  }

  function attachFile(fieldName: string, file: FileItem) {
    if (!file.uid) {
      toast.error(`Cannot attach ${file.name}`, { description: "TYPO3 did not return a file UID." })
      return
    }

    const field = schema?.fields[fieldName]
    const existing = toArray<FileReferenceValue>(selected[fieldName]).filter((reference) => !fileReferenceMatches(reference, file))
    const reference: FileReferenceValue = {
      uid_local: file.uid,
      title: String(selected.title || file.name),
      alternative: String(selected.title || file.name),
      publicUrl: file.publicUrl || undefined,
      url: file.publicUrl || undefined,
      name: file.name,
    }
    const maxItems = Number(field?.maxitems || 0)
    updateField(fieldName, maxItems === 1 ? [reference] : [...existing, reference])

    const targetTab = schema?.tabs.find((tab) => tab.fields.includes(fieldName))
    if (targetTab) setActiveTab(targetTab.id)
    toast.success(`Attached ${file.name}`)
  }

  async function uploadFile(event: ChangeEvent<HTMLInputElement>, fieldName: string) {
    const file = event.target.files?.[0]
    if (!file || !files?.current) return
    setLoading(true)
    try {
      const body = new FormData()
      body.append("file", file)
      body.append("folder", files.current.combinedIdentifier)
      const response = await apiFetch<FileItem>("/studio/files/upload", { method: "POST", body })
      attachFile(fieldName, response.data)
      const listing = await apiFetch<FileListing>(`/studio/files?folder=${encodeURIComponent(files.current.combinedIdentifier)}`)
      setFiles(listing.data)
    } catch (error) {
      toast.error("Upload failed", { description: errorMessage(error) })
    } finally {
      event.target.value = ""
      setLoading(false)
    }
  }

  async function previewRecord() {
    if (!selected.uid) return
    try {
      const response = await apiFetch<{ available: boolean; url: string | null; diagnostics: string[] }>(`/studio/news/${selected.uid}/preview`)
      if (response.data.url) {
        await openExternal(response.data.url)
      } else {
        toast.info(response.data.diagnostics[0] || "Preview URL unavailable")
      }
    } catch (error) {
      toast.error("Preview failed", { description: errorMessage(error) })
    }
  }

  async function submitRecord() {
    if (!selected.uid) return
    try {
      await apiFetch(`/studio/news/${selected.uid}/submit`, { method: "POST", body: JSON.stringify({}) })
      toast.success("Submitted to publish stage")
    } catch (error) {
      toast.error("Submit failed", { description: errorMessage(error) })
    }
  }

  async function publishRecord() {
    if (!selected.uid) return
    try {
      await apiFetch(`/studio/news/${selected.uid}/publish`, { method: "POST", body: JSON.stringify({}) })
      toast.success("Published")
      await reloadRecords()
    } catch (error) {
      toast.error("Publish failed", { description: errorMessage(error) })
    }
  }

  async function handleSelectProfile(id: string) {
    if (id === activeProfileId) return
    await setActiveProfileId(id)
    setActiveId(id)
    setRecords([])
    setSchema(null)
    setSelected({})
    setMe(null)
    setDirty(false)
    setDirtyFields(new Set())
    setHasDraft(false)
  }

  async function saveProfileAction(input: Omit<Profile, "id"> & { id?: string }) {
    const saved = await persistProfile(input)
    const list = await listProfiles()
    setProfiles(list)
    if (!activeProfileId) {
      await setActiveProfileId(saved.id)
      setActiveId(saved.id)
    }
    toast.success(`Profile saved`)
  }

  async function deleteProfileAction(id: string) {
    await removeProfile(id)
    const list = await listProfiles()
    setProfiles(list)
    if (id === activeProfileId) setActiveId(list[0]?.id || null)
    toast.success("Profile deleted")
  }

  const fileBrowserLabel = fileBrowserField ? schema?.fields[fileBrowserField]?.label || fileBrowserField : null

  if (profiles.length === 0) {
    return (
      <main className="min-h-screen bg-background text-foreground">
        <FirstRun
          onCreate={async (input) => {
            await saveProfileAction(input)
          }}
        />
      </main>
    )
  }

  return (
    <main className="flex h-screen min-h-0 flex-col bg-background text-foreground">
      <AppHeader
        profiles={profiles}
        activeProfileId={activeProfileId}
        workspaces={me?.workspaces || []}
        activeWorkspaceId={workspace}
        connection={connection}
        onSelectProfile={handleSelectProfile}
        onSelectWorkspace={(id) => setWorkspace(id)}
        onOpenSettings={() => setSettingsOpen(true)}
      />

      <div className="flex min-h-0 flex-1">
        {isRecordsPinned && (
          <aside className="hidden w-80 shrink-0 border-r lg:flex xl:w-96">
            <RecordsPanel
              surface="column"
              records={records}
              selectedUid={selected.uid}
              loading={loading && records.length === 0}
              loadingMore={loadingMore}
              search={search}
              filter={filter}
              totalLoaded={records.length}
              hasMore={hasMore}
              dirty={dirty}
              searchInputRef={searchInputRef}
              onSearchChange={setSearch}
              onFilterChange={setFilter}
              onSelect={selectRecord}
              onNew={newRecord}
              onLoadMore={loadMore}
            />
          </aside>
        )}

        <EditorPanel
          schema={schema}
          schemaHash={schemaHash}
          record={selected}
          files={files}
          loading={loading}
          saving={saving}
          dirty={dirty}
          hasDraft={hasDraft}
          baseUrl={activeProfile?.baseUrl || ""}
          workspace={workspace}
          activeTab={activeTab}
          relationResults={relationResults}
          relationSearch={relationSearch}
          onSave={saveRecord}
          onDiscard={discardChanges}
          onPreview={previewRecord}
          onSubmit={submitRecord}
          onPublish={publishRecord}
          onDelete={() => setPendingDelete(true)}
          onTabChange={setActiveTab}
          onUpdateField={updateField}
          onSearchRelation={searchRelation}
          onRelationSearchChange={(name, value) => setRelationSearch((state) => ({ ...state, [name]: value }))}
          onRelationAdd={setRelationValue}
          onRelationRemove={removeRelationValue}
          onFileUpload={uploadFile}
          attachFile={attachFile}
          onOpenFileBrowser={(name) => setFileBrowserField(name)}
          onDismissDraft={dismissDraft}
          onOpenRecords={() => setRecordsOpen(true)}
          recordsCount={records.length}
          recordsPinned={isRecordsPinned}
          canPin={canPin}
          onTogglePinned={(value) => setRecordsPinned(value)}
        />
      </div>

      {/* Records browser — slides in from the left as a Sheet (when not pinned) */}
      <Sheet open={recordsOpen && !isRecordsPinned} onOpenChange={setRecordsOpen}>
        <SheetContent side="left" className="w-full max-w-md p-0">
          <SheetHeader className="sr-only">
            <SheetTitle>Records</SheetTitle>
            <SheetDescription>Browse and select TYPO3 news records.</SheetDescription>
          </SheetHeader>
          <SheetBody className="flex flex-1 flex-col px-0 py-0">
            <RecordsPanel
              surface="sheet"
              records={records}
              selectedUid={selected.uid}
              loading={loading && records.length === 0}
              loadingMore={loadingMore}
              search={search}
              filter={filter}
              totalLoaded={records.length}
              hasMore={hasMore}
              dirty={dirty}
              searchInputRef={searchInputRef}
              onSearchChange={setSearch}
              onFilterChange={setFilter}
              onSelect={(record) => {
                selectRecord(record)
                setRecordsOpen(false)
              }}
              onNew={() => {
                newRecord()
                setRecordsOpen(false)
              }}
              onLoadMore={loadMore}
            />
          </SheetBody>
        </SheetContent>
      </Sheet>

      <SettingsSheet
        open={settingsOpen}
        onOpenChange={setSettingsOpen}
        profiles={profiles}
        activeProfileId={activeProfileId}
        onSetActive={(id) => {
          void handleSelectProfile(id)
          setSettingsOpen(false)
        }}
        onSave={saveProfileAction}
        onDelete={deleteProfileAction}
        appVersion={appVersion}
      />

      <FileBrowserSheet
        open={fileBrowserField !== null}
        onOpenChange={(open) => !open && setFileBrowserField(null)}
        fieldName={fileBrowserField}
        fieldLabel={fileBrowserLabel}
        files={files}
        baseUrl={activeProfile?.baseUrl || ""}
        record={selected}
        loading={loading}
        onAttach={attachFile}
        onUpload={uploadFile}
      />

      <AlertDialog open={pendingDelete} onOpenChange={setPendingDelete}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete news #{selected.uid}?</AlertDialogTitle>
            <AlertDialogDescription>
              This calls TYPO3 DataHandler. The record is moved to the recycler depending on workspace, and cannot be undone from this app.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              onClick={() => void confirmDelete()}
            >
              Delete
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <AlertDialog open={pendingSwitch !== null} onOpenChange={(open) => !open && setPendingSwitch(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Discard unsaved changes?</AlertDialogTitle>
            <AlertDialogDescription>
              You have unsaved edits in the current record. Switching will discard them.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel onClick={() => applyPendingSwitch(false)}>Keep editing</AlertDialogCancel>
            <AlertDialogAction
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              onClick={() => applyPendingSwitch(true)}
            >
              Discard
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </main>
  )
}

function extractRecords(payload: unknown): NewsRecord[] {
  if (Array.isArray(payload)) return payload as NewsRecord[]
  if (payload && typeof payload === "object" && "data" in (payload as Record<string, unknown>)) {
    const inner = (payload as { data?: unknown }).data
    if (Array.isArray(inner)) return inner as NewsRecord[]
  }
  return []
}

function errorMessage(error: unknown): string {
  return error instanceof Error ? error.message : String(error)
}
