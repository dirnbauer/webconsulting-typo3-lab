"use client"

import { type ChangeEvent, type FormEvent, useMemo } from "react"
import { Check, Eye, FileText, ListChecks, PanelLeft, PanelLeftClose, RotateCcw, Save, Send, Trash2 } from "lucide-react"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { ScrollArea } from "@/components/ui/scroll-area"
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip"
import { FieldEditor } from "@/components/editor/field-editor"
import type { FieldSchema, FileItem, FileListing, FormSchema, NewsRecord, RecordOption } from "@/lib/types"

type Props = {
  schema: FormSchema | null
  schemaHash: string
  record: NewsRecord
  files: FileListing | null
  loading: boolean
  saving: boolean
  dirty: boolean
  hasDraft: boolean
  baseUrl: string
  workspace: number | null
  activeTab: string
  relationResults: Record<string, RecordOption[]>
  relationSearch: Record<string, string>
  onSave: (event?: FormEvent) => void
  onDiscard: () => void
  onPreview: () => void
  onSubmit: () => void
  onPublish: () => void
  onDelete: () => void
  onTabChange: (tab: string) => void
  onUpdateField: (field: string, value: unknown) => void
  onSearchRelation: (field: FieldSchema) => void
  onRelationSearchChange: (fieldName: string, value: string) => void
  onRelationAdd: (field: FieldSchema, option: RecordOption) => void
  onRelationRemove: (field: FieldSchema, uid: number) => void
  onFileUpload: (event: ChangeEvent<HTMLInputElement>, fieldName: string) => void
  attachFile: (fieldName: string, file: FileItem) => void
  onOpenFileBrowser: (fieldName: string) => void
  onDismissDraft: () => void
  onOpenRecords: () => void
  recordsCount: number
  recordsPinned: boolean
  canPin: boolean
  onTogglePinned: (value: boolean) => void
}

export function EditorPanel(props: Props) {
  const {
    schema,
    schemaHash,
    record,
    files,
    loading,
    saving,
    dirty,
    hasDraft,
    baseUrl,
    workspace,
    activeTab,
    relationResults,
    relationSearch,
    onSave,
    onDiscard,
    onPreview,
    onSubmit,
    onPublish,
    onDelete,
    onTabChange,
    onUpdateField,
    onSearchRelation,
    onRelationSearchChange,
    onRelationAdd,
    onRelationRemove,
    onFileUpload,
    attachFile,
    onOpenFileBrowser,
    onDismissDraft,
    onOpenRecords,
    recordsCount,
    recordsPinned,
    canPin,
    onTogglePinned,
  } = props

  const activeFields = useMemo(
    () => schema?.tabs.find((tab) => tab.id === activeTab)?.fields || [],
    [schema, activeTab],
  )

  const isExisting = Boolean(record.uid)
  const recordTitle = isExisting ? `Edit news #${record.uid}` : "Create news"

  if (!schema) {
    return (
      <section className="flex flex-1 items-center justify-center bg-background">
        <div className="rounded-lg border border-dashed bg-card px-6 py-8 text-sm text-muted-foreground">
          {loading ? "Loading schema…" : "Schema not loaded yet."}
        </div>
      </section>
    )
  }

  return (
    <section className="flex h-full min-w-0 flex-1 flex-col bg-background">
      <header className="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3 sm:px-6">
        <div className="flex min-w-0 items-center gap-3">
          {!recordsPinned && (
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={onOpenRecords}
              className="shrink-0 gap-2"
              title="Browse records"
            >
              <ListChecks className="size-3.5" />
              <span className="hidden sm:inline">Records</span>
              <Badge variant="secondary" className="ml-0.5 px-1.5 text-[10px]">
                {recordsCount}
              </Badge>
            </Button>
          )}

          {/* Records column toggle — sidebar-style icon (lg+ only).
              Icon mirrors current state: PanelLeftClose when pinned/visible,
              PanelLeft when collapsed. */}
          {canPin && (
            <Tooltip>
              <TooltipTrigger asChild>
                <Button
                  type="button"
                  variant={recordsPinned ? "secondary" : "outline"}
                  size="icon-sm"
                  onClick={() => onTogglePinned(!recordsPinned)}
                  aria-label={recordsPinned ? "Hide records list" : "Show records list as permanent column"}
                  aria-pressed={recordsPinned}
                  className="hidden shrink-0 lg:inline-flex"
                >
                  {recordsPinned ? <PanelLeftClose className="size-4" /> : <PanelLeft className="size-4" />}
                </Button>
              </TooltipTrigger>
              <TooltipContent>
                {recordsPinned ? "Hide records list" : "Show records list as permanent column"}
              </TooltipContent>
            </Tooltip>
          )}

          <div className="min-w-0">
            <div className="flex items-center gap-2">
              <h2 className="truncate text-base font-semibold">
                {isExisting ? String(record.title || recordTitle) : recordTitle}
              </h2>
              {dirty && <Badge variant="secondary">Unsaved</Badge>}
            </div>
            <p className="mt-0.5 text-xs text-muted-foreground">
              TCA-generated form{schemaHash ? ` · ${schemaHash.slice(0, 8)}` : ""}
            </p>
          </div>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          {dirty && (
            <Button type="button" variant="ghost" size="sm" onClick={onDiscard} disabled={loading || saving}>
              <RotateCcw className="size-3.5" />
              Discard
            </Button>
          )}
          <Button type="button" variant="outline" size="sm" onClick={onPreview} disabled={!isExisting || loading}>
            <Eye className="size-3.5" />
            Preview
          </Button>
          <Button type="button" variant="outline" size="sm" onClick={onSubmit} disabled={!isExisting || workspace === 0 || loading}>
            <Send className="size-3.5" />
            Submit
          </Button>
          <Button type="button" variant="outline" size="sm" onClick={onPublish} disabled={!isExisting || workspace === 0 || loading}>
            <Check className="size-3.5" />
            Publish
          </Button>
          <Button type="button" variant="destructive" size="sm" onClick={onDelete} disabled={!isExisting || loading}>
            <Trash2 className="size-3.5" />
            Delete
          </Button>
          <Button type="button" size="sm" onClick={() => onSave()} disabled={saving || loading} title="Save (⌘S)">
            <Save className="size-3.5" />
            {saving ? "Saving…" : "Save"}
          </Button>
        </div>
      </header>

      {hasDraft && (
        <div className="flex items-center justify-between gap-3 border-b bg-amber-50 px-6 py-2 text-xs text-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
          <span className="flex items-center gap-1.5">
            <FileText className="size-3.5" />
            Draft restored from local storage.
          </span>
          <Button type="button" size="xs" variant="ghost" onClick={onDismissDraft}>
            Discard draft
          </Button>
        </div>
      )}

      <Tabs value={activeTab} onValueChange={onTabChange} className="flex min-h-0 flex-1 flex-col gap-0">
        <div className="border-b px-4 py-2">
          <TabsList className="h-8">
            {schema.tabs.map((tab) => (
              <TabsTrigger key={tab.id} value={tab.id}>
                {tab.label}
              </TabsTrigger>
            ))}
          </TabsList>
        </div>

        <ScrollArea className="flex-1">
          <form
            onSubmit={(event) => {
              event.preventDefault()
              onSave(event)
            }}
            className="px-4 py-5 sm:px-6"
          >
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
              {activeFields.map((fieldName) => {
                const field = schema.fields[fieldName]
                if (!field) return null
                return (
                  <FieldEditor
                    key={field.name}
                    apiSearch={() => onSearchRelation(field)}
                    attachFile={attachFile}
                    baseUrl={baseUrl}
                    files={files}
                    field={field}
                    loading={loading}
                    onFileUpload={onFileUpload}
                    onOpenFileBrowser={onOpenFileBrowser}
                    onRelationAdd={onRelationAdd}
                    onRelationRemove={onRelationRemove}
                    onRelationSearch={(value) => onRelationSearchChange(field.name, value)}
                    onUpdate={onUpdateField}
                    record={record}
                    relationResults={relationResults[field.name] || []}
                    relationSearch={relationSearch[field.name] || ""}
                  />
                )
              })}
            </div>
            <button type="submit" hidden tabIndex={-1} aria-hidden />
          </form>
        </ScrollArea>
      </Tabs>
    </section>
  )
}
