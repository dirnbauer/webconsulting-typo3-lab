# PULSE - TYPO3 Native Newsroom

PULSE is a native-rendered editorial cockpit for TYPO3 EXT:news, built with
[Vercel Labs Native SDK](https://github.com/vercel-labs/native). Zig owns the
state and declarative `.native` markup draws the interface. There is no
Electron renderer, browser, or WebView in the app binary.

The current product slice includes:

- a calm, guided Story → Image → Review workflow that reveals one task at a
  time;
- a familiar story sidebar and plain-language guidance for first-time users;
- a focused writing canvas with workspace review and publish transitions;
- a one-click Visual Lab for background removal, subject crop, relighting,
  upscaling and accessible alt text;
- a non-destructive image model: the original FAL asset is always retained;
- a restrained neutral palette with a single violet-blue accent and semantic
  status colors;
- native search, context menus, scrolling, command dialog and menu-bar item;
- deterministic tests and Native SDK screenshot automation.

Run it:

```sh
cd apps/news-native-studio
native dev -Dautomation=true
```

Validate it:

```sh
native check
native test
native build -Dautomation=true
```

The current slice uses representative newsroom data. The production adapter
will connect `fx.fetch` to the existing `/studio/me`, schema, news, FAL,
preview, review and publish endpoints. Image operations are modeled as
one-click, non-destructive derivatives; the processing provider and FAL write
back are the next backend seam.
