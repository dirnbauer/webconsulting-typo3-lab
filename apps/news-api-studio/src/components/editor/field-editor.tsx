"use client"

import { type ChangeEvent } from "react"
import { FileImage, FolderOpen, ImagePlus, Search, Upload } from "lucide-react"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue, SelectViewport } from "@/components/ui/select"
import { Textarea } from "@/components/ui/textarea"
import { RichTextEditor } from "@/components/editor/rich-text-editor"
import { cn } from "@/lib/utils"
import type { FieldSchema, FileItem, FileListing, FileReferenceValue, NewsRecord, RecordOption } from "@/lib/types"
import { displayDateTime, fromDateTime, assetUrl, toArray } from "@/lib/format"
import { fileReferenceMatches, isRichTextField, valueToIds } from "@/lib/normalize"

type Props = {
  apiSearch: () => void
  attachFile: (fieldName: string, file: FileItem) => void
  baseUrl: string
  field: FieldSchema
  files: FileListing | null
  loading: boolean
  onFileUpload: (event: ChangeEvent<HTMLInputElement>, fieldName: string) => void
  onOpenFileBrowser: (fieldName: string) => void
  onRelationAdd: (field: FieldSchema, option: RecordOption) => void
  onRelationRemove: (field: FieldSchema, uid: number) => void
  onRelationSearch: (value: string) => void
  onUpdate: (field: string, value: unknown) => void
  record: NewsRecord
  relationResults: RecordOption[]
  relationSearch: string
}

const NEWS_TYPE_ITEMS: NonNullable<FieldSchema["items"]> = [
  { label: "News", value: 0 },
  { label: "Internal link", value: 1 },
  { label: "External page", value: 2 },
]
const EMPTY_SELECT_VALUE_PREFIX = "__empty-select-option-"

export function FieldEditor({
  apiSearch,
  attachFile,
  baseUrl,
  field,
  files,
  loading,
  onFileUpload,
  onOpenFileBrowser,
  onRelationAdd,
  onRelationRemove,
  onRelationSearch,
  onUpdate,
  record,
  relationResults,
  relationSearch,
}: Props) {
  const disabled = field.readOnly || field.writeable === false || loading
  const value = record[field.name]
  const richText = isRichTextField(field)
  const selectItems = getSelectItems(field)
  const span = richText || field.type === "text" || field.type === "file" ? "lg:col-span-2" : ""

  return (
    <div className={cn("space-y-2", span)}>
      <Label htmlFor={`field-${field.name}`} className="flex items-center gap-2">
        <span>{field.label}</span>
        {field.required && <Badge variant="outline">required</Badge>}
        {disabled && <Badge variant="secondary">read only</Badge>}
      </Label>

      {richText ? (
        <RichTextEditor value={String(value || "")} onChange={(next) => onUpdate(field.name, next)} disabled={disabled} />
      ) : field.type === "text" ? (
        <Textarea
          id={`field-${field.name}`}
          rows={field.rows || 5}
          value={String(value || "")}
          onChange={(event) => onUpdate(field.name, event.target.value)}
          disabled={disabled}
        />
      ) : selectItems.length > 0 ? (
        <Select
          value={getSelectRenderValue(value, selectItems)}
          onValueChange={(nextValue) => onUpdate(field.name, getSelectValue(nextValue, selectItems))}
          disabled={disabled}
        >
          <SelectTrigger id={`field-${field.name}`}>
            <SelectValue placeholder={`Select ${field.label.toLowerCase()}`} />
          </SelectTrigger>
          <SelectContent>
            <SelectViewport>
              {selectItems.map((item, index) => (
                <SelectItem key={`${getSelectItemValue(item, index)}-${item.label}`} value={getSelectItemValue(item, index)}>
                  {item.label}
                </SelectItem>
              ))}
            </SelectViewport>
          </SelectContent>
        </Select>
      ) : field.type === "boolean" ? (
        <label className="flex h-9 cursor-pointer items-center gap-2 rounded-md border bg-background px-3 text-sm">
          <input
            type="checkbox"
            checked={Boolean(Number(value || 0))}
            onChange={(event) => onUpdate(field.name, event.target.checked ? 1 : 0)}
            disabled={disabled}
            className="size-3.5 accent-primary"
          />
          {Boolean(Number(value || 0)) ? "Enabled" : "Disabled"}
        </label>
      ) : field.type === "datetime" ? (
        <Input
          id={`field-${field.name}`}
          type="datetime-local"
          value={displayDateTime(value)}
          onChange={(event) => onUpdate(field.name, fromDateTime(event.target.value))}
          disabled={disabled}
        />
      ) : field.type === "number" ? (
        <Input
          id={`field-${field.name}`}
          type="number"
          value={Number(value || 0)}
          onChange={(event) => onUpdate(field.name, Number(event.target.value))}
          disabled={disabled}
        />
      ) : field.type === "relation" ? (
        <div className="space-y-2">
          <div className="flex gap-2">
            <Input
              value={relationSearch}
              onChange={(event) => onRelationSearch(event.target.value)}
              placeholder={`Search ${field.relation?.table || "records"}`}
              disabled={disabled}
            />
            <Button type="button" variant="outline" size="icon" onClick={apiSearch} disabled={disabled} title="Search records">
              <Search className="size-4" />
            </Button>
          </div>
          <div className="flex flex-wrap gap-1.5">
            {valueToIds(value).map((uid) => (
              <Badge
                key={uid}
                variant="secondary"
                className="cursor-pointer hover:bg-destructive/20 hover:text-destructive"
                onClick={() => !disabled && onRelationRemove(field, uid)}
              >
                #{uid} ×
              </Badge>
            ))}
          </div>
          {relationResults.length > 0 && (
            <div className="grid max-h-44 gap-px overflow-auto rounded-md border bg-card p-1">
              {relationResults.map((option) => (
                <button
                  key={`${option.table}-${option.uid}`}
                  type="button"
                  className="flex items-center justify-between rounded px-2 py-1.5 text-left text-sm transition-colors hover:bg-accent"
                  onClick={() => onRelationAdd(field, option)}
                  disabled={disabled}
                >
                  <span>{option.label}</span>
                  <span className="font-mono text-xs text-muted-foreground">#{option.uid}</span>
                </button>
              ))}
            </div>
          )}
        </div>
      ) : field.type === "file" ? (
        <div className="space-y-3 rounded-md border p-3">
          <div className="grid gap-2 sm:grid-cols-2">
            {toArray<FileReferenceValue>(value).map((item, index) => (
              <div key={`${item.uid || item.uid_local || index}`} className="overflow-hidden rounded-md border bg-card">
                {item.url || item.publicUrl ? (
                  <img
                    src={assetUrl(baseUrl, String(item.url || item.publicUrl))}
                    alt={item.alternative || item.title || "Selected media"}
                    className="h-32 w-full object-cover"
                  />
                ) : (
                  <div className="flex h-32 items-center justify-center bg-muted">
                    <FileImage className="size-5 text-muted-foreground" />
                  </div>
                )}
                <div className="px-2 py-1.5">
                  <div className="truncate text-sm font-medium">
                    {item.title || item.name || `File #${item.uid || item.uid_local}`}
                  </div>
                  {(item.uid || item.uid_local) && (
                    <div className="font-mono text-[10px] text-muted-foreground">
                      uid {item.uid || item.uid_local}
                    </div>
                  )}
                </div>
              </div>
            ))}
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <Button type="button" size="sm" asChild>
              <label className="cursor-pointer">
                <Upload className="size-3.5" />
                Upload
                <input type="file" className="sr-only" accept="image/*" onChange={(event) => onFileUpload(event, field.name)} disabled={disabled} />
              </label>
            </Button>
            <Button type="button" variant="outline" size="sm" onClick={() => onOpenFileBrowser(field.name)} disabled={disabled}>
              <FolderOpen className="size-3.5" />
              Browse files
            </Button>
            {(files?.files || []).slice(0, 3).map((file) => {
              const attached = toArray<FileReferenceValue>(value).some((reference) => fileReferenceMatches(reference, file))
              return (
                <Button
                  key={file.combinedIdentifier || file.name}
                  type="button"
                  variant={attached ? "secondary" : "outline"}
                  size="sm"
                  title={attached ? "Already attached" : `Attach ${file.name}`}
                  aria-label={`${attached ? "Attached" : "Attach"} ${file.name} to ${field.label}`}
                  onClick={() => attachFile(field.name, file)}
                  disabled={disabled || !file.uid}
                  className="max-w-[180px] truncate"
                >
                  <ImagePlus className="size-3.5" />
                  {file.name}
                </Button>
              )
            })}
          </div>
        </div>
      ) : (
        <Input
          id={`field-${field.name}`}
          value={String(value ?? "")}
          onChange={(event) => onUpdate(field.name, event.target.value)}
          disabled={disabled}
        />
      )}
      {field.description && <p className="text-xs text-muted-foreground">{field.description}</p>}
    </div>
  )
}

function getSelectItems(field: FieldSchema): NonNullable<FieldSchema["items"]> {
  if (field.name === "type") {
    return NEWS_TYPE_ITEMS
  }

  return field.items && field.items.length > 0 ? field.items : []
}

function getSelectValue(value: string, items: NonNullable<FieldSchema["items"]>): string | number {
  const item = items.find((candidate, index) => getSelectItemValue(candidate, index) === value)
  if (!item) {
    return value
  }

  const rawValue = getSelectRawValue(item)
  return typeof rawValue === "number" ? rawValue : String(rawValue ?? "")
}

function getSelectRenderValue(value: unknown, items: NonNullable<FieldSchema["items"]>): string {
  const rawValue = String(value ?? "")
  const index = items.findIndex((item) => String(getSelectRawValue(item) ?? "") === rawValue)

  return index >= 0 ? getSelectItemValue(items[index], index) : rawValue
}

function getSelectItemValue(item: NonNullable<FieldSchema["items"]>[number], index: number): string {
  const rawValue = String(getSelectRawValue(item) ?? "")

  // Radix Select reserves an empty string for clearing the value / showing the placeholder.
  return rawValue === "" ? `${EMPTY_SELECT_VALUE_PREFIX}${index}__` : rawValue
}

function getSelectRawValue(item: NonNullable<FieldSchema["items"]>[number]): string | number | null | undefined {
  return (item as { value?: string | number | null }).value
}
