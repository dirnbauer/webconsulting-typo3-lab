const std = @import("std");
const native_sdk = @import("native_sdk");
const main = @import("main.zig");

const canvas = native_sdk.canvas;
const testing = std.testing;
const AppUi = main.AppUi;
const AppMarkup = canvas.MarkupView(main.Model, main.Msg);

fn buildTree(arena: std.mem.Allocator, model: *const main.Model) !AppUi.Tree {
    var view = try AppMarkup.init(arena, main.app_markup);
    var ui = AppUi.init(arena);
    const node = view.build(&ui, model) catch |err| {
        if (err == error.MarkupBuild) std.debug.print("app.native:{d}:{d}: {s}\n", .{ view.diagnostic.line, view.diagnostic.column, view.diagnostic.message });
        return err;
    };
    return ui.finalize(node);
}

fn findByText(widget: canvas.Widget, kind: canvas.WidgetKind, text: []const u8) ?canvas.Widget {
    if (widget.kind == kind and std.mem.eql(u8, widget.text, text)) return widget;
    for (widget.children) |child| if (findByText(child, kind, text)) |found| return found;
    return null;
}

test "story selection moves the editorial focus" {
    var model = main.initialModel();
    main.update(&model, .{ .select_story = 2 });
    try testing.expectEqual(@as(usize, 2), model.selected_id);
    try testing.expectEqualStrings("When public space moves", model.title());
    try testing.expect(!model.dirty);
}

test "image actions are one click and reversible" {
    var model = main.initialModel();
    main.update(&model, .{ .apply_image_tool = .background_removed });
    try testing.expect(model.background_removed);
    try testing.expect(model.image_dirty);
    try testing.expectEqualStrings("BACKGROUND REMOVED", model.imageStatus());
    main.update(&model, .undo_image);
    try testing.expect(!model.background_removed);
    try testing.expect(!model.image_dirty);
}

test "publishing is guarded by readiness" {
    var model = main.initialModel();
    model.stories[0].score = 72;
    main.update(&model, .publish);
    try testing.expect(model.stories[0].state != .live);
    model.stories[0].score = 96;
    main.update(&model, .publish);
    try testing.expectEqual(main.StoryState.live, model.stories[0].state);
}

test "guided workflow moves one clear step at a time" {
    var model = main.initialModel();
    try testing.expectEqual(main.Lens.write, model.lens);
    try testing.expectEqualStrings("1", model.stepNumber());

    main.update(&model, .next_step);
    try testing.expectEqual(main.Lens.visual, model.lens);
    try testing.expectEqualStrings("2", model.stepNumber());

    main.update(&model, .next_step);
    try testing.expectEqual(main.Lens.review, model.lens);
    try testing.expectEqualStrings("3", model.stepNumber());

    main.update(&model, .previous_step);
    try testing.expectEqual(main.Lens.visual, model.lens);
}

test "native view builds and lays out at desktop scale" {
    var arena_state = std.heap.ArenaAllocator.init(testing.allocator);
    defer arena_state.deinit();
    var model = main.initialModel();
    var tree = try buildTree(arena_state.allocator(), &model);
    try testing.expect(findByText(tree.root, .text, "PULSE") != null);
    try testing.expect(findByText(tree.root, .button, "Continue to image") != null);

    main.update(&model, .{ .set_lens = .visual });
    tree = try buildTree(arena_state.allocator(), &model);
    try testing.expect(findByText(tree.root, .button, "Remove background") != null);

    main.update(&model, .next_step);
    tree = try buildTree(arena_state.allocator(), &model);
    try testing.expect(findByText(tree.root, .button, "Publish story") != null);

    var nodes: [1024]canvas.WidgetLayoutNode = undefined;
    const layout = try canvas.layoutWidgetTree(tree.root, native_sdk.geometry.RectF.init(0, 0, 1440, 920), &nodes);
    try testing.expect(layout.nodes.len > 70);
}
