from PIL import Image
from pathlib import Path

src = Path(r'public/images/cspc-logo.png')
# Re-copy from original asset if needed; process current file with flood-fill
img = Image.open(src).convert('RGBA')
w, h = img.size
pixels = img.load()

# Restore near-black pixels that may have been half-cleared, then flood-fill from edges
def is_backdrop(r, g, b, a):
    # Black / near-black outside the seal (not navy gear blues)
    return a < 40 or (r <= 40 and g <= 40 and b <= 40 and max(r, g, b) - min(r, g, b) <= 12)

visited = [[False] * w for _ in range(h)]
stack = []

for x in range(w):
    stack.append((x, 0))
    stack.append((x, h - 1))
for y in range(h):
    stack.append((0, y))
    stack.append((w - 1, y))

while stack:
    x, y = stack.pop()
    if x < 0 or y < 0 or x >= w or y >= h or visited[y][x]:
        continue
    visited[y][x] = True
    r, g, b, a = pixels[x, y]
    if not is_backdrop(r, g, b, a):
        continue
    pixels[x, y] = (0, 0, 0, 0)
    stack.extend(((x + 1, y), (x - 1, y), (x, y + 1), (x, y - 1)))

img.save(src, 'PNG')

# Verify corner alpha
print('corner alpha', pixels[0, 0][3], pixels[w // 2, 0][3], pixels[0, h // 2][3])
# Count transparent
transparent = sum(1 for y in range(h) for x in range(w) if pixels[x, y][3] == 0)
print('transparent_pixels', transparent, 'of', w * h)

public = Path('public')

def save_resized(path, size):
    resized = img.copy()
    resized.thumbnail(size, Image.Resampling.LANCZOS)
    canvas = Image.new('RGBA', size, (0, 0, 0, 0))
    offset = ((size[0] - resized.width) // 2, (size[1] - resized.height) // 2)
    canvas.paste(resized, offset, resized)
    canvas.save(path, 'PNG')
    return canvas

icons = [save_resized(public / f'_tmp_icon_{s}.png', (s, s)) for s in (16, 32, 48)]
save_resized(public / 'favicon-32x32.png', (32, 32))
save_resized(public / 'favicon-16x16.png', (16, 16))
save_resized(public / 'apple-touch-icon.png', (180, 180))
save_resized(public / 'images/cspc-logo-192.png', (192, 192))
icons[0].save(public / 'favicon.ico', format='ICO', sizes=[(16, 16), (32, 32), (48, 48)], append_images=icons[1:])
for s in (16, 32, 48):
    (public / f'_tmp_icon_{s}.png').unlink(missing_ok=True)
print('done')
