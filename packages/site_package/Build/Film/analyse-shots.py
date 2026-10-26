#!/usr/bin/env python3
"""
Where the content of each tour screenshot sits, for the camera of film.html.

Backend screenshots often fill only the top left of the frame; a camera that
pushes into the middle shows empty grey. For every feature screenshot and
every element still of the content-type films this
finds the box that holds most of the detail (the edge energy between its 5th
and 95th percentile along each axis) and writes it, as fractions of the
image, to data/focus.json. A film spec can still set its own `focus`.

    python3 packages/site_package/Build/Film/analyse-shots.py
"""

import json
from pathlib import Path

import numpy as np
from PIL import Image

FILM = Path(__file__).resolve().parent
LAB = FILM.parents[3]
STYLEGUIDE = LAB / 'vendor/webconsulting/desiderio/Resources/Public/Styleguide'
FOOTAGE = Path.home() / '.cache/lab-films/footage'


def box(path: Path, top: float = 0.0) -> list[float]:
    """`top`: the share of the image above the content (a sticky site header) that is left out."""
    image = Image.open(path).convert('L')
    image = image.resize((image.width // 4, image.height // 4))
    pixels = np.asarray(image, dtype=np.float32)[round(image.height * top):]
    energy = np.abs(np.diff(pixels, axis=0))[:, :-1] + np.abs(np.diff(pixels, axis=1))[:-1, :]
    energy[energy < 6] = 0

    def span(profile: np.ndarray) -> tuple[float, float]:
        total = profile.cumsum()
        if total[-1] == 0:
            return 0.0, 1.0
        low = np.searchsorted(total, total[-1] * 0.05) / len(profile)
        high = (np.searchsorted(total, total[-1] * 0.95) + 1) / len(profile)
        return low, high

    x0, x1 = span(energy.sum(axis=0))
    y0, y1 = span(energy.sum(axis=1))
    margin = 0.03
    y0, y1 = top + y0 * (1 - top), top + y1 * (1 - top)
    x0, y0 = max(0.0, x0 - margin), max(top, y0 - margin)
    x1, y1 = min(1.0, x1 + margin), min(1.0, y1 + margin)
    return [round(x0, 3), round(y0, 3), round(x1 - x0, 3), round(y1 - y0, 3)]


def main() -> None:
    focus = {}
    for path in sorted(STYLEGUIDE.glob('*/*feature-*.webp')):
        focus[str(path.relative_to(LAB))] = box(path)
    # Group pages as a visitor sees them (capture-frontend.mjs): below the 88 px header.
    for path in sorted((FOOTAGE / 'stills/elements').glob('*/*.jpg')):
        focus[str(path.relative_to(FOOTAGE))] = box(path, top=0.085)
    # Backend stills that stand in for a tour screenshot (capture-backend.mjs).
    for path in sorted((FOOTAGE / 'stills/backend').glob('*.jpg')):
        focus[str(path.relative_to(FOOTAGE))] = box(path)
    target = FILM / 'data/focus.json'
    target.write_text(json.dumps(focus, indent=1) + '\n')
    print(f'{len(focus)} screenshots → {target.relative_to(LAB)}')


if __name__ == '__main__':
    main()
