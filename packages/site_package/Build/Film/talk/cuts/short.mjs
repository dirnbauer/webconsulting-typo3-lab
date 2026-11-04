/**
 * SHORT cut (~58 s). Kurt stays on screen the whole time: the ten slogans are
 * one lower-band card (the deck) whose slogan changes with a 250 ms slide
 * and fade, never dimming the frame; each key phrase grows the card when he
 * says it. Intro, Nielsen source and end card as in the long cut. Anchors are
 * phrases of var/heygen-usability/render/short.txt (see ../timeline.mjs).
 */
const say = (phrase, offset = 0, edge = 'start') => ({ say: phrase, offset, edge });
const slide = (n, phrase, text, at) => ({ n, at: say(phrase, -0.12), ...(text ? { phrase: { text, at: say(at) } } : {}) });

export default {
    id: 'short',
    poster: 2.6,
    events: [
        { type: 'intro', start: 0, end: say('jakob nielsen', -0.3) },
        { type: 'source', start: say('jakob nielsen', -0.05), end: say('one your best', -0.15) },
        {
            type: 'deck',
            start: say('one your best', -0.12),
            slides: [
                slide(1, 'one your best', 'Test it.', 'test it'),
                slide(2, 'two the user', '… about the experience.', 'about the experience'),
                slide(3, 'three but not', 'But not about the solution.', 'about the solution'),
                slide(4, 'four users are', 'Watch what they do.', 'watch what they do'),
                slide(5, 'five designers', 'They know too much.', 'they know too much'),
                slide(6, 'six vice'),
                slide(7, 'seven less is more', 'Focused, not empty.', 'focused not empty'),
                slide(8, 'eight details', 'Labels. Errors. Loading states.', 'labels errors'),
                slide(9, 'nine help', 'If users need help, fix the interface.', 'if users need help'),
                { n: 10, at: say('ten usability engineering', -0.12), phrase: { steps: [['Research', 'research'], ['Prototype', 'prototype'], ['Test', 'test repeat'], ['Repeat', 'repeat']].map(([text, phrase]) => ({ text, at: say(phrase) })) } },
            ],
            end: say('usability is not magic', -0.12),
        },
        { type: 'closing', anchor: 'deck', fadeIn: 0.25, start: say('usability is not magic', -0.12), items: [{ text: 'Usability is not magic.', at: say('usability is not magic') }, { text: 'It is method.', at: say('it is method') }], end: say('it is method', 1.2, 'end') },
        { type: 'end', start: say('it is method', 0.45, 'end'), fade: 0.5, hold: 4.6 },
    ],
};
