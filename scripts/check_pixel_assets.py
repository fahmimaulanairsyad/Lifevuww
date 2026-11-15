"""Validate shipped sprite references and transparent bounds."""
from pathlib import Path
import re
from PIL import Image

root = Path(__file__).resolve().parents[1]
paths = re.findall(r"assets/images/(?:bosses|companions|relics)/[^']+\.png", (root / 'config/game.php').read_text(encoding='utf-8'))
assert len(paths) == len(set(paths)) == 13
total = 0
for path in paths:
    image = Image.open(root / path)
    assert image.size == (128, 128) and image.mode == 'RGBA', path
    alpha = image.getchannel('A')
    assert alpha.getextrema() == (0, 255), path
    left, top, right, bottom = alpha.getbbox()
    assert left > 0 and top > 0 and right < 128 and bottom < 128, path
    total += (root / path).stat().st_size
print(f'PASS: 13 catalog assets, RGBA 128x128, transparent margins, {total:,} bytes total')
