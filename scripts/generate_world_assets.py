"""Original Nawasela landscape sprites. Pillow, integer pixels, no borrowed art."""
from pathlib import Path
import random
from PIL import Image, ImageDraw

OUT=Path(__file__).resolve().parents[1]/'assets/images/world'
OUT.mkdir(parents=True,exist_ok=True)
INK='#111c2a'
ROCK='#263341'
STONE='#6e858a'
SAND='#b8b69c'
GREEN='#375b4b'
LIGHT='#789b63'
states=['quiet','awakening','flourishing']
keys=['aksarimba','lintasura','teduhara','bekalara','temuraya']

def draw_region(key,state):
    rng=random.Random(key)
    im=Image.new('RGBA',(192,144))
    d=ImageDraw.Draw(im)
    stage=states.index(state)
    def poly(points,fill,edge=INK): d.polygon(points,fill=fill,outline=edge)
    def rect(box,fill): d.rectangle(box,fill=fill)
    def line(points,fill,width=1):d.line(points,fill=fill,width=width)
    # Terraced floating rock with hand-placed fractured strata.
    shore=[(16,81),(29,64),(49,58),(60,50),(84,53),(104,46),(130,54),(155,60),(177,77),(169,96),(145,105),(111,111),(74,107),(50,113),(28,102)]
    poly([(x,y+9) for x,y in shore],ROCK)
    poly([(28,100),(49,113),(74,107),(111,111),(145,105),(166,96),(149,118),(130,122),(118,134),(102,125),(79,131),(67,119),(44,122)],ROCK)
    for pts in [[(46,112),(57,116),(62,122)],[(83,111),(83,124)],[(120,111),(117,125)],[(147,104),(139,117)]]:line(pts,'#415260',2)
    poly(shore,GREEN if key!='lintasura' else '#636e65')
    line([(19,84),(30,101),(48,109),(74,104),(111,108),(144,102),(166,94)],'#9c9e78',2)
    for _ in range(120):
        x=rng.randrange(26,166);y=rng.randrange(65,99)
        if im.getpixel((x,y))[:3] in [(55,91,75),(99,110,101)]: rect((x,y,x+1,y),rng.choice(['#426552','#496d55','#688462']))
    def tree(x,y,size=1):
        rect((x-1,y-6,x+1,y+3),'#5b4c3d')
        poly([(x,y-24*size),(x-8*size,y-12),(x-5,y-12),(x-11*size,y-4),(x+10*size,y-4),(x+5,y-12),(x+8*size,y-12)],'#254b43')
        line([(x,y-21*size),(x-5*size,y-11),(x-2,y-11),(x-7*size,y-5)],'#4f8060',2)
    def roof(x,y,w=22,h=12,color='#416c6c'):
        poly([(x-w//2,y),(x,y-h),(x+w//2,y),(x+w//2-3,y+4),(x-w//2-3,y+4)],color)
        line([(x-w//2,y),(x,y-h),(x+w//2,y)],'#8caa96')
    def building(x,y,w=24,h=22):
        poly([(x-w//2,y-h),(x+w//2,y-h),(x+w//2,y),(x,y+6),(x-w//2,y)],'#758e86')
        poly([(x,y-h),(x+w//2,y-h),(x+w//2,y),(x,y+6)],'#445f60')
        rect((x-3,y-8,x+3,y+2),'#172d38')
        for dx in [-8,7]:rect((x+dx-1,y-h+6,x+dx+1,y-h+10),'#e6cb83' if stage else '#374c51')
        roof(x,y-h,w+10,12)
    def flowers(x,y):
        for dx,dy in [(-3,1),(3,-1),(0,-3)]:rect((x+dx,y+dy,x+dx+1,y+dy+1),'#dbb372')
    if key=='aksarimba':
        for x,y in [(43,81),(61,68),(143,78),(161,90),(36,99)]:tree(x,y)
        poly([(72,89),(97,79),(126,92),(101,105)],SAND)
        building(96,83,39,32)
        building(120,85,17,24)
        roof(96,39,22,17)
        rect((90,36,102,43),'#9eaf99');roof(96,34,23,13)
        for x in [83,90,97,104]:rect((x,59,x+2,68),'#35505c')
        # Outdoor reading stair, clearly a library rather than generic castle.
        for y in range(88,97,3):line([(83,y),(106,y+5)],'#b6c4a4',2)
        rect((118,89,135,94),'#9b7953');rect((122,85,132,90),'#d5c7a2')
        line([(127,85),(127,91)],'#6d705d')
        if stage:rect((93,49,100,53),'#dce3ae');flowers(65,92)
        if stage==2:
            for x,y in [(48,85),(143,93),(59,102)]:flowers(x,y)
            poly([(106,43),(117,39),(116,46),(106,49)],'#80cbbc');line([(106,40),(106,59)],SAND)
    elif key=='lintasura':
        poly([(46,85),(64,39),(77,57),(98,16),(120,55),(134,37),(158,84)],'#60767b')
        poly([(98,16),(103,53),(121,76),(99,68),(75,85),(77,57)],'#3f535f')
        poly([(87,37),(98,16),(108,39),(101,35),(96,40),(93,34)],'#d6e2d7')
        poly([(126,52),(134,37),(141,53),(135,49),(131,54)],'#cedbd1')
        line([(48,100),(74,92),(68,83),(87,75),(89,61),(102,55)],'#bcbaa0',4)
        for x,y in [(77,86),(87,76),(90,64)]:line([(x-3,y),(x+3,y-2)],'#ded4b3',2)
        line([(110,43),(110,21)],'#bdc1aa',2);poly([(110,21),(128,24),(120,29),(110,27)],'#cf8974' if stage else '#787c74')
        building(131,94,20,14)
        for x,y in [(36,89),(156,98)]:tree(x,y)
        if stage==2:flowers(54,103);flowers(137,101);rect((47,82,52,85),'#d8bb85')
    elif key=='teduhara':
        poly([(57,78),(77,65),(111,66),(142,81),(148,95),(123,103),(84,103),(58,92)],'#5a8f8d')
        poly([(64,80),(86,71),(114,72),(137,83),(139,92),(117,97),(84,98),(65,89)],'#2f777e')
        for x,y in [(78,80),(91,91),(117,81)]:line([(x,y),(x+12,y)],'#8ec4b4',2)
        poly([(93,65),(94,39),(109,35),(113,65)],'#546c70')
        rect((99,40,106,77),'#75bcc3');line([(101,41),(101,76)],'#d0eee0',2)
        poly([(84,43),(88,29),(106,24),(120,30),(121,40),(107,46)],'#749276')
        building(48,81,22,19);roof(48,59,34,16)
        for x,y in [(33,97),(145,84),(151,103)]:tree(x,y)
        if stage:
            for x,y in [(74,89),(123,93)]:rect((x,y,x+4,y+2),'#84ad65');rect((x+1,y-1,x+2,y),'#e0c099')
        if stage==2:
            for x,y in [(54,99),(144,70),(118,104)]:flowers(x,y)
    elif key=='bekalara':
        for x,y,w in [(34,82,34),(42,90,39),(52,99,44),(114,89,42)]:
            poly([(x,y),(x+w,y-7),(x+w+8,y-2),(x+8,y+6)],'#9c9158')
            for dx in range(6,w,7):line([(x+dx,y-1),(x+dx+7,y+3)],'#d6c57a' if stage else '#8c895a',2)
        building(103,79,37,25);roof(103,51,47,19,'#a7794e')
        poly([(98,54),(103,43),(109,54)],'#d3b573')
        building(140,86,17,18)
        # Windmill with crossed wood blades, not a coin icon.
        poly([(61,73),(65,39),(76,39),(81,75)],'#9b9e7c');roof(71,37,22,12,'#a7794e')
        for dx,dy in [(0,-17),(17,0),(0,17),(-17,0)]:
            line([(71,43),(71+dx,43+dy)],'#ccb88a',3)
            line([(71+dx,43+dy),(71+dx+5,43+dy+5)],'#ddceaa',2)
        rect((127,96,136,102),'#9b6c40');rect((139,91,146,97),'#b4874e')
        if stage==2:flowers(40,102);flowers(157,85);rect((97,61,101,66),'#f2d693')
    else:
        poly([(79,56),(92,60),(101,79),(114,95),(107,107),(96,108),(86,91),(77,76)],'#326b80')
        line([(87,63),(91,75),(103,96)],'#6fa2ac',2)
        # Long stone bridge over a ravine; multiple visible arch piers.
        poly([(51,77),(110,66),(142,79),(86,92)],'#a8ad9a')
        poly([(51,77),(86,92),(142,79),(142,89),(86,102),(51,87)],'#6c827c')
        for x,y in [(69,87),(90,91),(114,85),(132,81)]:
            rect((x,y,x+7,y+11),'#465d64');poly([(x,y),(x+3,y-3),(x+7,y),(x+7,y+6),(x,y+6)],'#233b4c')
        line([(51,76),(86,89),(142,77)],'#d1cbb0',2)
        building(49,73,23,23);building(141,70,27,29)
        building(116,52,19,20)
        for x,y in [(34,92),(153,96)]:tree(x,y)
        if stage:
            for x,y in [(55,75),(128,78)]:rect((x,y-10,x+1,y),'#c5ae77');rect((x-1,y-12,x+2,y-9),'#f4d495')
        if stage==2:
            line([(54,58),(135,49)],'#999f89')
            for x,y in [(65,57),(83,55),(103,52),(123,50)]:poly([(x,y),(x+6,y),(x+3,y+7)],'#d29079')
            flowers(43,99)
    # Distinct restoration details in addition to palette, visible in monochrome.
    if stage==0:
        if key=='aksarimba':
            poly([(80,48),(87,46),(90,54),(84,59),(77,54)],'#20352f');line([(83,50),(84,65)],'#182d2d',2)
            line([(89,91),(103,94)],'#385045',3)
        elif key=='lintasura':
            for x,y in [(70,88),(87,75),(94,60)]:poly([(x-5,y),(x,y-7),(x+6,y+1)],'#4b5f60')
            line([(110,21),(118,23)],'#7b8175',2)
        elif key=='teduhara':
            rect((99,54,106,72),'#546c70');poly([(77,92),(84,87),(91,94),(84,99)],'#61776a')
        elif key=='bekalara':
            rect((73,27,79,37),'#101e2b');line([(63,43),(72,43)],'#536653',4)
            for x,y in [(45,83),(65,96),(137,89)]:line([(x,y),(x+7,y-2)],'#3f513d',3)
        else:
            poly([(86,80),(101,77),(106,89),(92,93)],'#294953')
            line([(86,81),(94,85)],'#1d3542',2)
    elif stage==2:
        if key=='aksarimba':
            for x,y in [(55,86),(137,84)]:
                line([(x,y),(x,y-13)],'#bab393',2);rect((x-2,y-14,x+3,y-11),'#f2dc91')
        elif key=='lintasura':
            poly([(105,46),(119,45),(124,51),(110,55)],'#829184');line([(105,46),(119,45)],'#d4d8b6',2)
        elif key=='teduhara':
            poly([(120,99),(128,94),(138,98),(130,104)],'#a3b79b');flowers(129,96)
        elif key=='bekalara':
            poly([(145,78),(157,74),(161,80),(149,84)],'#cfbd7b');rect((148,80,157,86),'#718574')
        else:
            poly([(85,69),(95,67),(100,72),(90,75)],'#b4b79c');roof(93,65,17,8,'#809d87')
    # Progress is a visual layer tied to activity counts, never a lock or punishment.
    if stage==0:
        overlay=Image.new('RGBA',im.size,(0,0,0,0));od=ImageDraw.Draw(overlay)
        for x,y in [(30,87),(87,102),(128,92)]:od.rectangle((x,y,x+30,y+5),fill=(100,127,143,56))
        im.alpha_composite(overlay)
        alpha=im.getchannel('A');muted=im.convert('RGB').convert('L').convert('RGB');im=Image.blend(im.convert('RGB'),muted,0.30).convert('RGBA');im.putalpha(alpha)
    elif stage==2:
        for x,y in [(61,105),(133,100)]:d.rectangle((x,y,x+1,y+1),fill='#e7e6ad')
    return im.resize((768,576),Image.Resampling.NEAREST)

for key in keys:
    for state in states:draw_region(key,state).save(OUT/f'{key}_{state}.png')

# Shared sea backdrop with subtle currents; all art is deterministic.
sea=Image.new('RGB',(480,256),'#101e2b');sd=ImageDraw.Draw(sea);rng=random.Random(48)
for y in range(10,256,13):
    for x in range(5,480,19):
        if rng.random()<0.6:sd.line((x,y+rng.randrange(4),x+rng.randrange(4,11),y+rng.randrange(4)),fill=rng.choice(['#1a3040','#1d3542','#233b47']))
for points in [[(190,100),(260,160)],[(155,157),(238,151)],[(270,83),(261,140)],[(352,141),(285,152)]]:
    sd.line(points,fill='#46524e',width=1)
sea.resize((1920,1024),Image.Resampling.NEAREST).save(OUT/'nawasela_sea.png')

board=Image.new('RGB',(1152,1700),'#101b28')
from PIL import ImageFont
font=ImageFont.truetype('C:/Windows/Fonts/arial.ttf',20)
bd=ImageDraw.Draw(board)
for i,key in enumerate(keys):
    for j,state in enumerate(states):
        art=draw_region(key,state).resize((384,288),Image.Resampling.NEAREST)
        board.paste(art,(j*384,i*340),art)
        bd.text((j*384+20,i*340+290),key+' / '+state,font=font,fill='#dce6dc')
board.save(OUT/'world_art_board.png')
print('Generated 15 region landscapes, sea backdrop, and art board')
