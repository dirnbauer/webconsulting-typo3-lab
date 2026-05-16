"use client"

import { type ChangeEvent } from "react"
import { FileImage, FolderOpen, ImagePlus, Upload } from "lucide-react"

import { Button } from "@/components/ui/button"
import { ScrollArea } from "@/components/ui/scroll-area"
import { Sheet, SheetBody, SheetContent, SheetDescription, SheetHeader, SheetTitle } from "@/components/ui/sheet"
import { assetUrl, toArray } from "@/lib/format"
import { fileReferenceMatches } from "@/lib/normalize"
import type { FileItem, FileListing, FileReferenceValue, NewsRecord } from "@/lib/types"

type Props = {
  open: boolean
  onOpenChange: (open: boolean) => void
  fieldName: string | null
  fieldLabel: string | null
  files: FileListing | null
  baseUrl: string
  record: NewsRecord
  loading: boolean
  onAttach: (fieldName: string, file: FileItem) => void
  onUpload: (event: ChangeEvent<HTMLInputElement>, fieldName: string) => void
}

export function FileBrowserSheet({
  open,
  onOpenChange,
  fieldName,
  fieldLabel,
  files,
  baseUrl,
  record,
  loading,
  onAttach,
  onUpload,
}: Props) {
  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="max-w-lg">
        <SheetHeader>
          <SheetTitle>Browse files</SheetTitle>
          <SheetDescription>
            Attach to <strong>{fieldLabel || "field"}</strong> · <span className="font-mono">{files?.current?.combinedIdentifier || "—"}</span>
          </SheetDescription>
        </SheetHeader>

        <SheetBody className="space-y-4">
          <div className="flex flex-wrap items-center gap-2">
            <label className="inline-flex h-8 cursor-pointer items-center gap-1.5 rounded-md bg-primary px-2.5 text-sm font-medium text-primary-foreground hover:bg-primary/90">
              <Upload className="size-4" />
              Upload to current folder
              <input
                type="file"
                className="sr-only"
                accept="image/*"
                disabled={!fieldName || loading}
                onChange={(event) => fieldName && onUpload(event, fieldName)}
              />
            </label>
            <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
              <FolderOpen className="size-3.5" />
              {files?.current?.name || "No folder loaded"}
            </span>
          </div>

          <ScrollArea className="h-[calc(100vh-15rem)]">
            <div className="grid gap-2 pr-2">
              {(files?.files || []).map((file) => {
                const attached = fieldName ? toArray<FileReferenceValue>(record[fieldName]).some((reference) => fileReferenceMatches(reference, file)) : false
                return (
                  <div key={file.combinedIdentifier || file.name} className="flex items-center gap-3 rounded-md border border-border/60 bg-card p-2">
                    <div className="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-md bg-muted/40 text-primary">
                      {file.publicUrl && file.mimeType.startsWith("image/") ? (
                        <img src={assetUrl(baseUrl, file.publicUrl)} alt="" className="size-full object-cover" />
                      ) : (
                        <FileImage className="size-5 text-muted-foreground" />
                      )}
                    </div>
                    <div className="min-w-0 flex-1">
                      <div className="truncate text-sm font-medium">{file.name}</div>
                      <div className="text-[11px] text-muted-foreground">
                        <span className="uppercase">{file.extension}</span> · {Math.round(file.size / 1024)} KB
                      </div>
                    </div>
                    <Button
                      type="button"
                      size="sm"
                      variant={attached ? "secondary" : "outline"}
                      title={attached ? "Already attached" : `Attach ${file.name}`}
                      aria-label={`${attached ? "Attached" : "Attach"} ${file.name} to ${fieldLabel || fieldName}`}
                      onClick={() => fieldName && onAttach(fieldName, file)}
                      disabled={!fieldName || !file.uid || loading}
                    >
                      <ImagePlus className="size-3.5" />
                      {attached ? "Attached" : "Attach"}
                    </Button>
                  </div>
                )
              })}
              {(!files || files.files.length === 0) && (
                <div className="rounded-md border border-dashed border-border/60 px-3 py-8 text-center text-sm text-muted-foreground">
                  No files in this folder.
                </div>
              )}
            </div>
          </ScrollArea>
        </SheetBody>
      </SheetContent>
    </Sheet>
  )
}
