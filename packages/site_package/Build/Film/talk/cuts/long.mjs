/**
 * LONG cut (~5 min): a card per slogan while Kurt says it, then lower thirds
 * with his key phrases, built line by line on his words. Panels stay in the
 * lower band (face-safe); Kurt is alone on screen between them. Anchors are
 * phrases of var/heygen-usability/render/long.txt (see ../timeline.mjs).
 */
const say = (phrase, offset = 0, edge = 'start') => ({ say: phrase, offset, edge });
const card = (n, phrase, dur = 2.2) => ({ type: 'card', n, start: say(phrase, -0.12), dur });
const lines = (items) => items.map(([text, phrase]) => ({ text, at: say(phrase) }));
const lower = (n, items, until, extra = {}) => ({ type: 'lower', n, start: say(items[0][1], -0.15), items: lines(items), end: say(until, -0.3), ...extra });
const steps = (n, items, until, extra = {}) => ({ type: 'steps', n, start: say(items[0][1], -0.15), items: lines(items), end: say(until, -0.3), ...extra });

export default {
    id: 'long',
    poster: 3.34,
    events: [
        { type: 'intro', start: 0, end: say('good usability means', -0.35) },
        lower(0, [['Real people can use it,', 'real people'], ['understand it, trust it,', 'understand it'], ['and reach their goals.', 'achieve their goals']], 'here are ten', { label: 'Good usability' }),
        { type: 'source', start: say('jakob nielsen', -0.1), end: say('one your best', -0.05) },

        card(1, 'one your best'),
        lower(1, [['Opinions are not evidence.', 'but opinions are not']], 'it may even'),
        lower(1, [['Test early.', 'test early'], ['Observe carefully.', 'observe carefully'], ['Let behavior replace assumptions.', 'let behavior']], 'two the user'),

        card(2, 'two the user'),
        lower(2, [['Right about their experience.', 'about their experience']], 'when they cannot find'),
        lower(2, [['Don’t argue with user behavior.', 'we should not argue'], ['The interface is giving us feedback.', 'the interface is giving']], 'three the user'),

        card(3, 'three the user'),
        lower(3, [['… but not about the solution.', 'about the solution'], ['They can tell us what hurts.', 'tell us what hurts']], 'but they may not know'),
        lower(3, [['Listen to users deeply.', 'listen to users deeply'], ['Users reveal problems.', 'users reveal problems'], ['Designers create solutions.', 'designers create solutions']], 'four users are'),

        card(4, 'four users are'),
        lower(4, [['Their job: use the system.', 'their job is to use'], ['Our job: understand their needs.', 'our job is to understand']], 'do not ask only'),
        lower(4, [['Don’t ask only what they want.', 'do not ask only'], ['Watch what they do.', 'watch what they do']], 'five designers are'),

        card(5, 'five designers are'),
        lower(5, [['Designers know too much.', 'designers know too much'], ['That knowledge makes them biased.', 'that knowledge makes']], 'the interface that feels'),
        lower(5, [['Never test only with the team.', 'so never use the product'], ['Designers design for users.', 'designers design for users']], 'six vice presidents'),

        card(6, 'six vice presidents'),
        steps(6, [['Stakeholders', 'stakeholders matter'], ['Business goals', 'business goals matter'], ['Leadership', 'leadership matters']], 'a vice president may'),
        lower(6, [['Opinion is not user research.', 'that opinion is not'], ['Balance business needs with evidence.', 'the best product decisions']], 'a loud voice'),
        lower(6, [['A loud voice should not replace', 'a loud voice'], ['the quiet truth of testing.', 'the quiet truth']], 'seven less is more'),

        card(7, 'seven less is more'),
        lower(7, [['Every option adds cognitive load.', 'every extra option'], ['Every field slows people down.', 'every unnecessary field'], ['Every feature competes for attention.', 'every additional feature']], 'less is more does'),
        lower(7, [['Less is not empty.', 'less is more does'], ['It means focused.', 'it means focused']], 'remove what distracts'),
        steps(7, [['Remove', 'remove what distracts'], ['Simplify', 'simplify what confuses'], ['Prioritize', 'prioritize what matters']], 'the best interface often'),

        card(8, 'eight details matter'),
        steps(8, [['Labels', 'button labels'], ['Errors', 'error messages'], ['Spacing', 'spacing'], ['Loading states', 'loading states'], ['Validation', 'form validation'], ['Keyboard', 'keyboard navigation'], ['Microcopy', 'microcopy']], 'small details create'),
        lower(8, [['Small details create trust,', 'small details create trust'], ['prevent mistakes,', 'small details prevent'], ['make a product feel professional.', 'small details make']], 'a confusing label'),
        lower(8, [['Details are not decoration.', 'details are not decoration'], ['Details are usability.', 'details are usability']], 'nine help doesnt'),

        card(9, 'nine help doesnt'),
        lower(9, [['Help can’t repair a bad interface.', 'used to repair'], ['If users constantly need help …', 'if users constantly']], 'good usability means the'),
        lower(9, [['The interface explains itself.', 'the interface explains itself']], 'help should support'),
        lower(9, [['Help should support the experience,', 'help should support'], ['not be required to survive it.', 'it should not be required']], 'ten usability engineering'),

        card(10, 'ten usability engineering', 2.4),
        lower(10, [['Not a one-time review.', 'not a one-time review'], ['It is a process.', 'it is a process']], 'research prototype'),
        steps(10, [['Research', 'research'], ['Prototype', 'prototype'], ['Test', 'test measure'], ['Measure', 'measure'], ['Improve', 'improve'], ['Repeat', 'repeat']], 'great usability comes'),
        lower(10, [['Don’t guess once and hope forever.', 'you do not guess once'], ['Build, observe, learn, refine.', 'you build observe']], 'usability engineering turns'),
        lower(10, [['From opinion into evidence.', 'usability engineering turns']], 'these slogans are'),

        lower(0, [['Better usability equals better products.', 'better usability equals']], 'do not rely', { label: 'Simple. Powerful.' }),
        steps(0, [['No guesses', 'do not rely'], ['Respect users', 'respect users'], ['Evidence, not hierarchy', 'listen to evidence'], ['Simplify', 'simplify'], ['Details', 'care about details'], ['Process', 'treat usability']], 'because great products', { label: 'In short' }),
        lower(0, [['Not just built.', 'are not just built'], ['Tested, learned from, improved.', 'they are tested']], 'usability is not magic', { label: 'Great products' }),

        { type: 'closing', start: say('usability is not magic', -0.12), items: [{ text: 'Usability is not magic.', at: say('usability is not magic') }, { text: 'It is method.', at: say('it is method') }], end: say('it is method', 1.6, 'end') },
        { type: 'end', start: say('it is method', 0.45, 'end'), fade: 0.8, hold: 4.6 },
    ],
};
