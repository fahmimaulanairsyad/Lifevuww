"""Generate original pixel badges for ranks, attributes, classes and boss quests."""
from pathlib import Path
from PIL import Image, ImageDraw

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'assets/images/badges'
OUT.mkdir(parents=True, exist_ok=True)

palettes = {
    'f_rank_novice': ['#252a35', '#596273', '#aeb8c8', '#d8e2ef'],
    'e_rank_rookie': ['#183b3b', '#287f79', '#55c4a9', '#c4fff0'],
    'd_rank_explorer': ['#1d3150', '#386ca8', '#71b8ff', '#e1f2ff'],
    'c_rank_vanguard': ['#263249', '#4d6b96', '#91b7e8', '#e9f3ff'],
    'b_rank_elite': ['#3f2b14', '#8b5a1f', '#d49a3a', '#ffe3a1'],
    'a_rank_master': ['#3e1e2b', '#9c3d57', '#ed7187', '#ffe0e5'],
    's_rank_sovereign': ['#3b2911', '#a56c18', '#f4c34e', '#fff5bd'],
    'intelligence': ['#173746', '#1d8fa6', '#5ce2e5', '#d8ffff'],
    'physical': ['#3d1d29', '#a23a54', '#ef6e79', '#ffe0df'],
    'health': ['#17382d', '#228b60', '#64d29b', '#d8ffe9'],
    'wealth': ['#463015', '#b37922', '#e6b64b', '#fff1b5'],
    'social': ['#30234e', '#744cc0', '#b38aff', '#f0e7ff'],
    'obsidian_vanguard': ['#192d2c', '#237d6d', '#52c5a1', '#d7fff3'],
    'arcane_scholar': ['#16334a', '#247fa1', '#61d0f1', '#d9f7ff'],
    'crimson_striker': ['#481b2b', '#ad3751', '#f06c76', '#ffe0df'],
    'solar_sovereign': ['#493116', '#ad7525', '#f0bc47', '#fff2bb'],
    'abyssal_archon': ['#2e1947', '#6c3aa0', '#ae71e5', '#f1dcff'],
    'boss_quest': ['#381827', '#923a52', '#e96b72', '#ffe3d1'],
}

def sprite(key):
    im = Image.new('RGBA', (64, 64))
    d = ImageDraw.Draw(im)
    p = ['#111522'] + palettes[key]
    def poly(points, color, outline=True): d.polygon(points, fill=p[color], outline=p[0] if outline else None)
    def rect(box, color): d.rectangle(box, fill=p[color])
    def gem(x,y,color=4): poly([(x,y-5),(x+4,y),(x,y+5),(x-4,y)],color)
    # Every family has its own silhouette, with rank progression encoded geometrically.
    if key.endswith('_rank_novice') or '_rank_' in key:
        rank = key.split('_rank_')[0]
        if rank == 'f':
            poly([(16,42),(20,25),(28,16),(36,25),(44,42),(38,52),(22,52)],1); poly([(23,27),(32,20),(41,27),(36,37),(26,37)],2); rect((29,30,34,35),4)
        elif rank == 'e':
            poly([(16,44),(18,24),(25,14),(32,24),(39,14),(46,24),(48,44),(40,52),(24,52)],1); poly([(23,28),(32,20),(41,28),(38,40),(26,40)],2); gem(32,31)
        elif rank == 'd':
            poly([(13,45),(20,36),(20,19),(28,25),(36,19),(44,36),(51,45),(42,53),(22,53)],1); poly([(23,35),(32,22),(41,35),(38,46),(26,46)],2); rect((29,32,34,37),4)
        elif rank == 'c':
            poly([(14,40),(20,19),(29,25),(35,16),(44,27),(50,40),(42,52),(22,52)],1); poly([(22,31),(32,22),(42,31),(39,43),(25,43)],2); gem(32,33)
        elif rank == 'b':
            poly([(12,45),(18,27),(25,35),(32,15),(39,35),(46,27),(52,45),(43,54),(21,54)],2); poly([(23,27),(32,18),(41,27),(37,38),(27,38)],3); gem(32,31)
        elif rank == 'a':
            poly([(11,47),(18,28),(25,34),(32,10),(39,34),(46,28),(53,47),(43,55),(21,55)],2); poly([(20,25),(32,15),(44,25),(39,39),(25,39)],3); rect((29,27,35,34),4); rect((30,44,34,48),4)
        else:
            poly([(9,48),(16,29),(24,35),(32,7),(40,35),(48,29),(55,48),(43,57),(21,57)],2); poly([(18,22),(32,11),(46,22),(40,40),(24,40)],3); gem(32,29); rect((29,45,35,49),4)
    elif key in ('intelligence','physical','health','wealth','social'):
        if key == 'intelligence': poly([(32,8),(39,17),(48,18),(44,27),(48,36),(38,39),(32,55),(26,39),(16,36),(20,27),(16,18),(25,17)],2); gem(32,27,4); rect((27,42,37,45),3)
        elif key == 'physical': poly([(23,13),(31,20),(33,29),(42,14),(48,18),(39,35),(53,36),(53,45),(38,44),(36,55),(27,55),(27,44),(12,45),(12,36),(26,35),(16,18)],2); rect((29,29,35,37),3)
        elif key == 'health': poly([(32,53),(13,31),(13,21),(22,15),(32,21),(42,15),(51,21),(51,31)],2); poly([(32,45),(21,30),(21,23),(27,19),(32,24),(37,19),(43,23),(43,30)],3); rect((29,25,35,38),4); rect((23,31,41,35),4)
        elif key == 'wealth': poly([(14,23),(50,23),(50,46),(14,46)],1); poly([(20,19),(44,19),(49,25),(15,25)],2); poly([(20,46),(44,46),(38,54),(26,54)],2); gem(32,35,4); rect((28,32,36,38),3)
        else: poly([(32,12),(41,20),(48,22),(43,29),(52,38),(43,42),(32,53),(21,42),(12,38),(21,29),(16,22),(23,20)],2); rect((29,24,35,30),3); rect((24,34,40,38),4); gem(32,47,3)
    elif key == 'boss_quest':
        poly([(10,43),(16,26),(23,30),(25,15),(32,23),(39,15),(41,30),(48,26),(54,43),(45,54),(19,54)],1); poly([(20,31),(27,27),(32,33),(37,27),(44,31),(40,43),(24,43)],2); rect((27,34,30,38),4); rect((34,34,37,38),4); poly([(24,46),(32,39),(40,46),(36,52),(28,52)],3); gem(32,26,4)
    else:
        # Class crests use a unique central mark and outer silhouette.
        cls = key.split('_')[0]
        if cls == 'obsidian': poly([(32,8),(43,17),(52,32),(43,48),(32,56),(21,48),(12,32),(21,17)],1); poly([(32,14),(45,31),(32,48),(19,31)],2); rect((27,28,37,34),4)
        elif cls == 'arcane': poly([(16,48),(20,19),(32,10),(44,19),(48,48),(32,56)],1); poly([(24,37),(32,17),(40,37),(32,48)],3); gem(32,29,4)
        elif cls == 'crimson': poly([(13,48),(23,13),(32,23),(41,13),(51,48),(41,56),(23,56)],1); poly([(25,29),(32,18),(39,29),(36,44),(28,44)],2); rect((29,32,35,37),4)
        elif cls == 'solar': poly([(32,8),(38,21),(52,22),(42,32),(46,47),(32,41),(18,47),(22,32),(12,22),(26,21)],2); gem(32,29,4); rect((30,39,34,46),3)
        else: poly([(12,22),(26,22),(32,10),(38,22),(52,22),(43,33),(46,49),(32,43),(18,49),(21,33)],1); poly([(25,28),(32,18),(39,28),(36,42),(28,42)],3); gem(32,33,4)
    return im.resize((128,128), Image.Resampling.NEAREST)

for key in palettes:
    sprite(key).save(OUT / (key + '.png'))
print(f'Generated {len(palettes)} original badge sprites in {OUT}')
