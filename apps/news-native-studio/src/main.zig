//! PULSE: a native editorial cockpit for TYPO3 news and visual assets.

const std = @import("std");
const runner = @import("runner");
const native_sdk = @import("native_sdk");

pub const panic = std.debug.FullPanic(native_sdk.debug.capturePanic);
const canvas = native_sdk.canvas;
const geometry = native_sdk.geometry;

const canvas_label = "pulse-canvas";
const window_width: f32 = 1440;
const window_height: f32 = 920;
const app_permissions = [_][]const u8{ native_sdk.security.permission_command, native_sdk.security.permission_view };
const shell_views = [_]native_sdk.ShellView{
    .{ .label = canvas_label, .kind = .gpu_surface, .fill = true, .role = "TYPO3 editorial canvas", .accessibility_label = "PULSE Newsroom", .gpu_backend = .metal, .gpu_pixel_format = .bgra8_unorm, .gpu_present_mode = .timer, .gpu_alpha_mode = .@"opaque", .gpu_color_space = .srgb, .gpu_vsync = true },
};
const shell_windows = [_]native_sdk.ShellWindow{.{
    .label = "main",
    .title = "PULSE - TYPO3 Newsroom",
    .width = window_width,
    .height = window_height,
    .min_width = 1120,
    .min_height = 720,
    .titlebar = .hidden_inset_tall,
    .restore_state = true,
    .views = &shell_views,
}};
const shell_scene: native_sdk.ShellConfig = .{ .windows = &shell_windows };

pub const StoryState = enum { draft, review, scheduled, live };
pub const Lens = enum { write, visual, review };
pub const ImageTool = enum { original, background_removed, subject_crop, relit, upscaled };

pub const Story = struct {
    id: usize,
    eyebrow: []const u8,
    title: []const u8,
    brief: []const u8,
    body: []const u8,
    state: StoryState,
    score: u8,
    signal: []const u8,
    updated: []const u8,
};

pub const StoryView = struct {
    id: usize,
    eyebrow: []const u8,
    title: []const u8,
    state: StoryState,
    score: u8,
    signal: []const u8,
    selected: bool,
};

pub const Msg = union(enum) {
    select_story: usize,
    set_lens: Lens,
    show_write,
    show_visual,
    show_review,
    next_step,
    previous_step,
    search_edit: canvas.TextInputEvent,
    title_edit: canvas.TextInputEvent,
    brief_edit: canvas.TextInputEvent,
    body_edit: canvas.TextInputEvent,
    alt_edit: canvas.TextInputEvent,
    apply_image_tool: ImageTool,
    image_remove_background,
    image_subject_crop,
    image_relit,
    image_upscale,
    undo_image,
    save,
    submit_review,
    publish,
    open_commands,
    close_commands,
    toggle_insights,
    new_story,

    pub const view_unbound = .{ "set_lens", "apply_image_tool", "toggle_insights" };
};

pub const Model = struct {
    stories: [6]Story = .{
        .{ .id = 0, .eyebrow = "CULTURE", .title = "The city that learned to listen", .brief = "A summer festival turns an abandoned railway station into a public listening room.", .body = "At dusk, the old railway station begins to hum. Not with trains, but with the recorded memories of people who once crossed its platforms.\n\nThe installation is part archive, part instrument, and entirely alive.", .state = .review, .score = 92, .signal = "HIGH SIGNAL", .updated = "edited 4 min ago" },
        .{ .id = 1, .eyebrow = "CLIMATE", .title = "A forest grown by algorithms", .brief = "Foresters are using local data to design a woodland for the next hundred years.", .body = "The new forest begins as a model. Soil, wind and heat are mapped before the first tree reaches the ground.", .state = .draft, .score = 68, .signal = "NEEDS MEDIA", .updated = "edited 18 min ago" },
        .{ .id = 2, .eyebrow = "DESIGN", .title = "When public space moves", .brief = "Modular street furniture changes the rhythm of a small town square.", .body = "Every morning the square is different. Benches rotate toward the sun, tables collect around the market, and the stage unfolds at night.", .state = .scheduled, .score = 100, .signal = "READY", .updated = "tomorrow at 08:30" },
        .{ .id = 3, .eyebrow = "PEOPLE", .title = "Seven kitchens, one long table", .brief = "A neighborhood dinner becomes an unlikely civic institution.", .body = "The table is built from doors donated by the houses on the street. It now seats eighty people.", .state = .live, .score = 100, .signal = "LIVE", .updated = "published 1 h ago" },
        .{ .id = 4, .eyebrow = "TECHNOLOGY", .title = "The archive answers back", .brief = "A museum experiments with a conversational interface to its collection.", .body = "Visitors no longer begin with a search box. They begin with a question, a memory, or a feeling.", .state = .draft, .score = 54, .signal = "STRUCTURE", .updated = "edited yesterday" },
        .{ .id = 5, .eyebrow = "ARCHITECTURE", .title = "A school without corridors", .brief = "Learning spaces gather around a single indoor landscape.", .body = "There are no corridors here. Movement, meeting and learning share one continuous room.", .state = .review, .score = 87, .signal = "IN REVIEW", .updated = "edited yesterday" },
    },
    selected_id: usize = 0,
    lens: Lens = .write,
    image_tool: ImageTool = .original,
    search_buffer: canvas.TextBuffer(96) = .{},
    title_buffer: canvas.TextBuffer(180) = .{},
    brief_buffer: canvas.TextBuffer(420) = .{},
    body_buffer: canvas.TextBuffer(4096) = .{},
    alt_buffer: canvas.TextBuffer(240) = .{},
    command_open: bool = false,
    dirty: bool = false,
    image_dirty: bool = false,
    background_removed: bool = false,
    activity: []const u8 = "Workspace synced just now",

    pub const view_unbound = .{
        "stories", "selected_id", "image_tool", "search_buffer", "title_buffer",
        "brief_buffer", "body_buffer", "alt_buffer", "dirty", "image_dirty",
        "background_removed",
    };

    pub fn search(model: *const Model) []const u8 { return model.search_buffer.text(); }
    pub fn title(model: *const Model) []const u8 { return model.title_buffer.text(); }
    pub fn brief(model: *const Model) []const u8 { return model.brief_buffer.text(); }
    pub fn body(model: *const Model) []const u8 { return model.body_buffer.text(); }
    pub fn altText(model: *const Model) []const u8 { return model.alt_buffer.text(); }

    pub fn visibleStories(model: *const Model, arena: std.mem.Allocator) []const StoryView {
        const out = arena.alloc(StoryView, model.stories.len) catch return &.{};
        var count: usize = 0;
        for (model.stories) |story| {
            if (!matches(story, model.search())) continue;
            out[count] = .{ .id = story.id, .eyebrow = story.eyebrow, .title = story.title, .state = story.state, .score = story.score, .signal = story.signal, .selected = story.id == model.selected_id };
            count += 1;
        }
        return out[0..count];
    }

    pub fn selectedStory(model: *const Model) *const Story { return &model.stories[model.selected_id]; }
    pub fn currentEyebrow(model: *const Model) []const u8 { return model.selectedStory().eyebrow; }
    pub fn currentState(model: *const Model) StoryState { return model.selectedStory().state; }
    pub fn currentScore(model: *const Model) u8 { return model.selectedStory().score; }
    pub fn currentScoreFraction(model: *const Model) f32 { return @as(f32, @floatFromInt(model.currentScore())) / 100.0; }
    pub fn currentUpdated(model: *const Model) []const u8 { return model.selectedStory().updated; }
    pub fn dirtyLabel(model: *const Model) []const u8 { return if (model.dirty or model.image_dirty) "UNSAVED CHANGES" else "ALL CHANGES SAVED"; }
    pub fn canGoBack(model: *const Model) bool { return model.lens != .write; }
    pub fn stepNumber(model: *const Model) []const u8 {
        return switch (model.lens) { .write => "1", .visual => "2", .review => "3" };
    }
    pub fn stepTitle(model: *const Model) []const u8 {
        return switch (model.lens) { .write => "Write", .visual => "Image", .review => "Review" };
    }
    pub fn stepHelp(model: *const Model) []const u8 {
        return switch (model.lens) {
            .write => "Build the story in three simple fields.",
            .visual => "Improve the hero image with one-click actions.",
            .review => "Check the complete story before it goes live.",
        };
    }
    pub fn stepProgress(model: *const Model) f32 {
        return switch (model.lens) { .write => 0.333, .visual => 0.666, .review => 1.0 };
    }
    pub fn canPublish(model: *const Model) bool { return model.currentScore() >= 90 and model.currentState() != .live; }
    pub fn reviewCount(model: *const Model) usize {
        var count: usize = 0;
        for (model.stories) |story| if (story.state == .review) { count += 1; };
        return count;
    }
    pub fn imageStatus(model: *const Model) []const u8 {
        return switch (model.image_tool) {
            .original => "ORIGINAL FAL ASSET",
            .background_removed => "BACKGROUND REMOVED",
            .subject_crop => "SUBJECT CROP APPLIED",
            .relit => "SMART RELIGHT APPLIED",
            .upscaled => "2X UPSCALE READY",
        };
    }
    pub fn imageHint(model: *const Model) []const u8 {
        return if (model.background_removed) "TRANSPARENT PNG PREVIEW" else "ORIGINAL BACKGROUND";
    }
};

fn matches(story: Story, query: []const u8) bool {
    if (query.len == 0) return true;
    return containsIgnoreCase(story.title, query) or containsIgnoreCase(story.eyebrow, query);
}

fn containsIgnoreCase(haystack: []const u8, needle: []const u8) bool {
    if (needle.len == 0) return true;
    if (needle.len > haystack.len) return false;
    var i: usize = 0;
    while (i + needle.len <= haystack.len) : (i += 1) {
        var same = true;
        for (needle, 0..) |ch, j| {
            if (std.ascii.toLower(haystack[i + j]) != std.ascii.toLower(ch)) { same = false; break; }
        }
        if (same) return true;
    }
    return false;
}

pub fn update(model: *Model, msg: Msg) void {
    switch (msg) {
        .select_story => |id| loadStory(model, id),
        .set_lens => |lens| model.lens = lens,
        .show_write => model.lens = .write,
        .show_visual => model.lens = .visual,
        .show_review => model.lens = .review,
        .next_step => model.lens = switch (model.lens) { .write => .visual, .visual => .review, .review => .review },
        .previous_step => model.lens = switch (model.lens) { .write => .write, .visual => .write, .review => .visual },
        .search_edit => |edit| model.search_buffer.apply(edit),
        .title_edit => |edit| { model.title_buffer.apply(edit); model.dirty = true; model.activity = "Headline evolving locally"; },
        .brief_edit => |edit| { model.brief_buffer.apply(edit); model.dirty = true; model.activity = "Brief evolving locally"; },
        .body_edit => |edit| { model.body_buffer.apply(edit); model.dirty = true; model.activity = "Story evolving locally"; },
        .alt_edit => |edit| { model.alt_buffer.apply(edit); model.image_dirty = true; model.activity = "Accessible image description updated"; },
        .apply_image_tool => |tool| {
            model.image_tool = tool;
            model.background_removed = tool == .background_removed;
            model.image_dirty = true;
            model.activity = switch (tool) {
                .original => "Original image restored",
                .background_removed => "Background removed in one click",
                .subject_crop => "Subject-aware news crop created",
                .relit => "Image relit for editorial clarity",
                .upscaled => "High-resolution derivative created",
            };
        },
        .image_remove_background => applyImageTool(model, .background_removed),
        .image_subject_crop => applyImageTool(model, .subject_crop),
        .image_relit => applyImageTool(model, .relit),
        .image_upscale => applyImageTool(model, .upscaled),
        .undo_image => {
            model.image_tool = .original;
            model.background_removed = false;
            model.image_dirty = false;
            model.activity = "Visual changes reverted";
        },
        .save => { model.dirty = false; model.image_dirty = false; model.activity = "Story and FAL derivative saved to TYPO3 workspace"; },
        .submit_review => { model.stories[model.selected_id].state = .review; model.activity = "Story entered the review orbit"; },
        .publish => if (model.canPublish()) { model.stories[model.selected_id].state = .live; model.activity = "Story published to the live workspace"; },
        .open_commands => model.command_open = true,
        .close_commands => model.command_open = false,
        .toggle_insights => {},
        .new_story => {
            loadStory(model, 4);
            model.title_buffer.set("Untitled signal");
            model.brief_buffer.clear();
            model.body_buffer.clear();
            model.dirty = true;
            model.activity = "New story seed created";
        },
    }
}

fn applyImageTool(model: *Model, tool: ImageTool) void {
    model.image_tool = tool;
    model.background_removed = tool == .background_removed;
    model.image_dirty = true;
    model.activity = switch (tool) {
        .original => "Original image restored",
        .background_removed => "Background removed in one click",
        .subject_crop => "Subject-aware news crop created",
        .relit => "Image relit for editorial clarity",
        .upscaled => "High-resolution derivative created",
    };
}

fn loadStory(model: *Model, id: usize) void {
    if (id >= model.stories.len) return;
    model.selected_id = id;
    const story = model.stories[id];
    model.title_buffer.set(story.title);
    model.brief_buffer.set(story.brief);
    model.body_buffer.set(story.body);
    model.alt_buffer.set("People listening to an outdoor sound installation at the old railway station.");
    model.dirty = false;
    model.image_dirty = false;
    model.image_tool = .original;
    model.background_removed = false;
    model.activity = "Story focused";
}

pub const AppUi = canvas.Ui(Msg);
pub const app_markup = @embedFile("app.native");
const PulseApp = native_sdk.UiApp(Model, Msg);

pub fn initialModel() Model {
    var model: Model = .{};
    loadStory(&model, 0);
    return model;
}

fn pulseTokens(_: *const Model) canvas.DesignTokens {
    var tokens = canvas.DesignTokens.theme(.{ .color_scheme = .dark, .pack = .geist });
    tokens.colors.background = canvas.Color.rgb8(22, 23, 26);
    tokens.colors.surface = canvas.Color.rgb8(29, 30, 34);
    tokens.colors.surface_subtle = canvas.Color.rgb8(36, 37, 42);
    tokens.colors.surface_pressed = canvas.Color.rgb8(45, 47, 54);
    tokens.colors.text = canvas.Color.rgb8(240, 240, 242);
    tokens.colors.text_muted = canvas.Color.rgb8(157, 158, 166);
    tokens.colors.border = canvas.Color.rgba8(255, 255, 255, 22);
    tokens.colors.accent = canvas.Color.rgb8(133, 146, 255);
    tokens.colors.accent_text = canvas.Color.rgb8(18, 19, 25);
    tokens.colors.info = canvas.Color.rgb8(133, 146, 255);
    tokens.colors.warning = canvas.Color.rgb8(211, 169, 102);
    tokens.colors.success = canvas.Color.rgb8(118, 184, 145);
    tokens.colors.focus_ring = canvas.Color.rgb8(133, 146, 255);
    tokens.typography.heading_size = 26;
    tokens.typography.display_size = 42;
    return tokens;
}

pub fn main(init: std.process.Init) !void {
    const app_state = try PulseApp.create(std.heap.page_allocator, .{
        .name = "pulse-newsroom",
        .scene = shell_scene,
        .canvas_label = canvas_label,
        .update = update,
        .tokens_fn = pulseTokens,
        .markup = .{ .source = app_markup, .watch_path = "src/app.native", .io = init.io },
        .status_item = .{ .title = "PULSE", .tooltip = "TYPO3 publishing pulse", .items = &.{
            .{ .id = 1, .label = "Open Newsroom", .command = "app.focus" },
            .{ .separator = true },
            .{ .id = 2, .label = "Save Current Story", .command = "app.save" },
        } },
    });
    defer app_state.destroy();
    app_state.model = initialModel();
    try runner.runWithOptions(app_state.app(), .{
        .app_name = "pulse-newsroom",
        .window_title = "PULSE - TYPO3 Newsroom",
        .bundle_id = "at.webconsulting.typo3.pulse",
        .icon_path = "assets/icon.png",
        .default_frame = geometry.RectF.init(0, 0, window_width, window_height),
        .restore_state = true,
        .js_window_api = false,
        .security = .{ .permissions = &app_permissions, .navigation = .{ .allowed_origins = &.{ "zero://inline", "zero://app" } } },
    }, init);
}

test { _ = @import("tests.zig"); }
