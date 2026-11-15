"""Original Lifevuww sprites. Run from anywhere with Python and Pillow."""
from pathlib import Path
from PIL import Image, ImageDraw, ImageFont

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'assets/images'
INK = '#10121e'
sprites = []

class Sprite:
    def __init__(self, group, key, name, palette):
        self.group, self.key, self.name = group, key, name
        self.im = Image.new('RGBA', (64, 64))
        self.d = ImageDraw.Draw(self.im)
        self.pal = [INK] + palette

    def p(self, points, c, edge=True):
        self.d.polygon(points, fill=self.pal[c], outline=INK if edge else None)

    def r(self, box, c):
        self.d.rectangle(box, fill=self.pal[c])

    def eye(self, x, y, c=4):
        self.r((x, y, x+2, y+1), c)
        self.r((x, y, x, y), 5)

    def gem(self, x, y, c=4):
        self.p([(x,y-4),(x+3,y),(x,y+4),(x-3,y)], c)
        self.r((x-1,y-2,x,y),5)

    def save(self):
        path = OUT / self.group / (self.key + '.png')
        path.parent.mkdir(parents=True, exist_ok=True)
        self.im.resize((128,128), Image.Resampling.NEAREST).save(path)
        sprites.append(self)

def make(group,key,name,palette):
    return Sprite(group,key,name,palette)

steel=['#29313e','#4b5c6c','#91a7b4','#36ddc0','#d2fff1']
ice=['#203752','#355b80','#729bc4','#66e6ff','#e3fcff']
violet=['#292039','#4c365f','#80658d','#b18aff','#f0ddff']
red=['#492030','#8c344e','#d35c73','#ffab72','#fff1cf']
gold=['#453325','#8c6634','#c99d51','#ffe28a','#fff8db']

# Ironhide: low armored beetle, six articulated legs and tusks.
s=make('bosses','gate_e_crawler','Ironhide Crawler',steel)
for x,dx in [(20,-12),(44,12)]:
    for y in (29,38,47):
        s.p([(x,y),(x+dx,y-4),(x+dx+( -3 if dx<0 else 3),y+5),(x+dx,y+9),(x+dx,y+2),(x,y+4)],2)
s.p([(18,23),(24,15),(40,15),(47,24),(47,42),(41,50),(23,50),(17,42)],1)
s.p([(21,25),(27,18),(31,18),(31,43),(23,46),(20,40)],3)
s.p([(33,18),(38,18),(44,25),(44,40),(34,44)],2)
for y in (26,33,40): s.r((22,y,29,y+1),2); s.r((35,y,42,y+1),1)
s.p([(23,44),(41,44),(43,51),(37,57),(27,57),(21,51)],2)
s.eye(25,48); s.eye(36,48)
s.p([(23,51),(19,56),(23,60),(26,54)],3)
s.p([(41,51),(45,56),(41,60),(38,54)],3)
s.gem(32,24); s.save()

# Frostveil: floating robes, angular hood and crystal-tipped staff.
s=make('bosses','gate_c_lich','Frostveil Archmage',ice)
s.p([(26,25),(38,25),(42,38),(46,56),(36,53),(32,58),(26,54),(17,57),(22,39)],1)
s.p([(27,30),(31,34),(28,51),(22,53)],2)
s.p([(36,30),(39,38),(41,51),(34,49)],3)
s.p([(21,24),(23,17),(30,7),(37,16),(41,25),(35,31),(26,30)],2)
s.p([(26,20),(35,20),(37,26),(32,30),(26,26)],0)
s.eye(27,23); s.eye(33,23)
s.p([(23,32),(16,36),(13,43),(18,44),(26,39)],2)
s.p([(38,33),(45,37),(50,33),(52,37),(46,43),(39,40)],2)
s.r((50,18,52,54),3); s.gem(51,15)
s.r((29,34,33,36),4); s.r((31,39,32,47),4)
for x,y in [(13,22),(43,12),(13,51)]: s.gem(x,y)
s.save()

# Colossus: asymmetric stone slabs with luminous fracture lines.
s=make('bosses','gate_b_colossus','Obsidian Colossus',violet)
for pts in [[(17,40),(29,40),(28,56),(16,57)],[(35,40),(46,40),(49,57),(36,57)],[(8,25),(17,18),(23,25),(18,44),(8,41)],[(43,23),(53,20),(59,30),(55,47),(46,43)]]: s.p(pts,2)
s.p([(19,22),(27,18),(40,19),(47,27),(43,43),(35,48),(23,42)],1)
s.p([(23,24),(31,23),(29,34),(21,32)],3)
s.p([(34,24),(42,25),(41,37),(34,42),(31,34)],2)
s.p([(25,10),(37,9),(42,15),(39,23),(25,23),(22,17)],2)
s.r((26,16,29,17),4);s.r((34,16,37,17),4)
s.gem(32,31)
for box in [(12,28,13,36),(48,28,49,37),(22,47,23,53),(39,46,40,53)]:s.r(box,4)
s.save()

# Valkyrie: wide feathered wings and a long spear.
s=make('bosses','gate_a_valkyrie','Crimson Valkyrie',red)
for mirror in (False,True):
    pts=[(27,29),(18,20),(7,10),(9,27),(15,33),(9,31),(16,40),(26,38)]
    s.p([(64-x if mirror else x,y) for x,y in pts],2)
    for x,y in [(11,20),(15,26),(19,31)]:
        xx=64-x if mirror else x;s.r((xx,y,xx+1,y+5),3)
s.p([(27,31),(37,31),(39,42),(34,49),(25,44)],2)
s.p([(28,43),(32,44),(29,57),(24,57)],1);s.p([(34,44),(38,43),(40,56),(35,56)],1)
s.p([(25,18),(29,14),(36,14),(40,20),(36,31),(28,29)],1)
s.p([(28,20),(36,20),(36,26),(32,29),(28,26)],3)
s.r((28,22,36,23),0);s.eye(29,22);s.eye(34,22)
s.p([(25,21),(20,14),(21,9),(30,18)],3)
s.p([(38,21),(44,14),(43,9),(35,18)],3)
s.r((48,22,49,57),3);s.p([(49,7),(53,19),(49,24),(45,19)],4)
s.p([(36,32),(43,36),(48,32),(50,36),(43,40),(37,38)],2)
s.gem(32,34);s.save()

# Abyss monarch: crown, broad mantle and spectral lower body.
s=make('bosses','gate_s_monarch','Sovereign of the Abyss',gold)
s.p([(21,24),(12,28),(8,52),(16,49),(20,58),(28,54),(32,60),(38,54),(46,57),(49,49),(57,53),(51,28),(41,24)],1)
s.p([(18,29),(24,27),(27,48),(22,52)],2);s.p([(39,27),(46,29),(48,50),(39,47)],2)
s.p([(25,29),(39,29),(42,42),(34,51),(24,43)],0)
s.p([(25,15),(39,15),(41,23),(36,31),(28,30),(23,23)],2)
s.p([(26,19),(38,19),(36,26),(29,26)],0)
s.eye(27,21);s.eye(35,21)
s.p([(23,16),(20,7),(28,11),(32,4),(36,11),(44,7),(41,16)],3)
s.gem(32,13);s.gem(32,36)
for x in (13,49):s.p([(x,32),(x+4,29),(x+6,35),(x+2,38)],3)
s.r((29,44,35,45),4);s.save()

# Wolf: pointed ears, raised tail, four paws and emerald neck ruff.
s=make('companions','obsidian_wolf','Shadow Fang Wolf',steel)
s.p([(39,40),(48,35),(51,25),(57,21),(56,36),(50,45),(41,47)],1)
s.p([(20,35),(40,34),(46,42),(41,49),(22,48),(17,42)],2)
for x in (21,28,37,42):s.p([(x,44),(x+4,44),(x+3,55),(x-2,55)],1)
s.p([(14,20),(22,26),(31,20),(32,38),(27,44),(17,43),(11,34)],1)
s.p([(16,24),(19,29),(15,30)],3);s.p([(28,25),(26,30),(30,30)],3)
s.p([(14,34),(21,31),(30,34),(26,39),(20,42)],2)
s.eye(16,33);s.eye(26,33)
s.p([(19,38),(24,38),(22,41)],0)
s.p([(19,44),(26,40),(33,42),(29,48),(24,46),(22,50)],4)
s.r((15,53,19,55),3);s.save()

# Owl: compact body, oversized eye disks, clock medallion.
s=make('companions','chrono_owl','Chrono Starlight Owl',ice)
s.p([(18,27),(46,27),(49,44),(42,53),(23,53),(15,43)],1)
s.p([(17,32),(11,37),(13,48),(22,44)],2);s.p([(46,32),(53,37),(51,48),(43,44)],2)
s.p([(18,28),(16,13),(26,19),(38,19),(48,13),(46,31),(39,37),(24,37)],2)
for x in (24,39):
    s.p([(x-6,24),(x,20),(x+6,24),(x+6,30),(x,35),(x-6,30)],3)
    s.r((x-2,25,x+2,30),0);s.eye(x-1,26)
s.p([(29,32),(35,32),(32,37)],4)
s.p([(26,39),(38,39),(40,47),(32,52),(24,47)],3)
s.r((31,40,32,46),1);s.r((32,45,36,46),1)
for x in (24,37):s.r((x,53,x+4,55),4)
s.save()

# Drake: little upright dragon, bat wings, curling flame tail.
s=make('companions','crimson_drake','Crimson Ember Drake',red)
s.p([(22,32),(11,20),(7,24),(9,42),(15,36),(23,42)],2)
s.p([(39,32),(50,19),(56,25),(54,42),(47,36),(39,42)],2)
s.p([(39,44),(47,50),(53,44),(55,35),(59,42),(57,51),(49,56),(38,52)],1)
s.p([(25,30),(37,30),(42,46),(37,54),(24,53),(20,44)],2)
s.p([(28,36),(35,36),(37,49),(27,49)],3)
s.p([(24,20),(25,15),(39,15),(43,21),(40,31),(30,34),(22,28)],2)
s.p([(25,17),(22,8),(30,15)],3);s.p([(37,16),(43,9),(41,21)],3)
s.eye(27,23);s.eye(37,23)
s.r((31,29,36,30),1)
s.p([(54,39),(52,33),(56,27),(57,32),(61,33),(58,41)],4)
s.r((22,53,28,56),1);s.r((35,53,41,56),1);s.save()

# Griffin: eagle face and wings, lion body and curled tail.
s=make('companions','auric_griffin','Auric Sovereign Griffin',gold)
s.p([(38,43),(49,44),(52,37),(50,33),(53,31),(56,37),(54,47),(42,49)],2)
s.p([(19,37),(40,36),(46,44),(40,50),(20,49)],2)
for x in (20,28,37):s.p([(x,45),(x+4,45),(x+3,56),(x-2,56)],3)
s.p([(29,38),(32,21),(47,11),(47,26),(39,38)],3)
for x,y in [(36,22),(40,19),(44,16)]:s.r((x,y,x+1,y+12),2)
s.p([(15,21),(22,17),(31,21),(31,32),(25,41),(16,37),(12,29)],3)
s.p([(13,26),(21,22),(26,26),(25,33),(17,35)],5)
s.eye(22,26,1)
s.p([(13,28),(8,30),(14,34),(19,32)],4)
s.p([(20,19),(18,13),(24,16),(28,12),(29,21)],4)
s.save()

# Tortoise: layered shield shell and indigo crystal spines.
s=make('companions','aegis_tortoise','Aegis Spirit Tortoise',violet)
for x in (18,38):s.p([(x,43),(x+7,43),(x+5,54),(x-2,54)],2)
s.p([(16,34),(22,23),(39,21),(47,30),(48,43),(41,49),(22,49),(14,43)],1)
s.p([(23,27),(37,25),(43,32),(43,41),(36,45),(23,43),(19,36)],2)
s.p([(28,28),(35,27),(39,34),(35,41),(28,40),(24,34)],3)
s.gem(32,34)
for x,y in [(22,25),(33,21),(42,27)]:s.p([(x-3,y),(x,y-8),(x+3,y)],4)
s.p([(13,37),(8,38),(6,44),(10,49),(18,47),(19,41)],2)
s.eye(9,41)
s.p([(47,39),(55,42),(49,45)],2);s.save()

# Chrono blade: diagonal obsidian blade, teal edge and gold crossguard.
s=make('relics','chrono_blade','Obsidian Chrono Blade',steel)
s.p([(16,48),(11,53),(14,57),(20,51)],2)
s.p([(20,42),(26,48),(19,55),(13,49)],1)
s.p([(21,39),(24,36),(34,46),(31,49)],3)
s.p([(24,36),(43,12),(54,7),(51,20),(32,43)],1)
s.p([(28,36),(45,15),(50,12),(47,22),(32,40)],2)
s.p([(50,10),(49,20),(31,42),(29,39)],4)
s.gem(27,42);s.r((15,49,16,51),4)
s.save()

# Archon signet: chunky gold ring, hexagonal bezel, engraved glyph.
s=make('relics','archon_signet','Signet of the Archon',gold)
s.p([(17,29),(24,22),(40,22),(48,30),(48,46),(41,54),(24,54),(16,46)],1)
s.p([(20,33),(26,28),(38,28),(44,34),(44,44),(38,50),(26,50),(20,44)],3)
s.p([(26,35),(38,35),(39,44),(36,46),(28,46),(25,43)],0)
s.p([(20,17),(27,11),(39,11),(46,18),(44,31),(37,37),(26,35),(19,28)],3)
s.p([(25,19),(29,16),(37,16),(41,20),(39,28),(34,32),(26,29)],1)
s.p([(28,26),(31,20),(34,24),(37,20),(37,27),(33,29)],4)
s.r((23,39,24,45),5);s.r((39,35,41,39),2);s.save()

# Mantle: flared midnight cloak, pale fur collar and gold clasp.
s=make('relics','sovereign_mantle','Aegis Sovereign Mantle',gold)
s.p([(23,17),(40,17),(48,25),(52,55),(42,52),(33,57),(23,53),(12,56),(17,26)],1)
s.p([(23,25),(29,28),(24,49),(17,51)],2)
s.p([(36,27),(42,24),(46,49),(39,47)],2)
s.p([(27,27),(36,27),(40,49),(32,53),(25,49)],0)
s.p([(20,16),(25,11),(32,15),(39,11),(45,17),(43,25),(38,23),(34,29),(30,25),(25,29),(21,24)],3)
s.p([(23,17),(26,15),(31,19),(37,15),(42,18),(39,22),(34,21),(29,24),(25,21)],5)
s.gem(32,26)
for x,y in [(18,45),(43,43),(28,47)]:s.r((x,y,x+1,y+4),3)
s.save()

# Evolution keeps each original sprite intact. Hatchlings have their own compact
# anatomy; later stages gain species-specific equipment and a wider silhouette.
companions = [sprite for sprite in sprites if sprite.group == 'companions']
evolutions = []
for base in companions:
    for stage in ('hatchling', 'awakened', 'sovereign'):
        s = make('companions', base.key + '_' + stage, base.name, base.pal[1:])
        if stage == 'hatchling':
            if base.key == 'obsidian_wolf':
                s.p([(23,39),(40,38),(46,43),(42,50),(24,50),(19,45)],2)
                s.p([(40,42),(49,37),(50,30),(54,34),(53,43),(44,48)],1)
                for x in (25,37): s.r((x,48,x+4,55),1)
                s.p([(18,24),(25,29),(34,24),(37,39),(31,46),(20,44),(16,36)],1)
                s.p([(20,34),(33,34),(33,40),(25,44),(20,40)],2)
                s.eye(21,35);s.eye(30,35);s.r((25,40,27,41),0)
                s.p([(23,46),(32,44),(34,48),(28,51)],4)
            elif base.key == 'chrono_owl':
                s.p([(23,33),(40,33),(44,45),(38,53),(25,53),(20,45)],2)
                s.p([(22,38),(16,41),(18,48),(24,46)],1)
                s.p([(41,38),(47,41),(45,48),(39,46)],1)
                s.p([(20,32),(22,23),(28,25),(36,25),(42,23),(44,33),(39,41),(25,41)],2)
                for x in (26,37):
                    s.p([(x-5,30),(x,27),(x+5,30),(x+4,36),(x,39),(x-4,36)],3)
                    s.eye(x-1,32)
                s.p([(29,38),(35,38),(32,42)],4)
                s.gem(32,46);s.r((25,53,28,55),4);s.r((36,53,39,55),4)
            elif base.key == 'crimson_drake':
                s.p([(23,40),(16,32),(14,35),(16,45),(24,46)],2)
                s.p([(39,40),(46,32),(49,36),(46,46),(39,46)],2)
                s.p([(37,48),(46,51),(50,46),(50,40),(54,45),(52,52),(44,56),(36,54)],1)
                s.p([(26,34),(37,34),(41,49),(37,54),(25,54),(22,46)],2)
                s.p([(23,26),(25,21),(38,21),(42,27),(39,37),(28,39),(21,33)],2)
                s.p([(25,24),(24,17),(30,22)],3);s.p([(36,23),(41,18),(40,27)],3)
                s.eye(26,29);s.eye(36,29);s.r((29,42,34,49),3)
                s.p([(50,43),(49,38),(53,34),(55,40),(53,45)],4)
                s.r((24,53,29,56),1);s.r((35,53,40,56),1)
            elif base.key == 'auric_griffin':
                s.p([(24,40),(41,39),(46,46),(41,51),(24,51)],2)
                s.p([(41,46),(49,46),(50,40),(54,41),(53,50),(43,53)],2)
                for x in (26,38): s.r((x,49,x+4,56),3)
                s.p([(32,40),(35,29),(43,25),(42,35),(38,43)],3)
                s.p([(18,29),(25,24),(33,28),(34,38),(28,45),(19,41),(16,35)],3)
                s.p([(18,32),(25,29),(29,32),(27,39),(20,39)],5)
                s.eye(24,33,1);s.p([(19,35),(13,37),(20,41),(24,38)],4)
                s.p([(23,26),(24,20),(29,25)],4)
            else:
                for x in (24,38): s.r((x,46,x+5,54),2)
                s.p([(21,36),(28,29),(39,29),(46,36),(46,46),(39,50),(26,49),(20,44)],1)
                s.p([(27,34),(37,32),(42,38),(40,45),(29,46),(24,40)],2)
                s.gem(33,39);s.p([(30,30),(33,23),(36,30)],4)
                s.p([(20,40),(13,40),(10,45),(13,50),(22,48)],2);s.eye(14,44)
                s.p([(46,42),(52,45),(46,47)],2)
        else:
            s.im = base.im.copy()
            s.d = ImageDraw.Draw(s.im)
            if base.key == 'obsidian_wolf':
                # Jagged armored ruff and a long spectral tail plume.
                s.p([(12,40),(17,43),(20,39),(24,43),(30,38),(35,44),(31,51),(25,49),(21,54),(17,48)],3)
                s.gem(25,46)
                s.p([(47,36),(51,24),(58,16),(59,29),(54,40)],4)
                if stage == 'sovereign':
                    s.p([(29,36),(35,26),(39,30),(43,20),(47,36),(41,41)],3)
                    s.p([(12,24),(11,13),(18,19),(23,10),(27,19),(33,13),(32,25)],4)
                    s.gem(23,19);s.p([(38,46),(46,46),(48,51),(41,54),(36,51)],3)
            elif base.key == 'chrono_owl':
                # Unfurled clockwork wings sit behind the original body.
                wings = make('companions',s.key,s.name,base.pal[1:])
                for mirror in (False,True):
                    pts=[(21,35),(11,23),(4,25),(7,40),(13,49),(21,45)]
                    wings.p([(63-x if mirror else x,y) for x,y in pts],3)
                wings.im.alpha_composite(s.im);s.im=wings.im;s.d=ImageDraw.Draw(s.im)
                s.gem(32,41);s.r((31,42,32,47),1)
                if stage == 'sovereign':
                    s.p([(16,18),(14,7),(25,14),(32,5),(39,14),(50,7),(48,18),(38,21),(25,21)],4)
                    s.gem(32,13)
                    for x in (8,52):s.gem(x,48)
                    s.p([(25,49),(29,59),(32,54),(35,59),(39,49)],3)
            elif base.key == 'crimson_drake':
                s.p([(8,27),(4,13),(19,22),(24,32),(16,29),(12,37)],3)
                s.p([(55,26),(59,12),(45,21),(39,32),(48,28),(52,36)],3)
                s.p([(25,33),(31,37),(38,33),(37,41),(31,44),(26,40)],1);s.gem(31,38)
                if stage == 'sovereign':
                    s.p([(25,17),(18,5),(27,9),(31,3),(36,10),(46,5),(40,19)],4)
                    s.p([(42,38),(47,40),(44,45),(49,47),(43,50),(39,45)],3)
                    s.p([(54,38),(49,30),(52,20),(56,25),(60,19),(62,30),(59,40)],4)
                    s.gem(31,15)
            elif base.key == 'auric_griffin':
                s.p([(28,35),(27,22),(35,10),(40,7),(39,23),(35,37)],5)
                s.p([(17,39),(21,36),(27,40),(32,36),(34,43),(28,48),(22,46),(18,49)],3)
                s.gem(25,42)
                if stage == 'sovereign':
                    s.p([(37,31),(46,6),(55,3),(53,22),(48,34),(42,39)],4)
                    for x,y in ((45,18),(49,13)): s.r((x,y,x+1,y+12),2)
                    s.p([(16,21),(13,10),(21,15),(25,7),(29,15),(34,10),(32,23)],4)
                    s.gem(25,17)
                    s.p([(39,43),(46,41),(48,47),(43,52),(38,49)],3)
            else:
                s.p([(17,34),(15,25),(21,28),(25,20),(29,26),(37,23),(46,28),(50,39),(46,47),(41,43),(42,33),(35,29),(25,31),(21,41)],3)
                for x,y in ((22,26),(33,22),(44,29)):s.gem(x,y)
                if stage == 'sovereign':
                    s.p([(23,24),(20,13),(27,17),(32,5),(37,17),(45,12),(42,28)],4)
                    s.gem(32,17)
                    s.p([(17,34),(8,28),(4,32),(7,40),(15,41)],3)
                    s.p([(47,35),(57,29),(60,34),(56,43),(48,45)],3)
                    s.p([(25,43),(32,47),(40,43),(39,51),(32,57),(24,51)],3)
                    s.gem(32,49)
        s.save()
        evolutions.append(s)

# A readable review board, kept separate from shipped image assets.
board=Image.new('RGB',(1000,1480),'#171c27')
d=ImageDraw.Draw(board)
font=ImageFont.truetype('C:/Windows/Fonts/arial.ttf',16)
small=ImageFont.truetype('C:/Windows/Fonts/arial.ttf',13)
d.text((28,18),'LIFEVUWW / DARK FANTASY PIXEL COLLECTION',font=font,fill='#edf2f7')
for i,s in enumerate([sprite for sprite in sprites if sprite not in evolutions]):
    col,row=i%5,i//5;x,y=col*196+12,row*230+60
    d.rounded_rectangle((x,y,x+184,y+210),radius=8,fill='#222a39')
    board.paste(s.im.resize((128,128),Image.Resampling.NEAREST),(x+28,y+10),s.im.resize((128,128),Image.Resampling.NEAREST))
    d.text((x+10,y+150),s.name,font=small,fill='#edf2f7')
    d.text((x+10,y+177),s.group.upper()+'  /  128 x 128',font=small,fill='#9aa9bd')
d.text((28,755),'COMPANION EVOLUTION / HATCHLING > AWAKENED > SOVEREIGN',font=font,fill='#edf2f7')
for i,s in enumerate(evolutions):
    col,row=i//3,i%3;x,y=col*196+12,row*224+795
    d.rounded_rectangle((x,y,x+184,y+210),radius=8,fill='#222a39')
    image=s.im.resize((128,128),Image.Resampling.NEAREST)
    board.paste(image,(x+28,y+8),image)
    d.text((x+10,y+146),s.name,font=small,fill='#edf2f7')
    d.text((x+10,y+174),s.key.rsplit('_',1)[1].upper(),font=small,fill='#9aa9bd')
preview=ROOT/'docs/asset-preview.png'
board.save(preview)
print(f'Generated {len(sprites)} transparent sprites and {preview}')
