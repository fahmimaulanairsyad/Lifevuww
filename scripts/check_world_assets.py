"""Validate all five region silhouettes and their restoration variants."""
from pathlib import Path
from PIL import Image, ImageChops

root=Path(__file__).resolve().parents[1]/'assets/images/world'
keys=['aksarimba','lintasura','teduhara','bekalara','temuraya']
states=['quiet','awakening','flourishing']
for key in keys:
    images=[]
    for state in states:
        path=root/f'{key}_{state}.png'
        image=Image.open(path)
        assert image.size==(768,576) and image.mode=='RGBA',path
        alpha=image.getchannel('A')
        assert alpha.getextrema()==(0,255),path
        left,top,right,bottom=alpha.getbbox()
        assert left>0 and top>0 and right<768 and bottom<576,path
        images.append(image)
    for i in range(2):
        assert ImageChops.difference(images[i].convert('RGB'),images[i+1].convert('RGB')).getbbox(),key
sea=Image.open(root/'nawasela_sea.png')
assert sea.size==(1920,1024)
print('PASS: 15 distinct region PNGs, transparent margins, 768x576, and 1920x1024 sea backdrop')
