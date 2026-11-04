"""
Protected face box of a presenter render (OpenCV Haar cascades).

    ~/.cache/talk-venv/bin/python -I face.py <presenter.mp4> <out.json> [--every=0.5] [--debug=<dir>]

Samples a frame every --every seconds, finds the largest frontal face (and the
eyes inside it), turns each face into a head box (bald skull above the brows,
chin below the mouth, ears at the sides), and writes the union over all
samples plus a margin of 8 % of the frame on every side. Overlays must never
intersect `protected`. With --debug, a few annotated frames land in <dir>.
"""

import json
import sys

import cv2

args = [a for a in sys.argv[1:] if not a.startswith('--')]
opts = dict(a[2:].split('=', 1) for a in sys.argv[1:] if a.startswith('--') and '=' in a)
video, out = args[0], args[1]
every = float(opts.get('every', '0.5'))
debug = opts.get('debug')

faces = cv2.CascadeClassifier(cv2.data.haarcascades + 'haarcascade_frontalface_default.xml')
eyes = cv2.CascadeClassifier(cv2.data.haarcascades + 'haarcascade_eye_tree_eyeglasses.xml')
capture = cv2.VideoCapture(video)
fps = capture.get(cv2.CAP_PROP_FPS) or 25
count = int(capture.get(cv2.CAP_PROP_FRAME_COUNT))
width = int(capture.get(cv2.CAP_PROP_FRAME_WIDTH))
height = int(capture.get(cv2.CAP_PROP_FRAME_HEIGHT))
step = max(1, round(every * fps))

samples = []
missed = []
for index in range(0, count, step):
    capture.set(cv2.CAP_PROP_POS_FRAMES, index)
    ok, frame = capture.read()
    if not ok:
        continue
    gray = cv2.equalizeHist(cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY))
    found = faces.detectMultiScale(gray, scaleFactor=1.08, minNeighbors=6, minSize=(width // 6, width // 6))
    if len(found) == 0:
        missed.append(round(index / fps, 2))
        continue
    x, y, w, h = max(found, key=lambda f: f[2] * f[3])
    eye_boxes = eyes.detectMultiScale(gray[y:y + h // 2 + h // 6, x:x + w], scaleFactor=1.1, minNeighbors=5)
    eye_y = [int(y + ey) for (ex, ey, ew, eh) in eye_boxes] + [int(y + ey + eh) for (ex, ey, ew, eh) in eye_boxes]
    # On these renders the Haar box runs from the forehead to the chin and is
    # wider than the ears (measured on the debug frames): the bald skull
    # reaches ~0.27 h above it. Head box: 0.30 h up, 0.08 h down, 0.04 w aside.
    head = [int(x - 0.04 * w), int(y - 0.30 * h), int(x + w + 0.04 * w), int(y + h + 0.08 * h)]
    samples.append({'t': round(index / fps, 2), 'face': [int(x), int(y), int(x + w), int(y + h)], 'head': head,
                    'eyes': [min(eye_y), max(eye_y)] if eye_y else None})
    if debug and len(samples) % 20 == 1:
        cv2.rectangle(frame, (x, y), (x + w, y + h), (0, 255, 0), 3)
        cv2.rectangle(frame, (head[0], head[1]), (head[2], head[3]), (0, 0, 255), 3)
        for (ex, ey, ew, eh) in eye_boxes:
            cv2.rectangle(frame, (x + ex, y + ey), (x + ex + ew, y + ey + eh), (255, 0, 0), 2)
        cv2.imwrite(f'{debug}/face-{index:05d}.jpg', cv2.resize(frame, (width // 2, height // 2)))

if not samples:
    sys.exit('no face found')
union = [min(s['head'][0] for s in samples), min(s['head'][1] for s in samples),
         max(s['head'][2] for s in samples), max(s['head'][3] for s in samples)]
mx, my = round(0.08 * width), round(0.08 * height)
protected = [max(0, union[0] - mx), max(0, union[1] - my), min(width, union[2] + mx), min(height, union[3] + my)]
eye_rows = [s['eyes'] for s in samples if s['eyes']]
result = {
    'video': video, 'width': width, 'height': height, 'fps': fps, 'samples': len(samples), 'missed': missed,
    'face_union': [min(s['face'][0] for s in samples), min(s['face'][1] for s in samples), max(s['face'][2] for s in samples), max(s['face'][3] for s in samples)],
    'eyes_y': [min(e[0] for e in eye_rows), max(e[1] for e in eye_rows)] if eye_rows else None,
    'head_union': union, 'margin': [mx, my], 'protected': protected,
}
with open(out, 'w') as handle:
    json.dump({**result, 'per_sample': samples}, handle, indent=1)
print(json.dumps(result))
