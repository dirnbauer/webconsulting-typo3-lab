"use client"

import { useEffect, useState } from "react"
import { EditorContent, useEditor, type Editor } from "@tiptap/react"
import StarterKit from "@tiptap/starter-kit"
import Link from "@tiptap/extension-link"
import Image from "@tiptap/extension-image"
import Underline from "@tiptap/extension-underline"
import Subscript from "@tiptap/extension-subscript"
import Superscript from "@tiptap/extension-superscript"
import { Table } from "@tiptap/extension-table"
import { TableRow } from "@tiptap/extension-table-row"
import { TableCell } from "@tiptap/extension-table-cell"
import { TableHeader } from "@tiptap/extension-table-header"
import {
  Bold,
  Code2,
  Italic,
  Link as LinkIcon,
  Link2Off,
  List,
  ListOrdered,
  Minus,
  Quote,
  Redo2,
  RemoveFormatting,
  Strikethrough,
  Subscript as SubscriptIcon,
  Superscript as SuperscriptIcon,
  Table as TableIcon,
  Type,
  Underline as UnderlineIcon,
  Undo2,
} from "lucide-react"

import { Button } from "@/components/ui/button"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip"
import { Textarea } from "@/components/ui/textarea"
import { cn } from "@/lib/utils"

type Props = {
  disabled?: boolean
  onChange: (value: string) => void
  value: string
}

/**
 * TipTap editor configured to mirror TYPO3 v14's default CKEditor 5 toolbar
 * (`Configuration/RTE/Default.yaml`):
 *
 *   undo, redo · heading · bold, italic, underline, subscript, superscript ·
 *   link, unlink · bulletedList, numberedList · blockQuote, removeFormat,
 *   sourceEditing · insertTable, specialChar
 */
export function RichTextEditor({ disabled, onChange, value }: Props) {
  const [sourceMode, setSourceMode] = useState(false)
  const [sourceDraft, setSourceDraft] = useState(value || "")

  const editor = useEditor({
    extensions: [
      StarterKit.configure({ link: false }),
      Link.configure({ openOnClick: false, autolink: true }),
      Image.configure({ inline: false }),
      Underline,
      Subscript,
      Superscript,
      Table.configure({ resizable: true }),
      TableRow,
      TableHeader,
      TableCell,
    ],
    content: value || "",
    editable: !disabled,
    immediatelyRender: false,
    onUpdate: ({ editor }) => onChange(editor.getHTML()),
    editorProps: {
      attributes: {
        class: "ProseMirror min-h-[18rem] focus:outline-none",
      },
    },
  })

  useEffect(() => {
    if (!editor) return
    if (sourceMode) return
    if (editor.getHTML() !== (value || "")) {
      editor.commands.setContent(value || "", { emitUpdate: false })
    }
  }, [editor, value, sourceMode])

  if (!editor) {
    return <div className="h-72 rounded-md border bg-muted/40" />
  }

  function commitSource() {
    if (!editor) return
    editor.commands.setContent(sourceDraft || "", { emitUpdate: true })
    onChange(sourceDraft || "")
  }

  return (
    <div className={cn("overflow-hidden rounded-md border bg-background", disabled && "opacity-70")}>
      <Toolbar
        editor={editor}
        disabled={disabled}
        sourceMode={sourceMode}
        onToggleSource={() => {
          if (!sourceMode) {
            setSourceDraft(editor.getHTML())
            setSourceMode(true)
          } else {
            commitSource()
            setSourceMode(false)
          }
        }}
      />

      {sourceMode ? (
        <Textarea
          value={sourceDraft}
          onChange={(event) => setSourceDraft(event.target.value)}
          onBlur={commitSource}
          disabled={disabled}
          rows={14}
          spellCheck={false}
          className="min-h-72 rounded-none border-0 border-t font-mono text-[12.5px] leading-relaxed focus-visible:ring-0"
        />
      ) : (
        <EditorContent editor={editor} className="rte-editor border-t bg-background px-3 py-3" />
      )}
    </div>
  )
}

// ---------- toolbar ----------

function Toolbar({
  editor,
  disabled,
  sourceMode,
  onToggleSource,
}: {
  editor: Editor
  disabled?: boolean
  sourceMode: boolean
  onToggleSource: () => void
}) {
  const can = !disabled && !sourceMode

  return (
    <div className="flex flex-wrap items-center gap-0.5 bg-muted/40 px-1.5 py-1.5">
      <ToolGroup>
        <ToolBtn
          label="Undo"
          shortcut="⌘Z"
          icon={Undo2}
          onClick={() => editor.chain().focus().undo().run()}
          disabled={!can || !editor.can().undo()}
        />
        <ToolBtn
          label="Redo"
          shortcut="⇧⌘Z"
          icon={Redo2}
          onClick={() => editor.chain().focus().redo().run()}
          disabled={!can || !editor.can().redo()}
        />
      </ToolGroup>
      <Divider />

      <HeadingMenu editor={editor} disabled={!can} />
      <Divider />

      <ToolGroup>
        <ToolBtn
          label="Bold"
          shortcut="⌘B"
          icon={Bold}
          active={editor.isActive("bold")}
          onClick={() => editor.chain().focus().toggleBold().run()}
          disabled={!can}
        />
        <ToolBtn
          label="Italic"
          shortcut="⌘I"
          icon={Italic}
          active={editor.isActive("italic")}
          onClick={() => editor.chain().focus().toggleItalic().run()}
          disabled={!can}
        />
        <ToolBtn
          label="Underline"
          shortcut="⌘U"
          icon={UnderlineIcon}
          active={editor.isActive("underline")}
          onClick={() => editor.chain().focus().toggleUnderline().run()}
          disabled={!can}
        />
        <ToolBtn
          label="Strikethrough"
          icon={Strikethrough}
          active={editor.isActive("strike")}
          onClick={() => editor.chain().focus().toggleStrike().run()}
          disabled={!can}
        />
        <ToolBtn
          label="Subscript"
          icon={SubscriptIcon}
          active={editor.isActive("subscript")}
          onClick={() => editor.chain().focus().toggleSubscript().run()}
          disabled={!can}
        />
        <ToolBtn
          label="Superscript"
          icon={SuperscriptIcon}
          active={editor.isActive("superscript")}
          onClick={() => editor.chain().focus().toggleSuperscript().run()}
          disabled={!can}
        />
      </ToolGroup>
      <Divider />

      <ToolGroup>
        <LinkButton editor={editor} disabled={!can} />
        <ToolBtn
          label="Unlink"
          icon={Link2Off}
          onClick={() => editor.chain().focus().unsetLink().run()}
          disabled={!can || !editor.isActive("link")}
        />
      </ToolGroup>
      <Divider />

      <ToolGroup>
        <ToolBtn
          label="Bulleted list"
          icon={List}
          active={editor.isActive("bulletList")}
          onClick={() => editor.chain().focus().toggleBulletList().run()}
          disabled={!can}
        />
        <ToolBtn
          label="Numbered list"
          icon={ListOrdered}
          active={editor.isActive("orderedList")}
          onClick={() => editor.chain().focus().toggleOrderedList().run()}
          disabled={!can}
        />
      </ToolGroup>
      <Divider />

      <ToolGroup>
        <ToolBtn
          label="Block quote"
          icon={Quote}
          active={editor.isActive("blockquote")}
          onClick={() => editor.chain().focus().toggleBlockquote().run()}
          disabled={!can}
        />
        <ToolBtn
          label="Horizontal rule"
          icon={Minus}
          onClick={() => editor.chain().focus().setHorizontalRule().run()}
          disabled={!can}
        />
        <ToolBtn
          label="Remove format"
          icon={RemoveFormatting}
          onClick={() => editor.chain().focus().unsetAllMarks().clearNodes().run()}
          disabled={!can}
        />
        <ToolBtn
          label={sourceMode ? "Switch to rich text" : "Source code"}
          icon={Code2}
          active={sourceMode}
          onClick={onToggleSource}
          disabled={disabled}
        />
      </ToolGroup>
      <Divider />

      <ToolGroup>
        <ToolBtn
          label="Insert table"
          icon={TableIcon}
          onClick={() => editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()}
          disabled={!can}
        />
        <SpecialCharMenu editor={editor} disabled={!can} />
      </ToolGroup>
    </div>
  )
}

function ToolGroup({ children }: { children: React.ReactNode }) {
  return <div className="flex items-center gap-0.5">{children}</div>
}

function Divider() {
  return <span className="mx-1 h-5 w-px bg-border" aria-hidden />
}

function ToolBtn({
  label,
  shortcut,
  icon: Icon,
  active,
  disabled,
  onClick,
}: {
  label: string
  shortcut?: string
  icon: React.ComponentType<{ className?: string }>
  active?: boolean
  disabled?: boolean
  onClick: () => void
}) {
  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <Button
          type="button"
          size="icon-sm"
          variant={active ? "secondary" : "ghost"}
          onClick={onClick}
          disabled={disabled}
          aria-label={label}
          aria-pressed={active}
        >
          <Icon className="size-4" />
        </Button>
      </TooltipTrigger>
      <TooltipContent>
        {label}
        {shortcut && <span className="ml-2 text-muted-foreground">{shortcut}</span>}
      </TooltipContent>
    </Tooltip>
  )
}

const HEADINGS = [
  { level: 0, label: "Paragraph" },
  { level: 2, label: "Heading 2" },
  { level: 3, label: "Heading 3" },
  { level: 4, label: "Heading 4" },
  { level: 5, label: "Heading 5" },
] as const

function HeadingMenu({ editor, disabled }: { editor: Editor; disabled?: boolean }) {
  const current = HEADINGS.find((item) =>
    item.level === 0 ? editor.isActive("paragraph") : editor.isActive("heading", { level: item.level }),
  )

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button type="button" size="sm" variant="ghost" disabled={disabled} className="h-7 gap-1.5 px-2">
          <Type className="size-3.5" />
          <span className="text-xs">{current?.label || "Paragraph"}</span>
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="start" className="min-w-44">
        <DropdownMenuLabel>Block format</DropdownMenuLabel>
        <DropdownMenuSeparator />
        {HEADINGS.map((item) => (
          <DropdownMenuItem
            key={item.level}
            onSelect={() => {
              if (item.level === 0) editor.chain().focus().setParagraph().run()
              else editor.chain().focus().toggleHeading({ level: item.level as 2 | 3 | 4 | 5 }).run()
            }}
          >
            {item.label}
          </DropdownMenuItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}

function LinkButton({ editor, disabled }: { editor: Editor; disabled?: boolean }) {
  return (
    <ToolBtn
      label={editor.isActive("link") ? "Edit link" : "Insert link"}
      icon={LinkIcon}
      active={editor.isActive("link")}
      disabled={disabled}
      onClick={() => {
        if (typeof window === "undefined") return
        const previous = (editor.getAttributes("link") as { href?: string }).href
        const url = window.prompt("URL", previous || "https://")
        if (url === null) return
        if (url === "") {
          editor.chain().focus().unsetLink().run()
          return
        }
        editor.chain().focus().extendMarkRange("link").setLink({ href: url }).run()
      }}
    />
  )
}

const SPECIAL_CHARS = [
  "©", "®", "™", "§", "¶", "†", "‡",
  "—", "–", "…", "·", "•",
  "«", "»", "‹", "›", "“", "”", "‘", "’",
  "€", "£", "¥", "¢",
  "°", "±", "×", "÷", "≈", "≠", "≤", "≥",
  "←", "→", "↑", "↓", "⇒", "⇐",
  "α", "β", "γ", "δ", "π", "Ω", "μ",
]

function SpecialCharMenu({ editor, disabled }: { editor: Editor; disabled?: boolean }) {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button type="button" size="icon-sm" variant="ghost" disabled={disabled} aria-label="Special character">
          <span className="font-mono text-[13px] leading-none">Ω</span>
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="grid w-72 grid-cols-8 gap-1 p-2">
        {SPECIAL_CHARS.map((char) => (
          <button
            key={char}
            type="button"
            className="flex size-7 items-center justify-center rounded-sm font-mono text-sm transition-colors hover:bg-accent"
            onClick={() => editor.chain().focus().insertContent(char).run()}
          >
            {char}
          </button>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
