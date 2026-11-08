import ai from './ai.mjs';
import redaktion from './redaktion.mjs';
import contentTypes from './content-types.mjs';
import docx from './docx-editor.mjs';
import features from './features.mjs';
import howWeEarn from './how-we-earn.mjs';
import hubs from './hubs.mjs';
import labOverview from './lab-overview.mjs';
import skills from './skills.mjs';
import softwareFinance from './software-finance.mjs';
import softwareOps from './software-ops.mjs';
import strategy from './strategy.mjs';
import themes from './themes.mjs';

export const films = {
    ...redaktion, ...docx, ...features, ...ai, ...hubs, ...contentTypes, ...themes,
    ...softwareOps, ...softwareFinance, ...skills, ...strategy, ...labOverview, ...howWeEarn,
};
