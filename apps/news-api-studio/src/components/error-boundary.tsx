"use client"

import { Component, type ReactNode } from "react"
import { AlertTriangle, RotateCcw } from "lucide-react"

import { Button } from "@/components/ui/button"

type Props = { children: ReactNode }
type State = { error: Error | null }

/**
 * Top-level boundary so a render error in any panel surfaces a recovery UI
 * instead of blanking the entire app.
 */
export class ErrorBoundary extends Component<Props, State> {
  state: State = { error: null }

  static getDerivedStateFromError(error: Error): State {
    return { error }
  }

  componentDidCatch(error: Error) {
    if (typeof console !== "undefined") {
      console.error("[news-api-studio] render error", error)
    }
  }

  reset = () => this.setState({ error: null })

  render() {
    const { error } = this.state
    if (!error) return this.props.children

    return (
      <div className="flex min-h-screen items-center justify-center bg-background p-6">
        <div className="w-full max-w-md rounded-lg border border-border/60 bg-card p-6 text-card-foreground shadow-sm">
          <div className="mb-3 flex items-center gap-2 text-destructive">
            <AlertTriangle className="size-5" />
            <h1 className="text-base font-semibold">Something went wrong</h1>
          </div>
          <p className="mb-4 text-sm text-muted-foreground">
            The studio hit an unexpected render error. You can retry without losing any unsaved drafts (drafts are kept in browser storage).
          </p>
          <pre className="mb-4 max-h-40 overflow-auto rounded-md bg-muted p-3 font-mono text-xs leading-snug text-muted-foreground">
            {error.message}
          </pre>
          <Button onClick={this.reset}>
            <RotateCcw className="size-4" />
            Try again
          </Button>
        </div>
      </div>
    )
  }
}
