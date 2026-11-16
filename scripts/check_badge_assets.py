from pathlib import Path
from PIL import Image

root = Path(__file__).resolve().parents[1] / 'assets/images/badges'
files = sorted(root.glob('*.png'))
assert len(files) == 18, f'expected 18 badges, got {len(files)}'
for path in files:
    image = Image.open(path)
    assert image.size == (128, 128) and image.mode == 'RGBA', path
    assert image.getchannel('A').getbbox(), path
print(f'PASS: {len(files)} badge sprites are RGBA 128x128')
