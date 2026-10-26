import ai from './ai.mjs';
import contentTypes from './content-types.mjs';
import docx from './docx-editor.mjs';
import features from './features.mjs';
import hubs from './hubs.mjs';
import themes from './themes.mjs';

export const films = { ...docx, ...features, ...ai, ...hubs, ...contentTypes, ...themes };
