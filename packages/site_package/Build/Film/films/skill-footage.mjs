// Result footage of the ten superfeature skills, from the single-skill films of
// 4 November 2026 (git 995cd69, films/skill-films-a.mjs and -b.mjs): real runs, recorded
// once (stills and clips in ~/.cache/lab-films), each with the narration timed to it.
// The group films (skill-groups.mjs) show them after the skill's prompt.
export const FOOTAGE = {
    "typo3-upgrade-run": {
        "url": "fakeshop run 3 · loops/620-invariance-final-proof-e3/SUMMARY.md",
        "tag": "Final proof",
        "focus": [
            0.04,
            0.12,
            0.92,
            0.46
        ],
        "max": 1.3,
        "marks": [
            [
                0.195,
                0.466,
                0.265,
                0.048
            ]
        ],
        "marksAt": 2200,
        "image": "stills/wp6d/upgrade-run-summary.png",
        "say": "The final proof of a real run: 360 screenshots, no undeclared difference."
    },
    "typo3-wcag22-aa-agentic": {
        "url": "acme/.a11y/report.md",
        "tag": "Report",
        "focus": [
            0.03,
            0.05,
            0.8,
            0.52
        ],
        "max": 1.4,
        "image": "stills/wp6d/wcag-report.png",
        "say": "The report lists each violation by page and drafts the accessibility statement."
    },
    "typo3-webmcp": {
        "clip": "wp6d-webmcp-form",
        "url": "fakeshop.ddev.site/en/contact-us/report-an-error/",
        "tag": "Chrome",
        "segments": [
            [
                700,
                11000,
                1.15
            ]
        ],
        "camera": [
            [
                700,
                [
                    0.1,
                    0,
                    0.62,
                    0.9
                ]
            ],
            [
                2200,
                [
                    0.1,
                    0,
                    0.62,
                    0.9
                ]
            ],
            [
                3600,
                [
                    0.1,
                    0.42,
                    0.6,
                    0.55
                ]
            ],
            [
                6900,
                [
                    0.1,
                    0.42,
                    0.6,
                    0.55
                ]
            ],
            [
                8200,
                [
                    0.1,
                    0.3,
                    0.62,
                    0.7
                ]
            ]
        ],
        "say": "An agent fills the form. The consent box stays empty; a person sends it."
    },
    "typo3-structured-data": {
        "url": "validator.schema.org",
        "tag": "Validator",
        "camera": [
            [
                0.06,
                "full"
            ],
            [
                0.42,
                [
                    0.5,
                    0.15,
                    0.5,
                    0.42
                ]
            ],
            [
                1,
                [
                    0.5,
                    0.15,
                    0.5,
                    0.42
                ]
            ]
        ],
        "max": 1.9,
        "marks": [
            [
                0.832,
                0.296,
                0.128,
                0.04
            ]
        ],
        "marksAt": 3200,
        "image": "stills/wp6d/structured-data-validator.png",
        "say": "The validator finds no errors. The graph says what the page shows."
    },
    "typo3-backend-rights": {
        "aspect": 1.7777777777777777,
        "url": "webconsulting-typo3-lab.ddev.site/typo3/module/users/management",
        "tag": "Backend",
        "focus": [
            0.16,
            0.2,
            0.6,
            0.52
        ],
        "max": 1.6,
        "image": "stills/wp6d/backend-rights-groups.png",
        "say": "The lab fails: three groups, no leaves, 56 findings."
    },
    "typo3-content-blocks": {
        "clip": "wp6e-cb-wizard",
        "url": "webconsulting-typo3-lab.ddev.site/typo3/module/web/layout",
        "tag": "Backend",
        "segments": [
            [
                6400,
                9900,
                2
            ],
            [
                13700,
                23800,
                4
            ],
            [
                61000,
                61900,
                1
            ],
            [
                68300,
                71000,
                1.1
            ]
        ],
        "camera": [
            [
                6400,
                [
                    0.17,
                    0,
                    0.66,
                    0.5
                ]
            ],
            [
                9800,
                [
                    0.17,
                    0,
                    0.66,
                    0.5
                ]
            ],
            [
                13700,
                "full"
            ],
            [
                68300,
                [
                    0.42,
                    0.3,
                    0.58,
                    0.62
                ]
            ]
        ],
        "min": 7600,
        "say": "The teaser shows up in the wizard. An editor fills it in and saves."
    },
    "typo3-shadcn-content-elements": {
        "clip": "wp6e-shadcn-presets",
        "url": "webconsulting-typo3-lab.ddev.site/content-types/plans-pricing/",
        "tag": "Frontend",
        "segments": [
            [
                900,
                6900,
                1.3
            ],
            [
                8200,
                12600,
                1.5
            ]
        ],
        "min": 7600,
        "say": "Switch the preset, switch to dark mode: every element follows."
    },
    "typo3-visual-editor": {
        "clip": "wp6e-ve-inline",
        "url": "webconsulting-typo3-lab.ddev.site/typo3/module/web/edit",
        "tag": "Visual Editor",
        "segments": [
            [
                1600,
                13000,
                1.45
            ]
        ],
        "camera": [
            [
                1600,
                "full"
            ],
            [
                3600,
                [
                    0.06,
                    0.1,
                    0.88,
                    0.42
                ]
            ],
            [
                13000,
                [
                    0.06,
                    0.1,
                    0.88,
                    0.42
                ]
            ]
        ],
        "say": "In the Visual Editor: click the headline, type, save. The record is updated."
    },
    "typo3-rector": {
        "url": "powermail_cond 197bead · scratch copy",
        "tag": "Dry run",
        "focus": [
            0.04,
            0.3,
            0.92,
            0.66
        ],
        "max": 1.3,
        "marks": [
            [
                0.06,
                0.885,
                0.3,
                0.05
            ]
        ],
        "marksAt": 3600,
        "image": "stills/wp6e/rector-second-run.png",
        "say": "Apply, dry-run again: one generated file left. Once more: Rector is done."
    },
    "typo3-records-list-types": {
        "clip": "wp6e-records-views",
        "url": "webconsulting-typo3-lab.ddev.site/typo3/module/content/records",
        "tag": "Records",
        "segments": [
            [
                900,
                2500,
                1
            ],
            [
                7900,
                10700,
                1.4
            ],
            [
                16800,
                19600,
                1.4
            ],
            [
                25900,
                29000,
                1.4
            ]
        ],
        "camera": [
            [
                900,
                [
                    0.38,
                    0.12,
                    0.6,
                    0.62
                ]
            ],
            [
                29000,
                [
                    0.38,
                    0.12,
                    0.6,
                    0.62
                ]
            ]
        ],
        "max": 1.6,
        "min": 7800,
        "say": "Grid, timeline, catalogue: editors switch views, and nothing in core is patched."
    }
};
