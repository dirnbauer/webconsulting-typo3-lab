"use client"

import { type RefObject, useMemo } from "react"
import { ChevronRight, Loader2, Plus, Search } from "lucide-react"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Skeleton } from "@/components/ui/skeleton"
import { ScrollArea } from "@/components/ui/scroll-area"
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { cn } from "@/lib/utils"
import type { NewsRecord, RecordFilter } from "@/lib/types"

type Props = {
  records: NewsRecord[]
  selectedUid: number | undefined
  loading: boolean
  loadingMore: boolean
  search: string
  filter: RecordFilter
  totalLoaded: number
  hasMore: boolean
  dirty: boolean
  searchInputRef?: RefObject<HTMLInputElement | null>
  onSearchChange: (value: string) => void
  onFilterChange: (filter: RecordFilter) => void
  onSelect: (record: NewsRecord) => void
  onNew: () => void
  onLoadMore: () => void
  surface?: "column" | "sheet"
}

export function RecordsPanel({
  records,
  selectedUid,
  loading,
  loadingMore,
  search,
  filter,
  hasMore,
  dirty,
  searchInputRef,
  onSearchChange,
  onFilterChange,
  onSelect,
  onNew,
  onLoadMore,
  surface = "column",
}: Props) {
  const filtered = useMemo(() => {
    const needle = search.trim().toLowerCase()
    return records.filter((record) => {
      if (filter === "visible" && Number(record.hidden) === 1) return false
      if (filter === "hidden" && Number(record.hidden) !== 1) return false
      if (!needle) return true
      const haystack = `${record.uid ?? ""} ${record.title ?? ""}`.toLowerCase()
      return haystack.includes(needle)
    })
  }, [filter, records, search])

  const isSheet = surface === "sheet"

  return (
    <aside className={cn("flex h-full min-w-0 flex-col bg-card text-card-foreground", isSheet && "pt-12")}>
      <div className="flex items-center justify-between gap-3 border-b border-border/70 bg-card/95 px-4 py-3">
        <div>
          <h2 className="text-base font-semibold leading-none tracking-tight">Records</h2>
          <p className="mt-1.5 text-xs text-muted-foreground">
            {loading ? "Loading…" : `${filtered.length} of ${records.length}`}
          </p>
        </div>
        <Button type="button" size="sm" onClick={onNew} title="New record (⌘N)">
          <Plus className="size-3.5" />
          New
        </Button>
      </div>

      <div className="space-y-3 border-b border-border/70 bg-primary/5 px-4 py-3">
        <div className="relative">
          <Search className="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
          <Input
            ref={searchInputRef}
            value={search}
            onChange={(event) => onSearchChange(event.target.value)}
            placeholder="Search records…"
            className="h-9 bg-background pl-8 shadow-sm"
          />
        </div>
        <Tabs value={filter} onValueChange={(value) => onFilterChange(value as RecordFilter)}>
          <TabsList className="grid h-9 w-full grid-cols-3 bg-background/80 shadow-sm">
            <TabsTrigger value="all">All</TabsTrigger>
            <TabsTrigger value="visible">Visible</TabsTrigger>
            <TabsTrigger value="hidden">Hidden</TabsTrigger>
          </TabsList>
        </Tabs>
      </div>

      <ScrollArea className="flex-1">
        <div className="space-y-1 p-2">
          {loading && records.length === 0 ? (
            Array.from({ length: 6 }).map((_, index) => (
              <div key={index} className="rounded-md border p-3">
                <Skeleton className="h-3.5 w-3/4" />
                <Skeleton className="mt-2 h-3 w-1/2" />
              </div>
            ))
          ) : filtered.length === 0 ? (
            <EmptyState onNew={onNew} hasFilter={filter !== "all" || search.length > 0} />
          ) : (
            filtered.map((record) => {
              const uid = Number(record.uid || 0)
              const selected = selectedUid === uid
              const hidden = Number(record.hidden) === 1
              return (
                <button
                  key={uid || `new-${record.title}`}
                  type="button"
                  onClick={() => onSelect(record)}
                  data-state={selected ? "active" : undefined}
                  className={cn(
                    "block w-full rounded-lg border border-transparent px-3 py-2.5 text-left transition-all",
                    "hover:border-border/80 hover:bg-muted/70 hover:text-foreground",
                    selected && "border-primary/25 bg-primary/10 text-foreground shadow-sm",
                  )}
                >
                  <div className="flex items-center gap-2">
                    <span className="truncate text-sm font-medium">
                      {String(record.title || `News #${uid}`)}
                    </span>
                    {selected && dirty && (
                      <span className="size-1.5 shrink-0 rounded-full bg-amber-500" aria-label="Unsaved changes" />
                    )}
                  </div>
                  <div className="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                    <span className="font-mono">#{uid || "new"}</span>
                    <span>·</span>
                    <span className="font-mono">PID {Number(record.pid || 0)}</span>
                    {hidden && (
                      <Badge variant="secondary" className="ml-1 h-4 border border-border/50 px-1.5 text-[10px]">
                        hidden
                      </Badge>
                    )}
                  </div>
                </button>
              )
            })
          )}

          {hasMore && (
            <Button
              type="button"
              variant="ghost"
              size="sm"
              className="w-full justify-center"
              onClick={onLoadMore}
              disabled={loadingMore}
            >
              {loadingMore ? <Loader2 className="size-3.5 animate-spin" /> : <ChevronRight className="size-3.5" />}
              Load more
            </Button>
          )}
        </div>
      </ScrollArea>
    </aside>
  )
}

function EmptyState({ onNew, hasFilter }: { onNew: () => void; hasFilter: boolean }) {
  return (
    <div className="px-2 py-10 text-center">
      <p className="text-sm text-muted-foreground">
        {hasFilter ? "No records match your filters." : "No records loaded."}
      </p>
      {!hasFilter && (
        <Button type="button" size="sm" variant="outline" className="mt-3" onClick={onNew}>
          <Plus className="size-3.5" />
          Create the first one
        </Button>
      )}
    </div>
  )
}
